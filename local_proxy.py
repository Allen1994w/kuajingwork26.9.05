# -*- coding: utf-8 -*-
"""跨境电商工作台 本地 CORS 代理
解决 file:// 本地页面访问 AI/数据接口被浏览器跨域拦截的问题（Sorftime / 文字模型 / 新浪汇率等）。

用法：python local_proxy.py   （或双击运行）
端口：
  8787  →  Sorftime 数据源（自动转发 https://mcp.sorftime.com，key 从 query 透传）
  8788  →  通用代理（前端请求带 X-Proxy-Target 头指定真实接口地址，透传 Authorization 等头）
           支持 GET / POST，支持 X-Proxy-Referer 透传为 Referer（新浪汇率等需要）

部署到宝塔/服务器后无需此代理：用 Nginx 反向代理同源即可。
"""
import json
import threading
import urllib.parse
import urllib.request
from http.server import BaseHTTPRequestHandler, HTTPServer

SORFTIME_UPSTREAM = 'https://mcp.sorftime.com'
PORT_SORFTIME = 8787
PORT_GENERIC = 8788


class Handler(BaseHTTPRequestHandler):
    def _cors(self):
        self.send_header('Access-Control-Allow-Origin', '*')
        self.send_header('Access-Control-Allow-Methods', 'GET, POST, OPTIONS')
        self.send_header('Access-Control-Allow-Headers', 'Content-Type, Accept, Authorization, X-Proxy-Target, X-Proxy-Referer')

    def do_OPTIONS(self):
        self.send_response(204)
        self._cors()
        self.end_headers()

    def _forward(self, body=None):
        # 目标地址：X-Proxy-Target 头优先（8788通用模式），否则 Sorftime（8787）
        target = self.headers.get('X-Proxy-Target') or SORFTIME_UPSTREAM
        target = target.replace(' ', '')
        # 透传 query（Sorftime 的 key 在 query 中）
        qs = urllib.parse.urlparse(self.path).query
        if qs:
            target = target + ('&' if '?' in target else '?') + qs

        headers = {
            'Content-Type': self.headers.get('Content-Type', 'application/json'),
            'Accept': self.headers.get('Accept', 'application/json, text/event-stream, text/javascript'),
        }
        auth = self.headers.get('Authorization')
        if auth:
            headers['Authorization'] = auth
        # 透传 Referer（新浪汇率等需要）
        referer = self.headers.get('X-Proxy-Referer')
        if referer:
            headers['Referer'] = referer

        req = urllib.request.Request(target, data=body, headers=headers)
        try:
            with urllib.request.urlopen(req, timeout=60) as resp:
                raw = resp.read()
            self.send_response(200)
            ct = resp.headers.get('Content-Type', 'application/json')
            self.send_header('Content-Type', ct)
            self._cors()
            self.end_headers()
            self.wfile.write(raw)
        except urllib.error.HTTPError as e:
            raw = e.read()
            self.send_response(e.code)
            self.send_header('Content-Type', 'application/json')
            self._cors()
            self.end_headers()
            self.wfile.write(raw)
        except Exception as e:
            self.send_response(502)
            self.send_header('Content-Type', 'application/json')
            self._cors()
            self.end_headers()
            self.wfile.write(json.dumps({'error': str(e)}, ensure_ascii=False).encode('utf-8'))

    def do_POST(self):
        length = int(self.headers.get('Content-Length', 0) or 0)
        body = self.rfile.read(length) if length else b''
        self._forward(body)

    def do_GET(self):
        self._forward(None)

    def log_message(self, *args):
        pass


def serve(port):
    HTTPServer(('127.0.0.1', port), Handler).serve_forever()


if __name__ == '__main__':
    print('本地 CORS 代理已启动:')
    print('  8787 -> Sorftime 数据源 (mcp.sorftime.com)')
    print('  8788 -> 通用代理（X-Proxy-Target 指定目标，支持 GET/POST + Referer）')
    threading.Thread(target=serve, args=(PORT_SORFTIME,), daemon=True).start()
    serve(PORT_GENERIC)

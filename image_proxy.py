#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""跨境电商技能包 · 本地 CORS 生图代理

作用：把本机 file:// 页面的跨域生图请求转发到真实生图接口，并回填 CORS 响应头，
解决浏览器直接调接口被拦截（模型调用失败：Failed to fetch）的问题。

用法：
    python image_proxy.py [端口]
    # 默认端口 8787，转发目标 https://www.777codes.codes
    # 如需改目标： set TARGET=https://你的接口域名  （Linux: export TARGET=...）

前端后台「模型设置 - 生图模型」接口地址填：
    http://127.0.0.1:8787/
"""
import http.server
import urllib.request
import urllib.error
import os
import sys

PORT = int(sys.argv[1]) if len(sys.argv) > 1 else 8787
TARGET = os.environ.get("TARGET", "https://www.777codes.codes").rstrip("/")

# 目标接口对非浏览器 UA 返回 403，转发时伪装成 Chrome
CHROME_UA = ("Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 "
             "(KHTML, like Gecko) Chrome/126.0 Safari/537.36")

CORS = {
    "Access-Control-Allow-Origin": "*",
    "Access-Control-Allow-Methods": "GET, POST, OPTIONS",
    "Access-Control-Allow-Headers": "Content-Type, Authorization",
    "Access-Control-Max-Age": "86400",
}


class Handler(http.server.BaseHTTPRequestHandler):
    def _cors(self):
        for k, v in CORS.items():
            self.send_header(k, v)

    def do_OPTIONS(self):
        self.send_response(200)
        self._cors()
        self.end_headers()

    def _forward(self, method):
        length = int(self.headers.get("Content-Length") or 0)
        body = self.rfile.read(length) if length else None
        url = TARGET + (self.path or "/")
        req = urllib.request.Request(url, data=body, method=method)
        req.add_header("User-Agent", CHROME_UA)
        req.add_header("Content-Type", self.headers.get("Content-Type", "application/json"))
        auth = self.headers.get("Authorization")
        if auth:
            req.add_header("Authorization", auth)
        try:
            with urllib.request.urlopen(req, timeout=180) as resp:
                data = resp.read()
                self.send_response(resp.status)
                self._cors()
                self.send_header("Content-Type", resp.headers.get("Content-Type", "application/json"))
                self.send_header("Content-Length", str(len(data)))
                self.end_headers()
                self.wfile.write(data)
        except urllib.error.HTTPError as e:
            data = e.read()
            self.send_response(e.code)
            self._cors()
            self.send_header("Content-Type", e.headers.get("Content-Type", "application/json"))
            self.send_header("Content-Length", str(len(data)))
            self.end_headers()
            self.wfile.write(data)
        except Exception as e:
            body = ("{\"error\":\"proxy: %s\"}" % str(e)).encode("utf-8")
            self.send_response(502)
            self._cors()
            self.send_header("Content-Type", "application/json; charset=utf-8")
            self.send_header("Content-Length", str(len(body)))
            self.end_headers()
            self.wfile.write(body)

    def do_POST(self):
        self._forward("POST")

    def do_GET(self):
        self._forward("GET")

    def log_message(self, *a):
        pass


if __name__ == "__main__":
    srv = http.server.ThreadingHTTPServer(("127.0.0.1", PORT), Handler)
    print("CORS 生图代理已启动: http://127.0.0.1:%d  ->  %s" % (PORT, TARGET))
    print("生图模型接口地址请填: http://127.0.0.1:%d/" % PORT)
    srv.serve_forever()

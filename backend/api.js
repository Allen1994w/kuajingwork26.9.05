/**
 * 跨境电商工作台 - 后端API封装
 * 渐进式改造：USE_BACKEND=true时调用后端API，false时使用localStorage
 */
window.API = (function() {
    // 后端模式开关：true=调用PHP后端，false=使用localStorage（旧模式）
    const USE_BACKEND = false; // 部署到宝塔后改为true
    const API_BASE = 'backend/api/';

    // token存储
    const TOKEN_KEY = 'cbe_token_v1';

    function getToken() {
        return localStorage.getItem(TOKEN_KEY) || '';
    }
    function setToken(t) {
        if (t) localStorage.setItem(TOKEN_KEY, t);
        else localStorage.removeItem(TOKEN_KEY);
    }

    /**
     * 通用API调用
     * @param {string} endpoint - 如 'auth.php?action=login'
     * @param {object} data - POST数据
     * @param {object} options - {method, formData}
     */
    async function call(endpoint, data = {}, options = {}) {
        const url = API_BASE + endpoint;
        const headers = {};
        const token = getToken();
        if (token) headers['Authorization'] = 'Bearer ' + token;

        let body;
        if (options.formData) {
            body = data; // FormData对象
        } else {
            headers['Content-Type'] = 'application/json';
            body = JSON.stringify(data);
        }

        try {
            const resp = await fetch(url, {
                method: options.method || 'POST',
                headers,
                body,
            });
            const result = await resp.json();
            return result;
        } catch (e) {
            return { ok: false, error: '网络请求失败：' + e.message };
        }
    }

    // ============ 认证相关 ============
    const auth = {
        async login(email, password) {
            const r = await call('auth.php?action=login', { email, password });
            if (r.ok) {
                setToken(r.data.token);
            }
            return r;
        },
        async logout() {
            await call('auth.php?action=logout', {});
            setToken('');
        },
        async me() {
            return call('auth.php?action=me', {}, { method: 'GET' });
        },
        async updatePassword(oldPwd, newPwd) {
            return call('auth.php?action=update_password', { old_password: oldPwd, new_password: newPwd });
        },
    };

    // ============ 用户管理（后台） ============
    const users = {
        async list(page = 1, pageSize = 20, keyword = '') {
            return call('users.php?action=list', { page, pageSize, keyword });
        },
        async create(data) {
            return call('users.php?action=create', data);
        },
        async update(data) {
            return call('users.php?action=update', data);
        },
        async remove(id) {
            return call('users.php?action=delete', { id });
        },
        async recharge(id, points) {
            return call('users.php?action=recharge', { id, points });
        },
        async setPermissions(id, permissions) {
            return call('users.php?action=permissions', { id, permissions });
        },
        async orders(userId, type = 'all') {
            return call('users.php?action=orders', { user_id: userId, type });
        },
    };

    // ============ 历史记录 ============
    const history = {
        async list(page = 1, pageSize = 20, type = 'all', keyword = '', allUsers = false) {
            return call('history.php?action=list', { page, pageSize, type, keyword, all_users: allUsers });
        },
        async detail(id) {
            return call('history.php?action=detail', { id });
        },
        async create(data) {
            return call('history.php?action=create', data);
        },
        async update(id, data) {
            return call('history.php?action=update', { id, ...data });
        },
        async remove(id) {
            return call('history.php?action=delete', { id });
        },
    };

    // ============ 文件上传 ============
    const upload = {
        async image(file) {
            const fd = new FormData();
            fd.append('file', file);
            return call('upload.php?type=image', fd, { formData: true });
        },
        async video(file) {
            const fd = new FormData();
            fd.append('file', file);
            return call('upload.php?type=video', fd, { formData: true });
        },
        // base64图片转File后上传
        async imageFromBase64(base64, filename = 'image.png') {
            const arr = base64.split(',');
            const mime = arr[0].match(/:(.*?);/)[1];
            const bstr = atob(arr[1]);
            let n = bstr.length;
            const u8arr = new Uint8Array(n);
            while (n--) u8arr[n] = bstr.charCodeAt(n);
            const file = new File([u8arr], filename, { type: mime });
            return this.image(file);
        },
    };

    // ============ 订单明细 ============
    const orders = {
        async list(page = 1, pageSize = 20, type = 'all') {
            return call('orders.php?action=list', { page, pageSize, type });
        },
        async recharge(planId) {
            return call('orders.php?action=recharge', { plan_id: planId });
        },
    };

    // ============ 系统配置 ============
    const settings = {
        async get() {
            return call('settings.php?action=get', {}, { method: 'GET' });
        },
        async saveModels(models) {
            return call('settings.php?action=models', { models });
        },
        async savePackToggle(packToggle) {
            return call('settings.php?action=pack_toggle', { pack_toggle: packToggle });
        },
        async savePlans(plans) {
            return call('settings.php?action=plans', { plans });
        },
        async savePointRules(rules) {
            return call('settings.php?action=point_rules', { point_rules: rules });
        },
        async saveSysCfg(sysCfg) {
            return call('settings.php?action=sys_cfg', { sys_cfg: sysCfg });
        },
        async codes() {
            return call('settings.php?action=codes', {}, { method: 'GET' });
        },
        async createCode(code, points) {
            return call('settings.php?action=create_code', { code, points });
        },
        async deleteCode(id) {
            return call('settings.php?action=delete_code', { id });
        },
        async redeem(code) {
            return call('settings.php?action=redeem', { code });
        },
    };

    return {
        USE_BACKEND,
        API_BASE,
        call,
        auth,
        users,
        history,
        upload,
        orders,
        settings,
        getToken,
        setToken,
    };
})();

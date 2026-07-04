// ═══════════════════════════════════════════════════════════════
//  Zeekers Technology Solutions — API Client
//  src/js/api.js
// ═══════════════════════════════════════════════════════════════

const API_BASE = '/api';

// ─── Core fetch wrapper — NEVER throws, always returns safely ─
//
// IMPORTANT: the admin panel and the public helpdesk portal are two
// completely separate logins that can both be active in the SAME browser
// at the same time (e.g. an admin who also has a helpdesk account, or
// just testing both panels). Every authenticated call below states an
// explicit tokenScope ('admin' | 'user') so the correct token is always
// sent — never a guess based on "whichever token happens to exist".
function getScopedToken(scope) {
    if (scope === 'admin') return localStorage.getItem('zts_admin_token');
    if (scope === 'user')  return localStorage.getItem('zts_user_token');
    return null; // scope 'none' / unspecified → no Authorization header
}

async function apiFetch(endpoint, options = {}) {
    const { tokenScope = 'none', ...fetchOptions } = options;
    const token = getScopedToken(tokenScope);
    const headers = { 'Content-Type': 'application/json', ...(fetchOptions.headers || {}) };
    if (token) headers['Authorization'] = `Bearer ${token}`;

    try {
        const res = await fetch(`${API_BASE}/${endpoint}`, { ...fetchOptions, headers });
        const data = await res.json().catch(() => ({}));
        if (!res.ok) throw new Error(data.error || `HTTP ${res.status}`);
        return data;
    } catch (err) {
        console.warn(`[ZTS API] ${endpoint}: ${err.message}`);
        throw err;  // re-throw so callers can catch; all callers have try/catch
    }
}

// ─── Auth (admin panel login) ──────────────────────────────────
const AuthAPI = {
    login: (username, password) =>
        apiFetch('auth.php', { method: 'POST', body: JSON.stringify({ username, password }) }),
    logout: () => {
        localStorage.removeItem('zts_admin_token');
        localStorage.removeItem('zts_admin_user');
    },
    isLoggedIn: () => !!localStorage.getItem('zts_admin_token'),
};

// ─── Blog Posts ──────────────────────────────────────────────
const BlogAPI = {
    getPublished: ()       => apiFetch('blogs.php'),
    getAll: ()             => apiFetch('blogs.php?all=1', { tokenScope: 'admin' }),
    getOne: (id)           => apiFetch(`blogs.php?id=${id}`),
    create: (data)         => apiFetch('blogs.php', { method: 'POST', tokenScope: 'admin', body: JSON.stringify(data) }),
    update: (id, data)     => apiFetch(`blogs.php?id=${id}`, { method: 'PUT', tokenScope: 'admin', body: JSON.stringify(data) }),
    delete: (id)           => apiFetch(`blogs.php?id=${id}`, { method: 'DELETE', tokenScope: 'admin' }),
};

// ─── Jobs ────────────────────────────────────────────────────
const JobsAPI = {
    getActive: ()          => apiFetch('jobs.php'),
    getAll: ()             => apiFetch('jobs.php?all=1', { tokenScope: 'admin' }),
    getOne: (id)           => apiFetch(`jobs.php?id=${id}`),
    create: (data)         => apiFetch('jobs.php', { method: 'POST', tokenScope: 'admin', body: JSON.stringify(data) }),
    update: (id, data)     => apiFetch(`jobs.php?id=${id}`, { method: 'PUT', tokenScope: 'admin', body: JSON.stringify(data) }),
    delete: (id)           => apiFetch(`jobs.php?id=${id}`, { method: 'DELETE', tokenScope: 'admin' }),
};

// ─── Applications ────────────────────────────────────────────
const ApplicationsAPI = {
    submit: (data)         => apiFetch('applications.php', { method: 'POST', body: JSON.stringify(data) }),
    getAll: ()             => apiFetch('applications.php', { tokenScope: 'admin' }),
    getOne: (id)           => apiFetch(`applications.php?id=${id}`, { tokenScope: 'admin' }),
    updateStatus: (id, st) => apiFetch(`applications.php?id=${id}`, { method: 'PUT', tokenScope: 'admin', body: JSON.stringify({ status: st }) }),
    delete: (id)           => apiFetch(`applications.php?id=${id}`, { method: 'DELETE', tokenScope: 'admin' }),
};

// ─── Contact ─────────────────────────────────────────────────
const ContactAPI = {
    submit: (data)         => apiFetch('contact.php', { method: 'POST', body: JSON.stringify(data) }),
    getAll: ()             => apiFetch('contact.php', { tokenScope: 'admin' }),
    updateStatus: (id, st) => apiFetch(`contact.php?id=${id}`, { method: 'PUT', tokenScope: 'admin', body: JSON.stringify({ status: st }) }),
    delete: (id)           => apiFetch(`contact.php?id=${id}`, { method: 'DELETE', tokenScope: 'admin' }),
};

// ─── Helpdesk Portal Auth (public users) ──────────────────────
const HelpdeskAuthAPI = {
    login: (email, password) =>
        apiFetch('helpdesk-auth.php', { method: 'POST', body: JSON.stringify({ action: 'login', email, password }) }),
    register: (data) =>
        apiFetch('helpdesk-auth.php', { method: 'POST', body: JSON.stringify({ action: 'register', ...data }) }),
    updateProfile: (data) =>
        apiFetch('helpdesk-auth.php', { method: 'POST', tokenScope: 'user', body: JSON.stringify({ action: 'update-profile', ...data }) }),
    changePassword: (currentPassword, newPassword) =>
        apiFetch('helpdesk-auth.php', { method: 'POST', tokenScope: 'user', body: JSON.stringify({ action: 'change-password', current_password: currentPassword, new_password: newPassword }) }),
    logout: () => {
        localStorage.removeItem('zts_user_token');
        localStorage.removeItem('zts_session');
    },
};

// ─── Tickets ─────────────────────────────────────────────────
const TicketsAPI = {
    create: (data)          => apiFetch('tickets.php', { method: 'POST', body: JSON.stringify(data) }),
    getByEmail: (email)     => apiFetch(`tickets.php?email=${encodeURIComponent(email)}`),
    getMine: ()              => apiFetch('tickets.php', { tokenScope: 'user' }),   // the logged-in helpdesk user's own tickets
    getAll: ()               => apiFetch('tickets.php?all=1', { tokenScope: 'admin' }),
    // scope defaults to 'admin' since most callers are the admin panel;
    // the helpdesk dashboard passes 'user' explicitly for its own ticket.
    update: (id, data, scope = 'admin') => apiFetch(`tickets.php?id=${id}`, { method: 'PUT', tokenScope: scope, body: JSON.stringify(data) }),
    delete: (id, scope = 'admin')       => apiFetch(`tickets.php?id=${id}`, { method: 'DELETE', tokenScope: scope }),
};

// ─── Brochure Downloads ──────────────────────────────────────
const BrochureAPI = {
    submit: (data)         => apiFetch('brochure.php', { method: 'POST', body: JSON.stringify(data) }),
};

// ─── Admins (admin-panel login accounts) ──────────────────────
const AdminsAPI = {
    getAll: ()             => apiFetch('admins.php', { tokenScope: 'admin' }),
    create: (data)         => apiFetch('admins.php', { method: 'POST', tokenScope: 'admin', body: JSON.stringify(data) }),
    update: (id, data)     => apiFetch(`admins.php?id=${id}`, { method: 'PUT', tokenScope: 'admin', body: JSON.stringify(data) }),
    delete: (id)           => apiFetch(`admins.php?id=${id}`, { method: 'DELETE', tokenScope: 'admin' }),
};

// ─── Helpdesk Portal Users (admin management of public accounts) ─
const HelpdeskUsersAPI = {
    getAll: ()             => apiFetch('helpdesk-users.php', { tokenScope: 'admin' }),
    getOne: (id)           => apiFetch(`helpdesk-users.php?id=${id}`, { tokenScope: 'admin' }),
    create: (data)         => apiFetch('helpdesk-users.php', { method: 'POST', tokenScope: 'admin', body: JSON.stringify(data) }),
    update: (id, data)     => apiFetch(`helpdesk-users.php?id=${id}`, { method: 'PUT', tokenScope: 'admin', body: JSON.stringify(data) }),
    delete: (id)           => apiFetch(`helpdesk-users.php?id=${id}`, { method: 'DELETE', tokenScope: 'admin' }),
};

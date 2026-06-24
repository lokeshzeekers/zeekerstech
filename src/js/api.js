// ═══════════════════════════════════════════════════════════════
//  Zeekers Technology Solutions — API Client
//  src/js/api.js
// ═══════════════════════════════════════════════════════════════

const API_BASE = '/api';

// ─── Core fetch wrapper — NEVER throws, always returns safely ─
async function apiFetch(endpoint, options = {}) {
    const token = localStorage.getItem('zts_admin_token');
    const headers = { 'Content-Type': 'application/json', ...(options.headers || {}) };
    if (token) headers['Authorization'] = `Bearer ${token}`;

    try {
        const res = await fetch(`${API_BASE}/${endpoint}`, { ...options, headers });
        const data = await res.json().catch(() => ({}));
        if (!res.ok) throw new Error(data.error || `HTTP ${res.status}`);
        return data;
    } catch (err) {
        console.warn(`[ZTS API] ${endpoint}: ${err.message}`);
        throw err;  // re-throw so callers can catch; all callers have try/catch
    }
}

// ─── Auth ────────────────────────────────────────────────────
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
    getAll: ()             => apiFetch('blogs.php?all=1'),
    getOne: (id)           => apiFetch(`blogs.php?id=${id}`),
    create: (data)         => apiFetch('blogs.php', { method: 'POST', body: JSON.stringify(data) }),
    update: (id, data)     => apiFetch(`blogs.php?id=${id}`, { method: 'PUT', body: JSON.stringify(data) }),
    delete: (id)           => apiFetch(`blogs.php?id=${id}`, { method: 'DELETE' }),
};

// ─── Jobs ────────────────────────────────────────────────────
const JobsAPI = {
    getActive: ()          => apiFetch('jobs.php'),
    getAll: ()             => apiFetch('jobs.php?all=1'),
    getOne: (id)           => apiFetch(`jobs.php?id=${id}`),
    create: (data)         => apiFetch('jobs.php', { method: 'POST', body: JSON.stringify(data) }),
    update: (id, data)     => apiFetch(`jobs.php?id=${id}`, { method: 'PUT', body: JSON.stringify(data) }),
    delete: (id)           => apiFetch(`jobs.php?id=${id}`, { method: 'DELETE' }),
};

// ─── Applications ────────────────────────────────────────────
const ApplicationsAPI = {
    submit: (data)         => apiFetch('applications.php', { method: 'POST', body: JSON.stringify(data) }),
    getAll: ()             => apiFetch('applications.php'),
    getOne: (id)           => apiFetch(`applications.php?id=${id}`),
    updateStatus: (id, st) => apiFetch(`applications.php?id=${id}`, { method: 'PUT', body: JSON.stringify({ status: st }) }),
    delete: (id)           => apiFetch(`applications.php?id=${id}`, { method: 'DELETE' }),
};

// ─── Contact ─────────────────────────────────────────────────
const ContactAPI = {
    submit: (data)         => apiFetch('contact.php', { method: 'POST', body: JSON.stringify(data) }),
    getAll: ()             => apiFetch('contact.php'),
    updateStatus: (id, st) => apiFetch(`contact.php?id=${id}`, { method: 'PUT', body: JSON.stringify({ status: st }) }),
    delete: (id)           => apiFetch(`contact.php?id=${id}`, { method: 'DELETE' }),
};

// ─── Tickets ─────────────────────────────────────────────────
const TicketsAPI = {
    create: (data)         => apiFetch('tickets.php', { method: 'POST', body: JSON.stringify(data) }),
    getByEmail: (email)    => apiFetch(`tickets.php?email=${encodeURIComponent(email)}`),
    getAll: ()             => apiFetch('tickets.php?all=1'),
    update: (id, data)     => apiFetch(`tickets.php?id=${id}`, { method: 'PUT', body: JSON.stringify(data) }),
    delete: (id)           => apiFetch(`tickets.php?id=${id}`, { method: 'DELETE' }),
};

// ─── Brochure Downloads ──────────────────────────────────────
const BrochureAPI = {
    submit: (data)         => apiFetch('brochure.php', { method: 'POST', body: JSON.stringify(data) }),
};

// ─── Admins ──────────────────────────────────────────────────
const AdminsAPI = {
    getAll: ()             => apiFetch('admins.php'),
    create: (data)         => apiFetch('admins.php', { method: 'POST', body: JSON.stringify(data) }),
    update: (id, data)     => apiFetch(`admins.php?id=${id}`, { method: 'PUT', body: JSON.stringify(data) }),
    delete: (id)           => apiFetch(`admins.php?id=${id}`, { method: 'DELETE' }),
};

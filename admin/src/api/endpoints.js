import client from './client'

/**
 * كل نقاط الـ API في مكان واحد.
 *
 * لا مكوّن يكتب مساراً نصياً: تغيير مسار في الخادم يُعدَّل هنا وحده،
 * ولا يُبحث عنه في عشرين ملف .vue.
 */
const v1 = '/api/v1'

export const auth = {
  csrf: () => client.get('/sanctum/csrf-cookie'),
  login: (payload) => client.post(`${v1}/auth/login`, payload),
  logout: () => client.post(`${v1}/auth/logout`),
  me: () => client.get(`${v1}/auth/me`),
}

export const admin = {
  dashboard: (params) => client.get(`${v1}/admin/dashboard`, { params }),

  orphans: {
    list: (params) => client.get(`${v1}/admin/orphans`, { params }),
    show: (id) => client.get(`${v1}/admin/orphans/${id}`),
    create: (payload) => client.post(`${v1}/admin/orphans`, payload),
    update: (id, payload) => client.patch(`${v1}/admin/orphans/${id}`, payload),
    verify: (id) => client.post(`${v1}/admin/orphans/${id}/verify`),
    publish: (id) => client.post(`${v1}/admin/orphans/${id}/publish`),
    unpublish: (id) => client.post(`${v1}/admin/orphans/${id}/unpublish`),
    documents: (id) => client.get(`${v1}/admin/orphans/${id}/documents`),
  },

  families: {
    list: (params) => client.get(`${v1}/admin/families`, { params }),
    show: (id) => client.get(`${v1}/admin/families/${id}`),
    create: (payload) => client.post(`${v1}/admin/families`, payload),
  },

  sponsors: {
    list: (params) => client.get(`${v1}/admin/sponsors`, { params }),
    show: (id) => client.get(`${v1}/admin/sponsors/${id}`),
  },

  sponsorships: {
    list: (params) => client.get(`${v1}/admin/sponsorships`, { params }),
    show: (id) => client.get(`${v1}/admin/sponsorships/${id}`),
    update: (id, payload) => client.patch(`${v1}/admin/sponsorships/${id}`, payload),
    cancel: (id, reason) => client.delete(`${v1}/admin/sponsorships/${id}`, { data: { reason } }),
  },

  payments: {
    list: (params) => client.get(`${v1}/admin/payments`, { params }),
    record: (payload) => client.post(`${v1}/admin/payments`, payload),
    refund: (id, reason) => client.post(`${v1}/admin/payments/${id}/refund`, { reason }),
  },

  disbursements: {
    list: (params) => client.get(`${v1}/admin/disbursements`, { params }),
    categories: () => client.get(`${v1}/admin/disbursement-categories`),
    create: (payload) => client.post(`${v1}/admin/disbursements`, payload),
    approve: (id) => client.post(`${v1}/admin/disbursements/${id}/approve`),
  },

  reports: {
    list: (params) => client.get(`${v1}/admin/reports`, { params }),
    show: (id) => client.get(`${v1}/admin/reports/${id}`),
    submit: (id) => client.post(`${v1}/admin/reports/${id}/submit`),
    approve: (id) => client.post(`${v1}/admin/reports/${id}/approve`),
    publish: (id) => client.post(`${v1}/admin/reports/${id}/publish`),
  },

  visits: {
    list: (params) => client.get(`${v1}/admin/visits`, { params }),
    create: (payload) => client.post(`${v1}/admin/visits`, payload),
  },

  messages: {
    list: (params) => client.get(`${v1}/admin/messages`, { params }),
    review: (id, payload) => client.post(`${v1}/admin/messages/${id}/review`, payload),
  },

  users: {
    list: (params) => client.get(`${v1}/admin/users`, { params }),
    create: (payload) => client.post(`${v1}/admin/users`, payload),
    update: (id, payload) => client.patch(`${v1}/admin/users/${id}`, payload),
    roles: () => client.get(`${v1}/admin/roles`),
  },

  activity: (params) => client.get(`${v1}/admin/activity`, { params }),
  posts: (params) => client.get(`${v1}/admin/posts`, { params }),
  content: (type) => client.get(`${v1}/admin/content/${type}`),
  contacts: (params) => client.get(`${v1}/admin/contacts`, { params }),
  updateContact: (id, status) => client.patch(`${v1}/admin/contacts/${id}`, { status }),
  settings: () => client.get(`${v1}/admin/settings`),
}

export const portal = {
  overview: () => client.get(`${v1}/portal/overview`),
  sponsorships: (params) => client.get(`${v1}/portal/sponsorships`, { params }),
  payments: (params) => client.get(`${v1}/portal/payments`, { params }),
  reports: (params) => client.get(`${v1}/portal/reports`, { params }),
  report: (id) => client.get(`${v1}/portal/reports/${id}`),
}

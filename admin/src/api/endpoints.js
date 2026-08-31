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
    remove: (id) => client.delete(`${v1}/admin/orphans/${id}`),
  },

  documents: {
    /**
     * الرفع `FormData` لا JSON: الملف بايتات لا نصّ، وترميزه base64 داخل
     * JSON يضخّمه الثلث ويحمّل الخادم فكّه.
     *
     * ولا نضبط `Content-Type` يدوياً — المتصفح وحده يعرف حدّ الفصل
     * (`boundary`) الذي يفصل أجزاء الرسالة، وكتابته بأيدينا تكسر التحليل.
     */
    upload: (orphanId, payload) => {
      const body = new FormData()
      body.append('type', payload.type)
      body.append('file', payload.file)

      return client.post(`${v1}/admin/orphans/${orphanId}/documents`, body)
    },

    verify: (id) => client.post(`${v1}/admin/documents/${id}/verify`),
    remove: (id) => client.delete(`${v1}/admin/documents/${id}`),
    downloadUrl: (id) => `${v1}/admin/documents/${id}/download`,
  },

  families: {
    list: (params) => client.get(`${v1}/admin/families`, { params }),
    show: (id) => client.get(`${v1}/admin/families/${id}`),
    create: (payload) => client.post(`${v1}/admin/families`, payload),
    update: (id, payload) => client.patch(`${v1}/admin/families/${id}`, payload),
  },

  /** القوائم المرجعية للنماذج: البلدان والمدن والبرامج والتعدادات. */
  references: () => client.get(`${v1}/admin/references`),

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
    /**
     * ‏`multipart` عند وجود إيصال، وJSON بدونه.
     *
     * إرسال كل صرف بـ`FormData` كان أبسط، لكنه يحوّل كل قيمة إلى نصّ: فيصل
     * `null` نصَّ `"null"`، ويصل `fx_rate` الفارغ `""` — و`nullable|numeric`
     * ترفض كليهما. فنُبقي JSON هو الأصل، ولا نلجأ إلى `FormData` إلا حين
     * يوجد ملفٌ يوجبها.
     */
    create: (payload, receipt = null) => {
      if (!receipt) return client.post(`${v1}/admin/disbursements`, payload)

      const body = new FormData()
      Object.entries(payload).forEach(([key, value]) => {
        if (value !== null && value !== '') body.append(key, value)
      })
      body.append('receipt', receipt)

      return client.post(`${v1}/admin/disbursements`, body)
    },

    approve: (id) => client.post(`${v1}/admin/disbursements/${id}/approve`),
  },

  reports: {
    list: (params) => client.get(`${v1}/admin/reports`, { params }),
    show: (id) => client.get(`${v1}/admin/reports/${id}`),
    create: (payload) => client.post(`${v1}/admin/reports`, payload),
    update: (id, payload) => client.patch(`${v1}/admin/reports/${id}`, payload),
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
  updateSettings: (settings) => client.put(`${v1}/admin/settings`, { settings }),
}

export const portal = {
  overview: () => client.get(`${v1}/portal/overview`),
  sponsorships: (params) => client.get(`${v1}/portal/sponsorships`, { params }),
  payments: (params) => client.get(`${v1}/portal/payments`, { params }),
  reports: (params) => client.get(`${v1}/portal/reports`, { params }),
  report: (id) => client.get(`${v1}/portal/reports/${id}`),
}

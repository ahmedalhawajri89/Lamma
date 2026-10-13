import client from './client'

/**
 * كل نقاط الـ API في مكان واحد.
 *
 * لا مكوّن يكتب مساراً نصياً: تغيير مسار في الخادم يُعدَّل هنا وحده،
 * ولا يُبحث عنه في عشرين ملف .vue.
 */
const v1 = '/api/v1'

/**
 * يبني `FormData` من حمولة عادية.
 *
 * ثلاث قواعد لا يمكن تركها لكل نقطة على حدة:
 *
 *  1. **المنطقي يصير `'1'`/`'0'`.** `FormData` يحوّل كل قيمة إلى نصّ، و
 *     `append(key, false)` يُرسل النصّ `"false"` — وقاعدة `boolean` في
 *     لارافل تقبل `1` و`0` و`"1"` و`"0"` ولا تقبل `"false"`. فيُردّ الطلب
 *     بـ«يجب أن يكون صحيحاً أو خطأ» عن حقلٍ قيمته `false` فعلاً.
 *  2. **الفارغ يُسقَط.** `null` يصل نصَّ `"null"`، و`""` يصل فراغاً —
 *     و`nullable|numeric` ترفض كليهما.
 *  3. **الملفات تُلحَق كما هي.**
 *
 * ولا نضبط `Content-Type`: المتصفح وحده يعرف حدّ الفصل (`boundary`)،
 * وكتابته بأيدينا تكسر التحليل على الخادم.
 */
function toFormData(payload, files = {}) {
  const body = new FormData()

  Object.entries(payload).forEach(([key, value]) => {
    if (value === null || value === undefined || value === '') return
    body.append(key, typeof value === 'boolean' ? (value ? '1' : '0') : value)
  })

  Object.entries(files).forEach(([key, file]) => {
    if (file) body.append(key, file)
  })

  return body
}

export const auth = {
  csrf: () => client.get('/sanctum/csrf-cookie'),
  login: (payload) => client.post(`${v1}/auth/login`, payload),
  logout: () => client.post(`${v1}/auth/logout`),
  me: () => client.get(`${v1}/auth/me`),
}

export const admin = {
  dashboard: (params) => client.get(`${v1}/admin/dashboard`, { params }),
  queue: () => client.get(`${v1}/admin/queue`),

  orphans: {
    list: (params) => client.get(`${v1}/admin/orphans`, { params }),
    show: (id) => client.get(`${v1}/admin/orphans/${id}`),
    create: (payload, photo = null) => client.post(
      `${v1}/admin/orphans`,
      photo ? toFormData(payload, { photo }) : payload,
    ),

    /**
     * التعديل مع صورة يمرّ بـ`POST` مع `_method=PATCH`.
     *
     * وهذا ليس تجميلاً: PHP لا يحلّل جسم `multipart` إلا في `POST` —
     * فـ`PATCH` بـ`FormData` يصل الخادمَ بحقول فارغة وملفٍ مفقود، ولا
     * يقول شيء لماذا. وانتحال الأسلوب يعيد التوجيه إلى `PATCH` نفسها.
     */
    update: (id, payload, photo = null) => (photo
      ? client.post(`${v1}/admin/orphans/${id}`, toFormData({ ...payload, _method: 'PATCH' }, { photo }))
      : client.patch(`${v1}/admin/orphans/${id}`, payload)),
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
     */
    upload: (orphanId, payload) => client.post(
      `${v1}/admin/orphans/${orphanId}/documents`,
      toFormData({ type: payload.type }, { file: payload.file }),
    ),

    /** طابور التحقق: غير المُتحقَّق منها من كل الملفات، الأقدم أولاً */
    queue: (params) => client.get(`${v1}/admin/documents`, { params }),
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
     * JSON هو الأصل لأنه يحفظ الأنواع؛ ولا نلجأ إلى `FormData` إلا حين يوجد
     * ملفٌ يوجبها — وعندها يتولّى `toFormData` ترجمة الأنواع.
     */
    create: (payload, receipt = null) => client.post(
      `${v1}/admin/disbursements`,
      receipt ? toFormData(payload, { receipt }) : payload,
    ),

    approve: (id) => client.post(`${v1}/admin/disbursements/${id}/approve`),
  },

  reports: {
    list: (params) => client.get(`${v1}/admin/reports`, { params }),
    show: (id) => client.get(`${v1}/admin/reports/${id}`),
    create: (payload) => client.post(`${v1}/admin/reports`, payload),
    update: (id, payload) => client.patch(`${v1}/admin/reports/${id}`, payload),
    submit: (id) => client.post(`${v1}/admin/reports/${id}/submit`),
    approve: (id) => client.post(`${v1}/admin/reports/${id}/approve`),
    /** إرجاع لكاتبه مسوّدةً مع ملاحظة إلزامية */
    sendBack: (id, note) => client.post(`${v1}/admin/reports/${id}/return`, { note }),
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
    /** إعادة إرسال رابط تعيين كلمة المرور لموظف لم يفعّل حسابه */
    invite: (id) => client.post(`${v1}/admin/users/${id}/invite`),
    roles: () => client.get(`${v1}/admin/roles`),
  },

  activity: (params) => client.get(`${v1}/admin/activity`, { params }),

  posts: {
    list: (params) => client.get(`${v1}/admin/posts`, { params }),

    /**
     * الغلاف صورة، فالإنشاء `multipart` عند وجودها وJSON بدونها.
     *
     * والتحرير JSON دائماً: نقطة التعديل لا تقبل ملفاً أصلاً — الغلاف
     * يُضبط عند الإنشاء وحده. وإرسال `FormData` إلى `PATCH` يصل PHP فارغاً
     * لأنه لا يحلّل جسم الطلب إلا في `POST`.
     */
    create: (payload, cover = null) => client.post(
      `${v1}/admin/posts`,
      cover ? toFormData(payload, { cover }) : payload,
    ),

    update: (id, payload) => client.patch(`${v1}/admin/posts/${id}`, payload),
  },

  /** الأسئلة الشائعة والشهادات والشركاء — بنية واحدة وقواعد مختلفة. */
  content: {
    list: (type) => client.get(`${v1}/admin/content/${type}`),
    create: (type, payload) => client.post(`${v1}/admin/content/${type}`, payload),
    update: (type, id, payload) => client.patch(`${v1}/admin/content/${type}/${id}`, payload),
    remove: (type, id) => client.delete(`${v1}/admin/content/${type}/${id}`),
  },
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

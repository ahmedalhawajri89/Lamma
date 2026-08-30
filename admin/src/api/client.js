import axios from 'axios'

/**
 * طبقة HTTP الوحيدة في اللوحة.
 *
 * `withCredentials` هو ما يجعل المتصفح يرسل كوكي جلسة Sanctum عبر الأصل
 * المختلف (‏5173 → 8000). وبلا `withXSRFToken` في axios ≥1.6 لا تُرسَل ترويسة
 * X-XSRF-TOKEN تلقائياً فيردّ الخادم 419 على كل طلب مُغيِّر للحالة.
 */
/**
 * `baseURL` فارغ افتراضياً: في التطوير يمرّ كل شيء عبر وسيط Vite على الأصل
 * نفسه (انظر vite.config.js)، وفي الإنتاج يُضبط VITE_API_URL على نطاق الـ API.
 */
const client = axios.create({
  baseURL: import.meta.env.VITE_API_URL ?? '',
  withCredentials: true,
  withXSRFToken: true,
  headers: { Accept: 'application/json' },
})

/** يُطلب مرة واحدة قبل أول طلب يغيّر الحالة، ثم يبقى الكوكي صالحاً للجلسة. */
let csrfReady = null

export function ensureCsrf() {
  csrfReady ??= client.get('/sanctum/csrf-cookie')
  return csrfReady
}

client.interceptors.request.use(async (config) => {
  if (['post', 'put', 'patch', 'delete'].includes(config.method)) {
    await ensureCsrf()
  }
  return config
})

/**
 * معالجة موحّدة للأخطاء.
 *
 * 419 تعني أن كوكي CSRF انتهى (جلسة طويلة أو إعادة تشغيل الخادم). نجدّده
 * ونعيد المحاولة **مرة واحدة**: بلا حارس `_retried` يدخل الطلب في حلقة
 * لا نهائية إن بقي الخطأ.
 */
client.interceptors.response.use(
  (response) => response,
  async (error) => {
    const { response, config } = error

    if (response?.status === 419 && !config._retried) {
      config._retried = true
      csrfReady = null
      await ensureCsrf()
      return client(config)
    }

    error.userMessage =
      response?.data?.message ??
      (response ? 'تعذّر إتمام الطلب.' : 'لا اتصال بالخادم. تحقّق من تشغيله على المنفذ 8000.')

    error.fieldErrors = response?.data?.errors ?? {}

    return Promise.reject(error)
  },
)

export default client

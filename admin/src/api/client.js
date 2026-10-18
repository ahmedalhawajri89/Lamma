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

  // خادم لا يردّ يُعامَل كخطأ بعد مهلة، لا كانتظار بلا نهاية أمام شاشة فارغة
  timeout: 20_000,
  headers: { Accept: 'application/json' },
})

const MUTATING = ['post', 'put', 'patch', 'delete']

/** يُطلب مرة واحدة قبل أول طلب يغيّر الحالة، ثم يبقى الكوكي صالحاً للجلسة. */
let csrfReady = null

export function ensureCsrf() {
  csrfReady ??= client.get('/sanctum/csrf-cookie')
  return csrfReady
}

client.interceptors.request.use(async (config) => {
  if (MUTATING.includes(config.method)) {
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
/**
 * ما يُعرض للمستخدم من الخطأ.
 *
 * رسائل 4xx مكتوبة له (تحقّق، صلاحية، غير موجود) فتُعرض كما هي. أما 5xx
 * فعطلٌ في الخادم، ونصّه قد يحمل تفاصيل داخلية (استعلام، عنوان قاعدة
 * البيانات، معرّف جلسة) لو أفلت من معالج الخادم. فلا يُعرض منه إلا رسالتنا
 * العامة التي تحمل رقم مرجع، وكل ما سواها يُستبدل بنصّ ثابت.
 */
function userMessage(response) {
  if (!response) return 'لا اتصال بالخادم الآن. تأكد من تشغيله ثم أعد المحاولة.'

  const { status, data } = response

  if (status >= 500) {
    return data?.reference
      ? `${data.message} (المرجع ${data.reference})`
      : 'تعذّر إتمام الطلب بسبب عطل في الخادم. حاول بعد قليل.'
  }

  return data?.message ?? 'تعذّر إتمام الطلب.'
}

client.interceptors.response.use(
  (response) => {
    /*
     * بعد كل طلب يغيّر الحالة ونجح: إشارة عامة تلتقطها أرقام «بانتظارك»
     * فتتحدّث دون تحديث الصفحة. حدثٌ لا استيراد، كي لا تعتمد طبقة HTTP
     * على مخازن الواجهة.
     */
    const url = response.config?.url ?? ''
    if (MUTATING.includes(response.config?.method) && url.includes('/admin/')) {
      window.dispatchEvent(new CustomEvent('kafala:mutated'))
    }
    return response
  },
  async (error) => {
    const { response, config } = error

    if (response?.status === 419 && !config._retried) {
      config._retried = true
      csrfReady = null
      await ensureCsrf()
      return client(config)
    }

    error.userMessage = userMessage(response)

    error.fieldErrors = response?.data?.errors ?? {}

    return Promise.reject(error)
  },
)

export default client

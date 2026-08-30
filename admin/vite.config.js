import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import tailwindcss from '@tailwindcss/vite'
import { fileURLToPath, URL } from 'node:url'

export default defineConfig(({ mode }) => ({
  /**
   * في الإنتاج تُبنى اللوحة داخل `api/public` فتُقدَّم من خادم لارافل نفسه —
   * نشرٌ واحد للمشروع كله، ولا حاجة إلى CORS إطلاقاً.
   *
   * ولاحظ الاسم: المجلد `admin-app` والمسار `/admin`. **الاختلاف مقصود.**
   * لو تطابقا لصار `public/admin` مجلداً موجوداً، وخوادم الويب تخدم المجلدات
   * ساكنةً قبل أن تصل الطلبات إلى لارافل:
   *
   *   · `artisan serve` يرى المجلد فيتوقف عنده (‏404 من الخادم نفسه).
   *   · Apache يتخطى إعادة الكتابة بـ `RewriteCond %{REQUEST_FILENAME} !-d`.
   *   · وأسوأها: مع وجود `index.html` داخله، يمشي خادم PHP في المسار فيجعل
   *     `SCRIPT_NAME=/admin/index.html` و`PATH_INFO=/orphans`، فيقتطع
   *     Symfony الـ baseUrl ويرى لارافل `/orphans` — فيعرض صفحة الموقع
   *     العام مكان اللوحة، بلا خطأ ولا أثر يدلّ على السبب.
   *
   * وباسمٍ مختلف لا يوجد شيء على مسار `/admin` فيصل الطلب كاملاً.
   *
   * وفي التطوير تبقى اللوحة على جذر خادم Vite (‏`/`) فتبقى العناوين قصيرة،
   * والموجّه يقرأ `import.meta.env.BASE_URL` فيتبع الوضعين بلا تعديل.
   */
  base: mode === 'production' ? '/admin-app/' : '/',

  build: {
    outDir: '../api/public/admin-app',
    emptyOutDir: true,
  },

  plugins: [vue(), tailwindcss()],

  resolve: {
    alias: { '@': fileURLToPath(new URL('./src', import.meta.url)) },
  },

  server: {
    port: 5173,
    host: 'localhost',

    /**
     * وسيط بدل نداء الخادم مباشرةً — وهذا يحلّ ثلاث مشاكل دفعةً واحدة:
     *
     *  1. **الأداء.** نداء `localhost:8000` من المتصفح على ويندوز يكلّف
     *     ثانيتين زائدتين في كل طلب: `localhost` يُحلّ إلى `::1` أولاً،
     *     وخادم PHP المدمج يستمع على IPv4 وحده، فتنتظر محاولة IPv6 حتى
     *     تنتهي مهلتها قبل الرجوع إلى `127.0.0.1`. الوسيط ينادي
     *     `127.0.0.1` مباشرةً من الخادم فيسقط الانتظار كله.
     *
     *  2. **الكوكيز.** كل شيء يصير على أصل واحد (`localhost:5173`)،
     *     فكوكي الجلسة يصبح same-site لا cross-site، ولا يعتمد على
     *     سياسة المتصفح في كوكيز الطرف الثالث.
     *
     *  3. **CORS.** لا حاجة إليه في التطوير أصلاً. يبقى مضبوطاً للإنتاج
     *     حيث تختلف النطاقات فعلاً.
     */
    proxy: {
      '/api': { target: 'http://127.0.0.1:8000', changeOrigin: false },
      '/sanctum': { target: 'http://127.0.0.1:8000', changeOrigin: false },

      /**
       * `/storage` لازم أيضاً.
       *
       * دالة `asset()` في لارافل تبني الرابط من **مضيف الطلب** لا من
       * `APP_URL`، والطلب يصل عبر هذا الوسيط فيحمل ترويسة المضيف
       * `localhost:5173`. فتخرج روابط الصور مشيرةً إلى خادم Vite، وبلا
       * تمرير `/storage` يردّ عليها بصفحة index.html — فتظهر الصورة
       * مكسورة بلا خطأ في الشبكة (استجابة 200 وعرضٌ صفر).
       */
      '/storage': { target: 'http://127.0.0.1:8000', changeOrigin: false },
    },
  },

  test: {
    environment: 'jsdom',
    globals: true,
    include: ['tests/unit/**/*.spec.js'],
  },
}))

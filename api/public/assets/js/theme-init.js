/**
 * تطبيق السمة المحفوظة قبل أول رسم.
 *
 * سكربت عادي لا وحدة، ويُحمَّل في <head> قبل أوراق الأنماط: الوحدات مؤجّلة
 * بطبيعتها، فلو انتظرنا main.js لرأى الزائر الصفحة فاتحةً لحظةً ثم تنقلب
 * داكنة (وميض FOUC). وهو ملف منفصل لا كتلة inline لأن سياسة أمن المحتوى
 * تمنع السكربتات المضمّنة (script-src 'self').
 *
 * بلا اختيار محفوظ لا نضع شيئاً: CSS يتبع تفضيل الجهاز وحده.
 */
(function () {
  try {
    var theme = localStorage.getItem('kafala-theme');
    if (theme === 'dark' || theme === 'light') {
      document.documentElement.setAttribute('data-theme', theme);
    }
  } catch (e) {
    /* تخزين محجوب (تصفح خاص أو إعدادات صارمة) — نتبع تفضيل الجهاز */
  }
})();

/**
 * علامة `.js` تأذن لـ CSS بإخفاء عناصر الكشف عند التمرير قبل ظهورها.
 *
 * بلا JS لا توضع، فيبقى كل شيء ظاهراً. وإن وُضعت ثم تعطّل main.js (خطأ،
 * أو حُجب، أو علِق) لا تبقى الصفحة فارغة: بعد مهلة بلا إشارة جاهزية نرفعها
 * فيظهر المحتوى كما هو بلا حركة.
 */
(function () {
  var root = document.documentElement;
  root.classList.add('js');
  setTimeout(function () {
    if (!root.dataset.ready) root.classList.remove('js');
  }, 8000);
})();

/**
 * صفحات الحساب تقبل الانتقال بين المستندات (auth.css). لكن حين ننتقل منها إلى
 * صفحة لا تقبله، كاللوحة بعد الدخول أو الرئيسية، يبدأ المتصفح الانتقال ثم
 * يُلغيه في الصفحة الجديدة ويُظهر خطأً في الطرفية بلا داعٍ. فنُلغيه نحن من
 * البداية حين لا تكون الوجهة صفحة حساب.
 */
(function () {
  var AUTH = /\/(login|register|reset-password)(\.html)?$/;
  var PREFIX = /^\/en(?=\/|$)/;

  /*
   * تبديل اللغة: الصفحة نفسها بلغة أخرى («/orphans» ↔ «/en/orphans»). له
   * انتقاله الخاص في كل الصفحات (النوع «lang» في theme.css).
   */
  function isLangSwitch(from, to) {
    if (!from || !to) return false;
    var a = new URL(from, location.href);
    var b = new URL(to, location.href);
    return PREFIX.test(a.pathname) !== PREFIX.test(b.pathname)
      && (a.pathname.replace(PREFIX, '') || '/') === (b.pathname.replace(PREFIX, '') || '/');
  }

  window.addEventListener('pageswap', function (e) {
    var vt = e.viewTransition;
    if (!vt) return;
    var to = e.activation && e.activation.entry && e.activation.entry.url;
    var ignore = function () {};
    vt.ready.catch(ignore);
    vt.finished.catch(ignore);
    if (isLangSwitch(location.href, to)) {
      if (vt.types) vt.types.add('lang');
      return;
    }
    // وغير ذلك: بين صفحتي حساب وحدهما (الدخول ↔ التسجيل)
    if (!to || !AUTH.test(new URL(to).pathname) || !AUTH.test(location.pathname)) vt.skipTransition();
  });

  /*
   * اتجاه الانتقال في الصفحة الجديدة (account.css): إلى التسجيل «تقدّم»،
   * والعودة إلى الدخول «رجوع»، فينزلق النموذج في الاتجاه الذي يُقرأ به.
   * يُضبط هنا لأن هذا الملف يُحمَّل متزامناً في <head> قبل أول رسم.
   */
  window.addEventListener('pagereveal', function (e) {
    var vt = e.viewTransition;
    if (!vt || !vt.types || !window.navigation || !navigation.activation || !navigation.activation.from) return;
    if (isLangSwitch(navigation.activation.from.url, location.href)) {
      vt.types.add('lang');
      return;
    }
    var from = new URL(navigation.activation.from.url).pathname;
    var to = location.pathname;
    vt.types.add(/register/.test(to) ? 'acc-forward' : /register/.test(from) ? 'acc-back' : 'acc-forward');
  });
})();

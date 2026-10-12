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
 * صفحات الحساب تقبل الانتقال بين المستندات (auth.css). لكن حين ننتقل منها إلى
 * صفحة لا تقبله، كاللوحة بعد الدخول أو الرئيسية، يبدأ المتصفح الانتقال ثم
 * يُلغيه في الصفحة الجديدة ويُظهر خطأً في الطرفية بلا داعٍ. فنُلغيه نحن من
 * البداية حين لا تكون الوجهة صفحة حساب.
 */
(function () {
  var AUTH = /\/(login|register|reset-password)\.html$/;
  window.addEventListener('pageswap', function (e) {
    var vt = e.viewTransition;
    if (!vt) return;
    var to = e.activation && e.activation.entry && e.activation.entry.url;
    var ignore = function () {};
    vt.ready.catch(ignore);
    vt.finished.catch(ignore);
    if (!to || !AUTH.test(new URL(to).pathname)) vt.skipTransition();
  });
})();

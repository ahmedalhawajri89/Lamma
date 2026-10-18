{{--
  قالب صفحات الحساب (الدخول، والتسجيل، واستعادة كلمة المرور): Bootstrap بلا
  ترويسة الموقع ولا فوتره. صفحةٌ لها مهمة واحدة لا تُشتّت بالتنقل، وفيها
  الشعار للعودة، وتبديل السمة.
--}}
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ \App\Support\Locale::dir() }}" data-ui="bs">
<head>
  @include('site.partials.head')

  {{-- Bootstrap بنسخة اتجاه اللغة: rtl للعربية، والعادية لليسار --}}
  <link rel="stylesheet" href="/assets/vendor/bootstrap/css/{{ \App\Support\Locale::config()['css'] }}">
  <link rel="stylesheet" href="/assets/css/tokens.css">
  <link rel="stylesheet" href="/assets/css/theme.css">
  <link rel="stylesheet" href="/assets/css/account.css">
  @stack('styles')

  {{-- blocking="render": بدونه يرسم المتصفح الصفحة قبل وصول وحدات main.js
       فيُلغي انتقال الدخول ↔ التسجيل (account.css) في الصفحة الجديدة. --}}
  <script type="module" src="/assets/js/main.js" blocking="render"></script>
</head>
<body class="acc-body">
  <a class="visually-hidden-focusable kf-skip btn btn-primary" href="#main">{{ __('تخطَّ إلى المحتوى الرئيسي') }}</a>

  @yield('content')

  <script src="/assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
</body>
</html>

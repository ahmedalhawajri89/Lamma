{{--
  قالب صفحات الحساب (الدخول، والتسجيل، واستعادة كلمة المرور): Bootstrap بلا
  ترويسة الموقع ولا فوتره. صفحةٌ لها مهمة واحدة لا تُشتّت بالتنقل، وفيها
  الشعار للعودة، وتبديل السمة.
--}}
<!DOCTYPE html>
<html lang="ar" dir="rtl" data-ui="bs">
<head>
  @include('site.partials.head')

  <link rel="stylesheet" href="/assets/vendor/bootstrap/css/bootstrap.rtl.min.css">
  <link rel="stylesheet" href="/assets/css/tokens.css">
  <link rel="stylesheet" href="/assets/css/theme.css">
  <link rel="stylesheet" href="/assets/css/account.css">
  @stack('styles')

  <script type="module" src="/assets/js/main.js"></script>
</head>
<body class="acc-body">
  <a class="visually-hidden-focusable kf-skip btn btn-primary" href="#main">تخطَّ إلى المحتوى الرئيسي</a>

  @yield('content')

  <script src="/assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
</body>
</html>

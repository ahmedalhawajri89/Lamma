{{--
  قالب بوابة الكافل: تطبيقٌ صغير لا صفحة موقع. شريطٌ جانبي على الشاشة العريضة،
  وشريط تبويب سفلي على الجوال حيث يصل الإبهام، والمحتوى بينهما. بلا ترويسة
  الموقع ولا فوتره: الكافل هنا ليتابع، لا ليتصفح.
--}}
<!DOCTYPE html>
<html lang="ar" dir="rtl" data-ui="bs">
<head>
  @include('site.partials.head')

  <link rel="stylesheet" href="/assets/vendor/bootstrap/css/bootstrap.rtl.min.css">
  <link rel="stylesheet" href="/assets/css/tokens.css">
  <link rel="stylesheet" href="/assets/css/theme.css">
  <link rel="stylesheet" href="/assets/css/portal.css">
  @stack('styles')

  <script type="module" src="/assets/js/main.js"></script>
</head>
<body class="pt-body">
  <a class="visually-hidden-focusable kf-skip btn btn-primary" href="#main">تخطَّ إلى المحتوى الرئيسي</a>

  @yield('content')

  <script src="/assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
</body>
</html>

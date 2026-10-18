{{--
  قالب بوابة الكافل: تطبيقٌ صغير لا صفحة موقع. شريطٌ جانبي على الشاشة العريضة،
  وشريط تبويب سفلي على الجوال حيث يصل الإبهام، والمحتوى بينهما. بلا ترويسة
  الموقع ولا فوتره: الكافل هنا ليتابع، لا ليتصفح.
--}}
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ \App\Support\Locale::dir() }}" data-ui="bs">
<head>
  @include('site.partials.head')

  {{-- Bootstrap بنسخة اتجاه اللغة: rtl للعربية، والعادية لليسار --}}
  <link rel="stylesheet" href="/assets/vendor/bootstrap/css/{{ \App\Support\Locale::config()['css'] }}">
  <link rel="stylesheet" href="/assets/css/tokens.css">
  <link rel="stylesheet" href="/assets/css/theme.css">
  <link rel="stylesheet" href="/assets/css/portal.css">
  @stack('styles')

  {{-- blocking="render": بدونه يرسم المتصفح الصفحة قبل وصول وحدات main.js
       فيُلغي انتقال تبديل اللغة (theme.css) في الصفحة الجديدة. --}}
  <script type="module" src="/assets/js/main.js" blocking="render"></script>
</head>
<body class="pt-body">
  <a class="visually-hidden-focusable kf-skip btn btn-primary" href="#main">تخطَّ إلى المحتوى الرئيسي</a>

  @yield('content')

  <script src="/assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
</body>
</html>

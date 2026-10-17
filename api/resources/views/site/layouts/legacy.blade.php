{{--
  قالب الصفحات التي لم يُعَد تصميمها بعد: أنماط الموقع القديمة، والترويسة
  والفوتر يبنيهما layout.js في مكانيهما. حين تُصمَّم صفحة على Bootstrap
  تنتقل إلى site.layouts.app ويبقى هذا لما سواها، ثم يُحذف مع آخرها.

  $chrome = false لصفحات بلا ترويسة ولا فوتر: الدخول والتسجيل والبوابة والشكر.
--}}
@php($chrome ??= true)
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
  @include('site.partials.head')

  <link rel="stylesheet" href="/assets/css/tokens.css">
  <link rel="stylesheet" href="/assets/css/base.css">
  <link rel="stylesheet" href="/assets/css/layout.css">
  <link rel="stylesheet" href="/assets/css/site.css">
  <link rel="stylesheet" href="/assets/css/components.css">
  <link rel="stylesheet" href="/assets/css/sections.css">
  <link rel="stylesheet" href="/assets/css/pages.css">
  @stack('styles')

  <script type="module" src="/assets/js/main.js"></script>
</head>
@if ($chrome)
<body data-header="solid" class="page-body">
  <div data-layout="header"></div>
  <noscript>
    <nav class="container" style="padding-block:1rem" aria-label="تنقل بديل">
      <a href="/">الرئيسية</a> · <a href="/about">من نحن</a> ·
      <a href="/programs">برامج الكفالة</a> · <a href="/orphans">الأيتام</a> ·
      <a href="/transparency">الشفافية</a> · <a href="/blog">المدونة</a> ·
      <a href="/contact">تواصل معنا</a>
    </nav>
  </noscript>
@else
<body>
@endif

@yield('content')

@if ($chrome)
  <div data-layout="footer"></div>
@endif

@stack('after-footer')
</body>
</html>

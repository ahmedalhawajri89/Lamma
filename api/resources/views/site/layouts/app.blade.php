{{-- قالب الصفحات المصمَّمة على Bootstrap: الترويسة والفوتر من الخادم. --}}
<!DOCTYPE html>
<html lang="ar" dir="rtl" data-ui="bs">
<head>
  @include('site.partials.head')

  {{-- مشاركة اجتماعية: روابط مطلقة، فالمنصات لا تحلّ المسار النسبي --}}
  <meta property="og:type" content="@yield('og_type', 'website')">
  <meta property="og:locale" content="ar_AR">
  <meta property="og:site_name" content="{{ $site['name'] }}">
  <meta property="og:title" content="@yield('og_title', View::yieldContent('title', $site['name']))">
  <meta property="og:description" content="@yield('og_description', View::yieldContent('description'))">
  <meta property="og:url" content="{{ url()->current() }}">
  <meta property="og:image" content="@yield('og_image', asset('assets/img/hero-wide-md.webp'))">
  <meta name="twitter:card" content="summary_large_image">

  {{-- Bootstrap ثم رموز الهوية ثم جسرها إليه ثم أنماط الصفحة --}}
  <link rel="stylesheet" href="/assets/vendor/bootstrap/css/bootstrap.rtl.min.css">
  <link rel="stylesheet" href="/assets/css/tokens.css">
  <link rel="stylesheet" href="/assets/css/theme.css">
  @stack('styles')

  <script type="module" src="/assets/js/main.js"></script>
</head>

<body>
  @include('site.partials.header')

  @yield('content')

  @include('site.partials.footer')

  @stack('after-footer')

  <script src="/assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
</body>
</html>

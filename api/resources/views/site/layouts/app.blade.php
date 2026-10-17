<!DOCTYPE html>
<html lang="ar" dir="rtl" data-ui="bs">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <title>@yield('title', $site['name'])</title>
  <meta name="description" content="@yield('description')">
  <meta name="theme-color" content="#0f5c5c">

  {{-- سياسة أمان المحتوى: نسمح فقط بالمصادر التي نستخدمها فعلاً، فلا يستطيع
       سكربت خارجي أو إطار مدسوس أن يعمل داخل الصفحة. --}}
  <meta http-equiv="Content-Security-Policy"
        content="default-src 'self';
                 img-src 'self' data:;
                 style-src 'self' 'unsafe-inline' https://fonts.googleapis.com;
                 font-src 'self' https://fonts.gstatic.com;
                 script-src 'self';
                 connect-src 'self';
                 form-action 'self';
                 base-uri 'self';
                 object-src 'none'">
  <meta name="api-base" content="">
  <meta name="referrer" content="strict-origin-when-cross-origin">

  {{-- مشاركة اجتماعية: روابط مطلقة، فالمنصات لا تحلّ المسار النسبي --}}
  <meta property="og:type" content="@yield('og_type', 'website')">
  <meta property="og:locale" content="ar_AR">
  <meta property="og:site_name" content="{{ $site['name'] }}">
  <meta property="og:title" content="@yield('og_title', View::yieldContent('title', $site['name']))">
  <meta property="og:description" content="@yield('og_description', View::yieldContent('description'))">
  <meta property="og:url" content="{{ url()->current() }}">
  <meta property="og:image" content="@yield('og_image', asset('assets/img/hero-wide-md.webp'))">
  <meta name="twitter:card" content="summary_large_image">

  <link rel="canonical" href="@yield('canonical', url()->current())">
  <link rel="icon" href="/assets/img/favicon.svg" type="image/svg+xml">
  <link rel="apple-touch-icon" href="/assets/img/apple-touch-icon.png">

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Readex+Pro:wght@400;500;600;700&family=IBM+Plex+Sans+Arabic:wght@400;500;600;700&family=Aref+Ruqaa:wght@400;700&display=swap">

  {{-- السمة قبل أي رسم: يمنع وميض الوضع الفاتح لمن اختار الليلي --}}
  <script src="/assets/js/theme-init.js"></script>

  <link rel="preload" href="/assets/vendor/fontawesome/webfonts/fa-solid-900.woff2" as="font" type="font/woff2" crossorigin>
  <link rel="stylesheet" href="/assets/vendor/fontawesome/css/all.min.css">
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

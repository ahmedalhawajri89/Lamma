{{-- قالب الصفحات المصمَّمة على Bootstrap: الترويسة والفوتر من الخادم. --}}
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ \App\Support\Locale::dir() }}" data-ui="bs">
<head>
  @include('site.partials.head')

  {{-- مشاركة اجتماعية: روابط مطلقة، فالمنصات لا تحلّ المسار النسبي --}}
  <meta property="og:type" content="@yield('og_type', 'website')">
  <meta property="og:locale" content="{{ \App\Support\Locale::config()['og'] }}">
  @foreach (\App\Support\Locale::all() as $code => $lang)
    @if ($code !== app()->getLocale())<meta property="og:locale:alternate" content="{{ $lang['og'] }}">@endif
  @endforeach
  <meta property="og:site_name" content="{{ $site['name'] }}">
  <meta property="og:title" content="@yield('og_title', View::yieldContent('title', $site['name']))">
  <meta property="og:description" content="@yield('og_description', View::yieldContent('description'))">
  <meta property="og:url" content="{{ url()->current() }}">
  <meta property="og:image" content="@yield('og_image', asset('assets/img/og-image.jpg'))">
  <meta name="twitter:card" content="summary_large_image">

  {{-- Bootstrap ثم رموز الهوية ثم جسرها إليه ثم أنماط الصفحة --}}
  {{-- Bootstrap بنسخة اتجاه اللغة: rtl للعربية، والعادية لليسار --}}
  <link rel="stylesheet" href="/assets/vendor/bootstrap/css/{{ \App\Support\Locale::config()['css'] }}">
  <link rel="stylesheet" href="/assets/css/tokens.css">
  <link rel="stylesheet" href="/assets/css/theme.css">
  <link rel="stylesheet" href="/assets/css/footer.css">
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

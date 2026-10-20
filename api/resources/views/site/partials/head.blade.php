{{-- ما يشترك فيه رأس كل صفحة في القالبين: الترميز والأمان والأيقونات والخطوط والسمة.
     كل قالب يضيف بعده أنماطه، ثم الصفحة أنماطها عبر @push('styles'). --}}
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">

{{-- كل صفحة تكتب عنوانها وحده، والاسم يُلحق من الإعدادات: تغيير الاسم من مكانٍ واحد --}}
<title>@hasSection('title')@yield('title') | {{ $site['name'] }}@else{{ $site['name'] }}@endif</title>
<meta name="application-name" content="{{ $site['name'] }}">
@hasSection('description')
<meta name="description" content="@yield('description')">
@endif
@hasSection('robots')
<meta name="robots" content="@yield('robots')">
@endif
<meta name="theme-color" content="#064e3b">

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

@hasSection('canonical')
<link rel="canonical" href="@yield('canonical')">
@endif
{{-- الصفحة نفسها بكل لغة: محركات البحث تعرض لكل قارئ نسخته --}}
@foreach (\App\Support\Locale::alternates(request()) as $code => $href)
<link rel="alternate" hreflang="{{ $code }}" href="{{ $href }}">
@endforeach
<link rel="alternate" hreflang="x-default" href="{{ \App\Support\Locale::alternates(request())[\App\Support\Locale::default()] }}">

{{-- لغة الصفحة ونصوص JS: كتلة بيانات لا سكربت، فلا تخالف CSP (script-src 'self') --}}
@php
  $i18n = [
      'locale' => \App\Support\Locale::current(),
      'default' => \App\Support\Locale::default(),
      'dir' => \App\Support\Locale::dir(),
      'intl' => \App\Support\Locale::config()['intl'],
      'strings' => \App\Support\Locale::isDefault() ? (object) [] : (object) trans('js'),
  ];
@endphp
<script type="application/json" id="kf-i18n">{!! json_encode($i18n, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) !!}</script>
<link rel="icon" href="/assets/img/favicon.svg" type="image/svg+xml">
<link rel="apple-touch-icon" href="/assets/img/apple-touch-icon.png">

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Readex+Pro:wght@400;500;600;700&family=IBM+Plex+Sans+Arabic:wght@400;500;600;700&family=Aref+Ruqaa:wght@400;700&family=Tajawal:wght@300;800&display=swap">

{{-- السمة قبل أي رسم: يمنع وميض الوضع الفاتح لمن اختار الليلي --}}
<script src="/assets/js/theme-init.js"></script>

{{-- خط الأيقونات المصمتة يُطلب مع الأنماط لا بعدها، فلا تبقى الأيقونات فارغة --}}
<link rel="preload" href="/assets/vendor/fontawesome/webfonts/fa-solid-900.woff2" as="font" type="font/woff2" crossorigin>
<link rel="stylesheet" href="/assets/vendor/fontawesome/css/all.min.css">

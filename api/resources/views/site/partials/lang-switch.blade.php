{{--
  مبدّل اللغة: رابطٌ إلى الصفحة نفسها باللغة الأخرى (لا زرّ يغيّر الكوكي):
  يعمل بلا JS، ويفهرسه محرك البحث، ويُرسَل لمن يقرأ تلك اللغة.
  النص بلغة الوجهة ومعلَّمٌ بها (lang)، فيقرؤه قارئ الشاشة صحيحاً.
  $class: أصناف الرابط بحسب مكانه (الترويسة، أو شريط صفحات الحساب، أو البوابة).
--}}
@php
  $alternates = \App\Support\Locale::alternates(request());
  $current = \App\Support\Locale::current();
@endphp
@foreach (\App\Support\Locale::all() as $code => $lang)
  @continue($code === $current)
  <a class="{{ $class ?? 'kf-lang' }}" href="{{ $alternates[$code] }}" hreflang="{{ $code }}" lang="{{ $code }}"
     data-lang-switch="{{ $code }}" title="{{ $lang['name'] }}">
    <i class="fa-solid fa-globe" aria-hidden="true"></i><span aria-hidden="true">{{ $lang['short'] }}</span><span class="visually-hidden">{{ $lang['name'] }}</span>
  </a>
@endforeach

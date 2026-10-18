{{-- الترويسة تُرسم من الخادم فتظهر مع أول رسم، ولا تختفي لمن عطّل JS.
     القائمة الجوالة offcanvas-lg: درجٌ جانبي على الجوال وشريطٌ عادي من lg،
     وBootstrap يتولى فيها حبس التركيز وEscape وقفل التمرير. --}}
<a class="visually-hidden-focusable kf-skip btn btn-primary" href="#main">{{ __('تخطَّ إلى المحتوى الرئيسي') }}</a>
<header class="kf-header" id="siteHeader">
  <nav class="navbar navbar-expand-lg" aria-label="{{ __('التنقل الرئيسي') }}">
    <div class="container">
      <a class="navbar-brand kf-brand" href="{{ lurl('/') }}">
        <img src="/assets/img/logo.svg" alt="" width="46" height="46">
        <span class="kf-brand__name"><span data-site="name">{{ $site['name'] }}</span><span class="kf-brand__tag" data-site="tagline">{{ $site['tagline'] }}</span></span>
      </a>

      <div class="offcanvas-lg offcanvas-end flex-lg-grow-1" tabindex="-1" id="siteNav" aria-labelledby="siteNavTitle">
        <div class="offcanvas-header">
          <p class="offcanvas-title h5 mb-0" id="siteNavTitle">{{ __('القائمة') }}</p>
          <button type="button" class="btn-close" data-bs-dismiss="offcanvas" data-bs-target="#siteNav" aria-label="{{ __('إغلاق القائمة') }}"></button>
        </div>
        <div class="offcanvas-body">
          <ul class="navbar-nav mx-lg-auto">
            @foreach ($nav as $link)
              <li class="nav-item">
                <a @class(['nav-link', 'active' => $link['current']]) href="{{ $link['href'] }}"
                   @if ($link['current']) aria-current="{{ $link['current'] }}" @endif>{{ $link['label'] }}</a>
              </li>
            @endforeach
          </ul>
          <div class="d-grid gap-2 mt-4 d-lg-none">
            <a class="btn btn-outline-primary btn-lg" href="{{ lurl('/login') }}" data-account-link>{{ __('تسجيل الدخول') }}</a>
            <a class="btn btn-cta btn-lg" href="{{ lurl('/orphans') }}">{{ __('اكفل يتيماً') }}</a>
          </div>
        </div>
      </div>

      <div class="kf-header__actions">
        <a class="btn btn-outline-primary d-none d-lg-inline-flex" href="{{ lurl('/login') }}" data-account-link>{{ __('تسجيل الدخول') }}</a>
        <a class="btn btn-cta d-none d-sm-inline-flex" href="{{ lurl('/orphans') }}">
          <i class="fa-regular fa-heart" aria-hidden="true"></i> {{ __('اكفل يتيماً') }}
        </a>
        <a class="btn btn-icon btn-icon--plain" href="{{ lurl('/orphans') }}" aria-label="{{ __('ابحث عن طفل') }}" title="{{ __('ابحث عن طفل') }}">
          <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
        </a>
        @include('site.partials.lang-switch')
        <button class="btn btn-icon btn-icon--plain" type="button" id="themeToggle" aria-pressed="false" aria-label="{{ __('الوضع الليلي') }}">
          <i class="fa-solid fa-moon" aria-hidden="true"></i>
          <i class="fa-solid fa-sun" aria-hidden="true"></i>
        </button>
        <button class="navbar-toggler" type="button" data-bs-toggle="offcanvas" data-bs-target="#siteNav"
                aria-controls="siteNav" aria-label="{{ __('فتح قائمة التنقل') }}">
          <i class="fa-solid fa-bars" aria-hidden="true"></i>
        </button>
      </div>
    </div>
  </nav>
</header>

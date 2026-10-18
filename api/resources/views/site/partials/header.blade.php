{{-- الترويسة تُرسم من الخادم فتظهر مع أول رسم، ولا تختفي لمن عطّل JS.

     الشاشة العريضة (xl فما فوق): ثلاثة روابط وقائمة «عن لمّة»، ثم البحث
     واللغة والمظهر والحساب وزر الدعوة. وتحت xl درجٌ من جهة البداية فيه
     كل شيء، والشريط يبقى للشعار والبحث والقائمة.
     Bootstrap يتولى في الدرج والقوائم حبس التركيز وEscape وقفل التمرير؛
     والبحث نافذة <dialog> أصلية (site.partials.search، وsearch.js). --}}
@php
  $current = \App\Support\Locale::current();
  $alternates = \App\Support\Locale::alternates(request());
  $account = [
      ['label' => __('لوحتي'), 'href' => lurl('/dashboard'), 'icon' => 'fa-gauge-high'],
      ['label' => __('كفالاتي'), 'href' => lurl('/dashboard').'#children', 'icon' => 'fa-children'],
      ['label' => __('التقارير'), 'href' => lurl('/dashboard').'#reports', 'icon' => 'fa-file-lines'],
      ['label' => __('الإعدادات'), 'href' => lurl('/dashboard').'#account', 'icon' => 'fa-gear'],
  ];
@endphp
<a class="visually-hidden-focusable kf-skip btn btn-primary" href="#main">{{ __('تخطَّ إلى المحتوى الرئيسي') }}</a>
<header class="kf-header" id="siteHeader">
  <nav class="navbar navbar-expand-xl" aria-label="{{ __('التنقل الرئيسي') }}">
    <div class="container">
      <a class="navbar-brand kf-brand" href="{{ lurl('/') }}">
        <img class="kf-brand__logo" src="/assets/img/logo.svg" alt="" width="46" height="46">
        <span class="kf-brand__name"><span data-site="name">{{ $site['name'] }}</span><span class="kf-brand__tag" data-site="tagline">{{ $site['tagline'] }}</span></span>
      </a>

      <div class="offcanvas-xl offcanvas-start kf-drawer" tabindex="-1" id="siteNav" aria-labelledby="siteNavTitle">
        <div class="offcanvas-header kf-drawer__head">
          <p class="kf-drawer__title" id="siteNavTitle">
            <img src="/assets/img/logo.svg" alt="" width="34" height="34"> <span>{{ $site['name'] }}</span>
          </p>
          <button type="button" class="btn-close" data-bs-dismiss="offcanvas" data-bs-target="#siteNav" aria-label="{{ __('إغلاق القائمة') }}"></button>
        </div>

        <div class="offcanvas-body kf-drawer__body">
          <button class="kf-drawer__search" type="button" data-search-open>
            <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i> {{ __('ابحث عن طفل') }}
          </button>

          <ul class="navbar-nav kf-nav">
            @foreach ($nav as $link)
              @if (isset($link['children']))
                <li class="nav-item dropdown kf-nav__group">
                  {{-- الشاشة العريضة: قائمة بلوحة، لكل رابطٍ أيقونة ووصف --}}
                  <button @class(['nav-link dropdown-toggle kf-nav__toggle', 'is-current' => $link['current']]) type="button"
                          data-bs-toggle="dropdown" data-bs-offset="0,10" aria-expanded="false">{{ $link['label'] }}</button>
                  <div class="dropdown-menu kf-nav__panel">
                    @foreach ($link['children'] as $child)
                      <a @class(['kf-nav__item', 'is-current' => $child['current']]) href="{{ $child['href'] }}"
                         @if ($child['current']) aria-current="{{ $child['current'] }}" @endif>
                        <span class="kf-nav__item-icon" aria-hidden="true"><i class="fa-solid {{ $child['icon'] }}"></i></span>
                        <span class="kf-nav__item-text"><strong>{{ $child['label'] }}</strong><small>{{ $child['hint'] }}</small></span>
                      </a>
                    @endforeach
                  </div>

                  {{-- الدرج: الفروع ظاهرة تحت عنوانها، بلا قائمة متداخلة --}}
                  <p class="kf-nav__heading">{{ $link['label'] }}</p>
                  <ul class="kf-nav__sub">
                    @foreach ($link['children'] as $child)
                      <li>
                        <a @class(['nav-link', 'active' => $child['current']]) href="{{ $child['href'] }}"
                           @if ($child['current']) aria-current="{{ $child['current'] }}" @endif>
                          <i class="fa-solid {{ $child['icon'] }} kf-nav__icon" aria-hidden="true"></i><span>{{ $child['label'] }}</span>
                        </a>
                      </li>
                    @endforeach
                  </ul>
                </li>
              @else
                <li class="nav-item">
                  <a @class(['nav-link', 'active' => $link['current']]) href="{{ $link['href'] }}"
                     @if ($link['current']) aria-current="{{ $link['current'] }}" @endif>
                    <i class="fa-solid {{ $link['icon'] }} kf-nav__icon" aria-hidden="true"></i><span>{{ $link['label'] }}</span>
                  </a>
                </li>
              @endif
            @endforeach
          </ul>

          {{-- أسفل الدرج: الدعوة، ثم الحساب، ثم اللغة والمظهر --}}
          <div class="kf-drawer__foot">
            <a class="btn btn-cta btn-lg w-100" href="{{ lurl('/orphans') }}">
              <i class="fa-regular fa-heart" aria-hidden="true"></i> {{ __('كن من لمّة طفل') }}
            </a>

            <a class="btn btn-outline-primary btn-lg w-100" href="{{ lurl('/login') }}" data-account-login>{{ __('تسجيل الدخول') }}</a>
            <div class="kf-drawer__account" data-account hidden>
              <p class="kf-drawer__who">
                <span class="kf-avatar" aria-hidden="true" data-account-initials></span>
                <span><strong data-account-name></strong><small data-account-email dir="ltr"></small></span>
              </p>
              <ul class="kf-drawer__links">
                @foreach ($account as $item)
                  <li><a href="{{ $item['href'] }}"><i class="fa-solid {{ $item['icon'] }}" aria-hidden="true"></i> {{ $item['label'] }}</a></li>
                @endforeach
                <li><button type="button" data-account-logout><i class="fa-solid fa-arrow-right-from-bracket kf-dir-icon" aria-hidden="true"></i> {{ __('تسجيل الخروج') }}</button></li>
              </ul>
            </div>

            <div class="kf-drawer__prefs">
              <div class="kf-seg" role="group" aria-label="{{ __('اللغة') }}">
                @foreach (\App\Support\Locale::all() as $code => $lang)
                  <a href="{{ $alternates[$code] }}" hreflang="{{ $code }}" lang="{{ $code }}"
                     @if ($code === $current) aria-current="true" @endif>{{ $lang['name'] }}</a>
                @endforeach
              </div>
              <div class="kf-seg" role="group" aria-label="{{ __('المظهر') }}">
                <button type="button" data-theme-set="light" aria-pressed="false"><i class="fa-solid fa-sun" aria-hidden="true"></i> {{ __('فاتح') }}</button>
                <button type="button" data-theme-set="dark" aria-pressed="false"><i class="fa-solid fa-moon" aria-hidden="true"></i> {{ __('داكن') }}</button>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="kf-header__actions">
        <button class="kf-search-trigger" type="button" data-search-open aria-haspopup="dialog" aria-keyshortcuts="Control+K Meta+K /">
          <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
          <span class="kf-search-trigger__label">{{ __('ابحث عن طفل') }}</span>
          <kbd class="kf-search-trigger__kbd" aria-hidden="true" data-search-kbd>Ctrl K</kbd>
        </button>

        <span class="kf-header__wide">
          @include('site.partials.lang-switch')
          <button class="btn btn-icon btn-icon--plain" type="button" id="themeToggle" aria-pressed="false" aria-label="{{ __('الوضع الليلي') }}">
            <i class="fa-solid fa-moon" aria-hidden="true"></i>
            <i class="fa-solid fa-sun" aria-hidden="true"></i>
          </button>

          <a class="kf-header__login" href="{{ lurl('/login') }}" data-account-login>{{ __('تسجيل الدخول') }}</a>
          <span class="dropdown kf-account" data-account hidden>
            <button class="kf-avatar kf-avatar--button" type="button" data-bs-toggle="dropdown" data-bs-offset="0,10"
                    aria-expanded="false" aria-label="{{ __('حسابك') }}"><span data-account-initials></span></button>
            <span class="dropdown-menu dropdown-menu-end kf-account__panel">
              <span class="kf-account__who"><strong data-account-name></strong><small data-account-email dir="ltr"></small></span>
              @foreach ($account as $item)
                <a class="dropdown-item" href="{{ $item['href'] }}"><i class="fa-solid {{ $item['icon'] }}" aria-hidden="true"></i> {{ $item['label'] }}</a>
              @endforeach
              <span class="dropdown-divider" role="separator"></span>
              <button class="dropdown-item kf-account__logout" type="button" data-account-logout>
                <i class="fa-solid fa-arrow-right-from-bracket kf-dir-icon" aria-hidden="true"></i> {{ __('تسجيل الخروج') }}
              </button>
            </span>
          </span>
        </span>

        <a class="btn btn-cta kf-header__cta" href="{{ lurl('/orphans') }}">
          <i class="fa-regular fa-heart" aria-hidden="true"></i> {{ __('كن من لمّة طفل') }}
        </a>

        <button class="navbar-toggler" type="button" data-bs-toggle="offcanvas" data-bs-target="#siteNav"
                aria-controls="siteNav" aria-label="{{ __('فتح قائمة التنقل') }}">
          <i class="fa-solid fa-bars" aria-hidden="true"></i>
        </button>
      </div>
    </div>
  </nav>
</header>
@include('site.partials.search')

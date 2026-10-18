{{-- البحث السريع: Ctrl/⌘+K أو «/» أو زر الترويسة (search.js).

     <dialog> أصلي: المتصفح يحبس التركيز ويغلق بـ Escape ويعيد التركيز لما
     فتحه. والنموذج يعمل بلا JS أيضاً: Enter بلا اختيار يذهب إلى الفهرس
     بالكلمة نفسها (/orphans?q=). الاختصارات تُرسم هنا بلغة الصفحة، والنتائج
     يكتبها search.js من /public/orphans?q=. --}}
<dialog class="kf-search" id="siteSearch" aria-labelledby="siteSearchTitle">
  <form class="kf-search__box" role="search" action="{{ lurl('/orphans') }}" method="get" data-search-form>
    <h2 class="visually-hidden" id="siteSearchTitle">{{ __('ابحث عن طفل') }}</h2>
    <i class="fa-solid fa-magnifying-glass kf-search__icon" aria-hidden="true"></i>
    <input class="kf-search__input" type="search" name="q" id="siteSearchInput" maxlength="80"
           placeholder="{{ __('اسم طفل، أو مدينة، أو دولة') }}" autocomplete="off" spellcheck="false"
           role="combobox" aria-expanded="true" aria-controls="siteSearchList" aria-autocomplete="list"
           aria-labelledby="siteSearchTitle">
    <button class="kf-search__close" type="button" data-search-close aria-label="{{ __('إغلاق البحث') }}"><kbd>Esc</kbd></button>
  </form>

  <div class="kf-search__body">
    <p class="kf-search__label" id="siteSearchHeading" data-search-heading>{{ __('اختصارات') }}</p>
    <ul class="kf-search__list" id="siteSearchList" role="listbox" aria-labelledby="siteSearchHeading"></ul>
    <p class="visually-hidden" aria-live="polite" data-search-status></p>
  </div>

  <footer class="kf-search__foot" aria-hidden="true">
    <span><kbd>↑</kbd><kbd>↓</kbd> {{ __('للتنقل') }}</span>
    <span><kbd>Enter</kbd> {{ __('للفتح') }}</span>
    <span><kbd>Esc</kbd> {{ __('للإغلاق') }}</span>
  </footer>

  {{-- قبل الكتابة: صفحات الموقع، و«حالات عاجلة» --}}
  <template data-search-shortcuts>
    <li class="kf-search__opt" role="option">
      <a href="{{ lurl('/orphans?urgent=1') }}" tabindex="-1">
        <span class="kf-search__opt-icon kf-search__opt-icon--urgent" aria-hidden="true"><i class="fa-solid fa-heart-pulse"></i></span>
        <span class="kf-search__opt-text"><strong>{{ __('حالات عاجلة') }}</strong><small>{{ __('أطفال يحتاجون من يقف معهم الآن') }}</small></span>
      </a>
    </li>
    @foreach ($navFlat as $link)
      <li class="kf-search__opt" role="option">
        <a href="{{ $link['href'] }}" tabindex="-1">
          <span class="kf-search__opt-icon" aria-hidden="true"><i class="fa-solid {{ $link['icon'] }}"></i></span>
          <span class="kf-search__opt-text"><strong>{{ $link['label'] }}</strong>@if ($link['hint'])<small>{{ $link['hint'] }}</small>@endif</span>
        </a>
      </li>
    @endforeach
  </template>
</dialog>

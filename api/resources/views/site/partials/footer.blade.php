{{-- الفوتر: نسخة واحدة لكل الصفحات في القالبين. أنماطه في footer.css وحده،
     بلا اعتماد على Bootstrap ولا على الأنماط القديمة، فيظهر متطابقاً أينما كان. --}}
<footer class="kf-footer">
  <div class="kf-footer__wrap">

    <div class="kf-footer__lead">
      <div class="kf-footer__pitch">
        <p class="kf-footer__kicker">{{ __('منصة مرخّصة ومدقّقة منذ 2012') }}</p>
        <h2 class="kf-footer__statement">{{ __('كل طفل يستحق') }}<br><em>{{ __('من يسأل عنه.') }}</em></h2>
        <a class="kf-footer__cta" href="{{ lurl('/orphans') }}">{{ __('اكفل يتيماً') }} <i class="fa-solid fa-arrow-left" aria-hidden="true"></i></a>
      </div>

      <div class="kf-footer__news">
        <h2 class="kf-footer__news-title"><i class="fa-regular fa-envelope" aria-hidden="true"></i> {{ __('النشرة الشهرية') }}</h2>
        <p class="kf-footer__muted">{{ __('رسالة واحدة كل شهر: ما أنجزناه، وما صرفناه، وقصة طفل واحد.') }}</p>
        <form class="kf-news" data-validate-form data-action="newsletter" data-source="footer"
              data-success-message="تم اشتراكك، ستصلك أول رسالة مطلع الشهر" novalidate>
          <div class="field">
            <div class="kf-news__box">
              <label class="kf-footer__sr" for="footerNewsEmail">{{ __('البريد الإلكتروني') }}</label>
              <input class="kf-news__input" type="email" id="footerNewsEmail" name="email"
                     placeholder="{{ __('بريدك الإلكتروني') }}" autocomplete="email"
                     data-validate="required|email" aria-describedby="err-footerNews">
              <button class="kf-news__submit" type="submit">{{ __('اشترك') }}</button>
            </div>
            <p class="field__error" id="err-footerNews" aria-live="polite"></p>
          </div>
        </form>
      </div>
    </div>

    <div class="kf-footer__cols">
      <div class="kf-footer__about">
        <a class="kf-footer__brand" href="{{ lurl('/') }}">
          <img src="/assets/img/logo.svg" alt="" width="40" height="40">
          <span><b data-site="name">{{ $site['name'] }}</b><small data-site="tagline">{{ $site['tagline'] }}</small></span>
        </a>
        <p class="kf-footer__muted">{{ __('منصة تربط بين من يريد أن يعطي ومن يستحق أن يُعطى، بشفافية موثّقة وتقارير دورية.') }}</p>
      </div>

      <nav aria-labelledby="footerNavHeading">
        <h2 class="kf-footer__heading" id="footerNavHeading">{{ __('تصفّح') }}</h2>
        <ul class="kf-footer__links kf-footer__links--two">
          <li><a href="{{ lurl('/') }}">{{ __('الرئيسية') }}</a></li>
          @foreach ($nav as $link)
            <li><a href="{{ $link['href'] }}">{{ $link['label'] }}</a></li>
          @endforeach
        </ul>
      </nav>

      <nav aria-labelledby="footerHelpHeading">
        <h2 class="kf-footer__heading" id="footerHelpHeading">{{ __('مساعدة') }}</h2>
        <ul class="kf-footer__links">
          <li><a href="{{ lurl('/faq') }}">{{ __('الأسئلة الشائعة') }}</a></li>
          <li><a href="{{ lurl('/transparency') }}">{{ __('تقارير الشفافية') }}</a></li>
          <li><a href="{{ lurl('/transparency#child-policy') }}">{{ __('سياسة حماية الطفل') }}</a></li>
          <li><a href="{{ lurl('/dashboard') }}">{{ __('حسابي') }}</a></li>
        </ul>
      </nav>

      <div>
        <h2 class="kf-footer__heading">{{ __('تواصل معنا') }}</h2>
        <ul class="kf-footer__links">
          <li><a href="tel:{{ $site['phoneHref'] }}" dir="ltr" data-site="phone">{{ $site['phone'] }}</a></li>
          <li><a href="mailto:{{ $site['email'] }}" data-site="email">{{ $site['email'] }}</a></li>
          <li><span data-site="address">{{ $site['address'] }}</span></li>
        </ul>
        <div class="kf-footer__social">
          @foreach ($site['social'] as $social)
            <a href="{{ $social['url'] }}" aria-label="{{ __(':label (تفتح في نافذة جديدة)', ['label' => __($social['label'])]) }}" rel="noopener noreferrer" target="_blank"><i class="fa-brands {{ $social['icon'] }}" aria-hidden="true"></i></a>
          @endforeach
        </div>
      </div>
    </div>

    <div class="kf-footer__bottom">
      <p>© {{ now()->year }} <span data-site="name">{{ $site['name'] }}</span>{{ __('. جميع الحقوق محفوظة.') }}</p>
      <nav aria-label="{{ __('روابط قانونية') }}">
        <a href="{{ lurl('/transparency#child-policy') }}">{{ __('سياسة حماية الطفل') }}</a>
        <a href="{{ lurl('/transparency') }}">{{ __('الخصوصية') }}</a>
        <a href="{{ lurl('/faq') }}">{{ __('شروط الاستخدام') }}</a>
      </nav>
      <div class="kf-footer__pay" role="img" aria-label="{{ __('وسائل الدفع المقبولة: فيزا، ماستركارد، Apple Pay، والتحويل البنكي') }}">
        <i class="fa-brands fa-cc-visa" aria-hidden="true"></i>
        <i class="fa-brands fa-cc-mastercard" aria-hidden="true"></i>
        <i class="fa-brands fa-apple-pay" aria-hidden="true"></i>
        <i class="fa-solid fa-building-columns" aria-hidden="true"></i>
      </div>
    </div>
  </div>

  {{-- الاسم بخط ضخم باهت يُقصّ عند الحافة: علامة مائية لا نص يُقرأ --}}
  <p class="kf-footer__mark" aria-hidden="true"><span>{{ $site['name'] }}</span></p>
</footer>

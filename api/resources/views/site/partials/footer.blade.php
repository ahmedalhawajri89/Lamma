<footer class="kf-footer">
  <div class="container">
    <div class="kf-news-card">
      <span class="kf-news-card__icon" aria-hidden="true"><i class="fa-solid fa-envelope-open-text"></i></span>
      <div class="kf-news-card__text">
        <h2 class="kf-news-card__title">تابِع أثر الكفالة كل شهر، ولو لم تكفل بعد.</h2>
        <p class="kf-footer__hint">رسالة واحدة كل شهر: ما أنجزناه، وما صرفناه، وقصة طفل واحد.</p>
      </div>
      <form class="kf-news" data-validate-form data-action="newsletter" data-source="footer"
            data-success-message="تم اشتراكك، ستصلك أول رسالة مطلع الشهر" novalidate>
        <div class="field flex-grow-1">
          <label class="visually-hidden" for="footerNewsEmail">البريد الإلكتروني</label>
          <input class="form-control form-control-lg" type="email" id="footerNewsEmail" name="email"
                 placeholder="بريدك الإلكتروني" autocomplete="email"
                 data-validate="required|email" aria-describedby="err-footerNews">
          <p class="field__error" id="err-footerNews" aria-live="polite"></p>
        </div>
        <button class="btn btn-cta btn-lg" type="submit">اشترك</button>
      </form>
    </div>

    <div class="row g-4 g-lg-5 kf-footer__grid">
      <div class="col-lg-4">
        <a class="kf-brand kf-brand--on-ink" href="/">
          <img src="/assets/img/logo.svg" alt="" width="40" height="40">
          <span class="kf-brand__name"><span data-site="name">{{ $site['name'] }}</span><span class="kf-brand__tag" data-site="tagline">{{ $site['tagline'] }}</span></span>
        </a>
        <p class="kf-footer__about">منصة تربط بين من يريد أن يعطي ومن يستحق أن يُعطى، بشفافية موثّقة وتقارير دورية.</p>
        <div class="d-flex gap-2">
          @foreach ($site['social'] as $social)
            <a class="kf-footer__social" href="{{ $social['url'] }}" aria-label="{{ $social['label'] }} (تفتح في نافذة جديدة)" rel="noopener noreferrer" target="_blank"><i class="fa-brands {{ $social['icon'] }}" aria-hidden="true"></i></a>
          @endforeach
        </div>
      </div>
      <nav class="col-7 col-md-4 col-lg-3" aria-labelledby="footerNavHeading">
        <h2 class="kf-footer__heading" id="footerNavHeading">تصفّح</h2>
        <ul class="kf-footer__links kf-footer__links--two">
          <li><a href="/">الرئيسية</a></li>
          @foreach ($nav as $link)
            <li><a href="{{ $link['href'] }}">{{ $link['label'] }}</a></li>
          @endforeach
        </ul>
      </nav>
      <nav class="col-5 col-md-4 col-lg-2" aria-labelledby="footerHelpHeading">
        <h2 class="kf-footer__heading" id="footerHelpHeading">مساعدة</h2>
        <ul class="kf-footer__links">
          <li><a href="/faq">الأسئلة الشائعة</a></li>
          <li><a href="/transparency">تقارير الشفافية</a></li>
          <li><a href="/transparency#child-policy">سياسة حماية الطفل</a></li>
          <li><a href="/dashboard">حسابي</a></li>
        </ul>
      </nav>
      <div class="col-md-4 col-lg-3">
        <h2 class="kf-footer__heading">تواصل معنا</h2>
        <ul class="kf-footer__contact">
          <li><span class="kf-footer__ico" aria-hidden="true"><i class="fa-solid fa-phone"></i></span><a href="tel:{{ $site['phoneHref'] }}" dir="ltr" data-site="phone">{{ $site['phone'] }}</a></li>
          <li><span class="kf-footer__ico" aria-hidden="true"><i class="fa-solid fa-envelope"></i></span><a href="mailto:{{ $site['email'] }}" data-site="email">{{ $site['email'] }}</a></li>
          <li><span class="kf-footer__ico" aria-hidden="true"><i class="fa-solid fa-location-dot"></i></span><span data-site="address">{{ $site['address'] }}</span></li>
        </ul>
      </div>
    </div>

    <div class="kf-footer__bottom">
      <p class="mb-0">© {{ now()->year }} منصة <span data-site="name">{{ $site['name'] }}</span>. جميع الحقوق محفوظة.</p>
      <nav class="d-flex flex-wrap gap-3" aria-label="روابط قانونية">
        <a href="/transparency#child-policy">سياسة حماية الطفل</a>
        <a href="/transparency">الخصوصية</a>
        <a href="/faq">شروط الاستخدام</a>
      </nav>
      <div class="kf-footer__pay" role="img" aria-label="وسائل الدفع المقبولة: فيزا، ماستركارد، Apple Pay، والتحويل البنكي">
        <i class="fa-brands fa-cc-visa" aria-hidden="true"></i>
        <i class="fa-brands fa-cc-mastercard" aria-hidden="true"></i>
        <i class="fa-brands fa-apple-pay" aria-hidden="true"></i>
        <i class="fa-solid fa-building-columns" aria-hidden="true"></i>
      </div>
    </div>
  </div>
</footer>

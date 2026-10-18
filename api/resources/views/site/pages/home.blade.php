@extends('site.layouts.app')

@section('title', 'اكفل يتيماً وهو في أسرته، وتابع أثرك شهراً بشهر')
@section('description', $site['name'].': اكفل يتيماً يعيش مع أمّه أو أقاربه، فيبقى في بيته ومدرسته، وتابع أخباره وأين ذهب كل دولار في تقريرٍ مصوّر كل ثلاثة أشهر.')
@section('canonical', url(lurl('/')))
@section('og_description', 'اكفل يتيماً وهو في بيته بين أهله، وتابع أثرك بتقريرٍ مصوّر كل ثلاثة أشهر.')

@push('styles')
  <link rel="stylesheet" href="/assets/css/kids.css">
  <link rel="stylesheet" href="/assets/css/plans.css">
  <link rel="stylesheet" href="/assets/css/home.css">
@endpush

@section('content')
  <main id="main">

    <!-- ══ الهيرو: الصورة تذوب في الأرضية من جهة النص، وحافة سفلية منحنية،
         وبطاقة أرقام حيّة تتداخل مع أسفله. الحركة مرة واحدة عند الفتح. ══ -->
    <section class="hero" aria-labelledby="heroTitle">
      <figure class="hero__photo">
        <picture>
          <source media="(max-width: 61.98rem)" srcset="/assets/img/hero-girl-tall.webp">
          <img src="/assets/img/hero-girl.webp"
               srcset="/assets/img/hero-girl-md.webp 1100w, /assets/img/hero-girl.webp 1672w"
               sizes="(min-width: 62rem) 62vw, 100vw"
               alt="طفلة تحمل كتبها المدرسية وتبتسم أمام خيام المخيم"
               width="1672" height="941" fetchpriority="high">
        </picture>
      </figure>

      <!-- عبارة بخط اليد فوق الصورة، وتحتها خط ذهبي يُرسم مرة واحدة -->
      <p class="hero__script" aria-hidden="true">
        <span>بكفالتك..</span>
        <span>يبقى بين أهله</span>
        <svg class="hero__swoosh" viewBox="0 0 120 30" focusable="false"><path d="M4 24 C 34 8, 72 4, 116 6"/></svg>
      </p>

      <!-- أوراق خطية زخرفية: تُرسم خطوطها عند الفتح -->
      <svg class="hero__leaves" viewBox="0 0 140 240" aria-hidden="true" focusable="false">
        <path class="hero__stem" d="M20 236 C 46 180, 70 120, 118 20"/>
        <path d="M38 196 C 10 186, 2 160, 12 140 C 32 150, 42 172, 38 196 Z"/>
        <path d="M52 162 C 74 142, 98 142, 112 152 C 96 170, 72 174, 52 162 Z"/>
        <path d="M66 128 C 42 112, 40 86, 52 70 C 70 86, 72 108, 66 128 Z"/>
        <path d="M84 96 C 104 76, 126 72, 138 80 C 124 98, 104 104, 84 96 Z"/>
        <path d="M96 64 C 82 44, 86 22, 100 12 C 110 30, 108 50, 96 64 Z"/>
      </svg>
      <span class="hero__blob" aria-hidden="true"></span>

      <div class="container hero__inner">
        <div class="hero__text">
          <p class="hero__kicker">كفالةٌ تبقي الطفل في أسرته <span class="hero__dot" aria-hidden="true"></span> منذ <span class="num">2012</span></p>
          <h1 class="hero__title" id="heroTitle">
            <span class="hero__line">اكفل <mark class="hero__mark">يتيماً</mark>..</span>
            <span class="hero__line hero__line--brand">في لمّة أهله.</span>
          </h1>
          <p class="hero__lede">
            لأغلب الأيتام أمٌّ أو أقاربُ يحبّونهم، ويعجزون عن إعالتهم. كفالتك تُبقي
            الطفل في بيته ومدرسته، وتصلك أخباره في تقريرٍ مصوّر كل ثلاثة أشهر.
          </p>
          <div class="hero__actions">
            <a class="btn btn-cta btn-lg" href="{{ lurl('/orphans') }}">
              اكفل يتيماً <i class="fa-solid fa-arrow-left btn__icon btn__icon--arrow" aria-hidden="true"></i>
            </a>
            <a class="hero__how" href="#how">
              كيف نضمن وصولها؟
              <span class="hero__how-icon" aria-hidden="true"><i class="fa-solid fa-play"></i></span>
            </a>
          </div>
        </div>
      </div>

      <!-- الأرقام من الشفافية لحظة الفتح؛ البند الذي لا رقم له يُخفى -->
      <div class="container hero__stats-wrap">
        <ul class="hero__stats" id="heroStats" role="list" aria-label="المنصة بالأرقام">
          <li data-hero-item="sponsored">
            <span class="hero__stat-icon" aria-hidden="true"><i class="fa-solid fa-children"></i></span>
            <span class="hero__stat-text"><strong class="num" data-hero="sponsored">0</strong><span class="hero__stat-label">يتيماً مكفولاً اليوم</span></span>
          </li>
          <li data-hero-item="toChild">
            <span class="hero__stat-icon" aria-hidden="true"><i class="fa-regular fa-heart"></i></span>
            <span class="hero__stat-text"><strong class="num" data-hero="toChild">0</strong><span class="hero__stat-label">من كفالتك تصل للطفل</span></span>
          </li>
          <li data-hero-item="startYear">
            <span class="hero__stat-icon" aria-hidden="true"><i class="fa-solid fa-shield-halved"></i></span>
            <span class="hero__stat-text"><span class="hero__stat-pre">منذ</span><strong class="num" data-hero="startYear">2012</strong><span class="hero__stat-label">خبرة موثّقة</span></span>
          </li>
          <li data-hero-item="visits">
            <span class="hero__stat-icon" aria-hidden="true"><i class="fa-solid fa-house-chimney-user"></i></span>
            <span class="hero__stat-text"><strong class="num" data-hero="visits">0</strong><span class="hero__stat-label">زيارة ميدانية موثّقة</span></span>
          </li>
        </ul>
      </div>
    </section>

    <!-- ══ شريط الثقة ══ -->
    <section class="trust" aria-label="الترخيص والاعتماد">
      <div class="container d-flex flex-wrap align-items-center justify-content-between gap-3">
        <p class="trust__lead mb-0">
          <i class="fa-solid fa-shield-halved" aria-hidden="true"></i>
          جهة مرخّصة، وحساباتها يدقّقها مكتب مستقل كل سنة.
        </p>
        <div class="credentials" id="credentials"></div>
      </div>
    </section>

    <!-- ══ أطفالنا ينتظرون كافلاً: أربع بطاقات، والعاجلون أولاً ══
         الرسوم بدل الصور على الموقع العام: الصورة الحقيقية للكافل وحده في بوابته. -->
    <section class="kf-section kids" aria-labelledby="childrenTitle">
      <div class="container">
        <header class="kf-head kf-head--center" data-reveal>
          <h2 id="childrenTitle">أطفالٌ في بيوتهم، ينتظرون من يقف معهم</h2>
          <p class="kf-lede">زار مشرفٌ ميداني كل طفلٍ هنا في بيته ومع أسرته قبل نشر ملفه. تعرّف عليه باسمه وحلمه، ثم قرّر.</p>
        </header>
        <div class="row g-4" id="homeOrphans" aria-busy="true"></div>
        <div class="kids__more" data-reveal>
          <a class="btn btn-outline-primary btn-lg" href="{{ lurl('/orphans') }}">
            تعرّف على كل الأطفال <i class="fa-solid fa-arrow-left btn__icon btn__icon--arrow" aria-hidden="true"></i>
          </a>
        </div>
      </div>
    </section>

    <!-- ══ كيف تبدأ: ثلاث خطوات مرقّمة بجانب شاشة هاتف تتبدّل معها ══
         بلا دوران تلقائي: الزائر يختار الخطوة، والهاتف زينة تتبعه (aria-hidden). -->
    <section class="kf-section kf-section--surface" id="how" aria-labelledby="howTitle">
      <div class="container how" data-how>
        <header class="kf-head kf-head--center" data-reveal>
          <h2 id="howTitle">كيف تبدأ الكفالة؟</h2>
          <p class="kf-lede">ثلاث خطوات تأخذ دقائق، بلا أوراق ولا مقابلات.</p>
        </header>

        <div class="row g-4 g-lg-5 align-items-center">
          <div class="col-lg-6" data-reveal="start">
            <ol class="how-steps" role="list">
            <li class="how-step">
              <button class="how-step__tab" type="button" aria-expanded="true" aria-controls="howMore1">
                <span class="how-step__num num">1</span>
                <span class="how-step__head"><strong>اختر طفلاً</strong><span>ملفات موثّقة زارها مشرف ميداني</span></span>
              </button>
              <div class="how-step__more" id="howMore1">
                <p>كل ملف فيه عمر الطفل ومدرسته وصحته، ومع من يعيش، والمبلغ الذي تحتاجه أسرته كل شهر. ولا يُنشر ملف قبل أن يزور مشرف ميداني بيت الطفل.</p>
                <ul class="how-step__points" role="list">
                  <li><i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i> ابحث بالاسم أو المدينة أو البرنامج</li>
                  <li><i class="fa-solid fa-bolt" aria-hidden="true"></i> الحالات العاجلة تظهر أولاً</li>
                </ul>
              </div>
            </li>
            <li class="how-step">
              <button class="how-step__tab" type="button" aria-expanded="false" aria-controls="howMore2">
                <span class="how-step__num num">2</span>
                <span class="how-step__head"><strong>حدّد المبلغ ونوعه</strong><span>شهرية أو سنوية، من زكاتك أو صدقتك</span></span>
              </button>
              <div class="how-step__more" id="howMore2">
                <p>كفالة شهرية أو كل ثلاثة أشهر أو سنوية. وتحدد إن كانت من زكاتك أو من صدقتك، فنصرف كلاً منهما في بابه ويظهر ذلك في إيصالك.</p>
                <ul class="how-step__points" role="list">
                  <li><i class="fa-solid fa-credit-card" aria-hidden="true"></i> بطاقة أو Apple Pay أو تحويل بنكي</li>
                  <li><i class="fa-solid fa-scale-balanced" aria-hidden="true"></i> الزكاة لأسر ثبت استحقاقها فقط</li>
                </ul>
              </div>
            </li>
            <li class="how-step">
              <button class="how-step__tab" type="button" aria-expanded="false" aria-controls="howMore3">
                <span class="how-step__num num">3</span>
                <span class="how-step__head"><strong>تابع طفلك</strong><span>تقرير مصوّر كل 90 يوماً</span></span>
              </button>
              <div class="how-step__more" id="howMore3">
                <p>يصلك تقرير مصوّر كل 90 يوماً عن دراسته وصحته وحال أسرته، وفي حسابك كشف بكل دفعة وأين صُرفت.</p>
                <ul class="how-step__points" role="list">
                  <li><i class="fa-solid fa-circle-pause" aria-hidden="true"></i> إيقاف مؤقت أو إلغاء من حسابك</li>
                  <li><i class="fa-solid fa-comments" aria-hidden="true"></i> اسأل الفريق عن أي تقرير</li>
                </ul>
              </div>
            </li>
            </ol>
            <a class="btn btn-cta btn-lg how-cta" href="{{ lurl('/orphans') }}">
              ابدأ باختيار طفل <i class="fa-solid fa-arrow-left btn__icon btn__icon--arrow" aria-hidden="true"></i>
            </a>
          </div>

          <div class="col-lg-6" data-reveal="end" data-reveal-delay="120">
            <div class="how-phone" aria-hidden="true">
              <div class="how-orbit">
                <span class="how-orbit__disc"></span>
                <span class="how-orbit__ring how-orbit__ring--1"><i class="how-orbit__dot how-orbit__dot--gold"></i></span>
                <span class="how-orbit__ring how-orbit__ring--2"><i class="how-orbit__badge"><i class="fa-solid fa-heart"></i></i></span>
                <span class="how-orbit__ring how-orbit__ring--3"><i class="how-orbit__dot how-orbit__dot--teal"></i><i class="how-orbit__badge how-orbit__badge--gold"><i class="fa-solid fa-star"></i></i></span>
              </div>
              <div class="how-phone__frame">
                <span class="how-phone__island"></span>
              <div class="how__panel is-active" data-step="1">
                <div class="how__screen">
                <div class="how__bar"><span></span><span></span><span></span></div>
                <p class="how__label">أطفال ينتظرون كافلاً</p>
                <div class="how-search"><i class="fa-solid fa-magnifying-glass"></i> ابحث بالاسم أو المدينة</div>
                <ul class="how-kids">
                  <li class="is-picked">
                    <img class="avatar" src="/assets/img/kids/girl-02.svg" alt="" width="44" height="44">
                    <span><strong>أمنية، 8 سنوات</strong><small>غزة · الكفالة التعليمية</small></span>
                    <span class="how-kids__pick"><i class="fa-solid fa-check"></i> اخترتها</span>
                  </li>
                  <li>
                    <img class="avatar" src="/assets/img/kids/boy-03.svg" alt="" width="44" height="44">
                    <span><strong>يوسف، 7 سنوات</strong><small>الخليل · كفالة الأسرة</small></span>
                  </li>
                  <li>
                    <img class="avatar" src="/assets/img/kids/girl-08.svg" alt="" width="44" height="44">
                    <span><strong>مريم، 12 سنة</strong><small>أم درمان · الكفالة التعليمية</small></span>
                  </li>
                </ul>
              </div>
                <p class="how-float how-float--1"><span class="how-float__icon"><i class="fa-solid fa-circle-check"></i></span><span><strong>ملف موثّق</strong>زاره مشرف ميداني قبل النشر</span></p>
              </div>
              <div class="how__panel" data-step="2">
                <div class="how__screen">
                <div class="how__bar"><span></span><span></span><span></span></div>
                <p class="how__label">كفالة أمنية</p>
                <div class="how-seg"><span class="is-on">شهرية</span><span>كل 3 أشهر</span><span>سنوية</span></div>
                <div class="how-amounts"><span class="is-on num">$35</span><span class="num">$50</span><span class="num">$70</span></div>
                <p class="how__label">من أين تُدفع؟</p>
                <div class="how-kind">
                  <span class="is-on"><i class="fa-solid fa-circle-dot"></i> من زكاتي</span>
                  <span><i class="fa-regular fa-circle"></i> من صدقتي</span>
                </div>
                <div class="how-total"><span>الإجمالي هذا الشهر</span><strong class="num">$35</strong></div>
              </div>
                <p class="how-float how-float--2"><span class="how-float__icon"><i class="fa-solid fa-lock"></i></span><span><strong>دفع آمن</strong>إيصال الزكاة منفصل عن الصدقة</span></p>
              </div>
              <div class="how__panel" data-step="3">
                <div class="how__screen">
                <div class="how__bar"><span></span><span></span><span></span></div>
                <div class="how-note">
                  <span class="how-note__icon"><i class="fa-solid fa-file-image"></i></span>
                  <span><strong>وصل تقرير أمنية للربع الثالث</strong><small>الدرجات والصحة وكيف صُرفت كفالتك</small></span>
                </div>
                <p class="how__label">آخر الدفعات</p>
                <ul class="how-pays">
                  <li><span>أكتوبر</span><strong class="num">$35</strong><em>صُرفت</em></li>
                  <li><span>سبتمبر</span><strong class="num">$35</strong><em>صُرفت</em></li>
                </ul>
                <div class="how-pause">
                  <span><strong>إيقاف مؤقت</strong><small>بلا رسوم، وتستأنف متى شئت</small></span>
                  <span class="how-switch"></span>
                </div>
              </div>
                <p class="how-float how-float--3"><span class="how-float__icon"><i class="fa-solid fa-camera"></i></span><span><strong>وصل تقرير جديد</strong>الربع الثالث · مع الصور</span></p>
              </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- ══ أين تذهب كل مئة دولار: الحلقة والبنود متّصلتان ══
         المرور على بند (أو التركيز عليه) يُبرز قطعته ويكتب حصته في الوسط. -->
    <section class="kf-section kf-section--ink money" aria-labelledby="moneyTitle">
      <div class="container">
        <header class="kf-head kf-head--center" data-reveal>
          <h2 id="moneyTitle">أين تذهب كل مئة دولار؟</h2>
          <p class="kf-lede">أرقام هذه السنة من حساباتنا المعتمدة، وتتحدّث مع كل صرف. مرّر على أي بند لترى حصته.</p>
        </header>

        <div class="row g-4 g-lg-5 align-items-center">
          <div class="col-lg-7" data-reveal="start">
            <p class="money__caption">من كل <span class="num">$100</span> تدفعها</p>
            <ul class="money-list" id="moneyList" role="list"></ul>
            <div class="money__foot">
              <p><i class="fa-solid fa-file-shield" aria-hidden="true"></i> التقرير الكامل والوثائق منشورة للجميع، كفلاء وغير كفلاء.</p>
              <a class="btn btn-ink-outline" href="{{ lurl('/transparency') }}">
                اطّلع على تقرير الشفافية <i class="fa-solid fa-arrow-left btn__icon btn__icon--arrow" aria-hidden="true"></i>
              </a>
            </div>
          </div>
          <div class="col-lg-5 order-first order-lg-0" data-reveal="scale" data-reveal-delay="120">
            <div class="money-donut" id="moneyDonut"></div>
          </div>
        </div>
      </div>
    </section>

    <!-- ══ التقرير الربعي: نُري الكافل ما سيصله بدل أن نصفه ══
         كل بند مربوط بجزئه من التقرير: المرور عليه يُبرزه ويُخفت الباقي. -->
    <section class="kf-section report" aria-labelledby="reportTitle">
      <div class="container">
        <header class="kf-head kf-head--center" data-reveal>
          <h2 id="reportTitle">هذا ما يصلك عن طفلك كل ثلاثة أشهر</h2>
          <p class="kf-lede">يكتبه المشرف الميداني بعد زيارة الطفل في بيته ومدرسته، ويصلك على بريدك وفي حسابك. أربعة تقارير في السنة، لا تقرير واحد.</p>
        </header>

        <div class="row g-4 g-lg-5 align-items-center">
          <div class="col-lg-5">
            <ul class="report-points" role="list">
              <li data-reveal data-reveal-delay="0">
                <button class="report-point" type="button" data-block="study" aria-pressed="false">
                  <span class="report-point__icon" aria-hidden="true"><i class="fa-solid fa-book-open"></i></span>
                  <span><strong>الدراسة</strong><span>الدرجات في كل مادة، وملاحظة المعلم.</span></span>
                </button>
              </li>
              <li data-reveal data-reveal-delay="90">
                <button class="report-point" type="button" data-block="health" aria-pressed="false">
                  <span class="report-point__icon" aria-hidden="true"><i class="fa-solid fa-heart-pulse"></i></span>
                  <span><strong>الصحة</strong><span>آخر فحص، وأي علاج يحتاجه.</span></span>
                </button>
              </li>
              <li data-reveal data-reveal-delay="180">
                <button class="report-point" type="button" data-block="money" aria-pressed="false">
                  <span class="report-point__icon" aria-hidden="true"><i class="fa-solid fa-hand-holding-dollar"></i></span>
                  <span><strong>كفالتك</strong><span>كيف صُرف مبلغك بالدولار، بنداً بنداً.</span></span>
                </button>
              </li>
            </ul>
            <div class="report__foot" data-reveal>
              <p class="report__stat"><b class="num">4</b><span>تقارير مصوّرة<br>كل سنة</span></p>
              <a class="link-arrow" href="{{ lurl('/transparency') }}">كيف نتحقق من كل حالة <i class="fa-solid fa-arrow-left" aria-hidden="true"></i></a>
            </div>
          </div>

          <div class="col-lg-7">
            <div class="report-stage">
              <p class="report-float report-float--visit" aria-hidden="true"><span class="report-float__icon"><i class="fa-solid fa-house-user"></i></span><span><strong>زيارة ميدانية</strong>24 سبتمبر</span></p>
              <article class="report-mock" id="reportMock" data-reveal="end" data-reveal-delay="120" aria-label="نموذج توضيحي لتقرير ربعي">
                <header class="report-mock__head">
                  <img class="avatar avatar--lg" src="/assets/img/kids/girl-02.svg" alt="" width="48" height="48">
                  <div>
                    <strong>أمنية، 8 سنوات</strong>
                    <span>غزة · الكفالة التعليمية</span>
                  </div>
                  <span class="report-mock__tag">الربع الثالث <span class="num">2026</span></span>
                </header>

                <div class="report-mock__block" data-block="study">
                  <h3>الدراسة <span>الصف الثالث</span></h3>
                  <ul class="report-bars" role="list">
                    <li><span>اللغة العربية</span><span class="report-bars__track"><span style="--v:92%"></span></span><b class="num">92</b></li>
                    <li><span>الرياضيات</span><span class="report-bars__track"><span style="--v:85%"></span></span><b class="num">85</b></li>
                    <li><span>العلوم</span><span class="report-bars__track"><span style="--v:88%"></span></span><b class="num">88</b></li>
                  </ul>
                  <p class="report-mock__note">«تحسّنت قراءتها كثيراً هذا الفصل، وصارت تشارك في الحصة.» معلمة الصف</p>
                </div>

                <div class="report-mock__block report-mock__row" data-block="health">
                  <h3>الصحة</h3>
                  <p><i class="fa-solid fa-circle-check" aria-hidden="true"></i> فحص نظر في 12 سبتمبر، النتيجة سليمة</p>
                </div>

                <div class="report-mock__block" data-block="money">
                  <h3>كيف صُرفت كفالتك <span class="num">$35</span></h3>
                  <div class="report-split" role="img" aria-label="رسوم وقرطاسية 14 دولاراً، غذاء 12، صحة 5، أنشطة 4">
                    <span style="--w:40%; --c:var(--chart-1)"></span>
                    <span style="--w:34%; --c:var(--chart-4)"></span>
                    <span style="--w:14%; --c:var(--chart-2)"></span>
                    <span style="--w:12%; --c:var(--chart-5)"></span>
                  </div>
                  <ul class="report-legend" role="list">
                    <li><i style="--c:var(--chart-1)"></i>رسوم وقرطاسية <b class="num">$14</b></li>
                    <li><i style="--c:var(--chart-4)"></i>غذاء <b class="num">$12</b></li>
                    <li><i style="--c:var(--chart-2)"></i>صحة <b class="num">$5</b></li>
                    <li><i style="--c:var(--chart-5)"></i>أنشطة <b class="num">$4</b></li>
                  </ul>
                </div>

                <footer class="report-mock__foot">
                  <span>زيارة ميدانية في 24 سبتمبر</span>
                  <span>نموذج توضيحي</span>
                </footer>
              </article>
              <p class="report-float report-float--mail" aria-hidden="true"><span class="report-float__icon report-float__icon--gold"><i class="fa-solid fa-envelope-open-text"></i></span><span><strong>وصل على بريدك</strong>وفي حسابك أيضاً</span></p>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- ══ برامج الكفالة ══ -->
    <section class="kf-section kf-section--surface" aria-labelledby="programsTitle">
      <div class="container">
        <header class="kf-head kf-head--center" data-reveal>
          <h2 id="programsTitle">اختر البرنامج الذي يناسبك</h2>
          <p class="kf-lede">كل البرامج تشمل التقرير الربعي وكشف الحساب. الفرق في ما تغطيه الكفالة.</p>
        </header>
        <div class="plans" id="homePlans"></div>
        <div class="d-flex justify-content-center mt-4" data-reveal>
          <a class="link-arrow" href="{{ lurl('/programs') }}">قارن البرامج بالتفصيل <i class="fa-solid fa-arrow-left" aria-hidden="true"></i></a>
        </div>
      </div>
    </section>

    <!-- ══ كفلاء معنا ══ -->
    <section class="kf-section" aria-labelledby="storiesTitle">
      <div class="container">
        <header class="kf-head kf-head--center" data-reveal>
          <h2 id="storiesTitle">كفلاءُ صاروا من اللمّة</h2>
          <p class="kf-lede">بكلماتهم: ما الذي تغيّر حين صار للكفالة وجهٌ وأسرةٌ وتقرير.</p>
        </header>
        <div class="row g-4 align-items-stretch" id="homeVoices"></div>
      </div>
    </section>

    <!-- ══ من المدونة ══ -->
    <section class="kf-section kf-section--surface" aria-labelledby="blogTitle">
      <div class="container">
        <header class="kf-head kf-head--center" data-reveal>
          <h2 id="blogTitle">من المدونة</h2>
          <p class="kf-lede">ما نراه في الزيارات الميدانية، وكيف تُصرف الكفالات، بالأرقام.</p>
        </header>
        <div class="home-blog" id="postsGrid" data-limit="4"></div>
        <div class="d-flex justify-content-center mt-5" data-reveal>
          <a class="btn btn-outline-primary btn-lg" href="{{ lurl('/blog') }}">
            كل المقالات <i class="fa-solid fa-arrow-left btn__icon btn__icon--arrow" aria-hidden="true"></i>
          </a>
        </div>
      </div>
    </section>

    <!-- ══ أسئلة قبل أن تبدأ: الأسئلة بأيقونات موضوعها، وبطاقة تواصل بجانبها ══ -->
    <section class="kf-section faq" aria-labelledby="faqTitle">
      <div class="container">
        <header class="kf-head kf-head--center" data-reveal>
          <h2 id="faqTitle">أسئلة قبل أن تبدأ</h2>
          <p class="kf-lede">أكثر ما يسأله الكفلاء قبل القرار، بأجوبة مباشرة.</p>
        </header>
        <div class="row g-4 g-lg-5 align-items-start">
          <div class="col-lg-8">
            <div class="faq-list" data-reveal data-accordion="single" data-faq="rich" id="faqList" data-limit="4"></div>
          </div>
          <div class="col-lg-4">
            <aside class="faq-help" data-reveal data-reveal-delay="120" aria-labelledby="faqHelpTitle">
              <span class="faq-help__icon" aria-hidden="true"><i class="fa-solid fa-headset"></i></span>
              <h3 id="faqHelpTitle">لم تجد سؤالك؟</h3>
              <p>راسل الفريق، ونرد على كل رسالة خلال <b class="num">72</b> ساعة.</p>
              <a class="btn btn-primary w-100" href="{{ lurl('/contact') }}">راسل الفريق</a>
              <a class="link-arrow" href="{{ lurl('/faq') }}">كل الأسئلة الشائعة <i class="fa-solid fa-arrow-left" aria-hidden="true"></i></a>
            </aside>
          </div>
        </div>
      </div>
    </section>

    <!-- ══ الخاتمة: بطاقة دعوة على الأرضية الفاتحة، لا كتلة داكنة تلتصق بالفوتر ══ -->
    <section class="kf-section closing" aria-labelledby="closingTitle">
      <div class="container">
        <div class="closing-card" data-reveal>
          <div class="row g-4 g-lg-5 align-items-center">
            <div class="col-lg-7">
              <p class="closing__hadith">«أنا وكافل اليتيم في الجنة هكذا»<span>رواه البخاري</span></p>
              <h2 id="closingTitle" class="closing__title">كن من لمّة طفلٍ هذا الشهر</h2>
              <p class="closing__lede">من <b class="num">$35</b> في الشهر يبقى طفلٌ في بيته ومدرسته. وتستطيع إيقافها متى شئت بلا رسوم ولا اتصال.</p>
              <div class="closing__actions">
                <a class="btn btn-cta btn-lg" href="{{ lurl('/orphans') }}">
                  اكفل يتيماً <i class="fa-solid fa-arrow-left btn__icon btn__icon--arrow" aria-hidden="true"></i>
                </a>
                <a class="link-arrow" href="{{ lurl('/contact') }}">تحدّث مع فريقنا</a>
              </div>
            </div>
            <div class="col-lg-5">
              <div class="closing-kids" aria-hidden="true">
                <span class="closing-kids__ring"></span>
                <span class="closing-kids__heart"><i class="fa-solid fa-heart"></i></span>
                <img class="closing-kids__kid closing-kids__kid--1" src="/assets/img/kids/girl-02.svg" alt="" width="96" height="96">
                <img class="closing-kids__kid closing-kids__kid--2" src="/assets/img/kids/boy-03.svg" alt="" width="96" height="96">
                <img class="closing-kids__kid closing-kids__kid--3" src="/assets/img/kids/girl-08.svg" alt="" width="96" height="96">
                <img class="closing-kids__kid closing-kids__kid--4" src="/assets/img/kids/boy-07.svg" alt="" width="96" height="96">
                <img class="closing-kids__kid closing-kids__kid--5" src="/assets/img/kids/girl-11.svg" alt="" width="96" height="96">
              </div>
            </div>
          </div>

          <div class="closing__next">
            <p class="closing__next-title">ماذا يحدث بعد أن تكفل؟</p>
            <ol class="closing__steps" role="list">
              <li><span class="closing__num num">1</span><div><strong>تختار الطفل والبرنامج</strong><p>وتدفع شهرياً أو سنوياً بالطريقة التي تناسبك.</p></div></li>
              <li><span class="closing__num num">2</span><div><strong>يصلك ملف الطفل</strong><p>قصته وأسرته واحتياجاته، ويبقى في حسابك تعود إليه متى أردت.</p></div></li>
              <li><span class="closing__num num">3</span><div><strong>تقرير مصوّر كل ثلاثة أشهر</strong><p>وتستطيع الإيقاف أو التعديل من حسابك بلا إحراج.</p></div></li>
            </ol>
          </div>
        </div>
      </div>
    </section>

  </main>
@endsection

@push('after-footer')
  <!-- شريط إجراء سفلي للجوال -->
  <div class="action-bar" id="actionBar">
    <div class="action-bar__info">
      <span>الكفالة تبدأ من</span>
      <strong><b class="num">$35</b> في الشهر</strong>
    </div>
    <a class="btn btn-cta" href="{{ lurl('/orphans') }}">اكفل يتيماً</a>
  </div>

  <button class="to-top" type="button" id="toTop" aria-label="العودة إلى أعلى الصفحة">
    <i class="fa-solid fa-arrow-up" aria-hidden="true"></i>
  </button>
@endpush

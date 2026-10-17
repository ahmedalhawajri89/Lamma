@extends('site.layouts.app')

{{--
  البرامج من جدولها (ProgramsPageController): الاسم والسعر والبنود يحرّرها
  الفريق من اللوحة، ومع كلٍّ عدد من ينتظر فيه كافلاً الآن. وحاسبة الأثر
  يملؤها features.js (initImpactCalculator) كما كانت.
--}}
@php
  $money = fn ($v) => \App\Support\PublicChild::money($v);
  $bySlug = $programs->keyBy('slug');
  // «أيّها يناسبك؟»: إرشادٌ لا جدول بنود، فلا يناقض ما في البطاقات
  $guide = [
      'comprehensive' => ['fa-hands-holding-child', 'تريد أن تحمل عنه كل شيء', 'معيشته ودراسته وصحته معاً، بمبلغ واحد.'],
      'education' => ['fa-book-open-reader', 'يهمّك أن يبقى في مدرسته', 'الرسوم والكتب والزي ودروس التقوية.'],
      'health' => ['fa-stethoscope', 'تريد أن تقف مع طفلٍ مريض', 'فحوص وأدوية وعلاج وعمليات حين يلزم.'],
      'family' => ['fa-people-roof', 'تريد أن تبقى الأسرة مجتمعة', 'الطفل وإخوته وأمه، وسقفٌ ومصدر دخل.'],
  ];
@endphp

@section('title')برامج الكفالة | كفالة@endsection
@section('description')أربعة برامج كفالة: شاملة، تعليمية، صحية، وكفالة أسرة. ما يغطيه كل برنامج ومبلغه الشهري، وكم طفلاً ينتظر فيه.@endsection
@section('canonical', url('/programs'))

@push('styles')
  <link rel="stylesheet" href="/assets/css/programs.css">
@endpush

@section('content')
  <main id="main">

    {{-- ══ الصدر ══ --}}
    <section class="pg-hero" aria-labelledby="pgTitle">
      <div class="container">
        <nav aria-label="مسار التنقل">
          <ol class="breadcrumb pg-crumbs">
            <li class="breadcrumb-item"><a href="/">الرئيسية</a></li>
            <li class="breadcrumb-item active" aria-current="page">برامج الكفالة</li>
          </ol>
        </nav>
        <h1 class="pg-hero__title" id="pgTitle">برنامجٌ لكل حاجة،<br><em>وكلها تصلك تقاريرها.</em></h1>
        <p class="pg-hero__lede">المبالغ محسوبة على تكلفةٍ فعلية في كل بلد، لا مقدّرة جزافاً. ويمكنك أن تكفل جزءاً من أي برنامج، ويكمل غيرك الباقي.</p>
      </div>
    </section>

    {{-- ══ البطاقات ══ --}}
    <section class="pg-plans" aria-label="البرامج">
      <div class="container">
        <div class="row g-4">
          @foreach ($programs as $p)
            @php($n = (int) ($waiting[$p->id] ?? 0))
            <div class="col-md-6 col-xl-3">
              <article @class(['pg-card', 'pg-card--featured' => $p->is_featured]) style="--accent: {{ \App\Http\Controllers\Site\ProgramsPageController::accent($p->accent) }}" id="{{ $p->slug }}" data-reveal>
                @if ($p->is_featured)
                  <span class="pg-card__flag">الأشمل</span>
                @endif
                <span class="pg-card__icon" aria-hidden="true"><i class="fa-solid {{ preg_match('/^fa-[a-z0-9-]+$/', (string) $p->icon) ? $p->icon : 'fa-hand-holding-heart' }}"></i></span>
                <h2 class="pg-card__name">{{ $p->name_ar }}</h2>
                <p class="pg-card__summary">{{ $p->summary_ar }}</p>
                <p class="pg-card__price"><strong class="num">{{ $money($p->monthly_price) }}</strong> <span>في الشهر</span></p>

                <ul class="pg-card__features">
                  @foreach ($p->features as $f)
                    <li><i class="fa-solid fa-check" aria-hidden="true"></i> {{ $f->label_ar }}</li>
                  @endforeach
                </ul>

                <div class="pg-card__foot">
                  <p class="pg-card__waiting">
                    @if ($n)
                      <span class="pg-card__dot" aria-hidden="true"></span> <b class="num">{{ $n }}</b> {{ $n === 1 ? 'طفل ينتظر' : ($n === 2 ? 'طفلان ينتظران' : ($n <= 10 ? 'أطفال ينتظرون' : 'طفلاً ينتظرون')) }} كافلاً
                    @else
                      كل أطفاله مكفولون الآن
                    @endif
                  </p>
                  @if ($n)
                    <a class="btn {{ $p->is_featured ? 'btn-cta' : 'btn-outline-primary' }} w-100" href="/orphans?program={{ urlencode($p->slug) }}">اختر طفلاً من هذا البرنامج</a>
                  @else
                    <a class="btn btn-outline-primary w-100" href="/orphans">أطفال في برامج أخرى</a>
                  @endif
                </div>
              </article>
            </div>
          @endforeach
        </div>
      </div>
    </section>

    {{-- ══ أيّها يناسبك ══ --}}
    <section class="pg-guide" aria-labelledby="guideTitle">
      <div class="container">
        <div class="kf-head kf-head--center">
          <h2 class="kf-title" id="guideTitle">أيّها يناسبك؟</h2>
          <p class="kf-lede">ابدأ بما يهمّك أنت، والبرنامج يتبعه.</p>
        </div>
        <ul class="pg-guide__list">
          @foreach ($guide as $slug => [$icon, $when, $what])
            @continue(! $bySlug->has($slug))
            @php($p = $bySlug[$slug])
            <li>
              <a href="#{{ $slug }}" class="pg-guide__item">
                <span class="pg-guide__icon" aria-hidden="true"><i class="fa-solid {{ $icon }}"></i></span>
                <span class="pg-guide__text"><strong>{{ $when }}</strong><span>{{ $what }}</span></span>
                <span class="pg-guide__pick">{{ $p->name_ar }} <b class="num">{{ $money($p->monthly_price) }}</b> <i class="fa-solid fa-arrow-left" aria-hidden="true"></i></span>
              </a>
            </li>
          @endforeach
        </ul>
      </div>
    </section>

    {{-- ══ حاسبة الأثر: features.js ══ --}}
    <section class="pg-calc" aria-labelledby="calcTitle">
      <div class="container">
        <div class="row g-4 g-lg-5 align-items-start">
          <div class="col-lg-5">
            <h2 class="kf-title" id="calcTitle">ما الذي يغطيه مبلغك؟</h2>
            <p class="kf-lede">حرّك الشريط لترى ما يغطيه المبلغ في حياة طفلٍ واحد كل شهر.</p>
            <div class="pg-calc__amount" aria-hidden="true"><span>$</span><output class="num" id="impactAmount" for="impactRange">45</output><span>في الشهر</span></div>
            <label class="visually-hidden" for="impactRange">مبلغ الكفالة الشهرية بالدولار</label>
            <input class="form-range pg-calc__range" type="range" id="impactRange" min="15" max="150" step="5" value="45" aria-describedby="impactSuggestion">
            <div class="pg-calc__scale" aria-hidden="true"><span class="num">$15</span><span class="num">$150</span></div>
            <p class="pg-calc__hint" id="impactSuggestion" role="status" aria-live="polite"></p>
            <a class="btn btn-cta btn-lg" href="/orphans">اكفل يتيماً <i class="fa-solid fa-arrow-left ms-1" aria-hidden="true"></i></a>
          </div>
          <div class="col-lg-7">
            <p class="pg-calc__list-label">هذا المبلغ يغطي:</p>
            <ul class="pg-calc__list impact-list" id="impactList"></ul>
          </div>
        </div>
      </div>
    </section>

    {{-- ══ الزكاة ══ --}}
    <section class="pg-zakat" id="zakat" aria-labelledby="zakatTitle">
      <div class="container">
        <div class="row g-4 g-lg-5 align-items-center">
          <div class="col-lg-6">
            <p class="kf-eyebrow">مسألة فقهية</p>
            <h2 class="kf-title" id="zakatTitle">هل تُحتسب الكفالة من الزكاة؟</h2>
            <p class="pg-zakat__p">كفالة اليتيم في أصلها صدقة. لكن إن كان الطفل وأسرته من الفقراء أو المساكين، وهذا حال معظم الحالات عندنا، جاز صرف الزكاة إليهم.</p>
            <p class="pg-zakat__p">لذلك نفصل الحسابين تماماً: أموال الزكاة تُصرف على الحالات المستحقة وحدها، ولا يُخصم منها أي مصروف تشغيلي.</p>
            <a class="link-arrow" href="/faq#zakat">اقرأ التفاصيل في الأسئلة الشائعة <i class="fa-solid fa-arrow-left" aria-hidden="true"></i></a>
          </div>
          <div class="col-lg-6">
            <ol class="pg-zakat__steps">
              <li><span class="num">1</span><div><strong>تختار «من الزكاة» عند الدفع</strong><span>ويظهر ذلك في إيصالك.</span></div></li>
              <li><span class="num">2</span><div><strong>تُصرف على المستحقين وحدهم</strong><span>الأشد حاجةً وفق تقييم المشرف الميداني.</span></div></li>
              <li><span class="num">3</span><div><strong>كاملةً بلا خصم</strong><span>لا مصروف تشغيلي يُقتطع من الزكاة.</span></div></li>
              <li><span class="num">4</span><div><strong>بسجلٍّ منفصل</strong><span>يراجعه المدقق الخارجي كل سنة.</span></div></li>
            </ol>
          </div>
        </div>
      </div>
    </section>

    {{-- ══ الخاتمة ══ --}}
    <section class="pg-cta">
      <div class="container">
        <div class="pg-cta__card">
          <div>
            <h2>حدّدت البرنامج؟ اختر الطفل الآن</h2>
            <p>كل الحالات موثّقة ومزارة ميدانياً، وتنتظر من يقف معها.</p>
          </div>
          <a class="btn btn-cta btn-lg" href="/orphans">تصفّح الأطفال <i class="fa-solid fa-arrow-left ms-1" aria-hidden="true"></i></a>
        </div>
      </div>
    </section>

  </main>
@endsection

@push('after-footer')
  <button class="to-top" type="button" id="toTop" aria-label="العودة إلى أعلى الصفحة">
    <i class="fa-solid fa-arrow-up" aria-hidden="true"></i>
  </button>
@endpush

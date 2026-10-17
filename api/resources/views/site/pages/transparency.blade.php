@extends('site.layouts.app')

@section('title')الشفافية والتقارير | كفالة@endsection
@section('description')أين يذهب كل دولار؟ ميزانية مدقّقة، توزيع الإنفاق، سياسة حماية الطفل، وتقارير سنوية متاحة للجميع.@endsection
@section('canonical', url('/transparency'))

@push('styles')
  <link rel="stylesheet" href="/assets/css/transparency.css">
@endpush

@section('content')
  <main id="main">

    {{-- ══ الصدر: سؤالٌ واحد، وحلقةٌ تجيبه، وأرقام السنة تحته ══ --}}
    <section class="tp-hero" aria-labelledby="tpTitle">
      <div class="container">
        <nav aria-label="مسار التنقل">
          <ol class="breadcrumb tp-crumbs">
            <li class="breadcrumb-item"><a href="/">الرئيسية</a></li>
            <li class="breadcrumb-item active" aria-current="page">الشفافية</li>
          </ol>
        </nav>

        <div class="row g-4 g-lg-5 align-items-center">
          <div class="col-lg-7">
            <h1 class="tp-hero__title" id="tpTitle">من كل دولار تتبرّع به،<br><em>يصل الطفل <span class="num" id="tpCents">93</span> سنتاً.</em></h1>
            <p class="tp-hero__lede">والباقي تشغيلٌ نعلنه كما هو. كل رقمٍ في هذه الصفحة محسوبٌ من سجلات الصرف المعتمدة لحظة فتحها، لا مكتوبٌ باليد.</p>
            <div class="tp-years" role="group" aria-label="السنة المالية" id="tpYears"></div>

            <dl class="tp-stats" id="tpStats">
              <div><dt>دخل <span data-tp-year></span></dt><dd class="num" data-tp-stat="income">$0</dd><dd class="tp-stats__note">من دفعات الكفلاء</dd></div>
              <div><dt>صُرف على الأطفال</dt><dd class="num" data-tp-stat="spent">$0</dd><dd class="tp-stats__note">صرفٌ معتمد بإيصال</dd></div>
              <div><dt>مكفولون الآن</dt><dd class="num" data-tp-stat="sponsored">0</dd><dd class="tp-stats__note">في <span class="num" data-tp-stat="countries">0</span> دولة</dd></div>
              <div><dt>زيارات ميدانية</dt><dd class="num" data-tp-stat="visits">0</dd><dd class="tp-stats__note">في البيوت</dd></div>
            </dl>
          </div>

          <div class="col-lg-5">
            {{-- الحلقة: transparency.js يرسم القوسين بنسبتيهما ويملؤهما مرة --}}
            <figure class="tp-ring" id="tpRing" role="img" aria-label="من كل دولار 93 سنتاً تصل الطفل و7 للتشغيل">
              <div class="tp-ring__dial">
                <svg viewBox="0 0 240 240" aria-hidden="true">
                  <circle class="tp-ring__track" cx="120" cy="120" r="100"/>
                  <circle class="tp-ring__arc tp-ring__arc--child" id="tpRingChild" cx="120" cy="120" r="100"/>
                  <circle class="tp-ring__arc tp-ring__arc--ops" id="tpRingOps" cx="120" cy="120" r="100"/>
                </svg>
                <div class="tp-ring__center" aria-hidden="true">
                  <span class="tp-ring__num num" id="tpRingNum">93</span>
                  <span class="tp-ring__unit">سنتاً من كل دولار</span>
                </div>
              </div>
              <ul class="tp-ring__legend">
                <li class="tp-ring__chip tp-ring__chip--child"><span class="tp-ring__sw" aria-hidden="true"></span><span><b><span class="num" id="tpKeyChild">93</span>٪ يصل الطفل</b>غذاء وتعليم وصحة ودعم نفسي</span></li>
                <li class="tp-ring__chip tp-ring__chip--ops"><span class="tp-ring__sw" aria-hidden="true"></span><span><b><span class="num" id="tpKeyOps">7</span>٪ تشغيل</b>المشرفون الميدانيون والنقل والمكتب</span></li>
              </ul>
            </figure>
          </div>
        </div>
      </div>
    </section>

    <!-- ══ شهراً بشهر ══ -->
    <section class="tp-section" id="tpMonthlySection" aria-labelledby="tpMonthlyTitle">
      <div class="container">
        <div class="tp-head">
          <div>
            <p class="kf-eyebrow">آخر اثني عشر شهراً</p>
            <h2 id="tpMonthlyTitle">ما دخل وما صُرف، شهراً بشهر</h2>
          </div>
          <ul class="tp-key" aria-hidden="true">
            <li><span class="tp-key__dot tp-key__dot--in"></span>الدخل</li>
            <li><span class="tp-key__dot tp-key__dot--out"></span>الصرف على الأطفال</li>
          </ul>
        </div>
        <div class="tp-chart" id="tpMonthly"></div>
        <p class="tp-note" id="tpMonthlyNote"></p>
      </div>
    </section>

    <!-- ══ أين يذهب المال، والتشغيل ══ -->
    <section class="tp-section kf-section--surface" aria-labelledby="tpWhereTitle">
      <div class="container tp-where">
        <div class="tp-where__main">
          <p class="kf-eyebrow">السنة المالية <span data-tp-year></span></p>
          <h2 id="tpWhereTitle">أين يذهب كل دولار</h2>
          <div class="tp-stack" id="tpStack" role="img" aria-label="توزيع الصرف على البنود"></div>
          <ul class="tp-legend" id="tpLegend" role="list"></ul>
        </div>
        <aside class="tp-overhead" id="tpOverhead" aria-label="نسبة التشغيل"></aside>
      </div>
    </section>

    <!-- ══ الوثائق ══ -->
    <section class="tp-section" id="reports" aria-labelledby="tpDocsTitle">
      <div class="container">
        <div class="tp-head">
          <div>
            <p class="kf-eyebrow">متاحة للجميع</p>
            <h2 id="tpDocsTitle">الوثائق</h2>
          </div>
          <p class="tp-head__aside">تُحدَّث سنوياً بعد اعتماد المدقق الخارجي.</p>
        </div>
        <ul class="tp-docs" id="tpDocs" role="list"></ul>
      </div>
    </section>

    <!-- سياسة حماية الطفل -->
    <section class="tp-section kf-section--surface" id="child-policy">
      <div class="container">
        <header class="kf-head">
          <div>
            <p class="kf-eyebrow">سياسة حماية الطفل</p>
            <h2>قواعد نلتزم بها حتى حين تكلّفنا تبرعات</h2>
          </div>
        </header>

        <div class="tp-policy">
          <div>
            <span class="tp-policy__icon" aria-hidden="true"><i class="fa-solid fa-camera"></i></span>
            <h3>في التصوير والنشر</h3>
            <ul>
              <li>لا ننشر صورة طفل دون موافقة خطية من وصيّه القانوني.</li>
              <li>لا نصوّر أطفالاً باكين أو في وضع مُذِلّ، مهما كان أثر ذلك على التبرعات.</li>
              <li>لا نذكر العنوان التفصيلي أو اسم المدرسة في أي محتوى عام.</li>
              <li>يحق للأسرة سحب الموافقة في أي وقت، فنحذف المحتوى خلال ٤٨ ساعة.</li>
            </ul>
          </div>

          <div>
            <span class="tp-policy__icon" aria-hidden="true"><i class="fa-solid fa-envelope-open-text"></i></span>
            <h3>في التواصل بين الكافل والطفل</h3>
            <ul>
              <li>كل رسالة تمرّ بمشرف ميداني قبل تسليمها.</li>
              <li>لا تُشارك بيانات اتصال مباشرة بين الطرفين.</li>
              <li>الزيارات الميدانية بترتيب مسبق وبحضور مشرف دائماً.</li>
              <li>كل من يعمل مع الأطفال يخضع لفحص سوابق ودورة حماية معتمدة.</li>
            </ul>
          </div>

          <div>
            <span class="tp-policy__icon" aria-hidden="true"><i class="fa-solid fa-shield-halved"></i></span>
            <h3>في حماية بياناتك أنت</h3>
            <ul>
              <li>لا نبيع أو نشارك بياناتك مع أي جهة تسويقية.</li>
              <li>لا نحتفظ ببيانات بطاقتك على خوادمنا، فهي تُعالَج لدى بوابة الدفع مباشرة.</li>
              <li>يمكنك طلب حذف حسابك وبياناتك كاملةً في أي وقت.</li>
            </ul>
          </div>
        </div>
      </div>
    </section>

    <!-- الخاتمة -->
    <section class="tp-cta">
      <div class="container">
        <div class="tp-cta__card">
          <div>
            <h2>لديك سؤال عن بند معيّن؟</h2>
            <p>اسأل عن أي رقم في أي تقرير. نلتزم بالرد خلال ٧٢ ساعة، وننشر الجواب إن كان يهم غيرك.</p>
          </div>
          <a class="btn btn-cta btn-lg" href="/contact">اطرح سؤالك <i class="fa-solid fa-arrow-left ms-1" aria-hidden="true"></i></a>
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

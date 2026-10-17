@extends('site.layouts.legacy')

@section('title')الشفافية والتقارير | كفالة@endsection
@section('description')أين يذهب كل دولار؟ ميزانية مدقّقة، توزيع الإنفاق، سياسة حماية الطفل، وتقارير سنوية متاحة للجميع.@endsection
@section('canonical', url('/transparency'))

@section('content')
  <main id="main">

    <!-- ══ الصدر: من كل دولار، كم يصل الطفل ══ -->
    <section class="tp-hero" aria-labelledby="tpTitle">
      <div class="container tp-hero__inner">
        <div class="tp-hero__text">
          <nav aria-label="مسار التنقل">
            <ol class="breadcrumb breadcrumb--on-ink">
              <li><a href="/">الرئيسية</a></li>
              <li><span aria-current="page">الشفافية</span></li>
            </ol>
          </nav>
          <h1 class="tp-hero__title" id="tpTitle">من كل دولار تتبرّع به، يصل الطفل</h1>
          <p class="tp-hero__cents"><span class="num" id="tpCents">0</span> <span>سنتاً</span></p>
          <p class="tp-hero__lede">
            والباقي تشغيلٌ نعلنه كما هو. كل رقم في هذه الصفحة محسوبٌ من سجلات الصرف المعتمدة
            لحظة فتحها، لا مكتوبٌ باليد.
          </p>
          <div class="tp-years" role="group" aria-label="السنة المالية" id="tpYears"></div>
        </div>
        <!-- مئة خانة: كل خانة سنتٌ من الدولار، ومفتاحها تحتها -->
        <figure class="tp-cents-wrap">
          <div class="tp-cents" id="tpCells" role="img" aria-label="من كل مئة سنت"></div>
          <figcaption class="tp-cents-key">
            <span><i class="tp-cents-key__sw is-child" aria-hidden="true"></i>يصل الطفل <b class="num" id="tpKeyChild">93</b></span>
            <span><i class="tp-cents-key__sw is-ops" aria-hidden="true"></i>تشغيل <b class="num" id="tpKeyOps">7</b></span>
            <span class="tp-cents-key__note">كل مربع سنتٌ واحد من الدولار</span>
          </figcaption>
        </figure>
      </div>
    </section>

    <!-- ══ أرقام السنة ══ -->
    <section class="section tp-stats-wrap" aria-label="أرقام السنة">
      <div class="container">
        <dl class="tp-stats" id="tpStats">
          <div><dt>دخل <span data-tp-year></span></dt><dd class="num" data-tp-stat="income">$0</dd><dd class="tp-stats__note">من دفعات الكفلاء الناجحة</dd></div>
          <div><dt>صُرف على الأطفال</dt><dd class="num" data-tp-stat="spent">$0</dd><dd class="tp-stats__note">صرفٌ معتمد بإيصال</dd></div>
          <div><dt>أطفال مكفولون الآن</dt><dd class="num" data-tp-stat="sponsored">0</dd><dd class="tp-stats__note">في <span class="num" data-tp-stat="countries">0</span> دولة</dd></div>
          <div><dt>زيارات ميدانية</dt><dd class="num" data-tp-stat="visits">0</dd><dd class="tp-stats__note">زارها مشرفونا في البيوت</dd></div>
        </dl>
      </div>
    </section>

    <!-- ══ شهراً بشهر ══ -->
    <section class="section tp-section" id="tpMonthlySection" aria-labelledby="tpMonthlyTitle">
      <div class="container">
        <div class="tp-head">
          <div>
            <p class="meta-label">آخر اثني عشر شهراً</p>
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
    <section class="section section--surface tp-section" aria-labelledby="tpWhereTitle">
      <div class="container tp-where">
        <div class="tp-where__main">
          <p class="meta-label">السنة المالية <span data-tp-year></span></p>
          <h2 id="tpWhereTitle">أين يذهب كل دولار</h2>
          <div class="tp-stack" id="tpStack" role="img" aria-label="توزيع الصرف على البنود"></div>
          <ul class="tp-legend" id="tpLegend" role="list"></ul>
        </div>
        <aside class="tp-overhead" id="tpOverhead" aria-label="نسبة التشغيل"></aside>
      </div>
    </section>

    <!-- ══ الوثائق ══ -->
    <section class="section tp-section" id="reports" aria-labelledby="tpDocsTitle">
      <div class="container">
        <div class="tp-head">
          <div>
            <p class="meta-label">متاحة للجميع</p>
            <h2 id="tpDocsTitle">الوثائق</h2>
          </div>
          <p class="tp-head__aside">تُحدَّث سنوياً بعد اعتماد المدقق الخارجي.</p>
        </div>
        <ul class="tp-docs" id="tpDocs" role="list"></ul>
      </div>
    </section>

    <!-- سياسة حماية الطفل -->
    <section class="section section--surface" id="child-policy">
      <div class="container">
        <div class="block-head">
          <div class="block-head__text">
            <p class="meta-label">سياسة حماية الطفل</p>
            <h2>قواعد نلتزم بها حتى حين تكلّفنا تبرعات</h2>
          </div>
        </div>

        <div class="tp-policy">
          <div>
            <span class="tp-policy__icon" aria-hidden="true"><i class="fa-solid fa-camera"></i></span>
            <h3>في التصوير والنشر</h3>
            <ul class="check-list" role="list">
              <li>لا ننشر صورة طفل دون موافقة خطية من وصيّه القانوني.</li>
              <li>لا نصوّر أطفالاً باكين أو في وضع مُذِلّ، مهما كان أثر ذلك على التبرعات.</li>
              <li>لا نذكر العنوان التفصيلي أو اسم المدرسة في أي محتوى عام.</li>
              <li>يحق للأسرة سحب الموافقة في أي وقت، فنحذف المحتوى خلال ٤٨ ساعة.</li>
            </ul>
          </div>

          <div>
            <span class="tp-policy__icon" aria-hidden="true"><i class="fa-solid fa-envelope-open-text"></i></span>
            <h3>في التواصل بين الكافل والطفل</h3>
            <ul class="check-list" role="list">
              <li>كل رسالة تمرّ بمشرف ميداني قبل تسليمها.</li>
              <li>لا تُشارك بيانات اتصال مباشرة بين الطرفين.</li>
              <li>الزيارات الميدانية بترتيب مسبق وبحضور مشرف دائماً.</li>
              <li>كل من يعمل مع الأطفال يخضع لفحص سوابق ودورة حماية معتمدة.</li>
            </ul>
          </div>

          <div>
            <span class="tp-policy__icon" aria-hidden="true"><i class="fa-solid fa-shield-halved"></i></span>
            <h3>في حماية بياناتك أنت</h3>
            <ul class="check-list" role="list">
              <li>لا نبيع أو نشارك بياناتك مع أي جهة تسويقية.</li>
              <li>لا نحتفظ ببيانات بطاقتك على خوادمنا، فهي تُعالَج لدى بوابة الدفع مباشرة.</li>
              <li>يمكنك طلب حذف حسابك وبياناتك كاملةً في أي وقت.</li>
            </ul>
          </div>
        </div>
      </div>
    </section>

    <!-- الخاتمة -->
    <section class="closing" aria-labelledby="closingTitle">
      <div class="container">
        <div class="closing__inner closing__inner--solo">
          <div class="closing__pitch">
            <h2 id="closingTitle">لديك سؤال عن بند معيّن؟</h2>
            <p class="closing__lede">اسأل عن أي رقم في أي تقرير. نلتزم بالرد خلال ٧٢ ساعة، وننشر الجواب إن كان يهم غيرك.</p>
            <div class="closing__actions">
              <a class="btn btn--cta btn--lg" href="/contact">
                اطرح سؤالك
                <i class="fa-solid fa-arrow-left btn__icon btn__icon--arrow" aria-hidden="true"></i>
              </a>
            </div>
          </div>
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

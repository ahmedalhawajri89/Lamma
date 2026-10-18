@extends('site.layouts.app')

{{--
  «من نحن». الأرقام كلها من الخادم لحظة الفتح (about.js من /public/transparency):
  صفحة تقول «لا نُظهر ما لم نتحقق منه» لا تكتب أرقامها بيدها.
--}}
@section('title')من نحن@endsection
@section('description')عملٌ موثّق منذ 2012. تعرّف على قصتنا، وقيمنا، وكيف نتحقق من كل حالة قبل نشرها.@endsection
@section('canonical', url('/about'))

@push('styles')
  <link rel="stylesheet" href="/assets/css/about.css">
@endpush

@section('content')
  <main id="main">

    {{-- ══ الصدر ══ --}}
    <section class="ab-hero" aria-labelledby="abTitle">
      <div class="container">
        <nav aria-label="مسار التنقل">
          <ol class="breadcrumb ab-crumbs">
            <li class="breadcrumb-item"><a href="/">الرئيسية</a></li>
            <li class="breadcrumb-item active" aria-current="page">من نحن</li>
          </ol>
        </nav>

        <div class="row g-4 g-lg-5 align-items-center">
          <div class="col-lg-7">
            <p class="kf-eyebrow ab-eyebrow">منذ <span class="num" data-ab="startYear">2012</span>، في <span class="num" data-ab="countries">12</span> دولة</p>
            <h1 class="ab-title" id="abTitle">نوصل كفالتك إلى طفلٍ بعينه،<br><em>ونتابع أثرها معك.</em></h1>
            <p class="ab-lede">بدأنا بأربعة عشر طفلاً في مخيم واحد، ولم نغيّر القاعدة التي بدأنا بها: لا نُظهر حالةً لم نزرها بأنفسنا.</p>
            <div class="ab-actions">
              <a class="btn btn-cta btn-lg" href="/orphans">اكفل يتيماً <i class="fa-solid fa-arrow-left ms-1" aria-hidden="true"></i></a>
              <a class="link-arrow" href="/transparency">اطّلع على حساباتنا <i class="fa-solid fa-arrow-left" aria-hidden="true"></i></a>
            </div>
          </div>
          <div class="col-lg-5">
            <figure class="ab-art">
              <img src="/assets/img/about-illustration.webp" alt="" width="560" height="700" fetchpriority="high">
              <figcaption class="ab-art__badge">
                <span class="ab-art__icon" aria-hidden="true"><i class="fa-solid fa-house-user"></i></span>
                <span><b class="num" data-ab="visits">0</b> زيارة ميدانية موثّقة</span>
              </figcaption>
            </figure>
          </div>
        </div>

        {{-- الأرقام: تعدّ صعوداً مرة حين تظهر --}}
        <dl class="ab-figures" id="abFigures">
          <div><dt>طفلاً مكفولاً اليوم</dt><dd class="num" data-ab-count="sponsored">0</dd></div>
          <div><dt>سنة من العمل الموثّق</dt><dd class="num" data-ab-count="years">0</dd></div>
          <div><dt>دولة نعمل فيها</dt><dd class="num" data-ab-count="countries">0</dd></div>
          <div><dt>زيارة ميدانية</dt><dd class="num" data-ab-count="visits">0</dd></div>
        </dl>
      </div>
    </section>

    {{-- ══ القصة ══ --}}
    <section class="ab-story" aria-labelledby="abStoryTitle">
      <div class="container">
        <div class="row g-4 g-xl-5 align-items-center">
          <div class="col-lg-5">
            <figure class="ab-quote">
              <i class="fa-solid fa-quote-right ab-quote__mark" aria-hidden="true"></i>
              <blockquote><p>أين ذهب المبلغ؟</p></blockquote>
              <figcaption>سؤالٌ لم يجد مؤسسنا جوابه، فبنينا منصةً تجيب عنه.</figcaption>
            </figure>
          </div>
          <div class="col-lg-7">
            <p class="kf-eyebrow">قصتنا</p>
            <h2 class="kf-title" id="abStoryTitle">بدأت بسؤالٍ واحد لم نجد له جواباً مقنعاً</h2>
            <p class="ab-p">في <span class="num" data-ab="startYear">2012</span> تبرّع مؤسسنا لجمعية، وحين سأل بعد ستة أشهر «أين ذهب المبلغ؟» لم يحصل على جواب. لم يكن يشك في أمانتهم، لكنه أدرك أن غياب الجواب نفسه هو ما يوقف آلاف الناس عن العطاء.</p>
            <p class="ab-p">فبدأنا بأربعة عشر طفلاً، ودفترٍ ورقي، وقاعدةٍ واحدة: كل كافل يعرف اسم طفله وصورته وتقريره. القاعدة لم تتغيّر منذ ذلك اليوم، لكن الدفتر صار منصة.</p>
            <div class="ab-vm">
              <div>
                <span class="ab-vm__icon" aria-hidden="true"><i class="fa-solid fa-eye"></i></span>
                <h3>رؤيتنا</h3>
                <p>عالمٌ لا يُترك فيه طفلٌ وحيداً لأن أحداً لم يثق أن دعمه سيصل.</p>
              </div>
              <div>
                <span class="ab-vm__icon" aria-hidden="true"><i class="fa-solid fa-compass"></i></span>
                <h3>رسالتنا</h3>
                <p>أن نجعل الكفالة علاقةً مرئية وموثّقة، لا تحويلاً مصرفياً مجهول المصير.</p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>

    {{-- ══ المبادئ ══ --}}
    <section class="ab-principles" aria-labelledby="abPrinciplesTitle">
      <div class="container">
        <header class="kf-head kf-head--center">
          <h2 id="abPrinciplesTitle">أربعة مبادئ لا نتنازل عنها</h2>
          <p class="kf-lede">حتى حين تكلّفنا تبرعات.</p>
        </header>
        <ol class="ab-cards">
          <li data-reveal>
            <div class="ab-cards__top"><span class="ab-cards__icon" aria-hidden="true"><i class="fa-solid fa-heart"></i></span><span class="ab-cards__num num" aria-hidden="true">01</span></div>
            <h3>كرامة الطفل أولاً</h3>
            <p>لا ننشر صور أطفالٍ باكين أو مشاهد إذلال لجمع التبرعات، مهما كان أثرها على الحصيلة. الطفل ليس أداة إعلانية.</p>
          </li>
          <li data-reveal data-reveal-delay="80">
            <div class="ab-cards__top"><span class="ab-cards__icon" aria-hidden="true"><i class="fa-solid fa-scale-balanced"></i></span><span class="ab-cards__num num" aria-hidden="true">02</span></div>
            <h3>شفافية غير مشروطة</h3>
            <p>حساباتنا منشورة للعموم لا للكفلاء فقط، ومن حق أي شخص أن يسأل عن أي بند ويحصل على جواب خلال 72 ساعة.</p>
          </li>
          <li data-reveal data-reveal-delay="160">
            <div class="ab-cards__top"><span class="ab-cards__icon" aria-hidden="true"><i class="fa-solid fa-house-chimney-user"></i></span><span class="ab-cards__num num" aria-hidden="true">03</span></div>
            <h3>الأسرة قبل المؤسسة</h3>
            <p>بقاء الطفل بين أهله خيرٌ من أفضل دار رعاية، لذلك ندعم الأسرة كاملةً كي لا يُنتزع الطفل منها لأسباب مادية.</p>
          </li>
          <li data-reveal data-reveal-delay="240">
            <div class="ab-cards__top"><span class="ab-cards__icon" aria-hidden="true"><i class="fa-solid fa-seedling"></i></span><span class="ab-cards__num num" aria-hidden="true">04</span></div>
            <h3>حتى يقف بنفسه</h3>
            <p>الكفالة لا تنتهي عند الثامنة عشرة: برنامج التمكين يرافق الشاب حتى أول وظيفة أو مشروعٍ يعيله.</p>
          </li>
        </ol>
      </div>
    </section>

    {{-- ══ كيف نتحقق: خطٌّ زمني أفقي ══ --}}
    <section class="ab-verify" aria-labelledby="abVerifyTitle">
      <div class="container">
        <div class="ab-verify__head">
          <div>
            <p class="kf-eyebrow">قبل أن تظهر أي حالة</p>
            <h2 class="kf-title" id="abVerifyTitle">أربع خطوات، ثم متابعةٌ لا تنقطع</h2>
          </div>
          <p class="ab-verify__stat" id="abVerifyStat" hidden>
            <b class="num" data-ab="verifyDays">0</b>
            <span>يوماً في المتوسط بين فتح الملف وتوثيقه، محسوبةً من سجلاتنا</span>
          </p>
        </div>
        <ol class="ab-steps">
          <li data-reveal>
            <span class="ab-steps__dot" aria-hidden="true"><i class="fa-solid fa-file-shield"></i></span>
            <p class="ab-steps__when">الخطوة الأولى</p>
            <h3>الترشيح والتحقق الوثائقي</h3>
            <p>شهادة وفاة الوالد، وإثبات إقامة، وتقرير حالة اجتماعية من جهة محلية معتمدة.</p>
          </li>
          <li data-reveal data-reveal-delay="90">
            <span class="ab-steps__dot" aria-hidden="true"><i class="fa-solid fa-house-user"></i></span>
            <p class="ab-steps__when">الخطوة الثانية</p>
            <h3>زيارة ميدانية غير معلنة</h3>
            <p>مشرفٌ من فريقنا يزور البيت دون موعد مسبق، ويوثّق الحالة المعيشية كما هي فعلاً.</p>
          </li>
          <li data-reveal data-reveal-delay="180">
            <span class="ab-steps__dot" aria-hidden="true"><i class="fa-solid fa-users-viewfinder"></i></span>
            <p class="ab-steps__when">الخطوة الثالثة</p>
            <h3>المراجعة والموافقة</h3>
            <p>لجنة من ثلاثة أعضاء تراجع الملف، وتحدد البرنامج المناسب ومبلغ الكفالة العادل.</p>
          </li>
          <li data-reveal data-reveal-delay="270">
            <span class="ab-steps__dot" aria-hidden="true"><i class="fa-solid fa-rotate"></i></span>
            <p class="ab-steps__when">بعد النشر</p>
            <h3>متابعة كل 90 يوماً</h3>
            <p>زيارة ميدانية دورية، وتقرير مصوّر يصل الكافل، وتحديثٌ للملف على المنصة.</p>
          </li>
        </ol>
      </div>
    </section>

    {{-- ══ الخاتمة ══ --}}
    <section class="ab-cta">
      <div class="container">
        <div class="ab-cta__card">
          <div>
            <h2>هل ما زال لديك سؤال؟</h2>
            <p>نجيب على كل سؤال خلال 72 ساعة، مهما كان دقيقاً أو محرجاً.</p>
          </div>
          <div class="ab-cta__actions">
            <a class="btn btn-cta btn-lg" href="/orphans">اكفل يتيماً <i class="fa-solid fa-arrow-left ms-1" aria-hidden="true"></i></a>
            <a class="btn btn-outline-primary btn-lg" href="/contact">اسألنا مباشرة</a>
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

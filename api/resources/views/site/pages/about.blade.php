@extends('site.layouts.legacy')

@section('title')من نحن | كفالة@endsection
@section('description')١٤ عاماً من العمل الموثّق في ١٢ دولة. تعرّف على قصتنا، وقيمنا، وكيف نتحقق من كل حالة قبل نشرها.@endsection
@section('canonical', url('/about'))

@section('content')
  <main id="main">

    <!-- ══ الصدر ══ -->
    <section class="ab-hero" aria-labelledby="abTitle">
      <div class="container ab-hero__inner">
        <div class="ab-hero__text">
          <nav aria-label="مسار التنقل">
            <ol class="breadcrumb breadcrumb--on-ink">
              <li><a href="/">الرئيسية</a></li>
              <li><span aria-current="page">من نحن</span></li>
            </ol>
          </nav>
          <h1 class="ab-hero__title" id="abTitle">نوصل كفالتك إلى طفل بعينه،<br>ونتابع أثرها معك.</h1>
          <p class="ab-hero__lede">
            بدأنا عام <span class="num" data-ab="startYear">2012</span> بأربعة عشر طفلاً في مخيم واحد.
            اليوم نعمل في <span class="num" data-ab="countries">12</span> دولة، ولم نغيّر القاعدة التي بدأنا بها:
            لا نُظهر حالةً لم نزرها بأنفسنا.
          </p>
          <div class="ab-hero__actions">
            <a class="btn btn--cta btn--lg" href="/orphans">اكفل يتيماً <i class="fa-solid fa-arrow-left btn__icon btn__icon--arrow" aria-hidden="true"></i></a>
            <a class="ab-hero__link" href="/transparency">اطّلع على حساباتنا</a>
          </div>
        </div>
        <figure class="ab-hero__media">
          <img src="/assets/img/about-classroom.webp" alt="طفلة في فصلها الدراسي" width="560" height="700">
          <figcaption class="ab-hero__badge">
            <i class="fa-solid fa-circle-check" aria-hidden="true"></i>
            <span><b class="num" data-ab="visits">0</b> زيارة ميدانية موثّقة</span>
          </figcaption>
          <span class="ab-hero__since">منذ <b class="num" data-ab="startYear">2012</b></span>
        </figure>
      </div>
    </section>

    <!-- ══ الأرقام: من الخادم لحظة الفتح ══ -->
    <section class="ab-figures-wrap" aria-label="المنصة بالأرقام">
      <div class="container">
        <dl class="ab-figures" id="abFigures">
          <div><dt>طفلاً مكفولاً اليوم</dt><dd class="num" data-ab-count="sponsored">0</dd></div>
          <div><dt>سنة من العمل الموثّق</dt><dd class="num" data-ab-count="years">0</dd></div>
          <div><dt>دولة نعمل فيها</dt><dd class="num" data-ab-count="countries">0</dd></div>
          <div><dt>زيارة ميدانية</dt><dd class="num" data-ab-count="visits">0</dd></div>
        </dl>
      </div>
    </section>

    <!-- ══ القصة ══ -->
    <section class="section ab-story" aria-labelledby="abStoryTitle">
      <div class="container ab-story__inner">
        <blockquote class="ab-quote">
          <p>«أين ذهب المبلغ؟»</p>
          <footer>سؤالٌ لم يجد مؤسسنا جوابه، فبنينا منصةً تجيب عنه.</footer>
        </blockquote>
        <div class="ab-story__text">
          <p class="meta-label">قصتنا</p>
          <h2 id="abStoryTitle">بدأت بسؤال واحد لم نجد له جواباً مقنعاً</h2>
          <p>
            في <span class="num" data-ab="startYear">2012</span> تبرّع مؤسسنا لجمعية، وحين سأل بعد ستة أشهر «أين ذهب المبلغ؟»
            لم يحصل على جواب. لم يكن يشك في أمانتهم، لكنه أدرك أن غياب الجواب
            نفسه هو ما يوقف آلاف الناس عن العطاء.
          </p>
          <p>
            فبدأنا بأربعة عشر طفلاً، ودفتر ورقي، وقاعدة واحدة:
            كل كافل يعرف اسم طفله وصورته وتقريره. القاعدة لم تتغيّر منذ ذلك اليوم،
            لكن الدفتر صار منصة.
          </p>
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
    </section>

    <!-- ══ المبادئ ══ -->
    <section class="section section--surface" aria-labelledby="abPrinciplesTitle">
      <div class="container">
        <div class="ab-head">
          <p class="meta-label">ما نلتزم به</p>
          <h2 id="abPrinciplesTitle">أربعة مبادئ لا نتنازل عنها، حتى حين تكلّفنا تبرعات</h2>
        </div>
        <ol class="ab-principles" role="list">
          <li data-reveal>
            <span class="ab-principles__icon" aria-hidden="true"><i class="fa-solid fa-heart"></i></span>
            <span class="ab-principles__num num">01</span>
            <h3>كرامة الطفل أولاً</h3>
            <p>لا ننشر صور أطفال باكين أو مشاهد إذلال لجمع التبرعات، مهما كان أثرها على الحصيلة. الطفل ليس أداة إعلانية.</p>
          </li>
          <li data-reveal data-reveal-delay="80">
            <span class="ab-principles__icon" aria-hidden="true"><i class="fa-solid fa-scale-balanced"></i></span>
            <span class="ab-principles__num num">02</span>
            <h3>شفافية غير مشروطة</h3>
            <p>حساباتنا منشورة للعموم لا للكفلاء فقط. ومن حق أي شخص أن يسأل عن أي بند ويحصل على جواب خلال 72 ساعة.</p>
          </li>
          <li data-reveal data-reveal-delay="160">
            <span class="ab-principles__icon" aria-hidden="true"><i class="fa-solid fa-house-chimney-user"></i></span>
            <span class="ab-principles__num num">03</span>
            <h3>الأسرة قبل المؤسسة</h3>
            <p>بقاء الطفل بين أهله خيرٌ من أفضل دار رعاية. لذلك ندعم الأسرة كاملةً كي لا يُنتزع الطفل منها لأسباب مادية.</p>
          </li>
          <li data-reveal data-reveal-delay="240">
            <span class="ab-principles__icon" aria-hidden="true"><i class="fa-solid fa-seedling"></i></span>
            <span class="ab-principles__num num">04</span>
            <h3>حتى يقف بنفسه</h3>
            <p>الكفالة لا تنتهي عند الثامنة عشرة. برنامج التمكين يرافق الشاب حتى أول وظيفة أو مشروع يعيله.</p>
          </li>
        </ol>
      </div>
    </section>

    <!-- ══ كيف نتحقق ══ -->
    <section class="section" aria-labelledby="abVerifyTitle">
      <div class="container">
        <div class="ab-head ab-head--split">
          <div>
            <p class="meta-label">قبل أن تظهر أي حالة</p>
            <h2 id="abVerifyTitle">أربع خطوات، ثم متابعةٌ لا تنقطع</h2>
          </div>
          <p class="ab-head__stat" id="abVerifyStat" hidden>
            <b class="num" data-ab="verifyDays">0</b>
            <span>يوماً في المتوسط بين فتح الملف وتوثيقه، محسوبةً من سجلاتنا</span>
          </p>
        </div>
        <ol class="ab-steps" role="list">
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
            <p>زيارة ميدانية دورية، وتقرير مصوّر يصل الكافل، وتحديث للملف على المنصة.</p>
          </li>
        </ol>
      </div>
    </section>

    <!-- الخاتمة -->
    <section class="closing" aria-labelledby="closingTitle">
      <div class="container">
        <div class="closing__inner closing__inner--solo">
          <div class="closing__pitch">
            <h2 id="closingTitle">هل ما زال لديك سؤال؟</h2>
            <p class="closing__lede">نجيب على كل سؤال خلال 72 ساعة، مهما كان دقيقاً أو محرجاً.</p>
            <div class="closing__actions">
              <a class="btn btn--cta btn--lg" href="/orphans">
                اكفل يتيماً
                <i class="fa-solid fa-arrow-left btn__icon btn__icon--arrow" aria-hidden="true"></i>
              </a>
              <a class="closing__link" href="/contact">اسألنا مباشرة</a>
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

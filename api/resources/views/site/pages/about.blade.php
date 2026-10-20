@extends('site.layouts.app')

{{--
  «من نحن». الأرقام كلها من الخادم لحظة الفتح (about.js من /public/transparency):
  صفحة تقول «لا نُظهر ما لم نتحقق منه» لا تكتب أرقامها بيدها.
--}}
@section('title', __('من نحن'))
@section('description', __('عملٌ موثّق منذ 2012. تعرّف على قصتنا، وقيمنا، وكيف نتحقق من كل حالة قبل نشرها.'))
@section('canonical', url(lurl('/about')))

@push('styles')
  <link rel="stylesheet" href="/assets/css/about.css">
@endpush

@section('content')
  <main id="main">

    {{-- ══ الصدر ══ --}}
    <section class="ab-hero" aria-labelledby="abTitle">
      <div class="container">
        <nav aria-label="{{ __('مسار التنقل') }}">
          <ol class="breadcrumb ab-crumbs">
            <li class="breadcrumb-item"><a href="{{ lurl('/') }}">{{ __('الرئيسية') }}</a></li>
            <li class="breadcrumb-item active" aria-current="page">{{ __('من نحن') }}</li>
          </ol>
        </nav>

        <div class="row g-4 g-lg-5 align-items-center">
          <div class="col-lg-7">
            <p class="kf-eyebrow ab-eyebrow">{{ __('منذ') }} <span class="num" data-ab="startYear">2012</span>{{ __('، في') }} <span class="num" data-ab="countries">12</span> {{ __('دولة') }}</p>
            <h1 class="ab-title" id="abTitle">{{ __('نُبقي الطفل في لمّة أهله،') }}<br><em>{{ __('ونريك أثر عطائك فيها.') }}</em></h1>
            <p class="ab-lede">{{ __('بدأنا بأربعة عشر طفلاً في مخيم واحد، ولم نغيّر القاعدة التي بدأنا بها: لا نُظهر حالةً لم نزرها في بيتها بأنفسنا.') }}</p>
            <div class="ab-actions">
              <a class="btn btn-cta btn-lg" href="{{ lurl('/orphans') }}">{{ __('كن من لمّة طفل') }} <i class="fa-solid fa-arrow-left ms-1" aria-hidden="true"></i></a>
              <a class="link-arrow" href="{{ lurl('/transparency') }}">{{ __('اطّلع على حساباتنا') }} <i class="fa-solid fa-arrow-left" aria-hidden="true"></i></a>
            </div>
          </div>
          <div class="col-lg-5">
            <figure class="ab-art">
              <img src="/assets/img/about-illustration.webp" alt="" width="560" height="700" fetchpriority="high">
              <figcaption class="ab-art__badge">
                <span class="ab-art__icon" aria-hidden="true"><i class="fa-solid fa-house-user"></i></span>
                <span><b class="num" data-ab="visits">0</b> {{ __('زيارة ميدانية موثّقة') }}</span>
              </figcaption>
            </figure>
          </div>
        </div>

        {{-- الأرقام: تعدّ صعوداً مرة حين تظهر --}}
        <dl class="ab-figures" id="abFigures">
          <div><dt>{{ __('طفلاً مكفولاً اليوم') }}</dt><dd class="num" data-ab-count="sponsored">0</dd></div>
          <div><dt>{{ __('سنة من العمل الموثّق') }}</dt><dd class="num" data-ab-count="years">0</dd></div>
          <div><dt>{{ __('دولة نعمل فيها') }}</dt><dd class="num" data-ab-count="countries">0</dd></div>
          <div><dt>{{-- تحت رقم: «1,240 home visits» لا «Home visit» التي يحملها المفتاح نفسه في مواضع أخرى --}}{{ \App\Support\Locale::pick('زيارة ميدانية', 'home visits') }}</dt><dd class="num" data-ab-count="visits">0</dd></div>
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
              <blockquote><p>{{ __('أين ذهب المبلغ؟') }}</p></blockquote>
              <figcaption>{{ __('سؤالٌ لم يجد مؤسسنا جوابه، فبنينا منصةً تجيب عنه.') }}</figcaption>
            </figure>
          </div>
          <div class="col-lg-7">
            <p class="kf-eyebrow">{{ __('قصتنا') }}</p>
            <h2 class="kf-title" id="abStoryTitle">{{ __('بدأت بسؤالٍ واحد لم نجد له جواباً مقنعاً') }}</h2>
            <p class="ab-p">{{ \App\Support\Locale::pick('في', 'In') }} <span class="num" data-ab="startYear">2012</span> {{ __('تبرّع مؤسسنا لجمعية، وحين سأل بعد ستة أشهر «أين ذهب المبلغ؟» لم يحصل على جواب. لم يكن يشك في أمانتهم، لكنه أدرك أن غياب الجواب نفسه هو ما يوقف آلاف الناس عن العطاء.') }}</p>
            <p class="ab-p">{{ __('فبدأنا بأربعة عشر طفلاً، ودفترٍ ورقي، وقاعدةٍ واحدة: كل كافل يعرف اسم طفله وصورته وتقريره. القاعدة لم تتغيّر منذ ذلك اليوم، لكن الدفتر صار منصة.') }}</p>
            <p class="ab-p">{{ __('ومن آلاف الزيارات تعلّمنا شيئاً ثانياً: أغلب من نسمّيهم أيتاماً لهم أمٌّ أو جدّةٌ أو عمٌّ يحبّونهم ويعجزون عن إعالتهم. فصارت الكفالة تذهب إلى الطفل في بيته، بين أهله، لا إلى دارٍ تأخذه منهم. ومن هنا الاسم:') }} <b>{{ __('«لمّة»') }}</b>{{ __('، لمّة العيلة التي تبقى حول الطفل، والكافل واحدٌ منها.') }}</p>
            <div class="ab-vm">
              <div>
                <span class="ab-vm__icon" aria-hidden="true"><i class="fa-solid fa-eye"></i></span>
                <h3>{{ __('رؤيتنا') }}</h3>
                <p>{{ __('عالمٌ لا يُنتزع فيه طفلٌ من أهله لأن أحداً لم يقف معهم.') }}</p>
              </div>
              <div>
                <span class="ab-vm__icon" aria-hidden="true"><i class="fa-solid fa-compass"></i></span>
                <h3>{{ __('رسالتنا') }}</h3>
                <p>{{ __('أن نجعل الكفالة علاقةً مرئية وموثّقة بين كافلٍ وطفلٍ في بيته، لا تحويلاً مصرفياً مجهول المصير.') }}</p>
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
          <h2 id="abPrinciplesTitle">{{ __('أربعة مبادئ لا نتنازل عنها') }}</h2>
          <p class="kf-lede">{{ __('حتى حين تكلّفنا تبرعات.') }}</p>
        </header>
        <ol class="ab-cards">
          <li data-reveal>
            <div class="ab-cards__top"><span class="ab-cards__icon" aria-hidden="true"><i class="fa-solid fa-heart"></i></span><span class="ab-cards__num num" aria-hidden="true">01</span></div>
            <h3>{{ __('كرامة الطفل أولاً') }}</h3>
            <p>{{ __('لا ننشر صور أطفالٍ باكين أو مشاهد إذلال لجمع التبرعات، مهما كان أثرها على الحصيلة. الطفل ليس أداة إعلانية.') }}</p>
          </li>
          <li data-reveal data-reveal-delay="80">
            <div class="ab-cards__top"><span class="ab-cards__icon" aria-hidden="true"><i class="fa-solid fa-scale-balanced"></i></span><span class="ab-cards__num num" aria-hidden="true">02</span></div>
            <h3>{{ __('شفافية غير مشروطة') }}</h3>
            <p>{{ __('حساباتنا منشورة للعموم لا للكفلاء فقط، ومن حق أي شخص أن يسأل عن أي بند ويحصل على جواب خلال 72 ساعة.') }}</p>
          </li>
          <li data-reveal data-reveal-delay="160">
            <div class="ab-cards__top"><span class="ab-cards__icon" aria-hidden="true"><i class="fa-solid fa-house-chimney-user"></i></span><span class="ab-cards__num num" aria-hidden="true">03</span></div>
            <h3>{{ __('الأسرة قبل المؤسسة') }}</h3>
            <p>{{ __('بقاء الطفل بين أهله خيرٌ من أفضل دار رعاية، لذلك ندعم الأسرة كاملةً كي لا يُنتزع الطفل منها لأسباب مادية.') }}</p>
          </li>
          <li data-reveal data-reveal-delay="240">
            <div class="ab-cards__top"><span class="ab-cards__icon" aria-hidden="true"><i class="fa-solid fa-seedling"></i></span><span class="ab-cards__num num" aria-hidden="true">04</span></div>
            <h3>{{ __('حتى يقف بنفسه') }}</h3>
            <p>{{ __('الكفالة لا تنتهي عند الثامنة عشرة: برنامج التمكين يرافق الشاب حتى أول وظيفة أو مشروعٍ يعيله.') }}</p>
          </li>
        </ol>
      </div>
    </section>

    {{-- ══ كيف نتحقق: خطٌّ زمني أفقي ══ --}}
    <section class="ab-verify" aria-labelledby="abVerifyTitle">
      <div class="container">
        <div class="ab-verify__head">
          <div>
            <p class="kf-eyebrow">{{ __('قبل أن تظهر أي حالة') }}</p>
            <h2 class="kf-title" id="abVerifyTitle">{{ __('أربع خطوات، ثم متابعةٌ لا تنقطع') }}</h2>
          </div>
          <p class="ab-verify__stat" id="abVerifyStat" hidden>
            <b class="num" data-ab="verifyDays">0</b>
            <span>{{ __('يوماً في المتوسط بين فتح الملف وتوثيقه، محسوبةً من سجلاتنا') }}</span>
          </p>
        </div>
        <ol class="ab-steps">
          <li data-reveal>
            <span class="ab-steps__dot" aria-hidden="true"><i class="fa-solid fa-file-shield"></i></span>
            <p class="ab-steps__when">{{ __('الخطوة الأولى') }}</p>
            <h3>{{ __('الترشيح والتحقق الوثائقي') }}</h3>
            <p>{{ __('شهادة وفاة الوالد، وإثبات إقامة، وتقرير حالة اجتماعية من جهة محلية معتمدة.') }}</p>
          </li>
          <li data-reveal data-reveal-delay="90">
            <span class="ab-steps__dot" aria-hidden="true"><i class="fa-solid fa-house-user"></i></span>
            <p class="ab-steps__when">{{ __('الخطوة الثانية') }}</p>
            <h3>{{ __('زيارة ميدانية غير معلنة') }}</h3>
            <p>{{ __('مشرفٌ من فريقنا يزور البيت دون موعد مسبق، ويوثّق الحالة المعيشية كما هي فعلاً.') }}</p>
          </li>
          <li data-reveal data-reveal-delay="180">
            <span class="ab-steps__dot" aria-hidden="true"><i class="fa-solid fa-users-viewfinder"></i></span>
            <p class="ab-steps__when">{{ __('الخطوة الثالثة') }}</p>
            <h3>{{ __('المراجعة والموافقة') }}</h3>
            <p>{{ __('لجنة من ثلاثة أعضاء تراجع الملف، وتحدد البرنامج المناسب ومبلغ الكفالة العادل.') }}</p>
          </li>
          <li data-reveal data-reveal-delay="270">
            <span class="ab-steps__dot" aria-hidden="true"><i class="fa-solid fa-rotate"></i></span>
            <p class="ab-steps__when">{{ __('بعد النشر') }}</p>
            <h3>{{ __('متابعة كل 90 يوماً') }}</h3>
            <p>{{ __('زيارة ميدانية دورية، وتقرير مصوّر يصل الكافل، وتحديثٌ للملف على المنصة.') }}</p>
          </li>
        </ol>
      </div>
    </section>

    {{-- ══ الخاتمة ══ --}}
    <section class="ab-cta">
      <div class="container">
        <div class="ab-cta__card">
          <div>
            <h2>{{ __('هل ما زال لديك سؤال؟') }}</h2>
            <p>{{ __('نجيب على كل سؤال خلال 72 ساعة، مهما كان دقيقاً أو محرجاً.') }}</p>
          </div>
          <div class="ab-cta__actions">
            <a class="btn btn-cta btn-lg" href="{{ lurl('/orphans') }}">{{ __('كن من لمّة طفل') }} <i class="fa-solid fa-arrow-left ms-1" aria-hidden="true"></i></a>
            <a class="btn btn-outline-primary btn-lg" href="{{ lurl('/contact') }}">{{ __('اسألنا مباشرة') }}</a>
          </div>
        </div>
      </div>
    </section>

  </main>
@endsection

@push('after-footer')
  <button class="to-top" type="button" id="toTop" aria-label="{{ __('العودة إلى أعلى الصفحة') }}">
    <i class="fa-solid fa-arrow-up" aria-hidden="true"></i>
  </button>
@endpush

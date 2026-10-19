@extends('site.layouts.portal')

{{--
  بوابة الكافل، بلغة لوحة الإدارة نفسها: شريطٌ جانبي بأقسامٍ مجمّعة يُطوى إلى
  عمود أيقونات، ورأسٌ لكل صفحة بعنوانها وإجرائها، وأرقامٌ في بطاقةٍ مقسّمة.
  على الجوال يصير الشريط درجاً (offcanvas-lg) يُفتح من شريطٍ علوي.

  خمسة أقسام يظهر منها واحد، يتبع الرابط (#overview و#children و#reports
  و#payments و#account) فيعمل زرّ الرجوع. وكل رقم وصفّ من /api/v1/portal/*.
--}}
@section('title')بوابتي@endsection
@section('description')تابع كفالاتك وتقاريرها وسجل مدفوعاتك من مكان واحد.@endsection
@section('robots', 'noindex, nofollow')

@php
  $groups = [
      [null, [['overview', 'نظرة عامة', 'fa-gauge']]],
      ['كفالاتي', [['children', 'أطفالي', 'fa-children'], ['reports', 'التقارير', 'fa-file-lines']]],
      ['المال', [['payments', 'المدفوعات', 'fa-receipt']]],
      ['الحساب', [['account', 'حسابي', 'fa-user-gear']]],
  ];
@endphp

@section('content')
  <div class="pt" data-portal data-portal-loading>

    {{-- ══ الشريط الجانبي: ثابت على الشاشة العريضة، ودرجٌ على الجوال ══ --}}
    <aside class="pt-side offcanvas-lg offcanvas-start" id="ptSide" tabindex="-1" aria-labelledby="ptSideTitle">
      <div class="pt-side__head">
        <a class="pt-side__brand" href="{{ lurl('/') }}" title="الموقع">
          <img src="/assets/img/logo.svg" alt="" width="36" height="36">
          <span class="pt-label"><b id="ptSideTitle">{{ $site['name'] }}</b><small>بوابة الكافل</small></span>
        </a>
        <button class="pt-iconbtn pt-side__rail d-none d-lg-inline-grid" type="button" data-rail-toggle aria-expanded="true" aria-label="طيّ القائمة" title="طيّ القائمة">
          <i class="fa-solid fa-table-columns" aria-hidden="true"></i>
        </button>
        <button class="pt-iconbtn d-lg-none" type="button" data-bs-dismiss="offcanvas" data-bs-target="#ptSide" aria-label="إغلاق القائمة">
          <i class="fa-solid fa-xmark" aria-hidden="true"></i>
        </button>
      </div>

      <nav class="pt-nav" aria-label="أقسام بوابتك">
        @foreach ($groups as [$title, $links])
          <div class="pt-nav__group">
            @if ($title)
              <p class="pt-nav__title"><span class="pt-label">{{ $title }}</span></p>
            @endif
            @foreach ($links as [$id, $label, $icon])
              <a class="pt-nav__link" href="#{{ $id }}" data-pt-link data-tip="{{ $label }}" @if ($id === 'overview') aria-current="page" @endif>
                <i class="fa-solid {{ $icon }}" aria-hidden="true"></i>
                <span class="pt-label">{{ $label }}</span>
                @if ($id === 'reports')
                  <span class="pt-count num" data-reports-count hidden></span>
                @endif
                @if ($id === 'children')
                  <span class="pt-count pt-count--wait num" data-pending-count hidden></span>
                @endif
              </a>
            @endforeach
          </div>
        @endforeach

        <div class="pt-nav__group">
          <p class="pt-nav__title"><span class="pt-label">على الموقع</span></p>
          <a class="pt-nav__link" href="{{ lurl('/orphans') }}" data-tip="اكفل طفلاً آخر">
            <i class="fa-solid fa-hand-holding-heart" aria-hidden="true"></i>
            <span class="pt-label">اكفل طفلاً آخر</span>
          </a>
          <a class="pt-nav__link" href="{{ lurl('/transparency') }}" data-tip="الشفافية">
            <i class="fa-solid fa-scale-balanced" aria-hidden="true"></i>
            <span class="pt-label">أين تذهب الأموال</span>
          </a>
        </div>
      </nav>

      <div class="pt-side__foot">
        <div class="pt-user" data-tip-user>
          <span class="avatar avatar--1" aria-hidden="true" data-portal-avatar>·</span>
          <span class="pt-label pt-user__who">
            <b data-portal-name>…</b>
            <small class="num" data-portal-code></small>
          </span>
        </div>
        <div class="pt-side__actions">
          <button class="pt-nav__link pt-logout" type="button" data-portal-logout data-tip="تسجيل الخروج">
            <i class="fa-solid fa-arrow-right-from-bracket" aria-hidden="true"></i>
            <span class="pt-label">تسجيل الخروج</span>
          </button>
          @include('site.partials.lang-switch', ['class' => 'pt-iconbtn kf-lang kf-lang--icon'])
          <button class="pt-iconbtn" type="button" id="themeToggle" aria-pressed="false" aria-label="الوضع الليلي">
            <i class="fa-solid fa-moon" aria-hidden="true"></i>
            <i class="fa-solid fa-sun" aria-hidden="true"></i>
          </button>
        </div>
      </div>
    </aside>

    <div class="pt-main">
      {{-- ══ الشريط العلوي على الجوال: القائمة واسم الصفحة ══ --}}
      <header class="pt-top d-lg-none">
        <button class="pt-iconbtn" type="button" data-bs-toggle="offcanvas" data-bs-target="#ptSide" aria-controls="ptSide" aria-label="فتح القائمة">
          <i class="fa-solid fa-bars" aria-hidden="true"></i>
          <span class="pt-dot" data-reports-count hidden></span>
        </button>
        <span class="pt-top__title" data-pt-title>نظرة عامة</span>
        <a class="pt-top__brand" href="{{ lurl('/') }}" aria-label="{{ $site['name'] }}"><img src="/assets/img/logo.svg" alt="" width="30" height="30"></a>
      </header>

      <main class="pt-content" id="main">

        {{-- ══════════════ نظرة عامة ══════════════ --}}
        <section class="pt-view" id="overview" data-pt-view aria-labelledby="helloTitle">
          <header class="pt-head pt-head--rule">
            <div>
              <h1 class="pt-head__title" id="helloTitle" tabindex="-1" data-portal-greeting>أهلاً بك</h1>
              <p class="pt-head__sub"><span data-portal-today></span><span data-portal-since></span></p>
            </div>
            <div class="pt-head__actions">
              <a class="btn btn-cta" href="{{ lurl('/orphans') }}"><i class="fa-solid fa-plus" aria-hidden="true"></i> اكفل طفلاً آخر</a>
            </div>
          </header>

          <dl class="pt-kpis">
            <div>
              <dt>أطفال تكفلهم</dt>
              <dd class="num" data-stat="orphans">…</dd>
              <dd class="pt-kpis__sub" data-stat="orphans-hint"></dd>
            </div>
            <div>
              <dt>قدّمتَ حتى اليوم</dt>
              <dd class="num" data-stat="given">…</dd>
              <dd class="pt-kpis__sub" data-stat="given-hint"></dd>
            </div>
            <div>
              <dt>التزامك الشهري</dt>
              <dd class="num" data-stat="commitment">…</dd>
              <dd class="pt-kpis__sub">يُخصم بحسب دورة كل كفالة</dd>
            </div>
            <div>
              <dt>القسط القادم</dt>
              <dd data-stat="next">…</dd>
              <dd class="pt-kpis__sub" data-stat="next-hint"></dd>
            </div>
          </dl>

          <div class="pt-grid">
            <div class="pt-stack">
              {{-- بانتظارك: ما يحتاج قراراً منك الآن، أو طمأنةٌ بأن لا شيء --}}
              <section class="pt-card" aria-labelledby="todoTitle">
                <div class="pt-card__head"><h2 id="todoTitle">بانتظارك</h2></div>
                <div class="pt-attention" data-portal-attention data-tone="calm">
                  <span class="pt-attention__icon" aria-hidden="true"><i class="fa-solid fa-check" data-attention-icon></i></span>
                  <div class="pt-attention__text">
                    <b data-attention-title>جارٍ التحميل…</b>
                    <span data-portal-subtitle></span>
                  </div>
                  <span data-attention-action></span>
                </div>
              </section>

              <section class="pt-card pt-card--flush" aria-labelledby="kidsTitle">
                <div class="pt-card__head">
                  <h2 id="kidsTitle">أطفالك</h2>
                  <a class="pt-more" href="#children" data-pt-link>عرض الكل</a>
                </div>
                <ul class="pt-rows" role="list" data-portal-kids>
                  <li class="pt-skel pt-skel--row" aria-hidden="true"></li>
                  <li class="pt-skel pt-skel--row" aria-hidden="true"></li>
                </ul>
              </section>
            </div>

            <div class="pt-stack">
              <section class="pt-card" aria-labelledby="latestTitle">
                <div class="pt-card__head">
                  <h2 id="latestTitle">آخر تقرير</h2>
                  <a class="pt-more" href="#reports" data-pt-link>عرض الكل</a>
                </div>
                <div data-portal-latest><div class="pt-skel" aria-hidden="true"></div></div>
              </section>

              <section class="pt-card" aria-labelledby="recentTitle">
                <div class="pt-card__head">
                  <h2 id="recentTitle">آخر الدفعات</h2>
                  <a class="pt-more" href="#payments" data-pt-link>عرض الكل</a>
                </div>
                <ul class="pt-recent" role="list" data-portal-recent><li class="pt-skel pt-skel--row" aria-hidden="true"></li></ul>
              </section>
            </div>
          </div>
        </section>

        {{-- ══════════════ أطفالي ══════════════ --}}
        <section class="pt-view" id="children" data-pt-view aria-labelledby="childrenTitle" hidden>
          <header class="pt-head">
            <div>
              <h1 class="pt-head__title" id="childrenTitle" tabindex="-1">أطفالي</h1>
              <p class="pt-head__sub" data-children-sub></p>
            </div>
            <div class="pt-head__actions">
              <a class="btn btn-cta" href="{{ lurl('/orphans') }}"><i class="fa-solid fa-plus" aria-hidden="true"></i> اكفل طفلاً آخر</a>
            </div>
          </header>

          <div class="pt-tabs" role="group" aria-label="تصفية بالحالة" data-status-tabs hidden></div>

          <div class="pt-kids" data-portal-sponsorships>
            <div class="pt-skel pt-skel--card" aria-hidden="true"></div>
            <div class="pt-skel pt-skel--card" aria-hidden="true"></div>
          </div>

          <details class="pt-past" data-portal-past hidden>
            <summary>كفالات سابقة (<span class="num" data-portal-past-count>0</span>)</summary>
            <ul class="pt-past__list" role="list" data-portal-past-list></ul>
          </details>
        </section>

        {{-- ══════════════ التقارير ══════════════ --}}
        <section class="pt-view" id="reports" data-pt-view aria-labelledby="reportsTitle" hidden>
          <header class="pt-head">
            <div>
              <h1 class="pt-head__title" id="reportsTitle" tabindex="-1">التقارير</h1>
              <p class="pt-head__sub" data-reports-sub>زيارةٌ ميدانية لكل طفل كل ثلاثة أشهر.</p>
            </div>
          </header>

          <div class="pt-tabs" role="group" aria-label="تقارير أي طفل" data-report-filter hidden></div>
          <ul class="pt-reports" role="list" data-portal-reports>
            <li class="pt-skel pt-skel--card" aria-hidden="true"></li>
          </ul>
        </section>

        {{-- ══════════════ المدفوعات ══════════════ --}}
        <section class="pt-view" id="payments" data-pt-view aria-labelledby="paymentsTitle" hidden>
          <header class="pt-head">
            <div>
              <h1 class="pt-head__title" id="paymentsTitle" tabindex="-1">المدفوعات</h1>
              <p class="pt-head__sub">كل دفعةٍ بإيصالها. احتفظ بالرقم لأي استفسار، أو لحساب زكاتك.</p>
            </div>
          </header>

          <dl class="pt-kpis">
            <div><dt>الإجمالي</dt><dd class="num" data-stat="payments-total">…</dd><dd class="pt-kpis__sub">منذ أول كفالة</dd></div>
            <div><dt>عدد الدفعات</dt><dd class="num" data-stat="payments-count">…</dd><dd class="pt-kpis__sub">مكتملة</dd></div>
            <div><dt>التزامك الشهري</dt><dd class="num" data-stat="commitment">…</dd><dd class="pt-kpis__sub">لكل كفالاتك</dd></div>
            <div><dt>القسط القادم</dt><dd data-stat="payments-next">…</dd><dd class="pt-kpis__sub" data-stat="next-hint"></dd></div>
          </dl>

          <div class="pt-card pt-card--flush">
            <table class="table pt-table">
              <caption class="visually-hidden">سجل مدفوعاتك</caption>
              <thead>
                <tr>
                  <th scope="col">التاريخ</th>
                  <th scope="col">الطفل</th>
                  <th scope="col">المبلغ</th>
                  <th scope="col">الوسيلة</th>
                  <th scope="col">الحالة</th>
                  <th scope="col">الإيصال</th>
                </tr>
              </thead>
              <tbody data-portal-payments>
                <tr><td colspan="6" class="pt-table__empty">جارٍ التحميل…</td></tr>
              </tbody>
            </table>
            <div class="pt-card__foot" data-payments-foot hidden>
              <span class="pt-muted" data-payments-shown></span>
              <button class="btn btn-outline-primary btn-sm" type="button" data-payments-more hidden>دفعات أقدم</button>
            </div>
          </div>
        </section>

        {{-- ══════════════ حسابي ══════════════ --}}
        <section class="pt-view" id="account" data-pt-view aria-labelledby="accountTitle" hidden>
          <header class="pt-head">
            <div>
              <h1 class="pt-head__title" id="accountTitle" tabindex="-1">حسابي</h1>
              <p class="pt-head__sub">بياناتك وكلمة المرور.</p>
            </div>
          </header>

          <div class="pt-profile">
            <span class="avatar avatar--1" aria-hidden="true" data-portal-avatar>·</span>
            <div>
              <b data-portal-name>…</b>
              <span><span data-portal-email></span> · <span class="num" data-portal-code></span></span>
            </div>
            <span class="pt-chip pt-chip--ok ms-auto" data-portal-since-chip hidden></span>
          </div>

          <div class="pt-forms">
            <form class="pt-card pt-form" data-portal-profile novalidate>
              <div class="pt-form__head">
                <h2>بياناتك</h2>
                <p>كما تظهر في إيصالاتك ورسائلنا إليك.</p>
              </div>
              <div class="field">
                <label class="form-label" for="profileName">الاسم</label>
                <input class="form-control" type="text" id="profileName" name="name" autocomplete="name" required minlength="3">
              </div>
              <div class="field">
                <label class="form-label" for="profileEmail">البريد الإلكتروني</label>
                <input class="form-control" type="email" id="profileEmail" name="email" dir="ltr" autocomplete="email" required>
              </div>
              <div class="field">
                <label class="form-label" for="profilePhone">رقم الجوال <span class="pt-optional">اختياري</span></label>
                <input class="form-control" type="tel" id="profilePhone" name="phone" dir="ltr" autocomplete="tel" aria-describedby="hint-profilePhone">
                <p class="form-text" id="hint-profilePhone">نراسلك عليه إن تعذّر خصم قسط، لا غير.</p>
              </div>
              <div class="field" data-profile-confirm hidden>
                <label class="form-label" for="profileCurrent">كلمة المرور الحالية</label>
                <input class="form-control" type="password" id="profileCurrent" name="current_password" dir="ltr" autocomplete="current-password" aria-describedby="hint-profileCurrent">
                <p class="form-text" id="hint-profileCurrent">لتغيير البريد نتأكد أنك صاحب الحساب.</p>
              </div>
              <div class="pt-form__foot">
                <button class="btn btn-primary" type="submit">احفظ التعديلات</button>
              </div>
            </form>

            <form class="pt-card pt-form" data-portal-password novalidate>
              <div class="pt-form__head">
                <h2>كلمة المرور</h2>
                <p>بعد تغييرها تخرج أجهزتك الأخرى من حسابك.</p>
              </div>
              <div class="field">
                <label class="form-label" for="currentPassword">كلمة المرور الحالية</label>
                <input class="form-control" type="password" id="currentPassword" name="current_password" dir="ltr" autocomplete="current-password" required aria-describedby="hint-currentPassword">
                <p class="form-text" id="hint-currentPassword">أنشأنا حسابك عند الكفالة ولم تضع كلمة مرور؟ <button class="pt-linkbtn" type="button" data-send-reset>أرسل لي رابط تعيينها</button>.</p>
              </div>
              <div class="field">
                <label class="form-label" for="newPassword">كلمة المرور الجديدة</label>
                <input class="form-control" type="password" id="newPassword" name="password" dir="ltr" autocomplete="new-password" required minlength="8" aria-describedby="hint-newPassword">
                <p class="form-text" id="hint-newPassword">8 أحرف على الأقل، فيها حرف ورقم.</p>
              </div>
              <div class="field">
                <label class="form-label" for="newPassword2">أعد كتابتها</label>
                <input class="form-control" type="password" id="newPassword2" name="password_confirmation" dir="ltr" autocomplete="new-password" required>
              </div>
              <div class="pt-form__foot">
                <button class="btn btn-outline-primary" type="submit">غيّر كلمة المرور</button>
              </div>
            </form>
          </div>
        </section>
      </main>
    </div>
  </div>

  {{-- تلميح الشريط المطويّ: عنصرٌ واحد خارج الشريط كي لا يقصّه تمريره --}}
  <span class="pt-tip" aria-hidden="true" data-pt-tip></span>

  {{-- ══ نافذة التقرير ══ --}}
  <div class="modal fade" id="reportModal" tabindex="-1" aria-labelledby="reportModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-lg">
      <div class="modal-content pt-modal">
        <button class="btn-close pt-modal__close" type="button" data-bs-dismiss="modal" aria-label="إغلاق"></button>
        <div class="modal-body" data-report-content></div>
      </div>
    </div>
  </div>

  {{-- ══ أين ذهب مالك: سجلّ الطفل المالي (portal.js، ChildLedgerService) ══ --}}
  <div class="modal fade" id="ledgerModal" tabindex="-1" aria-labelledby="ledgerModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-lg">
      <div class="modal-content pt-modal">
        <button class="btn-close pt-modal__close" type="button" data-bs-dismiss="modal" aria-label="إغلاق"></button>
        <div class="modal-body" data-ledger-content></div>
      </div>
    </div>
  </div>

  {{-- ══ نافذة الرسائل: محادثة، يقرأ المشرف كل رسالة قبل أن تصل ══ --}}
  <div class="modal fade" id="messagesModal" tabindex="-1" aria-labelledby="messagesTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
      <div class="modal-content pt-modal">
        <div class="modal-header pt-chat__head">
          <img class="pt-chat__avatar" src="/assets/img/logo.svg" alt="" width="44" height="44" data-messages-avatar>
          <div>
            <h2 class="modal-title" id="messagesTitle">رسائلك</h2>
            <p><i class="fa-solid fa-shield-halved" aria-hidden="true"></i> يقرؤها المشرف قبل أن تصل، حمايةً للطفل.</p>
          </div>
          <button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="إغلاق"></button>
        </div>
        <div class="modal-body pt-chat__body">
          <ol class="pt-chat__list" role="list" data-messages-list></ol>
        </div>
        <form class="modal-footer pt-chat__form" data-messages-form novalidate>
          <label class="visually-hidden" for="messageBody">رسالتك</label>
          <textarea class="form-control" id="messageBody" name="body" rows="2" maxlength="2000"
                    placeholder="كلمة تشجيع، أو سؤالٌ عن دراسته…" aria-describedby="hint-messageBody"></textarea>
          <button class="btn btn-primary pt-chat__send" type="submit" aria-label="أرسل">
            <i class="fa-solid fa-paper-plane" aria-hidden="true"></i>
          </button>
          <p class="pt-chat__hint" id="hint-messageBody">Enter للإرسال، وShift+Enter لسطرٍ جديد. لا تكتب رقم هاتف أو عنواناً.</p>
        </form>
      </div>
    </div>
  </div>

  {{-- ══ نافذة الإيقاف أو الإنهاء: الاستراحة أولاً ══ --}}
  <div class="modal fade" id="cancelModal" tabindex="-1" aria-labelledby="cancelTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content pt-modal">
        <div class="modal-header">
          <h2 class="modal-title" id="cancelTitle"><span data-cancel-verb>إنهاء كفالة</span> <span data-cancel-name></span></h2>
          <button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="إغلاق"></button>
        </div>
        <div class="modal-body">
          <p class="pt-muted" data-cancel-lede></p>

          <div class="pt-pause" data-cancel-pause-box>
            <span class="pt-pause__icon" aria-hidden="true"><i class="fa-solid fa-mug-hot"></i></span>
            <div>
              <p><b>تحتاج استراحة فقط؟</b> أوقف الخصم مؤقتاً، ويبقى الطفل محجوزاً لك حتى تستأنف.</p>
              <button class="btn btn-primary btn-sm" type="button" data-cancel-pause>
                <i class="fa-solid fa-pause" aria-hidden="true"></i> أوقفها مؤقتاً
              </button>
            </div>
          </div>

          <form id="cancelForm" data-cancel-form novalidate>
            <div class="field" data-cancel-reason>
              <label class="form-label" for="cancelReason">هل تخبرنا بالسبب؟ <span class="pt-optional">اختياري</span></label>
              <textarea class="form-control" id="cancelReason" name="reason" rows="3" maxlength="255"></textarea>
            </div>
          </form>
        </div>
        <div class="modal-footer">
          <button class="btn btn-soft" type="button" data-bs-dismiss="modal">تراجع</button>
          <button class="btn btn-danger" type="submit" form="cancelForm" data-cancel-submit>أنهِ الكفالة</button>
        </div>
      </div>
    </div>
  </div>

  {{-- ══ نافذة بيانات التحويل للحجز ══ --}}
  <div class="modal fade" id="bankModal" tabindex="-1" aria-labelledby="bankTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content pt-modal">
        <div class="modal-header">
          <h2 class="modal-title" id="bankTitle">بيانات التحويل</h2>
          <button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="إغلاق"></button>
        </div>
        <div class="modal-body">
          <p class="pt-muted">اكتب مرجع الكفالة في خانة البيان حتى نربط التحويل بك. تبدأ الكفالة حين يصل.</p>
          <dl class="pt-bank">
            <div><dt>المبلغ</dt><dd class="num" data-bank="amount"></dd></div>
            <div><dt>مرجع الكفالة</dt><dd><span class="num" data-bank="reference"></span><button class="pt-copy" type="button" data-copy="reference" aria-label="انسخ المرجع"><i class="fa-regular fa-copy" aria-hidden="true"></i></button></dd></div>
            <div><dt>البنك</dt><dd data-bank="name"></dd></div>
            <div><dt>اسم الحساب</dt><dd data-bank="account"></dd></div>
            <div><dt>IBAN</dt><dd><span class="num" data-bank="iban"></span><button class="pt-copy" type="button" data-copy="iban" aria-label="انسخ IBAN"><i class="fa-regular fa-copy" aria-hidden="true"></i></button></dd></div>
            <div><dt>SWIFT</dt><dd class="num" data-bank="swift"></dd></div>
            <div><dt>يبقى الحجز حتى</dt><dd data-bank="until"></dd></div>
          </dl>
        </div>
      </div>
    </div>
  </div>
@endsection

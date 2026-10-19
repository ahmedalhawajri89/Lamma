{{-- نصٌّ خالص لا HTML: {!! !!} كي لا تصير الفاصلة العليا &#039; --}}
{!! __('مرحباً :name،', ['name' => $sponsorship->sponsor?->user?->name]) !!}

{!! __('حاولنا خصم قسط :child (:amount) ولم ينجح الخصم.', ['child' => $child, 'amount' => number_format($sponsorship->cycleAmount(), 2).' '.$sponsorship->currency]) !!}
{!! __('السبب كما وصلنا من البنك: :reason', ['reason' => __($reason)]) !!}

{!! __('سنعيد المحاولة تلقائياً بعد أيام. وإن تغيّرت بطاقتك، حدّثها من لوحتك فيُخصم القسط فوراً:') !!}
{!! $portal !!}

{!! __('وإن طال الأمر، يغطّي صندوق الاستمرارية قسط :child حتى :months أشهر، فلا ينقطع دعم :child.', ['child' => $child, 'months' => config('kafala.continuity.max_months', 3)]) !!}

{!! __('مع التحية،') !!}
{!! __('فريق :site', ['site' => $site]) !!}

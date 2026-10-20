{{-- نصٌّ خالص لا HTML: {!! !!} كي لا تصير الفاصلة العليا &#039; --}}
{!! __('مرحباً :name،', ['name' => $payment->sponsor?->user?->name]) !!}

{!! __('وصلتنا دفعتك لكفالة :child، شكراً لك.', ['child' => $child]) !!}

{!! __('رقم الإيصال: :value', ['value' => $payment->receipt_number]) !!}
{!! __('المبلغ: :value', ['value' => $amount]) !!}@if ($base) {!! __('(يعادل :value)', ['value' => $base]) !!}@endif

{!! __('التاريخ: :value', ['value' => $date]) !!}
{!! __('الوسيلة: :value', ['value' => $method]) !!}
{!! __('مرجع الكفالة: :value', ['value' => $payment->sponsorship?->reference_code]) !!}
@if ($zakat)
{!! __('من زكاتك: يُصرف على الحالات المستحقة للزكاة وحدها.') !!}
@endif

{!! __('إيصالاتك كلها في لوحتك، ومنها ترى أين ذهب كل دولار:') !!}
{!! $portal !!}

{!! __('مع التحية،') !!}
{!! __('فريق :site', ['site' => $site]) !!}

{{-- نصٌّ خالص لا HTML، وبلغة الزائر (ContactReplyMail) --}}
{!! __('مرحباً :name،', ['name' => $contact->name]) !!}

{!! $reply !!}

{!! __('مع التحية،') !!}
{!! __('فريق :site', ['site' => \App\Support\SiteProfile::site()['name']]) !!}

──
{!! __('رسالتك بتاريخ :date:', ['date' => $contact->created_at?->timezone(config('kafala.display_timezone'))->format('Y-m-d')]) !!}
{!! \Illuminate\Support\Str::limit($contact->body, 600) !!}

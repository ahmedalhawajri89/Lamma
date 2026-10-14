مرحباً {{ $contact->name }}،

{{ $reply }}

مع التحية،
فريق كفالة

──
رسالتك بتاريخ {{ $contact->created_at?->timezone(config('kafala.display_timezone'))->format('Y-m-d') }}:
{{ \Illuminate\Support\Str::limit($contact->body, 600) }}

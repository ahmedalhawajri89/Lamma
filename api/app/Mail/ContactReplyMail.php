<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\ContactMessage;
use App\Support\SiteProfile;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** الرد على زائرٍ راسلنا من «تواصل معنا»، من عنوان المنظمة لا من بريد موظف. */
class ContactReplyMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(public ContactMessage $contact, public string $reply) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: __('ردّ :site على رسالتك', ['site' => SiteProfile::site()['name']]));
    }

    public function content(): Content
    {
        return new Content(text: 'emails.contact-reply');
    }
}

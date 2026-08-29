<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/** مشترك النشرة. double opt-in: لا يُعدّ مشتركاً قبل تأكيد بريده. */
class NewsletterSubscriber extends Model
{
    use HasFactory;

    protected $fillable = ['email', 'confirm_token', 'source'];

    protected $hidden = ['confirm_token'];

    protected function casts(): array
    {
        return ['confirmed_at' => 'datetime', 'unsubscribed_at' => 'datetime'];
    }

    public function scopeConfirmed(Builder $query): Builder
    {
        return $query->whereNotNull('confirmed_at')->whereNull('unsubscribed_at');
    }
}

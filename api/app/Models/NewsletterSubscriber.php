<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/** مشترك النشرة. double opt-in: لا يُعدّ مشتركاً قبل تأكيد بريده.
 *
 * @property int $id
 * @property string $email
 * @property Carbon|null $confirmed_at
 * @property Carbon|null $unsubscribed_at
 */
class NewsletterSubscriber extends Model
{
    use HasFactory;

    protected $fillable = ['email', 'confirm_token', 'source', 'confirmed_at', 'unsubscribed_at'];

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

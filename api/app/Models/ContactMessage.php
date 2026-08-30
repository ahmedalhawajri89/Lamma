<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ContactStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property ContactStatus $status
 * @property Carbon|null $handled_at
 */
class ContactMessage extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'email', 'phone', 'subject', 'body', 'ip_address', 'status', 'handled_by', 'handled_at'];

    protected function casts(): array
    {
        return ['status' => ContactStatus::class, 'handled_at' => 'datetime'];
    }

    /** @return BelongsTo<User, $this> */
    public function handler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by');
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('status', [ContactStatus::New, ContactStatus::InProgress]);
    }
}

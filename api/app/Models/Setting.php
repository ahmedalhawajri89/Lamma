<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/** إعدادات الموقع. تُقرأ من الكاش لأنها تُطلب في كل صفحة وتتغيّر نادراً. */
class Setting extends Model
{
    protected $fillable = ['group', 'key', 'value', 'type'];

    protected static function booted(): void
    {
        static::saved(fn () => Cache::forget('settings'));
        static::deleted(fn () => Cache::forget('settings'));
    }

    /** @return array<string, mixed> */
    public static function cached(): array
    {
        return Cache::rememberForever('settings', static fn (): array => static::query()
            ->get()
            ->mapWithKeys(static fn (self $s): array => ["{$s->group}.{$s->key}" => $s->typedValue()])
            ->all());
    }

    public function typedValue(): mixed
    {
        return match ($this->type) {
            'number' => is_numeric($this->value) ? $this->value + 0 : 0,
            'boolean' => filter_var($this->value, FILTER_VALIDATE_BOOLEAN),
            'json' => json_decode((string) $this->value, true),
            default => $this->value,
        };
    }
}

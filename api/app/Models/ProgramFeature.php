<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** ميزة في برنامج — مُطبَّعة بدل مصفوفة JSON ليعدّلها المدير من اللوحة. */
class ProgramFeature extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = ['program_id', 'label_ar', 'sort_order'];

    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }
}

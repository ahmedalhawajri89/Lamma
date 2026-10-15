<?php

declare(strict_types=1);

use App\Services\RichText;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * نصوص المقالات من فقرات عادية إلى HTML المحرّر المنسّق.
 *
 * كل فقرة تصير <p> بنصّها مهرَّباً، فلا يُفسَّر شيءٌ مما كُتب قبل المحرّر
 * وسماً. وما هو HTML أصلاً يُترك.
 */
return new class extends Migration
{
    public function up(): void
    {
        $rich = app(RichText::class);

        DB::table('posts')->whereNotNull('body')->orderBy('id')->each(function ($post) use ($rich): void {
            if (! RichText::isHtml($post->body)) {
                DB::table('posts')->where('id', $post->id)->update(['body' => $rich->fromPlain($post->body)]);
            }
        });
    }

    public function down(): void
    {
        $rich = app(RichText::class);

        DB::table('posts')->whereNotNull('body')->orderBy('id')->each(function ($post) use ($rich): void {
            if (RichText::isHtml($post->body)) {
                DB::table('posts')->where('id', $post->id)->update(['body' => str_replace("\n\n\n", "\n\n", $rich->toText($post->body))]);
            }
        });
    }
};

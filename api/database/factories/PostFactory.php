<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\PostStatus;
use App\Models\Post;
use App\Models\PostCategory;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Post>
 */
class PostFactory extends Factory
{
    protected $model = Post::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $title = 'مقال '.$this->faker->unique()->numberBetween(1, 999999);

        return [
            /**
             * المسار عشوائي لا مشتقّ من العنوان.
             *
             * `Str::slug` تُسقط العربية فتُرجع نصّاً فارغاً، وعمود `slug`
             * فريد — فمقالان في اختبار واحد يصطدمان بخطأ قاعدة بيانات لا
             * علاقة له بما يُختبَر.
             */
            'slug' => 'post-'.Str::lower(Str::random(10)),

            'title' => $title,
            'excerpt' => 'مقتطف قصير.',
            'body' => 'نصّ المقال.',
            'category_id' => fn (): ?int => PostCategory::query()->value('id'),
            'author_id' => fn (): int => User::factory()->admin()->create()->id,
            'read_minutes' => 3,
            'status' => PostStatus::Published,
            'published_at' => now()->subDays(3),
            'is_featured' => false,
            'views_count' => 0,
        ];
    }

    public function draft(): static
    {
        return $this->state(fn (): array => [
            'status' => PostStatus::Draft,
            'published_at' => null,
        ]);
    }
}

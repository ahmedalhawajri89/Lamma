<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Public;

use App\Http\Controllers\Controller;
use App\Http\Resources\CountryResource;
use App\Http\Resources\FaqResource;
use App\Http\Resources\PartnerResource;
use App\Http\Resources\ProgramResource;
use App\Http\Resources\TestimonialResource;
use App\Models\Country;
use App\Models\Faq;
use App\Models\Partner;
use App\Models\Program;
use App\Models\Setting;
use App\Models\Testimonial;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

/**
 * كل ما كان ثابتاً في data.js عدا الأيتام.
 *
 * نقطة `bootstrap` تُرجعه دفعةً واحدة: الموقع الحالي يبني الهيدر والفوتر
 * والبرامج والشركاء في كل صفحة، وستّة طلبات متوازية على صفحة ساكنة إسرافٌ
 * بلا مقابل. طلب واحد مُخزَّن في الكاش أبسط وأسرع.
 */
class CatalogController extends Controller
{
    private const CACHE_TTL = 600;

    public function bootstrap(): JsonResponse
    {
        $data = Cache::remember('public.bootstrap', self::CACHE_TTL, fn (): array => [
            'site' => $this->site(),
            'programs' => ProgramResource::collection(
                Program::active()->with('features')->get(),
            )->resolve(),
            'countries' => Country::operational()->pluck('name_ar')->all(),
            'partners' => PartnerResource::collection(Partner::active()->get())->resolve(),
            'testimonials' => TestimonialResource::collection(Testimonial::published()->get())->resolve(),
            'faqs' => FaqResource::collection(Faq::published()->get())->resolve(),
        ]);

        return response()->json(['data' => $data]);
    }

    public function programs(): JsonResponse
    {
        return response()->json([
            'data' => ProgramResource::collection(Program::active()->with('features')->get()),
        ]);
    }

    public function countries(): JsonResponse
    {
        return response()->json([
            'data' => CountryResource::collection(Country::operational()->withCount('families')->get()),
        ]);
    }

    public function faqs(): JsonResponse
    {
        return response()->json([
            'data' => FaqResource::collection(Faq::published()->get()),
        ]);
    }

    /** SITE في data.js — من جدول الإعدادات لا من ثابت في الكود. */
    private function site(): array
    {
        $settings = Setting::cached();

        return [
            'name' => $settings['site.name'] ?? 'كفالة',
            'tagline' => $settings['site.tagline'] ?? '',
            'email' => $settings['site.email'] ?? '',
            'phone' => $settings['site.phone'] ?? '',
            'address' => $settings['site.address'] ?? '',
        ];
    }
}

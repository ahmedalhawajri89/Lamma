<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;

/**
 * القاعدة في Laravel 12 فارغة عمداً.
 * نضيف التفويض والتحقق هنا لأن كل متحكّم في هذا المشروع يستعمل `$this->authorize()`.
 */
abstract class Controller
{
    use AuthorizesRequests;
    use ValidatesRequests;
}

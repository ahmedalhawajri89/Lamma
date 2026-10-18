<?php

declare(strict_types=1);

namespace App\Payments;

use RuntimeException;

/**
 * قسطٌ لم يُخصم لسببٍ متوقع: رفضه البنك، أو لا بطاقة محفوظة. يُسجَّل فاشلاً
 * ويتابعه المحاسب، ولا يُبلَّغ عنه عطلاً في البرنامج.
 */
final class ChargeDeclined extends RuntimeException {}

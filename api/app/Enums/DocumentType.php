<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * نوع مستند التحقق. هذا الجدول هو ما يجعل «ثلاث مراحل تحقّق» قابلاً للإثبات.
 */
enum DocumentType: string
{
    use HasLabel;

    case BirthCertificate = 'birth_certificate';
    case DeathCertificate = 'death_certificate';
    case MedicalReport = 'medical_report';
    case SchoolRecord = 'school_record';
    case GuardianshipProof = 'guardianship_proof';
    case Photo = 'photo';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::BirthCertificate => __('شهادة ميلاد'),
            self::DeathCertificate => __('شهادة وفاة الوالد'),
            self::MedicalReport => __('تقرير طبي'),
            self::SchoolRecord => __('سجل مدرسي'),
            self::GuardianshipProof => __('إثبات وصاية'),
            self::Photo => __('صورة'),
            self::Other => __('أخرى'),
        };
    }
}

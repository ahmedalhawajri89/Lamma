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
            self::BirthCertificate => 'شهادة ميلاد',
            self::DeathCertificate => 'شهادة وفاة الوالد',
            self::MedicalReport => 'تقرير طبي',
            self::SchoolRecord => 'سجل مدرسي',
            self::GuardianshipProof => 'إثبات وصاية',
            self::Photo => 'صورة',
            self::Other => 'أخرى',
        };
    }
}

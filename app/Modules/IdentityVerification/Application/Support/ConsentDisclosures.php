<?php

namespace App\Modules\IdentityVerification\Application\Support;

final class ConsentDisclosures
{
    /** @return array<string, array{en:string,ar:string}> */
    public static function forNotice(string $noticeVersion): array
    {
        return [
            'what' => [
                'en' => 'Government identity attributes (verified name and nationality) required for event access.',
                'ar' => 'سمات الهوية الحكومية (الاسم الموثق والجنسية) المطلوبة للوصول إلى الفعالية.',
            ],
            'why' => [
                'en' => 'To confirm attendee identity before credential issuance or gate entry.',
                'ar' => 'لتأكيد هوية الحاضر قبل إصدار الاعتماد أو الدخول عند البوابة.',
            ],
            'retention' => [
                'en' => 'Verification metadata is retained for the configured event retention window.',
                'ar' => 'يتم الاحتفاظ ببيانات التحقق ضمن نافذة الاحتفاظ المحددة للفعالية.',
            ],
            'who' => [
                'en' => 'Authorized event organizers and compliance reviewers within your tenant.',
                'ar' => 'منظمو الفعالية المرخصون ومراجعو الامتثال ضمن المستأجر الخاص بك.',
            ],
            'processing_mode' => [
                'en' => 'Processed according to the configured residency mode for this deployment.',
                'ar' => 'تتم المعالجة وفق وضع الإقامة المُعد لهذا النشر.',
            ],
            'deletion' => [
                'en' => 'You may request deletion of sensitive identity data where permitted by policy.',
                'ar' => 'يمكنك طلب حذف بيانات الهوية الحساسة حيث يسمح النظام بذلك.',
            ],
            'notice_version' => [
                'en' => $noticeVersion,
                'ar' => $noticeVersion,
            ],
        ];
    }
}

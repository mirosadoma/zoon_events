/** Exact backend validation strings mapped to Arabic. Keys are normalized English messages. */
export const VALIDATION_MESSAGE_CATALOG: Record<string, string> = {
  'End time must be after start time.': 'يجب أن يكون وقت الانتهاء بعد وقت البداية.',
  'End time must be after start time': 'يجب أن يكون وقت الانتهاء بعد وقت البداية.',
  'Unknown fields are not permitted.': 'الحقول غير المعروفة غير مسموحة.',
  'Exactly one of qr_payload or credential_id must be provided.': 'يجب توفير qr_payload أو credential_id فقط.',
  'Provide only one of qr_payload or credential_id, not both.': 'قدّم qr_payload أو credential_id فقط، وليس كليهما.',
  'Exactly one of qr_payload or query must be provided.': 'يجب توفير qr_payload أو query فقط.',
  'Provide only one of qr_payload or query, not both.': 'قدّم qr_payload أو query فقط، وليس كليهما.',
  'At least one time or capacity selector is required.': 'مطلوب محدّد وقت أو سعة واحد على الأقل.',
  'You must accept the terms and privacy notice.': 'يجب الموافقة على الشروط وسياسة الخصوصية.',
  'A confirmation code is required when confirmation_required is true.': 'رمز التأكيد مطلوب عند تفعيل confirmation_required.',
  'The selected organizer must be an active tenant member with event management access.': 'يجب أن يكون المنظم المختار عضواً نشطاً في المستأجر ولديه صلاحية إدارة الفعاليات.',
  'Country is linked to event venues and cannot be deleted.': 'الدولة مرتبطة بمواقع فعاليات ولا يمكن حذفها.',
  'City is linked to event venues and cannot be deleted.': 'المدينة مرتبطة بمواقع فعاليات ولا يمكن حذفها.',
  'Mandatory security controls cannot be represented as feature flags.': 'لا يمكن تمثيل ضوابط الأمان الإلزامية كميزات اختيارية.',
  'These credentials do not match our records.': 'بيانات الاعتماد هذه غير صحيحة.',
  'The email field must be a valid email address.': 'يجب أن يكون بريداً إلكترونياً صالحاً.',
  'The password field is required.': 'مطلوب.',
  'The email field is required.': 'مطلوب.',
  'Registration form field count is invalid.': 'عدد حقول نموذج التسجيل غير صالح.',
  'Registration form contains an invalid field key.': 'يحتوي نموذج التسجيل على مفتاح حقل غير صالح.',
  'Registration fields require Arabic and English labels.': 'تتطلب حقول التسجيل تسميات بالعربية والإنجليزية.',
  'Registration field visibility is invalid.': 'رؤية حقل التسجيل غير صالحة.',
  'Server-owned field defaults must be scalar.': 'يجب أن تكون القيم الافتراضية للحقول المملوكة للخادم قيماً بسيطة.',
  'Choice fields require bounded options.': 'تتطلب حقول الاختيار خيارات محددة.',
  'Registration field validation rules are invalid.': 'قواعد التحقق من حقل التسجيل غير صالحة.',
  'Registration field maximum length is invalid.': 'الحد الأقصى لطول حقل التسجيل غير صالح.',
  'Registration field numeric bounds are invalid.': 'حدود الأرقام لحقل التسجيل غير صالحة.',
  'Conditional fields may reference only an earlier field.': 'قد تشير الحقول الشرطية إلى حقل سابق فقط.',
  'Conditional field operator is invalid.': 'عامل الحقل الشرطي غير صالح.',
  'Choice field options are invalid.': 'خيارات حقل الاختيار غير صالحة.',
  'Choice field options require Arabic and English labels.': 'تتطلب خيارات حقل الاختيار تسميات بالعربية والإنجليزية.',
  'Registration form system fields are missing.': 'حقول النظام في نموذج التسجيل مفقودة.',
  'Registration form system fields are invalid.': 'حقول النظام في نموذج التسجيل غير صالحة.',
  'Registration form system fields must remain required and public.': 'يجب أن تبقى حقول النظام في نموذج التسجيل مطلوبة وعامة.',
  'Published forms require consent document versions.': 'تتطلب النماذج المنشورة إصدارات مستندات الموافقة.',
  'One or more fields are invalid.': 'حقل واحد أو أكثر غير صالح.',
  'Authentication is required to access this resource.': 'يلزم تسجيل الدخول للوصول إلى هذا المورد.',
}

export function lookupValidationCatalog(message: string): string | null {
  const trimmed = message.trim()
  if (trimmed === '') {
    return null
  }

  const withoutTrailingDot = trimmed.replace(/\.+$/, '')
  const withDot = `${withoutTrailingDot}.`

  return VALIDATION_MESSAGE_CATALOG[trimmed]
    ?? VALIDATION_MESSAGE_CATALOG[withDot]
    ?? VALIDATION_MESSAGE_CATALOG[withoutTrailingDot]
    ?? null
}

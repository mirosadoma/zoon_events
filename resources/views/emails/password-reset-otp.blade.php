# {{ $locale === 'ar' ? 'رمز استعادة كلمة المرور' : 'Password reset code' }}

@if($locale === 'ar')
رمز التحقق الخاص بك هو:

**{{ $code }}**

ينتهي هذا الرمز خلال 15 دقيقة. إذا لم تطلب استعادة كلمة المرور فتجاهل هذه الرسالة.
@else
Your verification code is:

**{{ $code }}**

This code expires in 15 minutes. If you did not request a password reset, you can ignore this email.
@endif

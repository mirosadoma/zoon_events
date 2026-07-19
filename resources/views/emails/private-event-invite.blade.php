<x-mail::message>
@if($locale === 'ar')
# دعوة لتسجيل خاص

مرحباً،

تمت دعوتك للتسجيل في **{{ $eventName }}**.

هذا الرابط خاص بك فقط. اضغط الزر لفتح الأجندة ثم إكمال التسجيل.

<x-mail::button :url="$inviteUrl" color="primary">
فتح رابط التسجيل
</x-mail::button>

إذا لم تكن تتوقع هذه الرسالة يمكنك تجاهلها.
@else
# Private event invitation

Hello,

You have been invited to register for **{{ $eventName }}**.

This link is personal to you. Click the button to open the agenda and complete registration.

<x-mail::button :url="$inviteUrl" color="primary">
Open registration link
</x-mail::button>

If you did not expect this email, you can ignore it.
@endif
</x-mail::message>

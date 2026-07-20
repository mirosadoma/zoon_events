<x-mail::message>
# Welcome, {{ $name }}!

Your organization **{{ $organizationName }}** has been set up on our platform.

**Plan:** {{ $planName }}

Here are your login credentials:

- **Email:** {{ $email }}
- **Password:** {{ $password }}

<x-mail::button :url="$loginUrl">
Login Now
</x-mail::button>

Please change your password after your first login.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>

<x-mail::message>
# {{ __('invitation.mail.heading', ['workspace' => $workspace]) }}

@if ($inviter)
{{ __('invitation.mail.invited_by', ['name' => $inviter, 'workspace' => $workspace]) }}
@else
{{ __('invitation.mail.invited', ['workspace' => $workspace]) }}
@endif

<x-mail::button :url="$acceptUrl">
{{ __('invitation.mail.button') }}
</x-mail::button>

{{ __('invitation.mail.expires', ['date' => $expiresAt->toFormattedDateString()]) }}

{{ __('invitation.mail.ignore') }}
</x-mail::message>

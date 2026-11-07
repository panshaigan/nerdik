<x-mail::message>
<p>{{ __('feedback.notifications.replied_mail_intro', ['subject' => $subject]) }}</p>

{!! $htmlBody !!}
</x-mail::message>

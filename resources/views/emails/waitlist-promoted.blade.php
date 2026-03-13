@component('mail::message')
# {{ __('You got a place!') }}

{{ __('You have been moved from the waitlist and are now a participant in the activity:') }}

**{{ $activity->name }}**

{{ __('You can view your participations in your dashboard.') }}

@component('mail::button', ['url' => url('/dashboard')])
{{ __('Go to dashboard') }}
@endcomponent

{{ config('app.name') }}
@endcomponent

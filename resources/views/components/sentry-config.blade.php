@php
    $sentryDsn = config('sentry.dsn');
    $sentryBrowserConfig = filled($sentryDsn) ? [
        'dsn' => $sentryDsn,
        'environment' => config('sentry.environment') ?: app()->environment(),
        'release' => config('sentry.release'),
        'tracesSampleRate' => (float) (config('sentry.traces_sample_rate') ?? 0),
    ] : null;
@endphp
@if ($sentryBrowserConfig !== null)
<script>
    window.__nerdikSentry = @json($sentryBrowserConfig);
</script>
@endif

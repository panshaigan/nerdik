@if (config('broadcasting.echo_client'))
<script>
    window.__nerdikEchoConfig = @json(config('broadcasting.echo_client'));
</script>
@endif

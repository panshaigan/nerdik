@php
    $spaFullPagePrefixes = \App\Support\Livewire\SpaNavigate::fullPagePathPrefixes();
@endphp
<script>
    window.__nerdikSpaFullPagePrefixes = @json($spaFullPagePrefixes);
</script>

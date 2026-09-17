@php
    /** @var string $title */
    /** @var list<string> $scheduleBits */
    /** @var array{header: string, sign: string, footer: string} $brandLogos */
@endphp
<table class="doc-header">
    <tr>
        <td class="doc-header-title">
            <h1>
                {{ $title }}@if ($scheduleBits !== []) <span class="header-schedule">· {{ implode(' · ', $scheduleBits) }}</span>@endif
            </h1>
        </td>
        <td class="doc-header-logo">
            @include('pdf.partials.brand-logo', ['path' => $brandLogos['header'], 'height' => 32])
        </td>
    </tr>
</table>

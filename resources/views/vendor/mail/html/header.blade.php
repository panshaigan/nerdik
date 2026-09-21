@props(['url'])
@php
    $logo = \App\Support\Media\BrandLogoSources::fromManifest()->forPreset('md');
    $appName = (string) config('app.name');
@endphp
<tr>
<td class="header">
<a href="{{ $url }}" style="display: inline-block;">
<img src="{{ $logo['src'] }}" width="{{ $logo['width'] }}" height="{{ $logo['height'] }}" class="logo" alt="{{ $appName }}">
</a>
</td>
</tr>

@php
    /** @var list<array{name: string, is_absent: bool}> $participants */
    $showHeader = $showHeader ?? true;
@endphp
<table class="participants">
    @if ($showHeader)
        <thead>
            <tr>
                <th class="col-name">{{ __('ui.pdf.roster.participant') }}</th>
                <th class="col-check">{{ __('ui.pdf.roster.present') }}</th>
            </tr>
        </thead>
    @endif
    <tbody>
        @forelse ($participants as $participant)
            <tr>
                <td class="col-name">
                    {{ $participant['name'] }}
                    @if ($participant['is_absent'])
                        <span class="absent-note">({{ __('ui.pdf.roster.absent_note') }})</span>
                    @endif
                </td>
                <td class="col-check">
                    <span class="checkbox"></span>
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="2" class="empty">{{ __('ui.pdf.roster.no_participants') }}</td>
            </tr>
        @endforelse
    </tbody>
</table>

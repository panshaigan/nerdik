@php
    /** @var \App\Models\SentEmail $record */
    $record = $getRecord();
@endphp

@if (filled($record->html_path))
    <iframe
        sandbox
        src="{{ route('filament.admin.sent-emails.preview', ['sentEmail' => $record]) }}"
        class="fi-sent-email-html-preview rounded-lg border border-gray-200 dark:border-gray-700 bg-white"
        style="display: block; width: 100%; height: 30rem;"
        title="Email HTML preview"
    ></iframe>
@else
    <p class="text-sm text-gray-500 dark:text-gray-400">No HTML body stored.</p>
@endif

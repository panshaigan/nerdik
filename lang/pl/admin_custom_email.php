<?php

declare(strict_types=1);

return [
    'navigation' => 'Wyślij wiadomość',

    'fields' => [
        'subject' => 'Temat',
        'body' => 'Treść',
        'placeholders' => 'Dostępne placeholdery',
        'placeholders_hint' => 'Możesz użyć tych placeholderów w temacie i treści (zastępowane osobno dla każdego odbiorcy).',
    ],

    'placeholder_tokens' => [
        'name' => 'Imię i nazwisko odbiorcy',
        'nickname' => 'Nick odbiorcy',
        'email' => 'Adres e-mail odbiorcy',
        'organization' => 'Nazwa organizacji odbiorcy (puste, jeśli brak)',
    ],

    'filters' => [
        'verified_only' => 'Tylko zweryfikowany e-mail',
        'exclude_admins' => 'Bez administratorów',
    ],

    'actions' => [
        'send' => 'Wyślij wiadomość',
        'send_test' => 'Wyślij test do mnie',
    ],

    'confirm' => [
        'send_heading' => 'Wysłać wiadomość do wybranych użytkowników?',
        'send_body' => 'To doda do kolejki :count wiadomości. Każda wiadomość w kolejce to jedno wysłanie u dostawcy (np. jeden kredyt Brevo). Odbiorcy nie zobaczą adresów innych osób.',
    ],

    'validation' => [
        'subject_required' => 'Podaj temat.',
        'body_required' => 'Podaj treść wiadomości.',
        'no_eligible_recipients' => 'Brak uprawnionych odbiorców. Użytkownik musi mieć zweryfikowany e-mail i nie być administratorem.',
        'no_recipients_selected' => 'Wybierz co najmniej jednego uprawnionego odbiorcę.',
    ],

    'notifications' => [
        'test_queued' => 'Testowa wiadomość dodana do kolejki.',
        'sent_title' => 'Wiadomości w kolejce',
        'sent_summary' => 'W kolejce: :queued. Pominięto nieuprawnionych: :skipped. Pominięto z powodu limitu: :over_limit.',
    ],
];

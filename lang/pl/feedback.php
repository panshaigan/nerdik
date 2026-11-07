<?php

declare(strict_types=1);

return [
    'types' => [
        'question' => 'Pytanie',
        'feature' => 'Pomysł na funkcję',
        'bug' => 'Błąd',
        'problem' => 'Problem',
        'other' => 'Coś innego',
    ],

    'statuses' => [
        'open' => 'Otwarte',
        'resolved' => 'Rozwiązane',
    ],

    'modal' => [
        'title' => 'Kontakt',
        'type' => 'Typ',
        'subject' => 'Temat',
        'body' => 'Wiadomość',
        'email' => 'Twój e-mail',
        'email_hint' => 'Abyśmy mogli Ci odpowiedzieć.',
        'submit' => 'Wyślij',
        'cancel' => 'Anuluj',
        'success' => 'Dziękujemy — otrzymaliśmy Twoją opinię.',
        'rate_limited' => 'Zbyt wiele zgłoszeń. Spróbuj ponownie później.',
    ],

    'already_replied' => 'Na tę opinię już odpowiedziano.',

    'upload' => [
        'quota' => 'Osiągnięto limit przesyłania obrazów. Spróbuj ponownie jutro.',
        'unavailable' => 'Załączony obraz wygasł lub należy do innej sesji. Prześlij go ponownie.',
        'invalid' => 'Prześlij poprawny obraz.',
        'failed' => 'Przesyłanie obrazu nie powiodło się. Spróbuj ponownie.',
    ],

    'notifications' => [
        'received_title' => 'Nowa opinia',
        'received_subtitle' => ':type — :subject',
        'replied_title' => 'Odpowiedź na Twoją opinię',
        'replied_subtitle' => ':subject',
        'replied_mail_subject' => 'Odp: :subject',
        'replied_mail_intro' => 'Odpowiedzieliśmy na Twoją opinię „:subject”:',
    ],

    'templates' => [
        'thanks' => '<p>Dziękujemy za kontakt!</p><p>Otrzymaliśmy Twoją wiadomość i odezwiemy się, jeśli będziemy potrzebować więcej szczegółów.</p>',
        'need_more_info' => '<p>Dziękujemy za zgłoszenie.</p><p>Czy możesz podać więcej szczegółów (kroki reprodukcji, urządzenie/przeglądarka oraz zrzuty ekranu, jeśli to możliwe)?</p>',
        'looking_into_it' => '<p>Dziękujemy — przyglądamy się temu.</p><p>Dam znać, gdy będziemy wiedzieć więcej.</p>',
        'resolved' => '<p>Jeszcze raz dziękujemy za zgłoszenie.</p><p>Zajęliśmy się sprawą po naszej stronie. Daj znać, jeśli nadal widzisz problem.</p>',
        'wont_change' => '<p>Dziękujemy za sugestię.</p><p>Przejrzeliśmy ją i na razie nie będziemy tego zmieniać, ale doceniamy, że poświęciłeś czas na napisanie.</p>',
    ],

    'template_labels' => [
        'thanks' => 'Podziękowanie / potwierdzenie',
        'need_more_info' => 'Potrzebujemy więcej info',
        'looking_into_it' => 'Sprawdzamy',
        'resolved' => 'Rozwiązane / naprawione',
        'wont_change' => 'Bez zmian',
    ],

    'contact' => [
        'feedback_heading' => 'Wyślij opinię',
        'feedback_body' => 'Wolisz krótki formularz? Otwórz okno opinii, aby wysłać pytanie, pomysł lub zgłoszenie błędu.',
        'feedback_button' => 'Otwórz formularz zgłoszenia',
    ],

    'admin' => [
        'reply' => 'Odpowiedz',
        'reply_body' => 'Odpowiedź',
        'template' => 'Szablon',
        'template_placeholder' => 'Wybierz szablon…',
        'reply_sent' => 'Odpowiedź wysłana',
        'resolve' => 'Oznacz jako rozwiązane',
        'resolved' => 'Oznaczono jako rozwiązane',
        'reopen' => 'Otwórz ponownie',
        'reopened' => 'Otwarto ponownie',
    ],
];

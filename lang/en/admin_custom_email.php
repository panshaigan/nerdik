<?php

declare(strict_types=1);

return [
    'navigation' => 'Send custom email',

    'fields' => [
        'subject' => 'Subject',
        'body' => 'Message',
        'placeholders' => 'Available placeholders',
        'placeholders_hint' => 'You can use these placeholders in the subject and message (replaced per recipient).',
        'send_as_notification' => 'Send as in-app notification',
        'send_as_notification_hint' => 'Deliver inside the app instead of email. Recipients see it in their notification list.',
    ],

    'placeholder_tokens' => [
        'name' => 'Recipient full name',
        'nickname' => 'Recipient nickname',
        'email' => 'Recipient email address',
        'organization' => 'Recipient organization name (empty if none)',
    ],

    'filters' => [
        'verified_only' => 'Verified email only',
        'exclude_admins' => 'Exclude administrators',
    ],

    'actions' => [
        'send' => 'Send custom email',
        'send_test' => 'Send test to me',
    ],

    'confirm' => [
        'send_heading' => 'Send email to selected users?',
        'send_body' => 'This will queue :count email(s). Each queued message uses one provider send (e.g. one Brevo credit). Recipients will not see each other\'s addresses.',
        'send_body_notification' => 'This will queue :count in-app notification(s). Recipients see each message only in their own notification list.',
    ],

    'validation' => [
        'subject_required' => 'Enter a subject.',
        'body_required' => 'Enter a message.',
        'no_eligible_recipients' => 'No eligible recipients. Users must have a verified email and must not be administrators.',
        'no_recipients_selected' => 'Select at least one eligible recipient.',
    ],

    'notifications' => [
        'test_queued' => 'Test email queued.',
        'test_queued_in_app' => 'Test notification queued.',
        'sent_title' => 'Emails queued',
        'sent_title_notifications' => 'Notifications queued',
        'sent_summary' => 'Queued :queued. Skipped ineligible: :skipped. Skipped over limit: :over_limit.',
    ],
];

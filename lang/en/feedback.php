<?php

declare(strict_types=1);

return [
    'types' => [
        'question' => 'Question',
        'feature' => 'Feature idea',
        'bug' => 'Bug',
        'problem' => 'Problem',
        'other' => 'Something else',
    ],

    'statuses' => [
        'open' => 'Open',
        'resolved' => 'Resolved',
    ],

    'modal' => [
        'title' => 'Send feedback',
        'trigger' => 'Feedback',
        'type' => 'Type',
        'subject' => 'Subject',
        'body' => 'Message',
        'email' => 'Your email',
        'email_hint' => 'So we can reply to you.',
        'submit' => 'Send',
        'cancel' => 'Cancel',
        'success' => 'Thanks — we received your feedback.',
        'rate_limited' => 'Too many submissions. Please try again later.',
    ],

    'already_replied' => 'This feedback already has a reply.',

    'upload' => [
        'invalid' => 'Please upload a valid image.',
        'failed' => 'Image upload failed. Please try again.',
    ],

    'notifications' => [
        'received_title' => 'New feedback',
        'received_subtitle' => ':type — :subject',
        'replied_title' => 'Reply to your feedback',
        'replied_subtitle' => ':subject',
        'replied_mail_subject' => 'Re: :subject',
        'replied_mail_intro' => 'We replied to your feedback “:subject”:',
    ],

    'templates' => [
        'thanks' => "Thanks for getting in touch!\n\nWe've received your message and will follow up if we need more details.",
        'need_more_info' => "Thanks for the report.\n\nCould you share a bit more detail (steps to reproduce, device/browser, and screenshots if possible)?",
        'looking_into_it' => "Thanks — we're looking into this.\n\nWe'll update you when we know more.",
        'resolved' => "Thanks again for reporting this.\n\nWe've addressed the issue on our side. Please let us know if you still see a problem.",
        'wont_change' => "Thanks for the suggestion.\n\nWe've reviewed it and won't be changing this for now, but we appreciate you taking the time to write.",
    ],

    'template_labels' => [
        'thanks' => 'Thanks / acknowledged',
        'need_more_info' => 'Need more info',
        'looking_into_it' => 'Looking into it',
        'resolved' => 'Resolved / fixed',
        'wont_change' => "Won't change",
    ],

    'contact' => [
        'feedback_heading' => 'Send feedback',
        'feedback_body' => 'Prefer a quick form? Open the feedback popup to send a question, feature idea, or bug report.',
        'feedback_button' => 'Open feedback form',
    ],

    'admin' => [
        'reply' => 'Reply',
        'reply_body' => 'Reply',
        'template' => 'Template',
        'template_placeholder' => 'Choose a template…',
        'reply_sent' => 'Reply sent',
        'resolve' => 'Mark resolved',
        'resolved' => 'Marked as resolved',
        'reopen' => 'Reopen',
        'reopened' => 'Reopened',
    ],
];

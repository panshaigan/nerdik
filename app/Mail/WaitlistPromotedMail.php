<?php

namespace App\Mail;

use App\Models\Activity;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class WaitlistPromotedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public Activity $activity
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('You got a place on :activity', ['activity' => $this->activity->name]),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.waitlist-promoted',
        );
    }
}

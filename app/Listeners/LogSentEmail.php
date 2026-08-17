<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Services\SentEmails\SentEmailRecorder;
use Illuminate\Mail\Events\MessageSent;

class LogSentEmail
{
    public function __construct(
        private readonly SentEmailRecorder $recorder
    ) {}

    public function handle(MessageSent $event): void
    {
        $this->recorder->record($event);
    }
}

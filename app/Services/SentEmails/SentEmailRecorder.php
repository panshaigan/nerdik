<?php

declare(strict_types=1);

namespace App\Services\SentEmails;

use App\Enums\SentEmailKind;
use App\Models\SentEmail;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Throwable;

final class SentEmailRecorder
{
    public const CONTEXT_KEY = 'sent_email';

    public function record(MessageSent $event): void
    {
        $email = $event->message;
        if (! $email instanceof Email) {
            return;
        }

        $recipients = $this->emailsFromAddresses($email->getTo());
        if ($recipients === []) {
            return;
        }

        /** @var array{kind?: string, source_class?: ?string, recipient_user_id?: ?int, related_type?: ?string, related_id?: string|int|null}|null $context */
        $context = Context::has(self::CONTEXT_KEY) ? Context::pull(self::CONTEXT_KEY) : null;
        if (! is_array($context)) {
            $context = [];
        }

        $sourceClass = $this->sourceClass($event, $email, $context);
        $kind = $this->kind($email, $sourceClass, $context);
        $from = $email->getFrom()[0] ?? null;
        $uuid = (string) Str::uuid();
        $directory = now()->format('Y/m');
        $htmlPath = $this->storeBody($directory, $uuid, 'html', $this->stringifyBody($email->getHtmlBody()));
        $textPath = $this->storeBody($directory, $uuid, 'txt', $this->stringifyBody($email->getTextBody()));
        $now = now();

        $base = [
            'uuid' => $uuid,
            'sent_at' => $now,
            'kind' => $kind,
            'source_class' => $sourceClass,
            'subject' => $this->subject($email),
            'from_email' => $from instanceof Address ? $from->getAddress() : null,
            'from_name' => $from instanceof Address && $from->getName() !== '' ? $from->getName() : null,
            'cc' => $this->emailsFromAddresses($email->getCc()),
            'bcc' => $this->emailsFromAddresses($email->getBcc()),
            'locale' => app()->getLocale(),
            'mailer' => (string) config('mail.default'),
            'provider_message_id' => $this->providerMessageId($event, $email),
            'related_type' => $context['related_type'] ?? null,
            'related_id' => $context['related_id'] ?? null,
            'html_path' => $htmlPath,
            'text_path' => $textPath,
            'metadata' => [
                'has_attachments' => $email->getAttachments() !== [],
                'notification_id' => $event->data['__laravel_notification_id'] ?? null,
            ],
        ];

        $recipientUserId = isset($context['recipient_user_id']) ? (int) $context['recipient_user_id'] : null;

        foreach ($recipients as $recipientEmail) {
            SentEmail::query()->create([
                ...$base,
                'uuid' => (string) Str::uuid(),
                'recipient_email' => $recipientEmail,
                'recipient_user_id' => $recipientUserId,
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function sourceClass(MessageSent $event, Email $email, array $context): ?string
    {
        if (isset($context['source_class']) && is_string($context['source_class']) && $context['source_class'] !== '') {
            return $context['source_class'];
        }

        $fromData = $event->data['__laravel_notification'] ?? null;
        if (is_string($fromData) && $fromData !== '') {
            return $fromData;
        }

        $header = $this->headerValue($email, 'X-Nerdik-Source');

        return $header !== '' ? $header : null;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function kind(Email $email, ?string $sourceClass, array $context): SentEmailKind
    {
        if (isset($context['kind']) && is_string($context['kind'])) {
            $fromContext = SentEmailKind::tryFrom($context['kind']);
            if ($fromContext instanceof SentEmailKind) {
                return $fromContext;
            }
        }

        $headerKind = SentEmailKind::tryFrom($this->headerValue($email, 'X-Nerdik-Kind'));
        if ($headerKind instanceof SentEmailKind) {
            return $headerKind;
        }

        if (is_string($sourceClass) && $sourceClass !== '') {
            return SentEmailKind::fromSourceClass($sourceClass);
        }

        return SentEmailKind::Unknown;
    }

    private function subject(Email $email): string
    {
        $subject = (string) $email->getSubject();
        $subject = trim($subject);

        if ($subject === '') {
            return '(no subject)';
        }

        return Str::limit($subject, 255, '');
    }

    private function providerMessageId(MessageSent $event, Email $email): ?string
    {
        try {
            $messageId = $event->sent->getMessageId();
            if (is_string($messageId) && $messageId !== '') {
                return $messageId;
            }
        } catch (Throwable) {
            // Some transports do not expose a message id.
        }

        $header = $this->headerValue($email, 'Message-ID');

        return $header !== '' ? $header : null;
    }

    private function storeBody(string $directory, string $uuid, string $extension, ?string $body): ?string
    {
        if ($body === null) {
            return null;
        }

        $path = $directory.'/'.$uuid.'.'.$extension;

        try {
            Storage::disk('email_logs')->put($path, $body);
        } catch (Throwable $exception) {
            report($exception);

            return null;
        }

        return $path;
    }

    private function stringifyBody(mixed $body): ?string
    {
        if (is_string($body) && $body !== '') {
            return $body;
        }

        if (is_resource($body)) {
            $contents = stream_get_contents($body);

            return is_string($contents) && $contents !== '' ? $contents : null;
        }

        return null;
    }

    /**
     * @param  list<Address>  $addresses
     * @return list<string>
     */
    private function emailsFromAddresses(array $addresses): array
    {
        $emails = [];
        foreach ($addresses as $address) {
            $email = mb_strtolower(trim($address->getAddress()));
            if ($email !== '') {
                $emails[] = $email;
            }
        }

        return array_values(array_unique($emails));
    }

    private function headerValue(Email $email, string $name): string
    {
        $header = $email->getHeaders()->get($name);
        if ($header === null) {
            return '';
        }

        return trim($header->getBodyAsString());
    }
}

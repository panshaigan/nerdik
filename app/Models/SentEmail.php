<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SentEmailKind;
use Database\Factories\SentEmailFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Storage;

class SentEmail extends Model
{
    /** @use HasFactory<SentEmailFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'uuid',
        'sent_at',
        'kind',
        'source_class',
        'subject',
        'from_email',
        'from_name',
        'recipient_email',
        'recipient_user_id',
        'cc',
        'bcc',
        'locale',
        'mailer',
        'provider_message_id',
        'related_type',
        'related_id',
        'html_path',
        'text_path',
        'metadata',
    ];

    /**
     * @return array<string, string>
     */
    #[\Override]
    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
            'kind' => SentEmailKind::class,
            'cc' => 'array',
            'bcc' => 'array',
            'metadata' => 'array',
        ];
    }

    public function recipientUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recipient_user_id');
    }

    public function related(): MorphTo
    {
        return $this->morphTo();
    }

    public function htmlBody(): ?string
    {
        return $this->readStoredBody($this->html_path);
    }

    public function textBody(): ?string
    {
        return $this->readStoredBody($this->text_path);
    }

    public function relatedLabel(): ?string
    {
        $related = $this->related;
        if (! $related instanceof Model) {
            return null;
        }

        $name = $related->getAttribute('name')
            ?? $related->getAttribute('subject')
            ?? $related->getKey();

        return trim($this->related_type.' #'.$related->getKey().' '.$name);
    }

    private function readStoredBody(?string $path): ?string
    {
        if ($path === null || $path === '') {
            return null;
        }

        $disk = Storage::disk('email_logs');
        if (! $disk->exists($path)) {
            return null;
        }

        $contents = $disk->get($path);

        return is_string($contents) && $contents !== '' ? $contents : null;
    }
}

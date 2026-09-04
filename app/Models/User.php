<?php

namespace App\Models;

use App\Actions\Avatars\ResolveAvatarUrl;
use App\Enums\AppLocale;
use App\Enums\NotificationPreferenceKey;
use App\Enums\TimeDisplayFormat;
use App\Models\Concerns\InteractsWithAvatarImage;
use App\Notifications\VerifyPendingEmailNotification;
use App\Support\Ui\AvatarPicture;
use App\Support\Ui\AvatarSlot;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasName;
use Filament\Panel;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Contracts\Translation\HasLocalePreference;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\HasMedia;

class User extends Authenticatable implements FilamentUser, HasLocalePreference, HasMedia, HasName, MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, InteractsWithAvatarImage, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'nickname',
        'email',
        'pending_email',
        'password',
        'organization_id',
        'is_event_organizer',
        'is_deleted',
        'locale',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    #[\Override]
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_admin' => 'boolean',
            'is_event_organizer' => 'boolean',
            'is_deleted' => 'boolean',
            'locale' => AppLocale::class,
        ];
    }

    /**
     * Whether the account has been deleted (anonymised) by its owner.
     */
    public function isDeleted(): bool
    {
        return $this->is_deleted === true;
    }

    public function profile(): HasOne
    {
        return $this->hasOne(UserProfile::class);
    }

    public function sentEmails(): HasMany
    {
        return $this->hasMany(SentEmail::class, 'recipient_user_id');
    }

    /**
     * Canonical user identity. The single source of truth for how a user is rendered anywhere in the app.
     */
    public function displayName(): string
    {
        if ($this->isDeleted()) {
            return __('ui.common.deleted_user');
        }

        return (string) $this->nickname;
    }

    /**
     * Display name for user badges, including organization acronym when set.
     */
    public function badgeDisplayName(): string
    {
        if ($this->isDeleted()) {
            return __('ui.common.deleted_user');
        }

        $name = $this->displayName();
        $acronym = trim((string) ($this->organization?->acronym ?? ''));

        return $acronym !== '' ? "{$name} [{$acronym}]" : $name;
    }

    public function generatedAvatarName(): string
    {
        $initials = trim((string) ($this->profile?->avatar_initials ?? ''));

        return $initials !== '' ? $initials : $this->displayName();
    }

    public function generatedAvatarLength(): int
    {
        $initials = trim((string) ($this->profile?->avatar_initials ?? ''));

        if ($initials !== '') {
            return strlen($initials);
        }

        return 2;
    }

    public static function uiAvatarsUrl(
        string $name,
        string $backgroundColor,
        string $textColor,
        int $length,
        ?int $size = null,
    ): string {
        $bg = ltrim($backgroundColor, '#');
        $fg = ltrim($textColor, '#');

        $query = sprintf(
            'name=%s&background=%s&color=%s&length=%d&rounded=true&bold=true',
            rawurlencode($name),
            rawurlencode($bg),
            rawurlencode($fg),
            $length,
        );

        if ($size !== null && $size > 0) {
            $query .= '&size='.$size;
        }

        return 'https://ui-avatars.com/api/?'.$query;
    }

    public function generatedAvatarUrl(?int $size = null): string
    {
        $profile = $this->profile;

        return self::uiAvatarsUrl(
            $this->generatedAvatarName(),
            (string) ($profile?->avatar_bg_color ?? '#1d4ed8'),
            (string) ($profile?->avatar_text_color ?? '#ffffff'),
            $this->generatedAvatarLength(),
            $size,
        );
    }

    public function avatarPicture(AvatarSlot $slot = AvatarSlot::Badge): AvatarPicture
    {
        return AvatarPicture::fromUser($this, $slot);
    }

    public function avatarUrl(AvatarSlot|int|null $slotOrSize = null): string
    {
        if ($this->isDeleted()) {
            $size = $slotOrSize instanceof AvatarSlot
                ? $slotOrSize->displaySize()
                : (is_int($slotOrSize) ? $slotOrSize : AvatarSlot::Badge->displaySize());

            return self::uiAvatarsUrl(__('ui.common.deleted_user'), '#9ca3af', '#ffffff', 1, $size);
        }

        return app(ResolveAvatarUrl::class)($this, $slotOrSize);
    }

    /**
     * Filament uses this to label the authenticated user in the admin UI (topbar, menus).
     */
    public function getFilamentName(): string
    {
        return $this->displayName();
    }

    /**
     * Build a unique nickname from an email's local-part, appending a numeric suffix on collision.
     */
    public static function generateUniqueNicknameFromEmail(string $email): string
    {
        $localPart = explode('@', $email)[0] ?? '';
        $base = Str::slug($localPart, '_');

        if ($base === '') {
            $base = 'user';
        }

        if (! self::where('nickname', $base)->exists()) {
            return $base;
        }

        $suffix = 2;
        while (self::where('nickname', $base.'_'.$suffix)->exists()) {
            $suffix++;
        }

        return $base.'_'.$suffix;
    }

    public function hasPendingEmailChange(): bool
    {
        return $this->pending_email !== null && $this->pending_email !== '';
    }

    public function sendPendingEmailVerificationNotification(): void
    {
        if (! $this->hasPendingEmailChange()) {
            return;
        }

        Notification::route('mail', $this->pending_email)
            ->notify((new VerifyPendingEmailNotification($this))->locale($this->preferredLocale()));
    }

    public function preferredLocale(): string
    {
        return $this->locale?->value ?? AppLocale::En->value;
    }

    public function canCreateEvents(): bool
    {
        return $this->is_event_organizer === true || $this->is_admin === true;
    }

    public function canAccessPanel(Panel $panel): bool
    {
        if ($panel->getId() === 'admin') {
            return $this->is_admin === true;
        }

        return true;
    }

    /**
     * Whether this user may edit or delete the model: admins always; otherwise the row's `created_by` must match.
     */
    public function canModifyEntity(Model $entity): bool
    {
        if ($this->is_admin === true) {
            return true;
        }

        $ownerId = $entity->getAttribute('created_by');

        return $ownerId !== null && (int) $ownerId === (int) $this->id;
    }

    public function interestedEvents(): MorphToMany
    {
        return $this->morphedByMany(Event::class, 'interest', 'user_interests');
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function interestedActivities(): MorphToMany
    {
        return $this->morphedByMany(Activity::class, 'interest', 'user_interests');
    }

    /**
     * Resolved matrix: every known key is present; missing storage keys use {@see NotificationPreferenceKey::defaultMatrix()}.
     *
     * @return array<string, array{in_app: bool, email: bool, every_join?: bool}>
     */
    public function resolvedNotificationPreferences(): array
    {
        $matrix = NotificationPreferenceKey::defaultMatrix();
        $stored = $this->profile?->notification_preferences;
        if (! is_array($stored)) {
            return $matrix;
        }

        foreach (NotificationPreferenceKey::cases() as $case) {
            $key = $case->value;
            $block = $stored[$key] ?? null;
            if (! is_array($block)) {
                continue;
            }

            if (array_key_exists('in_app', $block)) {
                $matrix[$key]['in_app'] = (bool) $block['in_app'];
            }
            if (array_key_exists('email', $block)) {
                $matrix[$key]['email'] = (bool) $block['email'];
            }
            if ($case === NotificationPreferenceKey::ActivityParticipantJoined && array_key_exists('every_join', $block)) {
                $matrix[$key]['every_join'] = (bool) $block['every_join'];
            }
        }

        return $matrix;
    }

    public function wantsNotificationChannel(NotificationPreferenceKey $key, string $channel): bool
    {
        if ($channel !== 'in_app' && $channel !== 'email') {
            throw new \InvalidArgumentException('Channel must be in_app or email.');
        }

        return $this->resolvedNotificationPreferences()[$key->value][$channel];
    }

    /**
     * @param  array<string, array{in_app: bool, email: bool}>  $preferences
     */
    public function setNotificationPreferencesPayload(array $preferences): void
    {
        $profile = $this->profile()->firstOrCreate();
        $profile->notification_preferences = $preferences;
        $profile->save();
        $this->setRelation('profile', $profile);
    }

    /**
     * @param  array{category: string, title: string, lines: list<string>, url: string, dedupe_key: string}  $item
     */
    public function retainsScheduledDigestItem(array $item): bool
    {
        $category = isset($item['category']) ? (string) $item['category'] : '';
        $key = NotificationPreferenceKey::tryFromScheduledCategory($category);
        if ($key === null) {
            return true;
        }

        return $this->wantsNotificationChannel($key, 'in_app')
            || $this->wantsNotificationChannel($key, 'email');
    }

    protected function getTimezoneAttribute(): ?string
    {
        return $this->profile?->timezone;
    }

    protected function getTimeDisplayFormatAttribute(): TimeDisplayFormat
    {
        return $this->profile?->time_display_format ?? TimeDisplayFormat::TwentyFourHour;
    }
}

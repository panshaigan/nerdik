<?php

namespace App\Models;

use App\Actions\Organizations\ResolveOrganizationLogoUrl;
use App\Enums\OrganizationLogoSource;
use App\Traits\HasAutoSlug;
use App\Traits\HasMetaColumns;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Organization extends Model
{
    use HasAutoSlug, HasFactory, HasMetaColumns, SoftDeletes;

    #[\Override]
    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    protected $fillable = [
        'name',
        'acronym',
        'description',
        'logo_path',
        'logo_source',
        'logo_bg_color',
        'logo_text_color',
        'slug',
        'created_by',
        'updated_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'logo_source' => OrganizationLogoSource::class,
        ];
    }

    public function events(): HasMany
    {
        return $this->hasMany(Event::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function generatedLogoName(): string
    {
        $acronym = trim((string) ($this->acronym ?? ''));

        return $acronym !== '' ? $acronym : $this->name;
    }

    public function generatedLogoLength(): int
    {
        $acronym = trim((string) ($this->acronym ?? ''));

        if ($acronym !== '') {
            return min(strlen($acronym), 3);
        }

        return 2;
    }

    public function generatedLogoUrl(): string
    {
        return User::uiAvatarsUrl(
            $this->generatedLogoName(),
            (string) ($this->logo_bg_color ?? '#1d4ed8'),
            (string) ($this->logo_text_color ?? '#ffffff'),
            $this->generatedLogoLength(),
        );
    }

    public function logoUrl(): string
    {
        return app(ResolveOrganizationLogoUrl::class)($this);
    }
}

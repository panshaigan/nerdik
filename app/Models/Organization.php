<?php

namespace App\Models;

use App\Traits\HasAutoSlug;
use App\Traits\HasMetaColumns;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Organization extends Model
{
    use HasFactory, HasAutoSlug, HasMetaColumns, SoftDeletes;

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
        'slug',
        'created_by',
        'updated_by',
    ];

    public function events(): HasMany
    {
        return $this->hasMany(Event::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}

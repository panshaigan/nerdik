<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Enums\SentEmailKind;
use Illuminate\Database\Eloquent\Model;

interface ProvidesSentEmailContext
{
    public function sentEmailKind(): SentEmailKind;

    public function sentEmailRelated(): ?Model;
}

<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\TagPopularityRecalculator;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('tags:recalculate-popularity')]
#[Description('Recalculate tag popularity scores from upcoming browse-visible activity usage')]
class RecalculateTagPopularityCommand extends Command
{
    public function handle(TagPopularityRecalculator $recalculator): int
    {
        $updatedTagsCount = $recalculator->recalculateAll();

        $this->info(sprintf('Updated popularity for %d tag(s).', $updatedTagsCount));

        return self::SUCCESS;
    }
}

<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\UserProfile;
use App\Support\Ui\GeneratedAvatarColors;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('profiles:randomize-initials-avatar-colors {--dry-run : Report changes without writing}')]
#[Description('Assign random initials avatar colors to profiles still using the default palette')]
final class RandomizeInitialsAvatarColorsCommand extends Command
{
    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $updated = 0;
        $skipped = 0;
        $failed = 0;

        UserProfile::query()
            ->with('user')
            ->orderBy('user_id')
            ->each(function (UserProfile $profile) use ($dryRun, &$updated, &$skipped, &$failed): void {
                if (! GeneratedAvatarColors::usesDefaultColors($profile->avatar_bg_color, $profile->avatar_text_color)) {
                    $skipped++;

                    return;
                }

                if ($profile->user === null) {
                    $this->warn("Failed profile #{$profile->id}: user missing.");
                    $failed++;

                    return;
                }

                $colors = GeneratedAvatarColors::random();

                if ($dryRun) {
                    $this->line("Would update user #{$profile->user_id} to {$colors['background']} / {$colors['text']}.");
                    $updated++;

                    return;
                }

                $profile->forceFill([
                    'avatar_bg_color' => $colors['background'],
                    'avatar_text_color' => $colors['text'],
                ])->save();

                $updated++;
            });

        $this->info("Updated: {$updated}, skipped: {$skipped}, failed: {$failed}".($dryRun ? ' (dry run)' : ''));

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}

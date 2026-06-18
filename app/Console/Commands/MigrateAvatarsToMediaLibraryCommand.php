<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Avatars\AttachUserAvatarFromPath;
use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

#[Signature('avatars:migrate-to-media-library {--dry-run : Report changes without writing}')]
#[Description('Attach legacy avatars/{user_id}.webp files to the Spatie avatar media collection')]
final class MigrateAvatarsToMediaLibraryCommand extends Command
{
    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $migrated = 0;
        $skipped = 0;
        $failed = 0;

        UserProfile::query()
            ->whereNotNull('avatar_path')
            ->where('avatar_path', '!=', '')
            ->orderBy('user_id')
            ->each(function (UserProfile $profile) use ($dryRun, &$migrated, &$skipped, &$failed): void {
                $user = $profile->user;

                if ($user === null) {
                    $this->warn("Failed profile #{$profile->id}: user missing.");
                    $failed++;

                    return;
                }

                $result = $this->migrateUserAvatar($user, (string) $profile->avatar_path, $dryRun);

                if ($result === 'migrated') {
                    $migrated++;
                } elseif ($result === 'skipped') {
                    $skipped++;
                } else {
                    $failed++;
                }
            });

        User::query()
            ->whereDoesntHave('media', fn ($query) => $query->where('collection_name', 'avatar'))
            ->orderBy('id')
            ->each(function (User $user) use ($dryRun, &$migrated, &$skipped, &$failed): void {
                $legacyPath = 'avatars/'.$user->id.'.webp';

                if (! Storage::disk('public')->exists($legacyPath)) {
                    return;
                }

                $result = $this->migrateUserAvatar($user, $legacyPath, $dryRun);

                if ($result === 'migrated') {
                    $migrated++;
                } elseif ($result === 'skipped') {
                    $skipped++;
                } else {
                    $failed++;
                }
            });

        $this->info("Migrated: {$migrated}, skipped: {$skipped}, failed: {$failed}".($dryRun ? ' (dry run)' : ''));

        if (! $dryRun && $migrated > 0) {
            $this->line('Run: php artisan media-library:regenerate --only-missing --with-responsive-images');
        }

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @return 'migrated'|'skipped'|'failed'
     */
    private function migrateUserAvatar(User $user, string $path, bool $dryRun): string
    {
        if ($user->getFirstMedia('avatar') !== null) {
            $this->line("Skipping user #{$user->id}: avatar media already present.");

            return 'skipped';
        }

        if (! Storage::disk('public')->exists($path)) {
            $this->warn("Failed user #{$user->id}: file missing at [{$path}].");

            return 'failed';
        }

        if ($dryRun) {
            $this->line("Would migrate user #{$user->id} from [{$path}].");

            return 'migrated';
        }

        $absolutePath = Storage::disk('public')->path($path);

        app(AttachUserAvatarFromPath::class)($user, $absolutePath);

        $profile = $user->profile;
        if ($profile !== null) {
            $profile->forceFill(['avatar_path' => null])->save();
        }

        $this->line("Migrated user #{$user->id} from [{$path}].");

        return 'migrated';
    }
}

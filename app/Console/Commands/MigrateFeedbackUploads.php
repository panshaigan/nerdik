<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Feedback;
use App\Models\FeedbackUpload;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class MigrateFeedbackUploads extends Command
{
    protected $signature = 'feedback:migrate-uploads {--apply : Copy referenced images to private storage, update feedback, and remove their public originals} {--delete-unreferenced : Also delete remaining public feedback images; requires --apply}';

    protected $description = 'Preview or migrate legacy public feedback images into private storage';

    public function handle(): int
    {
        $public = Storage::disk('public');
        $private = Storage::disk('local');
        $referenced = [];
        $missing = false;

        Feedback::query()->eachById(function (Feedback $feedback) use ($public, $private, &$referenced, &$missing): void {
            preg_match_all('~(?:https?://[^"\s<>]+)?/storage/(feedback/editor/[a-zA-Z0-9_-]+\.(?:jpe?g|png|gif|webp))~i', $feedback->body, $matches, PREG_SET_ORDER);
            $matches = array_unique($matches, SORT_REGULAR);

            foreach ($matches as $match) {
                $referenced[$match[1]] = true;

                if (! $public->exists($match[1])) {
                    $this->warn('Missing legacy file: '.$match[1]);
                    $missing = true;
                }
            }

            if (! $this->option('apply')) {
                return;
            }

            $createdPaths = [];

            try {
                DB::transaction(function () use ($feedback, $matches, $public, $private, &$createdPaths): void {
                    $body = $feedback->body;

                    foreach ($matches as $match) {
                        if (! $public->exists($match[1])) {
                            continue;
                        }

                        $id = (string) Str::uuid();
                        $path = 'feedback/editor/'.$id.'.'.pathinfo($match[1], PATHINFO_EXTENSION);
                        $createdPaths[] = $path;
                        $stream = $public->readStream($match[1]);

                        try {
                            if ($stream === null || ! $private->put($path, $stream, 'private')) {
                                throw new RuntimeException('Unable to copy legacy feedback image.');
                            }
                        } finally {
                            if (is_resource($stream)) {
                                fclose($stream);
                            }
                        }

                        $upload = FeedbackUpload::query()->create([
                            'id' => $id,
                            'feedback_id' => $feedback->id,
                            'path' => $path,
                            'session_hash' => hash('sha256', Str::random(64)),
                            'ip_hash' => hash('sha256', Str::random(64)),
                            'bytes' => $private->size($path),
                            'expires_at' => null,
                        ]);
                        $body = str_replace($match[0], route('feedback.editor-images.show', $upload), $body);
                    }

                    $feedback->update(['body' => $body]);
                });
            } catch (Throwable $exception) {
                foreach ($createdPaths as $createdPath) {
                    $private->delete($createdPath);
                }

                throw $exception;
            }
        });

        $files = $public->files('feedback/editor');
        $this->info(count($referenced).' referenced public paths; '.count(array_diff($files, array_keys($referenced))).' unreferenced public files.');

        if (! $this->option('apply')) {
            $this->info('Preview only. Use --apply to migrate; add --delete-unreferenced to remove abandoned public uploads.');

            return $missing ? self::FAILURE : self::SUCCESS;
        }

        foreach ($files as $path) {
            if (isset($referenced[$path]) || $this->option('delete-unreferenced')) {
                if (! $public->delete($path)) {
                    $this->error('Unable to delete public file: '.$path);

                    return self::FAILURE;
                }
            }
        }

        return $missing ? self::FAILURE : self::SUCCESS;
    }
}

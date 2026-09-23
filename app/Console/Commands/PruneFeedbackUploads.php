<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\FeedbackUpload;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;

class PruneFeedbackUploads extends Command
{
    protected $signature = 'feedback:prune-uploads';

    protected $description = 'Delete expired temporary feedback images and attachments whose feedback was deleted';

    public function handle(): int
    {
        FeedbackUpload::query()
            ->whereNull('feedback_id')
            ->where(function (Builder $query): void {
                $query->where('expires_at', '<=', now())->orWhereNull('expires_at');
            })
            ->eachById(function (FeedbackUpload $upload): void {
                if (! Storage::disk('local')->exists($upload->path) || Storage::disk('local')->delete($upload->path)) {
                    $upload->delete();
                }
            });

        return self::SUCCESS;
    }
}

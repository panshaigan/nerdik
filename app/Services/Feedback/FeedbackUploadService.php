<?php

declare(strict_types=1);

namespace App\Services\Feedback;

use App\Models\Feedback;
use App\Models\FeedbackUpload;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class FeedbackUploadService
{
    public function sessionHash(Request $request): string
    {
        $token = $request->session()->get('feedback_upload_owner');

        if (! is_string($token)) {
            $token = Str::random(64);
            $request->session()->put('feedback_upload_owner', $token);
        }

        return hash_hmac('sha256', $token, (string) config('app.key'));
    }

    public function store(Request $request, UploadedFile $file): FeedbackUpload
    {
        return Cache::lock('feedback-upload-allocation', 30)->block(5, function () use ($request, $file): FeedbackUpload {
            $sessionHash = $this->sessionHash($request);
            $ipHash = hash_hmac('sha256', (string) $request->ip(), (string) config('app.key'));
            $bytes = (int) $file->getSize();
            $pending = FeedbackUpload::query()->whereNull('feedback_id');

            if ((clone $pending)->where('session_hash', $sessionHash)->sum('bytes') + $bytes > config('feedback.upload_session_bytes')
                || FeedbackUpload::query()->where('ip_hash', $ipHash)->where('created_at', '>=', now()->subDay())->sum('bytes') + $bytes > config('feedback.upload_daily_ip_bytes')
                || $pending->sum('bytes') + $bytes > config('feedback.upload_pending_bytes')) {
                throw ValidationException::withMessages(['file' => [__('feedback.upload.quota')]]);
            }

            $id = (string) Str::uuid();
            $path = 'feedback/editor/'.$id.'.'.$file->extension();
            $upload = FeedbackUpload::query()->create([
                'id' => $id,
                'path' => $path,
                'session_hash' => $sessionHash,
                'ip_hash' => $ipHash,
                'bytes' => $bytes,
                'expires_at' => now()->addDay(),
            ]);

            if (Storage::disk('local')->putFileAs('feedback/editor', $file, basename($path), 'private') === false) {
                $upload->delete();
                throw ValidationException::withMessages(['file' => [__('feedback.upload.failed')]]);
            }

            return $upload;
        });
    }

    public function attach(Feedback $feedback, Request $request): void
    {
        preg_match_all('~/feedback/editor-images/([0-9a-f-]{36})~i', $feedback->body, $matches);
        $ids = array_values(array_unique($matches[1]));

        foreach ($ids as $id) {
            $updated = FeedbackUpload::query()
                ->whereKey($id)
                ->where('session_hash', $this->sessionHash($request))
                ->whereNull('feedback_id')
                ->where('expires_at', '>', now())
                ->update(['feedback_id' => $feedback->id, 'expires_at' => null]);

            if ($updated !== 1) {
                throw ValidationException::withMessages(['body' => [__('feedback.upload.unavailable')]]);
            }
        }
    }
}

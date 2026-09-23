<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\FeedbackUpload;
use App\Services\Feedback\FeedbackUploadService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class FeedbackEditorImageController extends Controller
{
    public function __invoke(Request $request, FeedbackUpload $upload, FeedbackUploadService $uploads): StreamedResponse
    {
        abort_if($upload->expires_at?->isPast() === true, 404);

        $ownsSession = hash_equals($upload->session_hash, $uploads->sessionHash($request));
        $isSubmitted = $upload->feedback_id !== null;
        $isAdmin = $request->user()?->is_admin === true;
        $isReporter = $request->user() !== null && $upload->feedback?->user_id === $request->user()->id;

        abort_unless($ownsSession || ($isSubmitted && ($isAdmin || $isReporter)), 404);
        abort_unless(Storage::disk('local')->exists($upload->path), 404);

        return Storage::disk('local')->response($upload->path, null, [
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
        ]);
    }
}

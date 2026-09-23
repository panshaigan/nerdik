<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\Feedback\FeedbackUploadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

final class FeedbackEditorUploadController extends Controller
{
    private const MAX_KILOBYTES = 2048;

    /**
     * @var list<string>
     */
    private const ALLOWED_MIMES = [
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp',
    ];

    public function __invoke(Request $request, FeedbackUploadService $uploads): JsonResponse
    {
        $file = $request->file('file');

        if ($file === null || ! $file->isValid()) {
            throw ValidationException::withMessages([
                'file' => [__('feedback.upload.invalid')],
            ]);
        }

        $request->validate([
            'file' => [
                'required',
                'file',
                'image',
                'max:'.self::MAX_KILOBYTES,
                'mimetypes:'.implode(',', self::ALLOWED_MIMES),
            ],
        ]);

        $upload = $uploads->store($request, $file);

        return response()->json([
            'location' => route('feedback.editor-images.show', $upload),
        ]);
    }
}

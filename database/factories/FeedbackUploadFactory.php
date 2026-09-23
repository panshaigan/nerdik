<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\FeedbackUpload;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<FeedbackUpload> */
class FeedbackUploadFactory extends Factory
{
    public function definition(): array
    {
        return [
            'path' => 'feedback/editor/'.Str::uuid().'.png',
            'session_hash' => hash('sha256', Str::random(40)),
            'ip_hash' => hash('sha256', fake()->ipv4()),
            'bytes' => 1024,
            'expires_at' => now()->addDay(),
        ];
    }
}

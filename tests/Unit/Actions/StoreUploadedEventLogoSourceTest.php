<?php

declare(strict_types=1);

namespace Tests\Unit\Actions;

use App\Actions\Events\DeleteUploadedEventLogo;
use App\Actions\Events\StoreUploadedEventLogo;
use App\Enums\EventLogoSource;
use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class StoreUploadedEventLogoSourceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_stores_cropped_logo_and_source_media(): void
    {
        Storage::fake('public');

        $user = User::factory()->organizer()->create();
        $event = Event::factory()->create([
            'created_by' => $user->id,
            'updated_by' => $user->id,
            'logo_source' => EventLogoSource::Upload,
        ]);

        app(StoreUploadedEventLogo::class)(
            $event,
            UploadedFile::fake()->image('crop.jpg', 1280, 720),
            UploadedFile::fake()->image('original.jpg', 2000, 1500),
        );

        $event->refresh();
        $this->assertNotNull($event->getFirstMedia('logo'));
        $this->assertNotNull($event->getFirstMedia('source'));
        $this->assertSame('image/webp', $event->getFirstMedia('source')?->mime_type);
    }

    #[Test]
    public function delete_clears_logo_and_source_collections(): void
    {
        Storage::fake('public');

        $user = User::factory()->organizer()->create();
        $event = Event::factory()->create([
            'created_by' => $user->id,
            'updated_by' => $user->id,
            'logo_source' => EventLogoSource::Upload,
        ]);

        app(StoreUploadedEventLogo::class)(
            $event,
            UploadedFile::fake()->image('crop.jpg', 1280, 720),
            UploadedFile::fake()->image('original.jpg', 1600, 900),
        );

        app(DeleteUploadedEventLogo::class)($event);

        $event->refresh();
        $this->assertNull($event->getFirstMedia('logo'));
        $this->assertNull($event->getFirstMedia('source'));
    }
}

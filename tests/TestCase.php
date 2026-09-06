<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Storage;
use Tests\Support\EnsuresBrandLogoManifest;

abstract class TestCase extends BaseTestCase
{
    use EnsuresBrandLogoManifest;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('email_logs');
        $this->ensureBrandLogoManifest();
    }
}

<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Models\Organization;
use App\Models\User;
use App\Support\AdminEmail\AdminEmailPlaceholderReplacer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class AdminEmailPlaceholderReplacerTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_replaces_placeholders_in_subject(): void
    {
        $organization = Organization::factory()->create(['name' => 'Acme Org']);
        $user = User::factory()->create([
            'name' => 'Ada Lovelace',
            'nickname' => 'ada',
            'email' => 'ada@example.com',
            'organization_id' => $organization->id,
        ]);

        $replacer = new AdminEmailPlaceholderReplacer;

        $subject = $replacer->replaceSubject(
            'Hello {name} ({nickname}) at {organization}',
            $user,
        );

        $this->assertSame('Hello Ada Lovelace (ada) at Acme Org', $subject);
    }

    #[Test]
    public function it_escapes_placeholder_values_in_html_body(): void
    {
        $user = User::factory()->create([
            'name' => '<script>alert(1)</script>',
            'nickname' => 'nick',
            'email' => 'user@example.com',
        ]);

        $replacer = new AdminEmailPlaceholderReplacer;

        $html = $replacer->replaceBodyHtml('<p>{name}</p>', $user);

        $this->assertSame('<p>&lt;script&gt;alert(1)&lt;/script&gt;</p>', $html);
    }
}

<?php

declare(strict_types=1);

namespace Tests\Feature\Filament;

use App\Filament\Admin\Resources\SentEmails\SentEmailResource;
use App\Filament\Admin\Resources\Users\Pages\EditUser;
use App\Filament\Admin\Resources\Users\RelationManagers\SentEmailsRelationManager;
use App\Models\SentEmail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

final class SentEmailsRelationManagerTest extends TestCase
{
    use RefreshDatabase;

    public function test_view_action_links_to_sent_email_resource_page(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();
        $email = SentEmail::factory()->create([
            'recipient_user_id' => $user->id,
            'subject' => 'Waitlist promotion for Laser Tag',
        ]);

        Livewire::actingAs($admin)
            ->test(SentEmailsRelationManager::class, [
                'ownerRecord' => $user,
                'pageClass' => EditUser::class,
            ])
            ->assertCanSeeTableRecords([$email])
            ->assertTableActionHasUrl(
                'view',
                SentEmailResource::getUrl('view', ['record' => $email]),
                $email,
            );
    }
}

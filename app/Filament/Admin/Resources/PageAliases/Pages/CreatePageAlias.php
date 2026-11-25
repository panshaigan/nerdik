<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\PageAliases\Pages;

use App\Filament\Admin\Resources\PageAliases\PageAliasResource;
use App\Services\PageAliasService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreatePageAlias extends CreateRecord
{
    protected static string $resource = PageAliasResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $validated = app(PageAliasService::class)->validateForFilament(
            (string) ($data['alias'] ?? ''),
            (string) ($data['target_path'] ?? ''),
        );

        $data['alias'] = $validated['alias'];
        $data['target_path'] = $validated['target_path'];
        $data['created_by_id'] = Auth::id();

        return $data;
    }
}

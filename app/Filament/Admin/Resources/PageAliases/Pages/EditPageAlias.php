<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\PageAliases\Pages;

use App\Filament\Admin\Resources\PageAliases\PageAliasResource;
use App\Models\PageAlias;
use App\Services\PageAliasService;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditPageAlias extends EditRecord
{
    protected static string $resource = PageAliasResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        /** @var PageAlias $record */
        $record = $this->getRecord();

        $validated = app(PageAliasService::class)->validateForFilament(
            (string) ($data['alias'] ?? ''),
            (string) ($data['target_path'] ?? ''),
            $record->id,
        );

        $data['alias'] = $validated['alias'];
        $data['target_path'] = $validated['target_path'];

        return $data;
    }
}

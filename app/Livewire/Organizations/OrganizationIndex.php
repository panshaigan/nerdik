<?php

namespace App\Livewire\Organizations;

use App\Actions\Organizations\DeleteUploadedOrganizationLogo;
use App\Actions\Organizations\StoreUploadedOrganizationLogo;
use App\Enums\OrganizationLogoSource;
use App\Models\Organization;
use App\Support\RichText;
use App\Traits\AuthorizesOwnership;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\WithFileUploads;

class OrganizationIndex extends Component
{
    use AuthorizesOwnership;
    use WithFileUploads;

    public bool $modalOpen = false;

    /** @var 'create'|'edit' */
    public string $modalMode = 'create';

    public ?int $editingOrganizationId = null;

    public int $modalRenderKey = 0;

    public string $name = '';

    public string $acronym = '';

    public string $description = '';

    public string $logo_source = 'generated';

    public string $logo_bg_color = '#1d4ed8';

    public string $logo_text_color = '#ffffff';

    /** @var mixed */
    public $croppedLogo = null;

    public function mount(): void
    {
        $editSlug = request()->query('edit');
        if (is_string($editSlug) && $editSlug !== '') {
            $organization = Organization::query()
                ->where('slug', $editSlug)
                ->where('created_by', auth()->id())
                ->first();
            if ($organization !== null) {
                $this->openEditModal($organization->id);
            }

            return;
        }

        if (request()->query('create') === '1') {
            $this->openCreateModal();
        }
    }

    public function openCreateModal(): void
    {
        $this->resetForm();
        $this->modalRenderKey++;
        $this->modalMode = 'create';
        $this->modalOpen = true;
        $this->scheduleTinyMceModalRefresh();
    }

    public function openEditModal(int $id): void
    {
        $organization = Organization::query()->findOrFail($id);
        $this->authorizeCreatedBy($organization);

        $this->modalMode = 'edit';
        $this->editingOrganizationId = $organization->id;
        $this->name = $organization->name;
        $this->acronym = (string) ($organization->acronym ?? '');
        $this->description = (string) ($organization->description ?? '');
        $this->logo_bg_color = $organization->logo_bg_color ?? '#1d4ed8';
        $this->logo_text_color = $organization->logo_text_color ?? '#ffffff';

        $rawLogoSource = $organization->logo_source;
        if ($rawLogoSource instanceof OrganizationLogoSource) {
            $this->logo_source = $rawLogoSource->value;
        } elseif (is_string($rawLogoSource) && $rawLogoSource !== '') {
            $this->logo_source = $rawLogoSource;
        } else {
            $this->logo_source = OrganizationLogoSource::Generated->value;
        }

        $this->resetErrorBag();
        $this->reset('croppedLogo');
        $this->modalRenderKey++;
        $this->modalOpen = true;
        $this->scheduleTinyMceModalRefresh();
    }

    public function closeModal(): void
    {
        $this->modalOpen = false;
        $this->resetForm();
    }

    public function updatedLogoSource(string $value): void
    {
        if ($value !== OrganizationLogoSource::Upload->value) {
            $this->reset('croppedLogo');
        }
    }

    public function clearCroppedLogo(): void
    {
        $this->reset('croppedLogo');
    }

    public function save(): void
    {
        $previousSource = $this->resolvePreviousLogoSourceValue();
        $hasExistingUpload = $this->hasExistingUploadedLogo();

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'acronym' => ['nullable', 'string', 'max:12'],
            'description' => ['nullable', 'string'],
            'logo_source' => ['required', 'string', Rule::in(array_map(static fn (OrganizationLogoSource $s) => $s->value, OrganizationLogoSource::cases()))],
            'logo_bg_color' => ['required_if:logo_source,generated', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'logo_text_color' => ['required_if:logo_source,generated', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'croppedLogo' => [
                Rule::requiredIf(fn (): bool => $this->logo_source === OrganizationLogoSource::Upload->value
                    && ($previousSource !== OrganizationLogoSource::Upload->value || ! $hasExistingUpload)),
                'nullable',
                'image',
                'max:5120',
                'mimes:jpeg,jpg,png,webp',
            ],
        ]);

        $payload = [
            'name' => $validated['name'],
            'acronym' => filled($validated['acronym'] ?? null) ? $validated['acronym'] : null,
            'description' => RichText::sanitize($validated['description'] ?? null),
            'logo_source' => OrganizationLogoSource::from($validated['logo_source']),
            'logo_bg_color' => $validated['logo_bg_color'] ?? $this->logo_bg_color,
            'logo_text_color' => $validated['logo_text_color'] ?? $this->logo_text_color,
        ];

        if ($this->modalMode === 'create') {
            $organization = Organization::create($payload);
            session()->flash('status', __('Organization created.'));
        } else {
            $organization = Organization::query()->findOrFail($this->editingOrganizationId);
            $this->authorizeCreatedBy($organization);
            $organization->update($payload);
            session()->flash('status', __('Organization updated.'));
        }

        $this->applyOrganizationLogoFromForm($organization);
        $this->closeModal();
    }

    public function deleteOrganization(int $id): void
    {
        $organization = Organization::query()->findOrFail($id);
        $this->authorizeCreatedBy($organization);
        app(DeleteUploadedOrganizationLogo::class)($organization);
        $organization->delete();
        session()->flash('status', __('Organization deleted.'));
    }

    public function getLogoPreviewUrlProperty(): ?string
    {
        if ($this->editingOrganizationId === null) {
            return null;
        }

        $organization = Organization::query()->find($this->editingOrganizationId);
        if ($organization === null) {
            return null;
        }

        $source = $organization->logo_source;
        $isUpload = $source === OrganizationLogoSource::Upload
            || (is_string($source) && $source === OrganizationLogoSource::Upload->value);

        if (! $isUpload || ! filled($organization->logo_path)) {
            return null;
        }

        return $organization->logoUrl();
    }

    public function getGeneratedLogoPreviewUrlProperty(): string
    {
        $previewOrganization = new Organization([
            'name' => $this->name !== '' ? $this->name : __('ui.organizations.preview_name'),
            'acronym' => filled($this->acronym) ? $this->acronym : null,
            'logo_bg_color' => $this->logo_bg_color,
            'logo_text_color' => $this->logo_text_color,
        ]);

        return $previewOrganization->generatedLogoUrl();
    }

    /**
     * Mary/TinyMCE init runs before the DaisyUI dialog is fully visible; refresh after paint so
     * the editor works for both create and edit (including existing HTML in description).
     */
    protected function scheduleTinyMceModalRefresh(): void
    {
        $this->js(<<<'JS'
            queueMicrotask(() => {
                window.refreshNerdikOrgModalTinyMCE?.();
                requestAnimationFrame(() => {
                    window.refreshNerdikOrgModalTinyMCE?.();
                    setTimeout(() => window.refreshNerdikOrgModalTinyMCE?.(), 120);
                    setTimeout(() => window.refreshNerdikOrgModalTinyMCE?.(), 320);
                });
            });
        JS);
    }

    protected function resetForm(): void
    {
        $this->modalMode = 'create';
        $this->editingOrganizationId = null;
        $this->name = '';
        $this->acronym = '';
        $this->description = '';
        $this->logo_source = OrganizationLogoSource::Generated->value;
        $this->logo_bg_color = '#1d4ed8';
        $this->logo_text_color = '#ffffff';
        $this->reset('croppedLogo');
        $this->resetErrorBag();
    }

    private function resolvePreviousLogoSourceValue(): string
    {
        if ($this->editingOrganizationId === null) {
            return OrganizationLogoSource::Generated->value;
        }

        $organization = Organization::query()->find($this->editingOrganizationId);
        if ($organization === null) {
            return OrganizationLogoSource::Generated->value;
        }

        $rawSource = $organization->logo_source;

        return $rawSource instanceof OrganizationLogoSource
            ? $rawSource->value
            : (string) ($rawSource ?? OrganizationLogoSource::Generated->value);
    }

    private function hasExistingUploadedLogo(): bool
    {
        if ($this->editingOrganizationId === null) {
            return false;
        }

        $organization = Organization::query()->find($this->editingOrganizationId);
        if ($organization === null) {
            return false;
        }

        $source = $organization->logo_source;
        $isUpload = $source === OrganizationLogoSource::Upload
            || (is_string($source) && $source === OrganizationLogoSource::Upload->value);

        return $isUpload && filled($organization->logo_path)
            && Storage::disk('public')->exists((string) $organization->logo_path);
    }

    private function applyOrganizationLogoFromForm(Organization $organization): void
    {
        $source = $organization->logo_source;

        if ($source === OrganizationLogoSource::Generated) {
            app(DeleteUploadedOrganizationLogo::class)($organization);
            $organization->logo_path = null;
            $organization->save();

            return;
        }

        if ($source === OrganizationLogoSource::Upload && $this->croppedLogo !== null) {
            $path = app(StoreUploadedOrganizationLogo::class)($organization, $this->croppedLogo);
            $organization->logo_path = $path;
            $organization->save();
            $this->reset('croppedLogo');
        }
    }

    public function render()
    {
        $organizations = Organization::query()
            ->where('created_by', auth()->id())
            ->orderBy('name')
            ->get();

        return view('livewire.organizations.organization-index', [
            'organizations' => $organizations,
        ]);
    }
}

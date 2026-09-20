<?php

namespace App\Livewire\Organizations;

use App\Actions\Organizations\DeleteUploadedOrganizationLogo;
use App\Actions\Organizations\StoreUploadedOrganizationLogo;
use App\Enums\OrganizationLogoSource;
use App\Models\Organization;
use App\Support\RichText;
use App\Support\Ui\ManageFormBackUrl;
use App\Traits\AuthorizesOwnership;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\WithFileUploads;

class ManageOrganizationForm extends Component
{
    use AuthorizesOwnership;
    use WithFileUploads;

    public ?int $editingOrganizationId = null;

    public string $name = '';

    public string $acronym = '';

    public string $description = '';

    public string $logo_source = 'generated';

    public string $logo_bg_color = '#1d4ed8';

    public string $logo_text_color = '#ffffff';

    /** @var mixed */
    public $croppedLogo = null;

    /** @var mixed */
    public $sourceImage = null;

    public function mount(?Organization $organization = null): void
    {
        $this->assertCanManageOrganizations();

        if ($organization?->exists) {
            $this->authorizeCreatedBy($organization);
            $this->hydrateFormFromOrganization($organization);
        }

        ManageFormBackUrl::captureFromRequest();
    }

    public function updatedLogoSource(string $value): void
    {
        if ($value !== OrganizationLogoSource::Upload->value) {
            $this->reset('croppedLogo', 'sourceImage');
        }
    }

    public function clearCroppedLogo(): void
    {
        $this->reset('croppedLogo');
    }

    public function clearSourceImage(): void
    {
        $this->reset('sourceImage');
    }

    public function save()
    {
        if ($this->editingOrganizationId === null) {
            $this->assertCanManageOrganizations();
        }

        $previousSource = $this->resolvePreviousLogoSourceValue();
        $hasExistingUpload = $this->hasExistingUploadedLogo();

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'acronym' => ['nullable', 'string', 'max:5'],
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
            'sourceImage' => [
                'nullable',
                'image',
                'max:12288',
                'mimes:jpeg,jpg,png,webp',
            ],
        ]);

        $payload = [
            'name' => $validated['name'],
            'acronym' => filled($validated['acronym'] ?? null)
                ? Str::upper(trim((string) $validated['acronym']))
                : null,
            'description' => RichText::sanitize($validated['description'] ?? null),
            'logo_source' => OrganizationLogoSource::from($validated['logo_source']),
            'logo_bg_color' => $validated['logo_bg_color'] ?? $this->logo_bg_color,
            'logo_text_color' => $validated['logo_text_color'] ?? $this->logo_text_color,
        ];

        if ($this->editingOrganizationId === null) {
            $organization = Organization::create($payload);
            session()->flash('status', __('Organization created.'));
        } else {
            $organization = Organization::query()->findOrFail($this->editingOrganizationId);
            $this->authorizeCreatedBy($organization);
            $organization->update($payload);
            session()->flash('status', __('Organization updated.'));
        }

        $this->applyOrganizationLogoFromForm($organization);

        return redirect()->route('organizations.index');
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

    public function getCropSourceImageUrlProperty(): ?string
    {
        if ($this->editingOrganizationId === null) {
            return null;
        }

        $organization = Organization::query()->find($this->editingOrganizationId);

        return $organization?->cropSourceImageUrl();
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

    protected function hydrateFormFromOrganization(Organization $organization): void
    {
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

        $this->reset('croppedLogo', 'sourceImage');
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
            $path = app(StoreUploadedOrganizationLogo::class)($organization, $this->croppedLogo, $this->sourceImage);
            $organization->logo_path = $path;
            $organization->save();
            $this->reset('croppedLogo', 'sourceImage');
        }
    }

    protected function assertCanManageOrganizations(): void
    {
        abort_unless(auth()->user()?->canCreateEvents(), 403, __('ui.organizations.only_event_organizers_can_manage'));
    }

    public function render()
    {
        $editingOrganization = $this->editingOrganizationId !== null
            ? Organization::query()->find($this->editingOrganizationId)
            : null;

        return view('livewire.organizations.manage-organization-form', [
            'editingOrganization' => $editingOrganization,
            'backUrl' => ManageFormBackUrl::resolve(route('organizations.index')),
            'cancelUrl' => route('organizations.index'),
            'submitLabel' => $this->editingOrganizationId !== null
                ? __('ui.common.update')
                : __('ui.common.create'),
            'creator' => $editingOrganization?->creator,
        ]);
    }
}

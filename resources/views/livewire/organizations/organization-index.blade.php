<div class="py-12 p-1">
    <div class="mx-auto max-w-3xl sm:px-6 lg:px-8">
        @if (session('status'))
            <div role="status" class="alert alert-success mb-4 text-sm">
                {{ session('status') }}
            </div>
        @endif

        <div class="mb-6 flex justify-end">
            <x-button
                :link="route('organizations.create')"
                class="btn-primary btn-circle shadow-sm touch-manipulation"
                :title="__('ui.organizations.add')"
                :aria-label="__('ui.organizations.add')"
            >
                <svg class="h-6 w-6 shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                </svg>
            </x-button>
        </div>

        <ul class="space-y-3" role="list">
            @forelse ($organizations as $organization)
                <li
                    wire:key="org-{{ $organization->id }}"
                    class="group flex items-center gap-3 rounded-lg border border-base-300 bg-base-100 p-4 shadow-sm transition-all duration-200 hover:-translate-y-0.5 hover:border-primary/60 hover:shadow-lg hover:shadow-primary/15 motion-reduce:hover:translate-y-0"
                >
                    <button
                        type="button"
                        wire:click="openOrganizationPreview({{ (int) $organization->id }})"
                        class="flex min-w-0 flex-1 cursor-pointer items-center gap-3 rounded-lg text-left transition hover:bg-base-200/40 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary/40"
                        data-ui="organization-index-open-preview"
                    >
                        <div class="avatar shrink-0">
                            <div class="h-10 w-10 overflow-hidden rounded-full border border-base-300 bg-base-300">
                                <img
                                    src="{{ $organization->logoUrl() }}"
                                    alt="{{ $organization->name }}"
                                    class="h-full w-full object-cover"
                                    loading="lazy"
                                />
                            </div>
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="font-medium text-base-content">{{ $organization->name }}</p>
                            @if (filled(rich_text_excerpt($organization->description)))
                                <div class="rich-text-content mt-2 text-base-content/80">
                                    {!! rich_text($organization->description) !!}
                                </div>
                            @endif
                        </div>
                    </button>
                    @canModifyEntity($organization)
                    <div class="flex shrink-0 items-center gap-1">
                        <livewire:user-requests.invite-user-request
                            type="organization_invite"
                            :subject-id="$organization->id"
                            data-ui="organization-invite-user"
                            :key="'invite-org-'.$organization->id"
                        />
                        <x-button
                            :link="route('organizations.edit', $organization)"
                            class="btn-ghost btn-square btn-sm text-base-content/80 hover:text-primary"
                            :title="__('ui.common.edit')"
                            :aria-label="__('ui.common.edit').': '.$organization->name"
                        >
                            <x-ui.icons.pencil class="h-5 w-5 shrink-0" />
                        </x-button>
                        <x-button
                            type="button"
                            class="btn-ghost btn-square btn-sm text-base-content/80 hover:text-error"
                            wire:click="deleteOrganization({{ $organization->id }})"
                            wire:confirm="{{ __('ui.organizations.delete_confirm') }}"
                            :title="__('ui.common.delete')"
                            :aria-label="__('ui.common.delete').': '.$organization->name"
                        >
                            <x-ui.icons.trash class="h-5 w-5 shrink-0" />
                        </x-button>
                    </div>
                    @endcanModifyEntity
                </li>
            @empty
                <li class="rounded-lg border border-dashed border-base-300 bg-base-100/50 px-4 py-8 text-center text-sm text-base-content/70">
                    {{ __('ui.organizations.empty') }}
                </li>
            @endforelse
        </ul>
    </div>

    @if ($organizationPreviewModalOpen && $previewOrganization !== null)
        @teleport('body')
            <x-modal
                wire:model="organizationPreviewModalOpen"
                :title="__('ui.common.organization')"
                box-class="overflow-x-hidden ui-modal-surface ui-overlay-shell ui-overlay-sheet"
                class="backdrop-blur modal-bottom md:modal-end"
                separator
                data-ui="overlay-sheet"
            >
                <livewire:activities.organization-contact-popover
                    :target-organization-id="$previewOrganization->id"
                    :key="'organization-index-contact-popover-'.$previewOrganization->id"
                />
            </x-modal>
        @endteleport
    @endif
</div>

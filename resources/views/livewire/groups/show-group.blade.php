<div class="space-y-8 py-8" data-ui="group-show">
    <div class="mx-auto w-full max-w-7xl px-4 sm:px-6 lg:px-8">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="min-w-0">
                <h1 class="text-2xl font-semibold text-base-content">{{ $group->name }}</h1>
                @if (filled($group->description))
                    <p class="mt-2 max-w-2xl text-sm text-base-content/70">{{ $group->description }}</p>
                @endif
            </div>
            <div class="flex flex-wrap items-center gap-2">
                @if ($canViewMemberContent && $canManage)
                    <livewire:user-requests.invite-user-request
                        type="group_invite"
                        :subject-id="$group->id"
                        data-ui="group-invite-user"
                        :key="'invite-group-'.$group->id"
                    />
                    <x-button
                        type="button"
                        class="btn-ghost btn-sm text-error"
                        wire:click="deleteGroup"
                        wire:confirm="{{ __('ui.groups.delete_confirm') }}"
                        icon="o-trash"
                        :label="__('ui.common.delete')"
                    />
                @elseif ($canViewMemberContent && ! $isOwner)
                    <x-button
                        type="button"
                        class="btn-ghost btn-sm"
                        wire:click="leave"
                        wire:confirm="{{ __('ui.groups.leave_confirm') }}"
                        :label="__('ui.groups.leave')"
                    />
                @endif
            </div>
        </div>

        @if ($canManage && $canViewMemberContent)
            <div class="mt-6 max-w-xl" data-ui="group-visibility">
                <x-radio
                    wire:model.live="visibility"
                    :label="__('ui.groups.visibility_label')"
                    :options="$visibilityChoices"
                    error-field="visibility"
                />
            </div>
        @endif

        @if (! $canViewMemberContent)
            <div class="ui-content-card mt-8 rounded-2xl p-6 sm:p-8" data-ui="group-public-card">
                <p class="text-sm text-base-content/70">
                    {{ trans_choice('ui.groups.member_count', $group->users_count, ['count' => $group->users_count]) }}
                </p>

                @if ($tagLabels !== [])
                    <ul class="mt-4 flex flex-wrap gap-2" role="list" data-ui="group-public-tags">
                        @foreach ($tagLabels as $label)
                            <li class="badge badge-outline">{{ $label }}</li>
                        @endforeach
                    </ul>
                @endif

                @if ($group->hasLocation())
                    <p class="mt-4 text-sm text-base-content/70" data-ui="group-public-location">
                        {{ $group->locationLine() ?: __('ui.groups.location_untitled') }}
                        · {{ __('ui.groups.location_radius_summary', ['km' => (int) $group->radius_km]) }}
                    </p>
                @endif

                <div class="mt-6">
                    <x-button
                        type="button"
                        class="btn-primary"
                        wire:click="join"
                        spinner="join"
                        :label="__('ui.groups.join')"
                        data-ui="group-join"
                    />
                </div>
            </div>
        @else
            <div class="ui-content-card relative mt-8 min-w-0 rounded-2xl" data-ui="group-show-tabs-shell">
                <x-ui.tabs-with-toolbar
                    wire:model.live.preserve-scroll="tab"
                    label-div-class="flex gap-5 px-3 pt-1"
                    label-class="tab tab-lifted tab-md !px-0 !py-2 pb-2 text-sm font-semibold text-base-content/70 hover:text-base-content"
                    active-class="!text-base-content border-b border-primary text-primary"
                    tabs-class="w-full"
                    data-ui="group-show-tabs"
                >
                    <x-tab
                        name="calendar"
                        :label="__('ui.groups.tab_calendar')"
                        class="px-4 py-5 sm:px-6 sm:py-6"
                        data-ui="group-show-tab-calendar"
                        icon="o-calendar-days"
                    >
                        @if (in_array('calendar', $mountedTabs, true))
                            <livewire:groups.group-availability-calendar
                                :group-id="$group->id"
                                :key="'group-calendar-'.$group->id"
                            />
                        @endif
                    </x-tab>

                    <x-tab
                        name="members"
                        :label="__('ui.groups.tab_members')"
                        class="px-4 py-5 sm:px-6 sm:py-6"
                        data-ui="group-show-tab-members"
                        icon="o-users"
                    >
                        @if (in_array('members', $mountedTabs, true))
                            <livewire:groups.group-members-list
                                :group-id="$group->id"
                                :key="'group-members-'.$group->id"
                            />
                        @endif
                    </x-tab>

                    @if ($canManage)
                        <x-tab
                            name="discover"
                            :label="__('ui.groups.tab_discover')"
                            class="px-4 py-5 sm:px-6 sm:py-6"
                            data-ui="group-show-tab-discover"
                            icon="o-magnifying-glass"
                        >
                            @if (in_array('discover', $mountedTabs, true))
                                <livewire:groups.group-discover-matches
                                    :group-id="$group->id"
                                    :key="'group-discover-'.$group->id"
                                />
                            @endif
                        </x-tab>
                    @endif

                    <x-tab
                        name="location"
                        :label="__('ui.groups.tab_location')"
                        class="px-4 py-5 sm:px-6 sm:py-6"
                        data-ui="group-show-tab-location"
                        icon="o-map-pin"
                    >
                        @if (in_array('location', $mountedTabs, true))
                            <livewire:groups.group-location-form
                                :group-id="$group->id"
                                :key="'group-location-'.$group->id"
                            />
                        @endif
                    </x-tab>

                    <x-tab
                        name="tags"
                        :label="__('ui.groups.tab_tags')"
                        class="px-4 py-5 sm:px-6 sm:py-6"
                        data-ui="group-show-tab-tags"
                        icon="o-tag"
                    >
                        @if (in_array('tags', $mountedTabs, true))
                            <livewire:groups.group-tags-form
                                :group-id="$group->id"
                                :key="'group-tags-'.$group->id"
                            />
                        @endif
                    </x-tab>
                </x-ui.tabs-with-toolbar>
            </div>
        @endif
    </div>
</div>

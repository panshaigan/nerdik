<div class="space-y-8 py-8" data-ui="group-show">
    <div class="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="min-w-0">
                <h1 class="text-2xl font-semibold text-base-content">{{ $group->name }}</h1>
                @if (filled($group->description))
                    <p class="mt-2 max-w-2xl text-sm text-base-content/70">{{ $group->description }}</p>
                @endif
            </div>
            <div class="flex flex-wrap items-center gap-2">
                @if ($canManage)
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
                @elseif (! $isOwner)
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
                        <section data-ui="group-members">
                            <h2 class="text-lg font-medium text-base-content">{{ __('ui.groups.members_title') }}</h2>
                            <ul class="mt-4 space-y-2" role="list">
                                @foreach ($group->users as $member)
                                    <li wire:key="group-member-{{ $member->id }}" class="flex items-center justify-between gap-3 rounded-lg border border-base-300 bg-base-100 px-3 py-2">
                                        <x-user-badge :user="$member" />
                                        @if ($canManage && (int) $member->id !== (int) $group->created_by)
                                            <x-button
                                                type="button"
                                                class="btn-ghost btn-square btn-sm text-error"
                                                icon="o-x-mark"
                                                wire:click="removeMember({{ $member->id }})"
                                                wire:confirm="{{ __('ui.groups.remove_member_confirm') }}"
                                                :tooltip="__('ui.groups.remove_member')"
                                                :aria-label="__('ui.groups.remove_member')"
                                            />
                                        @endif
                                    </li>
                                @endforeach
                            </ul>
                        </section>
                    @endif
                </x-tab>

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
    </div>
</div>

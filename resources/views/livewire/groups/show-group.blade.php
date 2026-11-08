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

        <section class="mt-10">
            <livewire:groups.group-availability-calendar :group-id="$group->id" :key="'group-calendar-'.$group->id" />
        </section>

        <section class="mt-10" data-ui="group-members">
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
    </div>
</div>

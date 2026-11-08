<section data-ui="group-members">
    <h2 class="text-lg font-medium text-base-content">{{ __('ui.groups.members_title') }}</h2>
    <ul class="mt-4 space-y-2" role="list">
        @foreach ($members as $member)
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

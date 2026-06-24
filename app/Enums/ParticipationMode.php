<?php

namespace App\Enums;

enum ParticipationMode: string
{
    case Open = 'open';
    case HostApproval = 'host_approval';
    case Lottery = 'lottery';

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function label(): string
    {
        return match ($this) {
            self::Open => __('ui.activities.participation_mode_open'),
            self::HostApproval => __('ui.activities.participation_mode_host_approval'),
            self::Lottery => __('ui.activities.participation_mode_lottery'),
        };
    }

    public function hint(): string
    {
        return match ($this) {
            self::Open => __('ui.activities.participation_mode_open_hint'),
            self::HostApproval => __('ui.activities.participation_mode_host_approval_hint'),
            self::Lottery => __('ui.activities.participation_mode_lottery_hint'),
        };
    }
}

<?php

namespace App\Enums;

enum LotteryDrawTrigger: string
{
    case EnrollmentWindowEnd = 'enrollment_window_end';
    case Final = 'final';
}

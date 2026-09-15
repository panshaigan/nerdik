<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\UserRequests\UserRequestExpirer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ExpireUserRequestsJob implements ShouldQueue
{
    use Queueable;

    public function handle(UserRequestExpirer $expirer): void
    {
        $expirer->expireDue();
    }
}

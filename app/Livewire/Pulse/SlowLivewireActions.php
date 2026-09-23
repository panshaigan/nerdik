<?php

namespace App\Livewire\Pulse;

use App\Pulse\Recorders\SlowLivewireActions as SlowLivewireActionsRecorder;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\View;
use Laravel\Pulse\Livewire\Card;
use Livewire\Attributes\Lazy;

#[Lazy]
final class SlowLivewireActions extends Card
{
    public function render(): Renderable
    {
        [$actions, $time, $runAt] = $this->remember(
            fn () => $this->aggregate('slow_livewire_action', ['max', 'count'], 'max')
                ->map(function (object $row): object {
                    $label = json_decode($row->key, true, flags: JSON_THROW_ON_ERROR);

                    return (object) [
                        'path' => $label['path'] ?? '/livewire',
                        'component' => $label['component'] ?? 'unknown',
                        'action' => $label['action'] ?? 'update',
                        'slowest' => $row->max,
                        'count' => $row->count,
                    ];
                }),
        );

        return View::make('livewire.pulse.slow-livewire-actions', [
            'actions' => $actions,
            'time' => $time,
            'runAt' => $runAt,
            'threshold' => Config::get('pulse.recorders.'.SlowLivewireActionsRecorder::class.'.threshold', 1000),
        ]);
    }
}

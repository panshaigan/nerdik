<?php

namespace App\Livewire\Pulse;

use Illuminate\Contracts\Support\Renderable;
use Illuminate\Support\Facades\View;
use Laravel\Pulse\Livewire\Card;
use Livewire\Attributes\Lazy;

#[Lazy]
final class PersistencePhases extends Card
{
    public function render(): Renderable
    {
        [$phases, $time, $runAt] = $this->remember(
            fn () => $this->aggregate('persistence_phase', ['avg', 'max', 'count'], 'max')
                ->map(function (object $row): object {
                    [$operation, $phase] = array_pad(explode('|', $row->key, 2), 2, 'unknown');

                    return (object) [
                        'operation' => $operation,
                        'phase' => $phase,
                        'average' => $row->avg,
                        'slowest' => $row->max,
                        'count' => $row->count,
                    ];
                }),
        );

        return View::make('livewire.pulse.persistence-phases', [
            'phases' => $phases,
            'time' => $time,
            'runAt' => $runAt,
        ]);
    }
}

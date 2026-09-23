<?php

namespace App\Pulse\Recorders;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Laravel\Pulse\Concerns\ConfiguresAfterResolving;
use Laravel\Pulse\Pulse;
use Laravel\Pulse\Recorders\Concerns\Sampling;
use Laravel\Pulse\Recorders\Concerns\Thresholds;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final class SlowLivewireActions
{
    use ConfiguresAfterResolving;
    use Sampling;
    use Thresholds;

    public function __construct(private readonly Pulse $pulse) {}

    public function register(callable $record, Application $app): void
    {
        $this->afterResolving(
            $app,
            Kernel::class,
            fn (Kernel $kernel) => $kernel->whenRequestLifecycleIsLongerThan(-1, $record),
        );
    }

    public function record(Carbon $startedAt, Request $request, Response $response): void
    {
        $route = $request->route();
        if (! $route instanceof Route || ! $route->named('*livewire.update') || ! $this->shouldSample()) {
            return;
        }

        $duration = (int) $startedAt->diffInMilliseconds();
        if ($this->underThreshold($duration, $request->path())) {
            return;
        }

        foreach ($this->actionLabels($request) as $label) {
            $this->pulse->record(
                type: 'slow_livewire_action',
                key: json_encode($label, flags: JSON_THROW_ON_ERROR),
                value: $duration,
                timestamp: $startedAt,
            )->max()->count();
        }
    }

    /**
     * @return list<array{path: string, component: string, action: string}>
     */
    private function actionLabels(Request $request): array
    {
        $labels = [];

        foreach ((array) $request->input('components', []) as $component) {
            if (! is_array($component)) {
                continue;
            }

            try {
                $snapshot = json_decode((string) ($component['snapshot'] ?? ''), true, flags: JSON_THROW_ON_ERROR);
            } catch (Throwable) {
                continue;
            }

            $componentName = data_get($snapshot, 'memo.name');
            if (! is_string($componentName) || $componentName === '') {
                continue;
            }

            $path = data_get($snapshot, 'memo.path');
            $path = is_string($path) && $path !== '' ? Str::start($path, '/') : '/livewire';

            $actions = collect($component['calls'] ?? [])
                ->pluck('method')
                ->filter(fn (mixed $method): bool => is_string($method) && $method !== '')
                ->unique()
                ->sort()
                ->values()
                ->all();

            $labels[] = [
                'path' => $path,
                'component' => $componentName,
                'action' => $actions === [] ? 'update' : implode(', ', $actions),
            ];
        }

        return $labels;
    }
}

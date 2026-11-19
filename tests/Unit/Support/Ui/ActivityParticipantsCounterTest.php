<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Ui;

use App\Support\Ui\ActivityParticipantsCounter;
use Illuminate\Support\Facades\Config;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class ActivityParticipantsCounterTest extends TestCase
{
    private ActivityParticipantsCounter $counter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->counter = app(ActivityParticipantsCounter::class);
    }

    #[Test]
    public function should_show_is_always_true_when_max_participants_is_set(): void
    {
        $this->assertTrue($this->counter->shouldShow(10, 0));
        $this->assertTrue($this->counter->shouldShow(1, 0));
    }

    #[Test]
    public function should_show_when_uncapped_only_if_filled_exceeds_threshold(): void
    {
        Config::set('activities.uncapped_participants_counter_threshold', 6);

        $this->assertFalse($this->counter->shouldShow(null, 6));
        $this->assertTrue($this->counter->shouldShow(null, 7));
    }

    #[Test]
    public function label_returns_filled_over_max_when_capped(): void
    {
        $this->assertSame(__('ui.browse.participants_filled_max', [
            'filled' => 3,
            'max' => 10,
        ]), $this->counter->label(10, 3));
    }

    #[Test]
    public function label_returns_filled_only_when_uncapped(): void
    {
        $this->assertSame('7', $this->counter->label(null, 7));
    }
}

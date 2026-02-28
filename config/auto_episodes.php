<?php

declare(strict_types=1);

return [
    'preset_times' => [
        '06:00',
        '09:00',
        '12:00',
        '18:00',
        '21:00',
    ],
    'backfill_preset_counts' => [1, 3, 5, 10],
    'default_per_run_cap' => 5,
    'run_now_cooldown_seconds' => 300,
    'activity_retention_days' => 30,
];

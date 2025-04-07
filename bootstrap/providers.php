<?php

declare(strict_types=1);

use App\Providers\AppServiceProvider;
use App\Providers\PulseServiceProvider;
use App\Providers\TelescopeServiceProvider;

return [
    AppServiceProvider::class,
    PulseServiceProvider::class,
    TelescopeServiceProvider::class,
];

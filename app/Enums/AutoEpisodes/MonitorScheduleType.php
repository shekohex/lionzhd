<?php

declare(strict_types=1);

namespace App\Enums\AutoEpisodes;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
enum MonitorScheduleType: string
{
    case Hourly = 'hourly';
    case Daily = 'daily';
    case Weekly = 'weekly';
}

<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Aria2Config;
use App\Models\XtreamCodesConfig;
use Illuminate\Console\Command;

final class InitializeConfigurations extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'lionz:configure';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Initialize application configurations in the database';

    /**
     * Execute the console command.
     */
    public function handle(
        Aria2Config $aria2Config,
        XtreamCodesConfig $xtreamCodesConfig
    ): int {
        $this->initializeAria2($aria2Config);
        $this->initializeXtreamCodes($xtreamCodesConfig);
        $this->info('All configurations initialized successfully!');

        return Command::SUCCESS;
    }

    /**
     * Initialize Aria2 configuration.
     */
    private function initializeAria2(Aria2Config $model): void
    {
        if ($model->exists) {
            $this->comment('Aria2 configuration already exists.');
        } elseif ($model->save()) {
            $this->info('Aria2 configuration initialized successfully.');
        } else {
            $this->error('Failed to initialize Aria2 configuration.');
        }
    }

    /**
     * Initialize Xtream-Codes configuration.
     */
    private function initializeXtreamCodes(XtreamCodesConfig $model): void
    {
        if ($model->exists) {
            $this->comment('Xtream-Codes configuration already exists.');
        } elseif ($model->save()) {
            $this->info('Xtream-Codes configuration initialized successfully.');
        } else {
            $this->error('Failed to initialize Xtream-Codes configuration.');
        }
    }
}

<?php

namespace App\Console\Commands;

use App\Models\Aria2Config;
use App\Models\HttpClientConfig;
use App\Models\MeilisearchConfig;
use App\Models\XtreamCodeConfig;
use Illuminate\Console\Command;

class InitializeConfigurations extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'lionz:initialize-configurations';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Initialize application configurations in the database';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->initializeMeilisearch();
        $this->initializeAria2();
        $this->initializeHttpClient();
        $this->initializeXtreamCode();
        $this->info('All configurations initialized successfully!');

        return Command::SUCCESS;
    }

    /**
     * Initialize Meilisearch configuration.
     */
    protected function initializeMeilisearch(): void
    {
        $model = MeilisearchConfig::firstOrNew();
        if ($model->save() === true) {
            $this->info('Meilisearch configuration initialized successfully.');
        } else {
            $this->line('Meilisearch configuration already exists.');
        }
    }

    /**
     * Initialize Aria2 configuration.
     */
    protected function initializeAria2(): void
    {

        $model = Aria2Config::firstOrNew();

        if ($model->save() === true) {
            $this->info('Aria2 configuration initialized successfully.');
        } else {
            $this->line('Aria2 configuration already exists.');
        }
    }

    /**
     * Initialize HTTP client configuration.
     */
    protected function initializeHttpClient(): void
    {

        $model = HttpClientConfig::firstOrNew();

        if ($model->save() === true) {
            $this->info('HTTP client configuration initialized successfully.');
        } else {
            $this->line('HTTP client configuration already exists.');
        }
    }

    /**
     * Initialize Xtream-Code configuration.
     */
    protected function initializeXtreamCode(): void
    {

        $model = XtreamCodeConfig::firstOrNew();

        if ($model->save() === true) {
            $this->info('Xtream-Code configuration initialized successfully.');
        } else {
            $this->line('Xtream-Code configuration already exists.');
        }
    }
}

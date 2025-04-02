<?php

declare(strict_types=1);

namespace App\Actions;

use App\Concerns\AsAction;
use App\Http\Integrations\LionzTv\Responses\Episode;
use App\Http\Integrations\LionzTv\Responses\VodInformation;
use App\Models\XtreamCodesConfig;
use League\Uri\Uri;

/**
 * @method static Uri run(VodInformation|Episode $data)
 */
final class CreateXtreamcodesDownloadUrl
{
    use AsAction;

    public function __construct(private readonly XtreamCodesConfig $config) {}

    /**
     * Execute the action.
     */
    public function __invoke(
        VodInformation|Episode $data,
    ): Uri {
        $modelPath = match ($data::class) {
            VodInformation::class => 'movie',
            Episode::class => 'series',
        };
        $download_id = match ($data::class) {
            VodInformation::class => $data->vodId,
            Episode::class => $data->id,
        };

        $containerExtension = match ($data::class) {
            VodInformation::class => $data->movie->containerExtension,
            Episode::class => $data->containerExtension,
        };

        $template = "{$this->config->baseUrl()}/{model_path}/{username}/{password}/{download_id}.{container_extension}";
        $uri = Uri::fromTemplate($template, [
            'username' => $this->config->username,
            'password' => $this->config->password,
            'model_path' => $modelPath,
            'download_id' => $download_id,
            'container_extension' => $containerExtension,
        ]);

        return $uri;
    }
}

<?php

declare(strict_types=1);

namespace App\Actions;

use App\Concerns\AsAction;
use App\Http\Integrations\Aria2\Requests\GetGlobalOptionsRequest;
use App\Http\Integrations\Aria2\Responses\GetGlobalOptionsResponse;
use App\Http\Integrations\LionzTv\Responses\Episode;
use App\Http\Integrations\LionzTv\Responses\SeriesInformation;
use App\Http\Integrations\LionzTv\Responses\VodInformation;
use App\Http\Integrations\LionzTv\XtreamCodesConnector;
use InvalidArgumentException;

/**
 * @method static string run(VodInformation|SeriesInformation $data, Episode|null $episode = null)
 */
final readonly class CreateDownloadDir
{
    use AsAction;

    public function __construct(private XtreamCodesConnector $connector) {}

    /**
     * Execute the action.
     */
    public function __invoke(
        VodInformation|SeriesInformation $data,
        ?Episode $episode = null,
    ): string {
        // sanity check
        throw_if($data instanceof SeriesInformation && ! $episode instanceof Episode,
            new InvalidArgumentException('Episode is required for series information')
        );

        /** @var GetGlobalOptionsResponse */
        $res = $this->connector->send(new GetGlobalOptionsRequest)->dtoOrFail();
        $globalOptions = $res->getOptions();

        $mediaDir = match ($data::class) {
            VodInformation::class => 'movies',
            SeriesInformation::class => 'shows',
        };

        $mediaDirName = match ($data::class) {
            VodInformation::class => $data->movie->name,
            SeriesInformation::class => $data->name,
        };

        $mediaSubDir = match ($data::class) {
            VodInformation::class => '',
            SeriesInformation::class => 'Season '.mb_str_pad((string) $episode->season, 2, '0', STR_PAD_LEFT),
        };

        $mediaFileName = match ($data::class) {
            VodInformation::class => $data->movie->name,
            SeriesInformation::class => $episode->title,
        };

        $containerExtension = match ($data::class) {
            VodInformation::class => $data->movie->containerExtension,
            SeriesInformation::class => $episode->containerExtension,
        };

        $downloadsDir = $globalOptions['dir'] ?? '/aria2/data';
        $template = "{$downloadsDir}/{$mediaDir}/{$mediaDirName}/{$mediaSubDir}/{$mediaFileName}.{$containerExtension}";

        // replace `//` with `/` to avoid double slashes
        $template = preg_replace('/\/\//', '/', $template);

        return $template;
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Integrations\Aria2\Requests;

use App\Http\Integrations\Aria2\Responses\AddTorrentResponse;

/**
 * This is a request class for the addTorrent RPC method.
 *
 * @see https://aria2.github.io/manual/en/html/aria2c.html#aria2.addTorrent
 */
final class AddTorrentRequest extends JsonRpcRequest
{
    /**
     * The response class
     */
    protected ?string $response = AddTorrentResponse::class;

    /**
     * AddTorrentRequest constructor.
     *
     * @param  string  $torrent  Base64 encoded torrent file data
     * @param  array<array-key, string>  $uris  List of URIs used for Web-seeding. For single file torrents, URI can be a complete URL pointing to the resource; if URI ends with /, name in torrent file is added. For multi-file torrents, name and path in torrent are added to form a complete URL.
     * @param  array<string, mixed>  $options  Optional array of options for the download
     */
    public function __construct(
        string $torrent,
        array $uris = [],
        array $options = []
    ) {
        parent::__construct('addTorrent', [$torrent, $uris, $options]);
    }

    /**
     * Get the torrent data.
     */
    public function getTorrent(): string
    {
        return $this->params[0];
    }

    /**
     * Get the URIs.
     *
     * @return array<array-key, string>
     */
    public function getUris(): array
    {
        return $this->params[1];
    }

    /**
     * Get the options.
     *
     * @return array<string, mixed>
     */
    public function getOptions(): array
    {
        return $this->params[2];
    }
}

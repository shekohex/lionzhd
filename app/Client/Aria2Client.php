<?php

declare(strict_types=1);

namespace App\Client;

use App\Exceptions\Aria2\Aria2Exception;
use App\Exceptions\Aria2\AuthenticationException;
use App\Exceptions\Aria2\DownloadException;
use App\Models\Aria2Config;
use App\Rpc\Client;
use App\Rpc\Response;
use Illuminate\Support\Facades\Http;

final readonly class Aria2Client
{
    private Client $rpc;

    public function __construct(private Aria2Config $config)
    {
        $client = Http::withUserAgent('Lionz/0.1.0')
            ->timeout(30)
            ->connectTimeout(30)
            ->acceptJson()
            ->retry(5, throw: false)
            ->baseUrl($this->config->getRpcEndpoint())
            ->withoutVerifying();

        $this->rpc = new Client($client);
    }

    // Download Methods

    /**
     * Add a new download URI to aria2
     *
     * @param  string  $uri  The URI to download
     * @param  array<string,mixed>  $options  Additional options for the download
     * @return string The GID of the newly added download
     *
     * @throws AuthenticationException
     * @throws Aria2Exception
     */
    public function addUri(string $uri, array $options = []): string
    {
        $response = $this->rpc->execute('aria2.addUri', $this->withToken([[$uri], $options]));

        return (string) ($this->handleResponse($response));
    }

    /**
     * Add a torrent file to aria2 by providing the contents as base64
     *
     * @param  string  $torrent  Base64 encoded torrent file contents
     * @param  array<string,mixed>  $options  Additional options for the download
     * @return string The GID of the newly added download
     *
     * @throws AuthenticationException
     * @throws Aria2Exception
     */
    public function addTorrent(string $torrent, array $options = []): string
    {
        $response = $this->rpc->execute('aria2.addTorrent', $this->withToken([$torrent, [], $options]));

        return (string) ($this->handleResponse($response));
    }

    /**
     * Add a metalink file to aria2 by providing the contents as base64
     *
     * @param  string  $metalink  Base64 encoded metalink file contents
     * @param  array<string,mixed>  $options  Additional options for the download
     * @return string The GID of the newly added download
     *
     * @throws AuthenticationException
     * @throws Aria2Exception
     */
    public function addMetalink(string $metalink, array $options = []): string
    {
        $response = $this->rpc->execute('aria2.addMetalink', $this->withToken([$metalink, $options]));

        return (string) ($this->handleResponse($response));
    }

    // Control Methods

    /**
     * Remove a download from aria2
     *
     * @param  string  $gid  The GID of the download to remove
     *
     * @throws AuthenticationException
     * @throws DownloadException
     * @throws Aria2Exception
     */
    public function remove(string $gid): void
    {
        $response = $this->rpc->execute('aria2.remove', $this->withToken([$gid]));
        $this->handleResponse($response, $gid);
    }

    /**
     * Pause a download
     *
     * @param  string  $gid  The GID of the download to pause
     *
     * @throws AuthenticationException
     * @throws DownloadException
     * @throws Aria2Exception
     */
    public function pause(string $gid): void
    {
        $response = $this->rpc->execute('aria2.pause', $this->withToken([$gid]));
        $this->handleResponse($response, $gid);
    }

    /**
     * Pause all downloads
     *
     * @throws AuthenticationException
     * @throws Aria2Exception
     */
    public function pauseAll(): void
    {
        $response = $this->rpc->execute('aria2.pauseAll', $this->withToken());
        $this->handleResponse($response);
    }

    /**
     * Resume a paused download
     *
     * @param  string  $gid  The GID of the download to resume
     *
     * @throws AuthenticationException
     * @throws DownloadException
     * @throws Aria2Exception
     */
    public function unpause(string $gid): void
    {
        $response = $this->rpc->execute('aria2.unpause', $this->withToken([$gid]));
        $this->handleResponse($response, $gid);
    }

    /**
     * Resume all paused downloads
     *
     * @throws AuthenticationException
     * @throws Aria2Exception
     */
    public function unpauseAll(): void
    {
        $response = $this->rpc->execute('aria2.unpauseAll', $this->withToken());
        $this->handleResponse($response);
    }

    // Query Methods

    /**
     * Get the status of a download
     *
     * @param  string  $gid  The GID of the download
     * @return array<string,mixed> The status information
     *
     * @throws AuthenticationException
     * @throws DownloadException
     * @throws Aria2Exception
     */
    public function tellStatus(string $gid): array
    {
        $response = $this->rpc->execute('aria2.tellStatus', $this->withToken([$gid]));

        return $this->handleResponse($response, $gid);
    }

    /**
     * Get the URIs used in a download
     *
     * @param  string  $gid  The GID of the download
     * @return array<int,array<string,string>> The URI information
     *
     * @throws AuthenticationException
     * @throws DownloadException
     * @throws Aria2Exception
     */
    public function getUris(string $gid): array
    {
        $response = $this->rpc->execute('aria2.getUris', $this->withToken([$gid]));

        return $this->handleResponse($response, $gid);
    }

    /**
     * Get the files in a download
     *
     * @param  string  $gid  The GID of the download
     * @return array<int,array<string,mixed>> The file information
     *
     * @throws AuthenticationException
     * @throws DownloadException
     * @throws Aria2Exception
     */
    public function getFiles(string $gid): array
    {
        $response = $this->rpc->execute('aria2.getFiles', $this->withToken([$gid]));

        return $this->handleResponse($response, $gid);
    }

    /**
     * Get a list of active downloads
     *
     * @return array<int,array<string,mixed>> The active download information
     *
     * @throws AuthenticationException
     * @throws Aria2Exception
     */
    public function tellActive(): array
    {
        $response = $this->rpc->execute('aria2.tellActive', $this->withToken());

        return $this->handleResponse($response);
    }

    /**
     * Get a list of waiting downloads
     *
     * @param  int  $offset  The offset from the waiting list
     * @param  int  $num  The number of downloads to return
     * @return array<int,array<string,mixed>> The waiting download information
     *
     * @throws AuthenticationException
     * @throws Aria2Exception
     */
    public function tellWaiting(int $offset, int $num): array
    {
        $response = $this->rpc->execute('aria2.tellWaiting', $this->withToken([$offset, $num]));

        return $this->handleResponse($response);
    }

    /**
     * Get a list of stopped downloads
     *
     * @param  int  $offset  The offset from the stopped list
     * @param  int  $num  The number of downloads to return
     * @return array<int,array<string,mixed>> The stopped download information
     *
     * @throws AuthenticationException
     * @throws Aria2Exception
     */
    public function tellStopped(int $offset, int $num): array
    {
        $response = $this->rpc->execute('aria2.tellStopped', $this->withToken([$offset, $num]));

        return $this->handleResponse($response);
    }

    // System Methods

    /**
     * Get global statistics
     *
     * @return array<string,mixed> The global statistics
     *
     * @throws AuthenticationException
     * @throws Aria2Exception
     */
    public function getGlobalStat(): array
    {
        $response = $this->rpc->execute('aria2.getGlobalStat', $this->withToken());

        return $this->handleResponse($response);
    }

    /**
     * Get version information
     *
     * @return array<string,mixed> The version information
     *
     * @throws AuthenticationException
     * @throws Aria2Exception
     */
    public function getVersion(): array
    {
        $response = $this->rpc->execute('aria2.getVersion', $this->withToken());

        return $this->handleResponse($response);
    }

    /**
     * Get session information
     *
     * @return array<string,mixed> The session information
     *
     * @throws AuthenticationException
     * @throws Aria2Exception
     */
    public function getSessionInfo(): array
    {
        $response = $this->rpc->execute('aria2.getSessionInfo', $this->withToken());

        return $this->handleResponse($response);
    }

    /**
     * Shutdown aria2
     *
     * @throws AuthenticationException
     * @throws Aria2Exception
     */
    public function shutdown(): void
    {
        $response = $this->rpc->execute('aria2.shutdown', $this->withToken());
        $this->handleResponse($response);
    }

    /**
     * Force shutdown aria2
     *
     * @throws AuthenticationException
     * @throws Aria2Exception
     */
    public function forceShutdown(): void
    {
        $response = $this->rpc->execute('aria2.forceShutdown', $this->withToken());
        $this->handleResponse($response);
    }

    /**
     * Save the current session
     *
     * @throws AuthenticationException
     * @throws Aria2Exception
     */
    public function saveSession(): void
    {
        $response = $this->rpc->execute('aria2.saveSession', $this->withToken());
        $this->handleResponse($response);
    }

    /**
     * Handle the RPC response and check for errors
     *
     * @param  Response  $response  The RPC response to handle
     * @param  string|null  $gid  The GID of the download if applicable
     * @return mixed The response result
     *
     * @throws AuthenticationException When the RPC token is invalid
     * @throws DownloadException When there's an error with a specific download
     * @throws Aria2Exception When there's a general RPC error
     */
    private function handleResponse(Response $response, ?string $gid = null): mixed
    {
        if ($response->hasError()) {
            $error = $response->error();
            $code = $error['code'] ?? 0;
            $message = $error['message'] ?? 'Unknown error';

            throw_if($code === -32098, new AuthenticationException);

            throw_if($gid !== null, new DownloadException($gid, $message));

            throw new Aria2Exception($message, $code);
        }

        return $response->result();
    }

    /**
     * Add the token to the RPC call parameters
     *
     * @param  array<mixed>  $params  The parameters for the RPC call
     * @return array<mixed> The parameters with the token added
     */
    private function withToken(array $params = []): array
    {
        return array_merge(['token:'.$this->config->secret], $params);
    }
}

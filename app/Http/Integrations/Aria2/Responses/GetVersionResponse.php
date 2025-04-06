<?php

declare(strict_types=1);

namespace App\Http\Integrations\Aria2\Responses;

/**
 * This is a response class for the getVersion RPC method.
 * Returns version of the program and the list of enabled features.
 *
 * @see https://aria2.github.io/manual/en/html/aria2c.html#aria2.getVersion
 */
final class GetVersionResponse extends JsonRpcResponse
{
    /**
     * Get version information.
     *
     * Returns following keys:
     * - version: Version number of aria2
     * - enabledFeatures: List of enabled features. Each feature is given as a string
     *
     * @return array{version: string, enabledFeatures: array<string>}
     */
    public function getVersionInfo(): array
    {
        return $this->result() ?? [];
    }

    /**
     * Get the version number of aria2.
     */
    public function getVersion(): string
    {
        return $this->result()['version'] ?? '';
    }

    /**
     * Get the list of enabled features.
     *
     * @return array<string>
     */
    public function getEnabledFeatures(): array
    {
        return $this->result()['enabledFeatures'] ?? [];
    }
}

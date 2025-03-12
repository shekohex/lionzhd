<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\LoadsFromEnv;
use App\Contracts\Models\EnvConfigurable;
use App\Enums\XtreamCodesAction;
use Illuminate\Database\Eloquent\Model;

/**
 * @mixin IdeHelperXtreamCodesConfig
 */
final class XtreamCodesConfig extends Model implements EnvConfigurable
{
    use LoadsFromEnv;

    protected $hidden = [
        'password',
    ];

    protected $fillable = [
        'host',
        'port',
        'username',
        'password',
    ];

    /**
     * Get XtreamCode configuration.
     */
    public static function first(): ?self
    {
        return self::query()->first();
    }

    public static function fromEnv(): self
    {
        return new self(
            self::envAttributes()
        );
    }

    /**
     * Get the API URL for the XtreamCode API.
     */
    public function getApiUrl(): string
    {
        return "http://{$this->baseUrl()}/player_api.php";
    }

    /**
     * Get the base URL for the XtreamCode API.
     */
    public function baseUrl(): string
    {
        return "{$this->host}:{$this->port}";
    }

    /**
     * Get credentials with action as array.
     *
     * @return array{action: XtreamCodesAction, username: string, password: string}
     */
    public function credentialsWithAction(XtreamCodesAction $action): array
    {
        return array_merge($this->credentials(), ['action' => $action]);
    }

    /**
     * Get credentials as array.
     *
     * @return array{username: string, password: string}
     */
    public function credentials(): array
    {
        return [
            'username' => $this->username,
            'password' => $this->password,
        ];
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'port' => 'integer',
            'password' => 'encrypted',
        ];
    }

    /**
     * Get the attributes that should be retrieved from the environment.
     *
     * @return array<string,string|int>
     */
    private static function envAttributes(): array
    {
        return [
            'host' => config('services.xtream.host'),
            'port' => config('services.xtream.port'),
            'username' => config('services.xtream.username'),
            'password' => config('services.xtream.password'),
        ];
    }
}

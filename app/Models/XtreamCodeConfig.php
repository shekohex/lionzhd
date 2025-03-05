<?php

namespace App\Models;

use App\Enums\XtreamCodesAction;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Class XtreamCodeConfig
 *
 * Represents the configuration and functionality for managing XtreamCode API.
 *
 * @property string host The host URL for the XtreamCode API.
 * @property int port The port number for the XtreamCode API.
 * @property string username The username for the XtreamCode API.
 * @property string password The password for the XtreamCode API.
 */
class XtreamCodeConfig extends Model
{
    /** @use HasFactory<\Database\Factories\XtreamCodeConfigFactory> */
    use HasFactory;

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
        return static::query()->first();
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
     * @return array<string, string>
     */
    public function credentialsWithAction(XtreamCodesAction $action): array
    {
        return array_merge($this->credentials(), ['action' => $action]);
    }

    /**
     * Get credentials as array.
     *
     * @return array<string, string>
     */
    public function credentials(): array
    {
        return [
            'username' => $this->username,
            'password' => $this->password,
        ];
    }
}

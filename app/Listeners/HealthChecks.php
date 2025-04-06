<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Http\Integrations\Aria2\JsonRpcConnector;
use App\Http\Integrations\Aria2\Requests\GetVersionRequest;
use App\Http\Integrations\LionzTv\Requests\GetServerInfoRequest;
use App\Http\Integrations\LionzTv\XtreamCodesConnector;
use Illuminate\Foundation\Events\DiagnosingHealth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\UnauthorizedException;

final readonly class HealthChecks
{
    /**
     * Create the event listener.
     */
    public function __construct(
        private XtreamCodesConnector $xtreamCodesConnector,
        private JsonRpcConnector $jsonRpcConnector
    ) {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(DiagnosingHealth $event): void
    {
        Log::debug('Running health checks for Aria2 and Xtream Codes', ['event' => $event]);
        $req = new GetVersionRequest;
        $this->jsonRpcConnector->send($req)->dtoOrFail();
        $req = new GetServerInfoRequest;
        $res = $this->xtreamCodesConnector->send($req);
        $json = $res->json();
        throw_if(empty($json) || (array_key_exists('user_info', $json) && $json['user_info']['auth'] === 0), new UnauthorizedException);
    }
}

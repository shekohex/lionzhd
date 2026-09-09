<?php

declare(strict_types=1);

namespace App\Http\Controllers\OAuth;

use App\Models\User;
use App\Support\TokenAbilityRegistry;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Laravel\Passport\Http\Controllers\ConvertsPsrResponses;
use Laravel\Passport\Http\Controllers\HandlesOAuthErrors;
use Laravel\Passport\Http\Controllers\RetrievesAuthRequestFromSession;
use League\OAuth2\Server\AuthorizationServer;
use League\OAuth2\Server\Entities\ScopeEntityInterface;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\HttpFoundation\Response;

final class ApproveAuthorizationController
{
    use ConvertsPsrResponses;
    use HandlesOAuthErrors;
    use RetrievesAuthRequestFromSession;

    public function __construct(
        private readonly AuthorizationServer $server,
        private readonly TokenAbilityRegistry $abilities,
    ) {}

    public function __invoke(Request $request, ResponseInterface $psrResponse): Response
    {
        $authRequest = $this->getAuthRequestFromSession($request);
        $requestedScopes = collect($authRequest->getScopes());
        $requestedScopeIds = $requestedScopes
            ->map(static fn (ScopeEntityInterface $scope): string => $scope->getIdentifier())
            ->all();

        $validated = $request->validate([
            'scopes' => ['sometimes', 'array', 'list'],
            'scopes.*' => ['string', Rule::in($requestedScopeIds)],
        ]);

        /** @var list<string> $selectedScopes */
        $selectedScopes = $validated['scopes'] ?? [];
        $selectedScopeIds = collect($selectedScopes)->unique()->values();
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        foreach ($selectedScopeIds as $scope) {
            abort_if(! $this->abilities->canMintAbility($user, $scope, respectCurrentToken: false), 403);
        }

        $authRequest->setScopes($requestedScopes
            ->filter(static fn (ScopeEntityInterface $scope): bool => $selectedScopeIds->contains($scope->getIdentifier()))
            ->values()
            ->all());
        $authRequest->setAuthorizationApproved(true);

        return $this->withErrorHandling(fn () => $this->convertResponse(
            $this->server->completeAuthorizationRequest($authRequest, $psrResponse)
        ), $authRequest->getGrantTypeId() === 'implicit');
    }
}

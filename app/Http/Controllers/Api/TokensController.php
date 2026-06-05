<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreTokenRequest;
use App\Http\Resources\Api\TokenResource;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Resources\JsonApi\AnonymousResourceCollection;
use Laravel\Sanctum\PersonalAccessToken;

final class TokensController extends Controller
{
    public function index(#[CurrentUser] User $user): AnonymousResourceCollection
    {
        /** @var AnonymousResourceCollection $collection */
        $collection = TokenResource::collection(
            $user->tokens()
                ->latest()
                ->get()
        );

        return $collection;
    }

    public function store(StoreTokenRequest $request, #[CurrentUser] User $user): TokenResource
    {
        $newAccessToken = $user->createToken((string) $request->input('name'), $request->abilities());
        $token = $newAccessToken->accessToken;
        $token->setAttribute('plain_text_token', $newAccessToken->plainTextToken);

        return new TokenResource($token);
    }

    public function destroy(#[CurrentUser] User $user, PersonalAccessToken $token): TokenResource
    {
        if ($token->tokenable_type !== $user->getMorphClass() || (int) $token->tokenable_id !== (int) $user->getKey()) {
            throw new HttpResponseException(response()->json([
                'errors' => [[
                    'status' => '404',
                    'title' => 'Not Found',
                    'detail' => 'Not Found',
                ]],
            ], 404)->header('Content-Type', 'application/vnd.api+json'));
        }

        $resource = new TokenResource($token);
        $token->delete();

        return $resource;
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\SyncCategoriesSettingsRequest;
use App\Http\Resources\Api\SettingsResource;
use App\Jobs\SyncCategories;
use App\Models\CategorySyncRun;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\Resources\JsonApi\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

final class SettingsSyncCategoriesController extends Controller
{
    public function show(): SettingsResource
    {
        Gate::authorize('admin');

        return new SettingsResource(['id' => 'sync-categories', 'type' => 'settings', 'attributes' => ['status' => 'available']]);
    }

    public function update(SyncCategoriesSettingsRequest $request, #[CurrentUser] User $user): SettingsResource
    {
        Gate::authorize('admin');

        SyncCategories::dispatch($request->boolean('force_empty_vod'), $request->boolean('force_empty_series'), $user->id);

        return new SettingsResource(['id' => 'sync-categories', 'type' => 'settings-actions', 'attributes' => ['status' => 'queued']]);
    }

    public function index(): AnonymousResourceCollection
    {
        Gate::authorize('admin');

        /** @var AnonymousResourceCollection $collection */
        $collection = SettingsResource::collection(CategorySyncRun::query()->latest('id')->limit(50)->get()->map(static fn (CategorySyncRun $run): array => [
            'id' => (string) $run->id,
            'type' => 'category-sync-runs',
            'attributes' => [
                'status' => $run->status->value,
                'requested_by_user_id' => $run->requested_by_user_id,
                'started_at' => $run->started_at,
                'finished_at' => $run->finished_at,
                'summary' => $run->summary,
                'top_issues' => $run->top_issues,
            ],
        ]));

        return $collection;
    }
}

<?php

declare(strict_types=1);

use App\Enums\CategorySyncRunStatus;
use App\Http\Integrations\LionzTv\Requests\GetSeriesCategoriesRequest;
use App\Http\Integrations\LionzTv\Requests\GetVodCategoriesRequest;
use App\Http\Integrations\LionzTv\XtreamCodesConnector;
use App\Jobs\SyncCategories as SyncCategoriesJob;
use App\Models\CategorySyncRun;
use App\Models\User;
use App\Models\XtreamCodesConfig;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    app()->bind(XtreamCodesConfig::class, static fn () => new XtreamCodesConfig([
        'host' => 'http://test.api',
        'port' => 80,
        'username' => 'test_user',
        'password' => 'test_pass',
    ]));
});

it('requires explicit confirmation when vod preflight is empty and dispatches after force flag', function (): void {
    Queue::fake();

    $admin = User::factory()->admin()->create();

    fakeCategoryPreflight(
        vodPayload: [],
        seriesPayload: [['category_id' => 'series-1', 'category_name' => 'Drama']],
    );

    $blockedResponse = $this->actingAs($admin)->patch(route('synccategories.update'));

    $blockedResponse->assertRedirect();
    $blockedResponse->assertSessionHasErrors(['confirmation', 'forceEmptyVod']);
    Queue::assertNothingPushed();

    fakeCategoryPreflight(
        vodPayload: [],
        seriesPayload: [['category_id' => 'series-1', 'category_name' => 'Drama']],
    );

    $forcedResponse = $this->actingAs($admin)->patch(route('synccategories.update'), [
        'forceEmptyVod' => 1,
    ]);

    $forcedResponse->assertRedirect();
    $forcedResponse->assertSessionHas('success', 'Category sync queued successfully.');
    Queue::assertPushed(SyncCategoriesJob::class, function (SyncCategoriesJob $job) use ($admin): bool {
        return $job->forceEmptyVod === true
            && $job->forceEmptySeries === false
            && $job->requestedByUserId === $admin->id;
    });
    Queue::assertPushed(SyncCategoriesJob::class, 1);
});

it('requires explicit confirmation when series preflight is empty and dispatches after force flag', function (): void {
    Queue::fake();

    $admin = User::factory()->admin()->create();

    fakeCategoryPreflight(
        vodPayload: [['category_id' => 'vod-1', 'category_name' => 'Action']],
        seriesPayload: [],
    );

    $blockedResponse = $this->actingAs($admin)->patch(route('synccategories.update'));

    $blockedResponse->assertRedirect();
    $blockedResponse->assertSessionHasErrors(['confirmation', 'forceEmptySeries']);
    Queue::assertNothingPushed();

    fakeCategoryPreflight(
        vodPayload: [['category_id' => 'vod-1', 'category_name' => 'Action']],
        seriesPayload: [],
    );

    $forcedResponse = $this->actingAs($admin)->patch(route('synccategories.update'), [
        'forceEmptySeries' => true,
    ]);

    $forcedResponse->assertRedirect();
    $forcedResponse->assertSessionHas('success', 'Category sync queued successfully.');
    Queue::assertPushed(SyncCategoriesJob::class, function (SyncCategoriesJob $job) use ($admin): bool {
        return $job->forceEmptyVod === false
            && $job->forceEmptySeries === true
            && $job->requestedByUserId === $admin->id;
    });
    Queue::assertPushed(SyncCategoriesJob::class, 1);
});

it('dispatches sync immediately when both preflight sources are non-empty', function (): void {
    Queue::fake();

    $admin = User::factory()->admin()->create();

    fakeCategoryPreflight(
        vodPayload: [['category_id' => 'vod-1', 'category_name' => 'Action']],
        seriesPayload: [['category_id' => 'series-1', 'category_name' => 'Drama']],
    );

    $response = $this->actingAs($admin)->patch(route('synccategories.update'));

    $response->assertRedirect();
    $response->assertSessionHas('success', 'Category sync queued successfully.');
    Queue::assertPushed(SyncCategoriesJob::class, function (SyncCategoriesJob $job) use ($admin): bool {
        return $job->forceEmptyVod === false
            && $job->forceEmptySeries === false
            && $job->requestedByUserId === $admin->id;
    });
});

it('renders sync history page with run summaries and issues', function (): void {
    $admin = User::factory()->admin()->create();

    CategorySyncRun::query()->create([
        'requested_by_user_id' => $admin->id,
        'status' => CategorySyncRunStatus::Success,
        'started_at' => now(),
        'finished_at' => now(),
        'summary' => [
            'created' => 2,
            'updated' => 1,
            'removed' => 0,
        ],
        'top_issues' => ['Sample warning'],
    ]);

    $response = $this->actingAs($admin)
        ->withHeaders([
            'X-Inertia' => 'true',
            'X-Requested-With' => 'XMLHttpRequest',
        ])
        ->get(route('synccategories.history'));

    $response->assertOk();
    $response->assertHeader('X-Inertia', 'true');
    $response->assertJsonPath('component', 'settings/synccategories-history');
    $response->assertJsonPath('props.runs.data.0.status', CategorySyncRunStatus::Success->value);
    $response->assertJsonPath('props.runs.data.0.summary.created', 2);
    $response->assertJsonPath('props.runs.data.0.top_issues.0', 'Sample warning');
});

function fakeCategoryPreflight(array $vodPayload, array $seriesPayload, int $vodStatus = 200, int $seriesStatus = 200): void
{
    app()->bind(XtreamCodesConnector::class, function () use ($vodPayload, $seriesPayload, $vodStatus, $seriesStatus): XtreamCodesConnector {
        $connector = new XtreamCodesConnector(app(XtreamCodesConfig::class));

        return $connector->withMockClient(new MockClient([
            GetVodCategoriesRequest::class => MockResponse::make($vodPayload, $vodStatus),
            GetSeriesCategoriesRequest::class => MockResponse::make($seriesPayload, $seriesStatus),
        ]));
    });
}

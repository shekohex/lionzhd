<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\CategorySyncRun;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class CategorySyncRunsController extends Controller
{
    public function index(Request $request): Response
    {
        $runs = CategorySyncRun::query()
            ->with(['requestedBy:id,name'])
            ->orderByDesc('started_at')
            ->orderByDesc('id')
            ->paginate(10)
            ->withQueryString()
            ->through(static fn (CategorySyncRun $run): array => [
                'id' => $run->id,
                'status' => $run->status->value,
                'started_at' => $run->started_at?->toIso8601String(),
                'finished_at' => $run->finished_at?->toIso8601String(),
                'summary' => $run->summary ?? [],
                'top_issues' => array_slice($run->top_issues ?? [], 0, 5),
                'requested_by' => $run->requestedBy === null
                    ? null
                    : [
                        'id' => $run->requestedBy->id,
                        'name' => $run->requestedBy->name,
                    ],
            ]);

        return Inertia::render('settings/synccategories-history', [
            'runs' => $runs,
        ]);
    }
}

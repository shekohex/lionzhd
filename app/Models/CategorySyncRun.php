<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CategorySyncRunStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class CategorySyncRun extends Model
{
    protected $table = 'category_sync_runs';

    protected $fillable = [
        'requested_by_user_id',
        'status',
        'started_at',
        'finished_at',
        'summary',
        'top_issues',
    ];

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }

    protected function casts(): array
    {
        return [
            'status' => CategorySyncRunStatus::class,
            'summary' => 'array',
            'top_issues' => 'array',
            'started_at' => 'immutable_datetime',
            'finished_at' => 'immutable_datetime',
        ];
    }
}

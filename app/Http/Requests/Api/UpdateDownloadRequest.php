<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use App\Enums\MediaDownloadAction;
use Illuminate\Validation\Rule;

final class UpdateDownloadRequest extends ApiRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'action' => ['required', 'string', Rule::enum(MediaDownloadAction::class)],
            'delete_partial' => ['sometimes', 'boolean'],
            'restart_from_zero' => ['sometimes', 'boolean'],
        ];
    }

    public function action(): MediaDownloadAction
    {
        return MediaDownloadAction::from((string) $this->input('action'));
    }

    public function deletePartial(): bool
    {
        return (bool) $this->boolean('delete_partial');
    }

    public function restartFromZero(): bool
    {
        return (bool) $this->boolean('restart_from_zero');
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\BuildsSearchResults;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\SearchRequest;
use App\Http\Resources\Api\SearchResultResource;

final class SearchController extends Controller
{
    use BuildsSearchResults;

    public function store(SearchRequest $request): SearchResultResource
    {
        return $this->searchResults($request);
    }
}

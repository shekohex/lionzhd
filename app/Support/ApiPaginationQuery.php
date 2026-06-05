<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Http\Request;

final class ApiPaginationQuery
{
    /**
     * @return array<string, mixed>
     */
    public static function withoutPageNumber(Request $request): array
    {
        $query = $request->query();

        if (isset($query['page']) && is_array($query['page'])) {
            unset($query['page']['number']);

            if ($query['page'] === []) {
                unset($query['page']);
            }
        }

        return $query;
    }
}

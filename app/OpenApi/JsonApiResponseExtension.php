<?php

declare(strict_types=1);

namespace App\OpenApi;

use Dedoc\Scramble\Support\TypeToSchemaExtensions\JsonApiResourceTypeToSchema;

/**
 * Registers Scramble's native JSON:API resource schema support explicitly for this API.
 */
final class JsonApiResponseExtension extends JsonApiResourceTypeToSchema {}

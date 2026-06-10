<?php

namespace App\Domains\Api\Services;

use Illuminate\Http\Request;

class OpenApiService
{
    public function spec(Request $request): array
    {
        $path = resource_path('openapi/v1.json');
        $spec = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);

        $spec['servers'] = [[
            'url' => $request->getSchemeAndHttpHost().'/api/v1',
            'description' => 'Current workspace',
        ]];

        return $spec;
    }
}

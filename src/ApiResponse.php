<?php

namespace YesWiki\Core;

use Symfony\Component\HttpFoundation\JsonResponse;

class ApiResponse extends JsonResponse
{
    public function __construct($data = null, int $status = 200, array $headers = [], bool $json = false)
    {
        $headers = array_merge([
            'Access-Control-Allow-Origin' => '*',
            'Access-Control-Allow-Credentials' => 'true',
            'Access-Control-Allow-Headers' => 'X-Requested-With, Location, Slug, Accept, Content-Type',
            'Access-Control-Expose-Headers' => 'Location, Slug, Accept, Content-Type',
            'Access-Control-Allow-Methods' => 'POST, GET, OPTIONS, DELETE, PUT, PATCH',
            'Access-Control-Max-Age' => '86400',
        ], $headers);

        parent::__construct($data, $status, $headers, $json);
    }
}

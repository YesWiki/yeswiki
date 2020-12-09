<?php

namespace YesWiki\Core;

use Symfony\Component\HttpFoundation\JsonResponse;

class ApiResponse extends JsonResponse
{
    public function __construct($data = null, int $status = 200, array $headers = [], bool $json = false)
    {
        parent::__construct($data, $status, array_merge($headers, ['Access-Control-Allow-Origin: *']), $json);
    }
}

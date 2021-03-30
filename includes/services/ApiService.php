<?php

namespace YesWiki\Core\Service;

use Doctrine\Common\Annotations\AnnotationReader;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Response;

class ApiService
{
    protected $params;

    public function __construct(ParameterBagInterface $params)
    {
        $this->params = $params;
    }

    public function isAuthorized(array $requestParams = [])
    {
        $acl = $this->loadACL($requestParams);
        $apiKey = $this->getBearerToken();
        return(
            in_array("public", $acl) ||
            (
                $this->params->has('api_allowed_keys') &&
                (
                    (
                        isset($this->params->get('api_allowed_keys')['public']) &&
                        $this->params->get('api_allowed_keys')['public'] === true
                    ) ||
                    in_array($apiKey, $this->params->get('api_allowed_keys'))
                )
            )
        );
    }

    /**
     * Get header Authorization
     * */
    private function getAuthorizationHeader()
    {
        $headers = null;
        if (isset($_SERVER['Authorization'])) {
            $headers = trim($_SERVER["Authorization"]);
        } elseif (isset($_SERVER['HTTP_AUTHORIZATION'])) { //Nginx or fast CGI
            $headers = trim($_SERVER["HTTP_AUTHORIZATION"]);
        } elseif (function_exists('apache_request_headers')) {
            $requestHeaders = apache_request_headers();
            // Server-side fix for bug in old Android versions (a nice side-effect of this fix means we don't care about capitalization for Authorization)
            $requestHeaders = array_combine(array_map('ucwords', array_keys($requestHeaders)), array_values($requestHeaders));
            //print_r($requestHeaders);
            if (isset($requestHeaders['Authorization'])) {
                $headers = trim($requestHeaders['Authorization']);
            }
        }
        return $headers;
    }

    /**
    * get access token from header
    * */
    private function getBearerToken()
    {
        $headers = $this->getAuthorizationHeader();
        // HEADER: Get the access token from the header
        if (!empty($headers)) {
            if (preg_match('/Bearer\s(\S+)/', $headers, $matches)) {
                return $matches[1];
            }
        }
        return null;
    }

    private function loadACL(array $requestParams = []): array
    {
        if (empty($requestParams['_controller'])) {
            return [];
        }
        $reflexionMethod = new \ReflectionMethod($requestParams['_controller']);
        if (!$reflexionMethod) {
            return [];
        }
        $reader = new AnnotationReader();
        $annotation = $reader->getMethodAnnotations($reflexionMethod);
        // If there is no Field annotation
        if (isset($annotation[1]->keywords) && is_array($annotation[1]->keywords)) {
            return $annotation[1]->keywords;
        } else {
            return [];
        }
    }
}

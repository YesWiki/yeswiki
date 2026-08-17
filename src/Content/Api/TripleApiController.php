<?php

namespace YesWiki\Content\Api;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use YesWiki\Core\ApiResponse;
use YesWiki\Core\YesWikiController;
use YesWiki\Identity\Service\AclService;
use YesWiki\Identity\Service\AuthenticationService;
use YesWiki\Kernel\Service\TripleStore;

class TripleApiController extends YesWikiController
{
    #[Route('/api/triples', methods: ['GET'], options: ['acl' => ['+']])]
    public function ByResource()
    {
        ['property' => $property, 'username' => $username, 'apiResponse' => $apiResponse]
            = $this->extractTriplesParams(INPUT_GET, 'not empty');
        if (!empty($apiResponse)) {
            return $apiResponse;
        }
        $value = empty($username) ? null : "%\\\"user\\\":\\\"{$username}\\\"%";
        $triples = $this->getService(TripleStore::class)->getMatching(
            null,
            $property,
            $value,
            '=',
            '=',
            'LIKE'
        );

        return new ApiResponse(
            $triples,
            Response::HTTP_OK
        );
    }

    #[Route('/api/triples/{resource}', methods: ['GET'], options: ['acl' => ['+']])]
    public function getTriplesByResource($resource)
    {
        ['property' => $property, 'username' => $username, 'apiResponse' => $apiResponse]
            = $this->extractTriplesParams(INPUT_GET, $resource);
        if (!empty($apiResponse)) {
            return $apiResponse;
        }
        $value = empty($username) ? null : "%\\\"user\\\":\\\"{$username}\\\"%";
        $triples = $this->getService(TripleStore::class)->getMatching(
            $resource,
            $property,
            $value,
            '=',
            '=',
            'LIKE'
        );

        return new ApiResponse(
            $triples,
            Response::HTTP_OK
        );
    }

    #[Route('/api/triples/{resource}', methods: ['POST'], options: ['acl' => ['+']])]
    public function setTriple($resource)
    {
        ['property' => $property, 'username' => $username, 'apiResponse' => $apiResponse]
            = $this->extractTriplesParams(INPUT_POST, $resource);
        if (!empty($apiResponse)) {
            return $apiResponse;
        }
        if (empty($property)) {
            return new ApiResponse(
                ['error' => 'Property should not be empty !'],
                Response::HTTP_BAD_REQUEST
            );
        }
        if (empty($username)) {
            $username = $this->getService(AuthenticationService::class)->getLoggedUser()['name'];
        }
        // `InputBag::get()` refuses an array default -- and returns null for an array
        // parameter. `all()` is the accessor for one.
        $value = $this->getRequest()->request->all()['value'] ?? [];
        if (is_array($value)) {
            $rawValue = array_filter($value, function ($elem) {
                return is_scalar($elem);
            });
        } elseif (is_scalar($value)) {
            $rawValue = [
                'value' => $value,
            ];
        } else {
            $rawValue = [];
        }
        $rawValue['user'] = $username;
        $rawValue['date'] = date('Y-m-d H:i:s');
        $value = (string)json_encode($rawValue);
        $result = $this->getService(TripleStore::class)->create(
            $resource,
            $property,
            $value,
            '',
            ''
        );

        return new ApiResponse(
            ['result' => $result],
            in_array($result, [0, 3]) ? Response::HTTP_OK : Response::HTTP_INTERNAL_SERVER_ERROR
        );
    }

    #[Route('/api/triples/{resource}/delete', methods: ['POST'], options: ['acl' => ['+']])]
    public function deleteTriples($resource)
    {
        ['property' => $property, 'username' => $username, 'apiResponse' => $apiResponse]
            = $this->extractTriplesParams(INPUT_POST, $resource);
        if (!empty($apiResponse)) {
            return $apiResponse;
        }

        if (empty($property)) {
            return new ApiResponse(
                ['error' => 'Property should not be empty !'],
                Response::HTTP_BAD_REQUEST
            );
        }
        $rawFilters = $this->getRequest()->request->all()['filters'] ?? [];
        if (is_array($rawFilters)) {
            $rawFilters = array_filter($rawFilters, function ($elem) {
                return is_scalar($elem);
            });
        } else {
            $rawFilters = [];
        }
        if (!empty($username)) {
            $rawFilters['user'] = $username;
        }

        $triples = [];
        if (!empty($rawFilters)) {
            foreach ($rawFilters as $key => $rawValue) {
                $value = empty($rawValue) ? null : "%\\\"{$key}\\\":\\\"{$rawValue}\\\"%";
                $newTriples = $this->getService(TripleStore::class)->getMatching(
                    $resource,
                    $property,
                    $value,
                    '=',
                    '=',
                    'LIKE'
                );
                if (!empty($newTriples)) {
                    $newTriples = array_filter($newTriples, function ($triple) use ($triples) {
                        $sameTriples = array_filter($triples, function ($registeredTriple) use ($triple) {
                            return $registeredTriple['resource'] == $triple['resource']
                                && $registeredTriple['property'] == $triple['property']
                                && $registeredTriple['value'] == $triple['value'];
                        });

                        return empty($sameTriples);
                    });
                    foreach ($newTriples as $triple) {
                        $triples[] = $triple;
                    }
                }
            }
        }

        $allOk = true;
        $notDeletedTriples = [];
        foreach ($triples as $triple) {
            if ($this->getService(TripleStore::class)->delete(
                $triple['resource'],
                $triple['property'],
                $triple['value'],
                '',
                ''
            ) === false) {
                $allOk = false;
                $notDeletedTriples[] = $triple;
            }
        }
        if ($allOk) {
            return new ApiResponse(
                $triples,
                Response::HTTP_OK
            );
        }

        return new ApiResponse(
            [
                'triples' => $triples,
                'notDeletedTriples' => $notDeletedTriples,
            ],
            Response::HTTP_INTERNAL_SERVER_ERROR
        );
    }

    /**
     * Destructured by its callers rather than `extract()`ed, which is why the shape is declared:
     * `extract()` puts variables into scope that no analyser can see, so every use of
     * `$property` downstream read as possibly-undefined and sat baselined (ticket 40).
     *
     * `$method` is `INPUT_GET` or `INPUT_POST`, which are **ints**. It was declared `string`,
     * so PHP coerced the constant and `$method === INPUT_POST` inside compared `"0"` to `0`
     * with `===`: always false. Every call read the query string, and `POST /api/triples` never
     * saw `property` or `user` sent in the body (ticket 40).
     *
     * @return array{property: string|null, username: string|null, apiResponse: ApiResponse|null}
     */
    private function extractTriplesParams(int $method, $resource): array
    {
        $property = null;
        $username = null;
        $apiResponse = null;
        if (empty($resource)) {
            $apiResponse = new ApiResponse(
                ['error' => 'Resource should not be empty !'],
                Response::HTTP_BAD_REQUEST
            );
        } else {
            $bag = ($method === INPUT_POST) ? $this->getRequest()->request : $this->getRequest()->query;
            $rawProperty = $bag->get('property');
            $property = (empty($rawProperty) || !is_string($rawProperty)) ? '' : htmlspecialchars(strip_tags($rawProperty));
            if (empty($property)) {
                $property = null;
            }

            $rawUsername = $bag->get('user');
            $username = (empty($rawUsername) || !is_string($rawUsername)) ? '' : htmlspecialchars(strip_tags($rawUsername));
            if (empty($username)) {
                if (!$this->getService(AclService::class)->isAdmin()) {
                    $username = $this->getService(AuthenticationService::class)->getLoggedUser()['name'];
                } else {
                    $username = null;
                }
            }
            $currentUser = $this->getService(AuthenticationService::class)->getLoggedUser();
            if (!$this->getService(AclService::class)->isAdmin() && $currentUser['name'] != $username) {
                $apiResponse = new ApiResponse(
                    ['error' => 'Not authorized to access a triple of another user if not admin !'],
                    Response::HTTP_UNAUTHORIZED
                );
            }
        }

        return compact(['property', 'username', 'apiResponse']);
    }
}

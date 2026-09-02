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
    /** The logged-in user's name, or '' for an anonymous visitor. */
    private function loggedUserName(): string
    {
        $user = $this->getService(AuthenticationService::class)->getLoggedUser();

        return is_array($user) ? (string)($user['name'] ?? '') : '';
    }

    #[Route('/api/triples', methods: ['GET'], options: ['acl' => ['+']])]
    public function ByResource(): ApiResponse
    {
        ['property' => $property, 'username' => $username, 'apiResponse' => $apiResponse]
            = $this->extractTriplesParams(INPUT_GET, 'not empty');
        if (!empty($apiResponse)) {
            return $apiResponse;
        }
        $filters = empty($username) ? [] : ['user' => $username];
        $triples = $this->getService(TripleStore::class)->getMatching(
            null,
            $property,
            $this->userLikePattern($username),
            '=',
            '=',
            'LIKE'
        );

        return new ApiResponse(
            $this->triplesMatchingAllFilters($triples, $filters),
            Response::HTTP_OK
        );
    }

    #[Route('/api/triples/{resource}', methods: ['GET'], options: ['acl' => ['+']])]
    public function getTriplesByResource(string $resource): ApiResponse
    {
        ['property' => $property, 'username' => $username, 'apiResponse' => $apiResponse]
            = $this->extractTriplesParams(INPUT_GET, $resource);
        if (!empty($apiResponse)) {
            return $apiResponse;
        }
        $filters = empty($username) ? [] : ['user' => $username];
        $triples = $this->getService(TripleStore::class)->getMatching(
            $resource,
            $property,
            $this->userLikePattern($username),
            '=',
            '=',
            'LIKE'
        );

        return new ApiResponse(
            $this->triplesMatchingAllFilters($triples, $filters),
            Response::HTTP_OK
        );
    }

    #[Route('/api/triples/{resource}', methods: ['POST'], options: ['acl' => ['+']])]
    public function setTriple(string $resource): ApiResponse
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
            $username = $this->loggedUserName();
        }

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
    public function deleteTriples(string $resource): ApiResponse
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

        if (empty($rawFilters)) {
            return new ApiResponse(
                [],
                Response::HTTP_OK
            );
        }

        $triples = $this->triplesMatchingAllFilters(
            $this->getService(TripleStore::class)->getMatching(
                $resource,
                $property,
                $this->userLikePattern($username),
                '=',
                '=',
                'LIKE'
            ),
            $rawFilters
        );

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
     * A cheap sql pre-filter for the triples of one user; what it returns still has to be checked.
     *
     * The name comes from the caller and may hold LIKE wildcards, so this only ever widens the rows read: triplesMatchingAllFilters() is what decides which of them belong to the user.
     */
    private function userLikePattern(?string $username): ?string
    {
        return empty($username) ? null : '%"user":"' . $username . '"%';
    }

    /**
     * The triples whose value holds every one of the given keys with exactly the given value.
     *
     * @param array<int,array<string,string>> $triples
     * @param array<string,scalar>            $filters
     *
     * @return array<int,array<string,string>>
     */
    private function triplesMatchingAllFilters(array $triples, array $filters): array
    {
        if (empty($filters)) {
            return $triples;
        }

        return array_values(array_filter($triples, function ($triple) use ($filters) {
            $value = json_decode($triple['value'] ?? '', true);
            if (!is_array($value)) {
                return false;
            }
            foreach ($filters as $key => $expected) {
                if (!isset($value[$key]) || !is_scalar($value[$key]) || (string)$value[$key] !== (string)$expected) {
                    return false;
                }
            }

            return true;
        }));
    }

    /**
     * Destructured by its callers rather than `extract()`ed, which is why the shape is declared: `extract()` puts variables into scope that no analyser can see, so every use of `$property` downstream read as possibly-undefined and sat baselined (ticket 40).
     *
     * @return array{property: string|null, username: string|null, apiResponse: ApiResponse|null}
     */
    private function extractTriplesParams(int $method, string $resource): array
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
                    $username = $this->loggedUserName();
                } else {
                    $username = null;
                }
            }
            if (!$this->getService(AclService::class)->isAdmin() && $this->loggedUserName() != $username) {
                $apiResponse = new ApiResponse(
                    ['error' => 'Not authorized to access a triple of another user if not admin !'],
                    Response::HTTP_UNAUTHORIZED
                );
            }
        }

        return compact(['property', 'username', 'apiResponse']);
    }
}

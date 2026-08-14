<?php

namespace YesWiki\Social\Api;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use YesWiki\Core\ApiResponse;
use YesWiki\Core\YesWikiController;
use YesWiki\Identity\Service\AclService;
use YesWiki\Identity\Service\AuthenticationService;
use YesWiki\Social\Service\ReactionManager;

class ReactionApiController extends YesWikiController
{
    #[Route('/api/reactions', methods: ['GET'], options: ['acl' => ['public']])]
    public function getAllReactions()
    {
        return new ApiResponse($this->getService(ReactionManager::class)->getReactions('', []));
    }

    #[Route('/api/reactions/{id}', methods: ['GET'], options: ['acl' => ['public']])]
    public function getReactions($id)
    {
        $id = array_map('trim', explode(',', $id));

        return new ApiResponse($this->getService(ReactionManager::class)->getReactions('', $id));
    }

    #[Route('/api/users/{userId}/reactions', options: ['acl' => ['public']])]
    public function getAllReactionsFromUser($userId)
    {
        return new ApiResponse($this->getService(ReactionManager::class)->getReactions('', [], $userId));
    }

    #[Route('/api/users/{userId}/reactions/{id}', options: ['acl' => ['public']])]
    public function getReactionsFromUser($userId, $id)
    {
        $id = array_map('trim', explode(',', $id));

        return new ApiResponse($this->getService(ReactionManager::class)->getReactions('', $id, $userId));
    }

    // no route: reachable through the canonical POST /api/reactions/.../delete below
    public function deleteReaction($idreaction, $id, $page, $username)
    {
        if ($user = $this->getService(AuthenticationService::class)->getLoggedUser()) {
            if ($username == $user['name'] || $this->getService(AclService::class)->isAdmin()) {
                $reactionManager = $this->getService(ReactionManager::class);
                if ($reactionManager->deleteUserReaction($page, $idreaction, $id, $username)) {
                    return new ApiResponse(
                        [
                            'idReaction' => $idreaction,
                            'id' => $id,
                            'page' => $page,
                            'user' => $username,
                        ],
                        Response::HTTP_OK
                    );
                }

                return new ApiResponse(
                    ['error' => 'reaction not deleted'],
                    Response::HTTP_INTERNAL_SERVER_ERROR
                );
            }

            return new ApiResponse(
                ['error' => 'Seul les admins ou l\'utilisateur concerné peuvent supprimer les réactions.'],
                Response::HTTP_UNAUTHORIZED
            );
        }

        return new ApiResponse(
            ['error' => 'Vous devez être connecté pour supprimer les réactions.'],
            Response::HTTP_UNAUTHORIZED
        );
    }

    #[Route('/api/reactions/{idreaction}/{id}/{page}/{username}/delete', methods: ['POST'], options: ['acl' => ['+']])]
    public function deleteReactionViaPostMethod($idreaction, $id, $page, $username)
    {
        return $this->deleteReaction($idreaction, $id, $page, $username);
    }

    #[Route('/api/reactions', methods: ['POST'], options: ['acl' => ['+']])]
    public function addReactionFromUser()
    {
        $post = $this->getRequest()->request;
        if ($user = $this->getService(AuthenticationService::class)->getLoggedUser()) {
            if ($post->get('username') == $user['name'] || $this->getService(AclService::class)->isAdmin()) {
                $reactionid = $post->get('reactionid');
                $pagetag = $post->get('pagetag');
                $reactionIdValue = $post->get('id');
                if ($reactionid) {
                    if ($pagetag) { // save the reaction
                        // get reactions from user for this page
                        $userReactions = $this->getService(ReactionManager::class)->getReactions($pagetag, [$reactionid], $user['name']);
                        $params = $this->getService(ReactionManager::class)->getActionParameters($pagetag);
                        if (!empty($params[$reactionid])) {
                            // un choix de vote est fait
                            if ($reactionIdValue) {
                                // test if limits wherer put
                                if (!empty($params['maxreaction']) && count($userReactions) >= $params['maxreaction']) {
                                    return new ApiResponse(
                                        ['error' => 'Seulement ' . $params['maxreaction'] . ' réaction(s) possible(s). Vous pouvez désélectionner une de vos réactions pour changer.'],
                                        Response::HTTP_UNAUTHORIZED
                                    );
                                }
                                $reactionValues = [
                                    'userName' => $user['name'],
                                    'reactionId' => $reactionid,
                                    'id' => $reactionIdValue,
                                    'date' => date('Y-m-d H:i:s'),
                                ];
                                $this->getService(ReactionManager::class)->addUserReaction(
                                    $pagetag,
                                    $reactionValues
                                );

                                // hurra, the reaction is saved!
                                return new ApiResponse(
                                    $reactionValues,
                                    Response::HTTP_OK
                                );
                            }

                            return new ApiResponse(
                                ['error' => 'Il faut renseigner une valeur de reaction (id).'],
                                Response::HTTP_BAD_REQUEST
                            );
                        }

                        return new ApiResponse(
                            ['error' => "'" . strval($reactionid) . "' n'est pas une réaction déclarée sur la page '" . strval($pagetag) . "'"],
                            Response::HTTP_INTERNAL_SERVER_ERROR
                        );
                    }

                    return new ApiResponse(
                        ['error' => 'Il faut renseigner une page wiki contenant la réaction.'],
                        Response::HTTP_BAD_REQUEST
                    );
                }

                return new ApiResponse(
                    ['error' => 'Il faut renseigner un id de la réaction.'],
                    Response::HTTP_BAD_REQUEST
                );
            }

            return new ApiResponse(
                ['error' => 'Seul les admins ou l\'utilisateur concerné peuvent réagir.'],
                Response::HTTP_UNAUTHORIZED
            );
        }

        return new ApiResponse(
            json_encode(['error' => 'Vous devez être connecté pour réagir.']),
            Response::HTTP_UNAUTHORIZED
        );
    }
}

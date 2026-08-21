<?php

namespace YesWiki\Identity\Api;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use YesWiki\Core\ApiResponse;
use YesWiki\Core\YesWikiController;
use YesWiki\Identity\Exception\GroupNameAlreadyUsedException;
use YesWiki\Identity\Exception\GroupNameDoesNotExistException;
use YesWiki\Identity\Exception\InvalidGroupNameException;
use YesWiki\Identity\Exception\UserNameDoesNotExistException;
use YesWiki\Identity\Service\GroupOperationsService;
use YesWiki\Kernel\Exception\ExitException;

class GroupApiController extends YesWikiController
{
    #[Route('/api/groups/{group_name}/delete', methods: ['POST'], options: ['acl' => ['@admins']])]
    public function deleteGroup(string $group_name): ApiResponse
    {
        $this->denyAccessUnlessAdmin();
        $groupOperationsService = $this->getService(GroupOperationsService::class);
        try {
            $groupOperationsService->delete($group_name);
            $code = Response::HTTP_OK;
            $result = [
                'deleted' => [$group_name],
            ];
        } catch (GroupNameDoesNotExistException $th) {
            $code = Response::HTTP_NOT_FOUND;
            $result = [
                'name' => [$group_name],
                'error' => str_replace('{currentName}', $group_name, _t('USERSETTINGS_NAME_NOT_FOUND')),
            ];
        } catch (\Throwable $th) {
            $code = Response::HTTP_INTERNAL_SERVER_ERROR;
            $result = [
                'name' => [$group_name],
                'error' => $th->getMessage(),
            ];
        }

        return new ApiResponse($result, $code);
    }

    #[Route('/api/groups', methods: ['POST'], options: ['acl' => ['public']])]
    public function createGroup(): ApiResponse
    {
        $this->denyAccessUnlessAdmin();
        $groupOperationsService = $this->getService(GroupOperationsService::class);

        $post = $this->getRequest()->request;
        $postName = strval($post->get('name', ''));
        if (empty($postName)) {
            $code = Response::HTTP_BAD_REQUEST;
            $result = [
                'name' => '',
                'error' => $postName . 'should not be empty',
            ];
        } else {
            try {
                $group_name = $postName;
                $users = $post->has('users') ? $post->all('users') : [];
                $result = $groupOperationsService->create($group_name, $users);
                $code = Response::HTTP_OK;
            } catch (GroupNameAlreadyUsedException $th) {
                $code = Response::HTTP_UNPROCESSABLE_ENTITY;
                $result = [
                    'name' => $group_name,
                    'error' => str_replace('{currentName}', $group_name, _t('GROUP_NAME_ALREADY_USED')),
                ];
            } catch (InvalidGroupNameException $th) {
                $code = Response::HTTP_UNPROCESSABLE_ENTITY;
                $result = [
                    'name' => $group_name,
                    'error' => $th->getMessage(),
                ];
            } catch (UserNameDoesNotExistException|GroupNameDoesNotExistException $th) {
                $code = Response::HTTP_UNPROCESSABLE_ENTITY;
                $result = [
                    'name' => $group_name,
                    'error' => str_replace('{currentName}', $th->getMessage(), _t('USERSETTINGS_NAME_NOT_FOUND')),
                ];
            } catch (ExitException $th) {
                throw $th;
            } catch (\Exception $th) {
                $code = Response::HTTP_BAD_REQUEST;
                $result = [
                    'name' => $group_name,
                    'error' => $th->getMessage(),
                ];
            } catch (\Throwable $th) {
                $code = Response::HTTP_INTERNAL_SERVER_ERROR;
                $result = [
                    'name' => $group_name,
                    'error' => $th->getMessage(),
                ];
            }
        }

        return new ApiResponse($result, $code);
    }

    #[Route('/api/groups/{group_name}/update', methods: ['POST'], options: ['acl' => ['public']])]
    public function updateGroup(string $group_name): ApiResponse
    {
        $this->denyAccessUnlessAdmin();
        $groupOperationsService = $this->getService(GroupOperationsService::class);

        $post = $this->getRequest()->request;
        try {
            $users = $post->has('users') ? $post->all('users') : [];
            $groupOperationsService->update($group_name, $users);
            $result = null;
            $code = Response::HTTP_OK;
        } catch (InvalidGroupNameException $th) {
            $code = Response::HTTP_UNPROCESSABLE_ENTITY;
            $result = [
                'name' => $group_name,
                'error' => $th->getMessage(),
            ];
        } catch (UserNameDoesNotExistException|GroupNameDoesNotExistException $th) {
            $code = Response::HTTP_UNPROCESSABLE_ENTITY;
            $result = [
                'name' => $group_name,
                'error' => str_replace('{currentName}', $th->getMessage(), _t('USERSETTINGS_NAME_NOT_FOUND')),
            ];
        } catch (ExitException $th) {
            throw $th;
        } catch (\Exception $th) {
            $code = Response::HTTP_BAD_REQUEST;
            $result = [
                'name' => $group_name,
                'error' => $th->getMessage(),
            ];
        } catch (\Throwable $th) {
            $code = Response::HTTP_INTERNAL_SERVER_ERROR;
            $result = [
                'name' => $group_name,
                'error' => $th->getMessage(),
            ];
        }

        return new ApiResponse($result, $code);
    }

    #[Route('/api/groups', methods: ['GET'], options: ['acl' => ['public']])]
    public function getAllGroups(): ApiResponse
    {
        $this->denyAccessUnlessAdmin();
        $groupOperationsService = $this->getService(GroupOperationsService::class);

        return new ApiResponse($groupOperationsService->getAll());
    }

    #[Route('/api/groups/{group_name}', methods: ['GET'], options: ['acl' => ['public']])]
    public function getGroup(string $group_name): ApiResponse
    {
        $this->denyAccessUnlessAdmin();
        $groupOperationsService = $this->getService(GroupOperationsService::class);

        try {
            $result = $groupOperationsService->getMembers($group_name);
            $code = Response::HTTP_OK;
        } catch (GroupNameDoesNotExistException $th) {
            $code = Response::HTTP_NOT_FOUND;
            $result = [
                'name' => $group_name,
                'error' => $th->getMessage(),
            ];
        }

        return new ApiResponse($result, $code);
    }
}

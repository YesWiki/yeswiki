<?php

namespace YesWiki\Core\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Csrf\Exception\TokenNotFoundException;
use YesWiki\Bazar\Controller\EntryController;
use YesWiki\Bazar\Service\EntryManager;
use YesWiki\Core\ApiResponse;
use YesWiki\Core\Exception\DeleteUserException;
use YesWiki\Core\Exception\ExitException;
use YesWiki\Core\Exception\GroupNameAlreadyUsedException;
use YesWiki\Core\Exception\GroupNameDoesNotExistException;
use YesWiki\Core\Exception\InvalidGroupNameException;
use YesWiki\Core\Exception\UserEmailAlreadyUsedException;
use YesWiki\Core\Exception\UserNameAlreadyUsedException;
use YesWiki\Core\Exception\UserNameDoesNotExistException;
use YesWiki\Core\Service\AclService;
use YesWiki\Core\Service\ArchiveService;
use YesWiki\Core\Service\CommentService;
use YesWiki\Core\Service\ConfigurationFileProvider;
use YesWiki\Core\Service\ConfigurationService;
use YesWiki\Core\Service\DbService;
use YesWiki\Core\Service\DiffService;
use YesWiki\Core\Service\DuplicationManager;
use YesWiki\Core\Service\PageManager;
use YesWiki\Core\Service\ReactionManager;
use YesWiki\Core\Service\TripleStore;
use YesWiki\Core\Service\UserManager;
use YesWiki\Core\YesWikiController;

class CiController extends YesWikiController
{
    /**
     * @Route("/api/ci/update_config", methods={"POST"}, options={"acl":{"@admins"}})
     */
    public function updateConfig()
    {
        $configurationService = $this->getService(ConfigurationService::class);
        $config = $configurationService->getConfiguration(ConfigurationFileProvider::getConfigFileFromEnv());
        $config->load();

        $this->updateConfigRecursive($config, $this->wiki->request->toArray());
        $configurationService->write($config);

        return new ApiResponse();
    }

    private function updateConfigRecursive(\ArrayAccess &$currentConfig, array $newConfig)
    {
        foreach ($newConfig as $key => $value) {
            if (is_array($value)) {
                if (!isset($currentConfig[$key]) || !is_array($currentConfig[$key])) {
                    $currentConfig[$key] = [];
                }
                $this->updateConfigRecursive($currentConfig[$key], $value);
            } else {
                $currentConfig[$key] = $value;
            }
        }
    }
}

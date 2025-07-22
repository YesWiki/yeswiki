<?php

namespace YesWiki\Core\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Csrf\Exception\TokenNotFoundException;
use YesWiki\Bazar\Controller\EntryController;
use YesWiki\Bazar\Service\EntryManager;
use YesWiki\Core\ApiResponse;
use YesWiki\Core\Entity\ConfigurationFile;
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

        $this->updateConfigFieldByField($config, $this->wiki->request->toArray());

        $configurationService->write($config);

        return new ApiResponse();
    }

    private function updateConfigFieldByField(ConfigurationFile $configurationFile, array $newConfig)
    {
        foreach ($newConfig as $key => $value) {
            $configurationFile[$key] = $value;
        }
    }
}

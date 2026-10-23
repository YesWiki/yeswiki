<?php

namespace YesWiki\Core\Controller;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use YesWiki\Core\ApiResponse;
use YesWiki\Core\Entity\ConfigurationFile;
use YesWiki\Core\Service\ConfigurationFileProvider;
use YesWiki\Core\Service\ConfigurationService;
use YesWiki\Core\YesWikiController;

class CiController extends YesWikiController
{
    /**
     * Writes config keys for test stacks; exists only where the config sets `ci_api`.
     *
     * @Route("/api/ci/update_config", methods={"POST"}, options={"acl":{"@admins"}})
     */
    public function updateConfig()
    {
        if (!in_array($this->wiki->config['ci_api'] ?? false, [true, 1, '1', 'true'], true)) {
            return new ApiResponse(null, Response::HTTP_NOT_FOUND);
        }
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

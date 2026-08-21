<?php

namespace YesWiki\Admin\Api;

use Symfony\Component\Routing\Attribute\Route;
use YesWiki\Core\ApiResponse;
use YesWiki\Core\YesWikiController;
use YesWiki\Kernel\Entity\ConfigurationFile;
use YesWiki\Kernel\Service\ConfigurationFileProvider;
use YesWiki\Kernel\Service\ConfigurationService;

class CiApiController extends YesWikiController
{
    #[Route('/api/ci/update_config', methods: ['POST'], options: ['acl' => ['@admins']])]
    public function updateConfig(): ApiResponse
    {
        $configurationService = $this->getService(ConfigurationService::class);
        $config = $configurationService->getConfiguration(ConfigurationFileProvider::getConfigFileFromEnv());
        $config->load();

        $this->updateConfigFieldByField($config, $this->getService(\YesWiki\Kernel\Service\CurrentRequest::class)->get()->toArray());

        $configurationService->write($config);

        return new ApiResponse();
    }

    /** @param array<string, mixed> $newConfig */
    private function updateConfigFieldByField(ConfigurationFile $configurationFile, array $newConfig): void
    {
        foreach ($newConfig as $key => $value) {
            $configurationFile[$key] = $value;
        }
    }
}

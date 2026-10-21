<?php

namespace YesWiki\Admin\Api;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use YesWiki\Core\ApiResponse;
use YesWiki\Core\YesWikiController;
use YesWiki\Kernel\Entity\ConfigurationFile;
use YesWiki\Kernel\Service\ConfigurationFileProvider;
use YesWiki\Kernel\Service\ConfigurationService;
use YesWiki\Kernel\Service\RuntimeConfig;

class CiApiController extends YesWikiController
{
    /** Writes config keys for test stacks; exists only where the config sets `ci_api`. */
    #[Route('/api/ci/update_config', methods: ['POST'], options: ['acl' => ['@admins']])]
    public function updateConfig(): ApiResponse
    {
        if (!in_array($this->getService(RuntimeConfig::class)['ci_api'] ?? false, [true, 1, '1', 'true'], true)) {
            return new ApiResponse(null, Response::HTTP_NOT_FOUND);
        }
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

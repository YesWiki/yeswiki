<?php

namespace YesWiki\Test\Admin;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use YesWiki\Admin\Api\CiApiController;
use YesWiki\Kernel\Service\ConfigurationFileProvider;
use YesWiki\Kernel\Service\CurrentRequest;
use YesWiki\Kernel\Service\RuntimeConfig;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/** The config-writing endpoint does not exist on a wiki whose config does not ask for it. */
class CiApiControllerTest extends YesWikiTestCase
{
    public function testWithoutCiApiTheEndpointWritesNothing(): void
    {
        $services = $this->getWiki()->services;
        $config = $services->get(RuntimeConfig::class);
        $previous = $config['ci_api'] ?? null;
        unset($config['ci_api']);
        $configFile = ConfigurationFileProvider::getConfigFileFromEnv();
        $before = is_file($configFile) ? file_get_contents($configFile) : null;
        $previousRequest = $services->get(CurrentRequest::class)->get();

        try {
            $services->get(CurrentRequest::class)->replace(Request::create('/', 'POST', [], [], [], ['CONTENT_TYPE' => 'application/json'], '{"yeswiki_name":"Taken over"}'));
            $controller = new CiApiController();
            $controller->setServices($services);

            $response = $controller->updateConfig();

            $this->assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
            $this->assertSame($before, is_file($configFile) ? file_get_contents($configFile) : null);
        } finally {
            $services->get(CurrentRequest::class)->replace($previousRequest);
            if ($previous !== null) {
                $config['ci_api'] = $previous;
            }
        }
    }
}

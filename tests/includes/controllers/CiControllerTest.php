<?php

namespace YesWiki\Test\Core\Controller;

use Symfony\Component\HttpFoundation\Response;
use YesWiki\Core\Controller\CiController;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/**
 * The config-writing endpoint must not exist on a wiki whose config does not ask for it.
 */
class CiControllerTest extends YesWikiTestCase
{
    public function testWithoutCiApiTheEndpointWritesNothing()
    {
        $wiki = $this->getWiki();
        $previous = $wiki->config['ci_api'] ?? null;
        unset($wiki->config['ci_api']);
        $configFile = 'wakka.config.php';
        $before = is_file($configFile) ? file_get_contents($configFile) : null;

        try {
            $controller = new CiController();
            $controller->setWiki($wiki);
            $wiki->request->initialize([], [], [], [], [], ['REQUEST_METHOD' => 'POST', 'CONTENT_TYPE' => 'application/json'], '{"wakka_name":"Taken over"}');

            $response = $controller->updateConfig();

            $this->assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
            $this->assertSame($before, is_file($configFile) ? file_get_contents($configFile) : null);
        } finally {
            if ($previous !== null) {
                $wiki->config['ci_api'] = $previous;
            }
        }
    }
}

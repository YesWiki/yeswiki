<?php

namespace YesWiki\Test\Kernel;

use PHPUnit\Framework\TestCase;
use YesWiki\Kernel\Service\ConfigurationSecrets;

class ConfigurationSecretsSsoTest extends TestCase
{
    public function testTheSectionIsNotCompiled(): void
    {
        $config = ConfigurationSecrets::withPlaceholders(['sso_providers' => ['a' => ['connection' => ['client_secret' => 'hunter2']]], 'base_url' => 'x']);
        $this->assertArrayNotHasKey('sso_providers', $config);
        $this->assertSame('x', $config['base_url']);
    }
}

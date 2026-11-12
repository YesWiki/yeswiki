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

    /** What the runtime puts back beside the compiled parameters, so a request reads the section from the file it read. */
    public function testTheSectionIsReadAtRuntime(): void
    {
        $section = ['a' => ['type' => 'oidc', 'connection' => ['client_secret' => 'hunter2']]];

        $this->assertSame(['sso_providers' => $section], ConfigurationSecrets::runtimeOnly(['sso_providers' => $section, 'base_url' => 'x', 'db_password' => 'p']));
        $this->assertSame([], ConfigurationSecrets::runtimeOnly(['base_url' => 'x']));
    }
}

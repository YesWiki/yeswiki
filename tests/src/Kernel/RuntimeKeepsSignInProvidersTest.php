<?php

namespace YesWiki\Test\Kernel;

use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use YesWiki\Core\YesWikiLoader;
use YesWiki\Core\YesWikiRuntime;
use YesWiki\Kernel\Service\Journal;
use YesWiki\Kernel\Service\RuntimeConfig;

/** The runtime puts `sso_providers` back beside the compiled parameters. */
class RuntimeKeepsSignInProvidersTest extends TestCase
{
    private const SECTION = ['acme' => ['type' => 'oidc', 'connection' => ['client_secret' => 'hunter2']]];

    #[RunInSeparateProcess]
    public function testTheBootPutsTheSectionBackBesideTheCompiledParameters(): void
    {
        $wiki = self::wiki();
        $wiki->config['sso_providers'] = self::SECTION;

        (new \ReflectionMethod(YesWikiRuntime::class, 'boot'))->invoke($wiki);

        $this->assertSame(self::SECTION, $wiki->services->get(RuntimeConfig::class)->getValue('sso_providers'));
        $this->assertFalse($wiki->services->get(ParameterBagInterface::class)->has('sso_providers'), 'the secrets stay out of the compiled parameters');
    }

    /** In worker mode, a setting saved by another worker lays the stored settings over the parameters again: the providers stay. */
    #[RunInSeparateProcess]
    public function testTheStoredSettingsLeaveTheSectionInPlace(): void
    {
        $wiki = self::wiki();
        $wiki->config['sso_providers'] = self::SECTION;
        (new \ReflectionProperty(YesWikiRuntime::class, 'followedVersion'))->setValue($wiki, 'a version saved since');

        $wiki->followStoredSettings();

        $this->assertSame(self::SECTION, $wiki->services->get(RuntimeConfig::class)->getValue('sso_providers'));
    }

    /** The wiki, its Journal kept off the output: a line a child process prints is a failure to PHPUnit. */
    private static function wiki(): YesWikiRuntime
    {
        require_once 'src/YesWikiLoader.php';
        $wiki = YesWikiLoader::getWiki(true);
        $sink = fopen('php://memory', 'w+b');
        if ($sink !== false) {
            $wiki->services->get(Journal::class)->writeTo($sink);
        }

        return $wiki;
    }
}

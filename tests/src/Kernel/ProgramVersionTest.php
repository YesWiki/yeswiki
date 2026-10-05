<?php

namespace YesWiki\Test\Kernel;

use YesWiki\Kernel\Service\ProgramVersion;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/** The Program's composer.json says which YesWiki it is: the release line always, the version once a build has injected it. */
class ProgramVersionTest extends YesWikiTestCase
{
    private string $directory = '';

    protected function setUp(): void
    {
        parent::setUp();
        $this->directory = sys_get_temp_dir() . '/program-version-' . bin2hex(random_bytes(4));
        mkdir($this->directory);
    }

    protected function tearDown(): void
    {
        @unlink($this->directory . '/composer.json');
        @rmdir($this->directory);
        parent::tearDown();
    }

    public function testAPublishedProgramAnswersTheVersionTheBuildInjected(): void
    {
        $program = $this->programWith(['name' => 'yeswiki/yeswiki', 'version' => '5.2.0', 'extra' => ['yeswiki' => ['release-line' => 'ectoplasme']]]);

        $this->assertSame('ectoplasme', $program->releaseLine());
        $this->assertSame('5.2.0', $program->version());
    }

    public function testACheckoutWithNoVersionIsDev(): void
    {
        $program = $this->programWith(['name' => 'yeswiki/yeswiki', 'extra' => ['yeswiki' => ['release-line' => 'ectoplasme']]]);

        $this->assertSame('ectoplasme', $program->releaseLine());
        $this->assertSame(ProgramVersion::DEV, $program->version());
    }

    public function testAnEmptyVersionOrAnUnreadableManifestIsDev(): void
    {
        $this->assertSame('dev', $this->programWith(['version' => '  '])->version());
        $this->assertSame('dev', (new ProgramVersion('not json'))->version());
        $this->assertSame('', (new ProgramVersion('not json'))->releaseLine());
    }

    public function testThisRepositoryIsAnEctoplasmeCheckout(): void
    {
        $program = new ProgramVersion();

        $this->assertSame('ectoplasme', $program->releaseLine());
        $this->assertSame('dev', $program->version());
    }

    public function testTheServiceTheContainerHandsOutReadsTheProgram(): void
    {
        $program = self::getWiki()->services->get(ProgramVersion::class);

        $this->assertSame('ectoplasme', $program->releaseLine());
        $this->assertSame('dev', $program->version());
    }

    /** @param array<string, mixed> $manifest */
    private function programWith(array $manifest): ProgramVersion
    {
        file_put_contents($this->directory . '/composer.json', json_encode($manifest));

        return new ProgramVersion((string)file_get_contents($this->directory . '/composer.json'));
    }
}

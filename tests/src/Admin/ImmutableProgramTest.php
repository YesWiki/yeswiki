<?php

namespace YesWiki\Test\Admin;

use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\Console\Tester\CommandTester;
use YesWiki\Admin\Command\UpgradeCommand;
use YesWiki\Admin\Service\AutoUpdateService;
use YesWiki\Kernel\Service\Replication;
use YesWiki\Render\Service\TemplateEngine;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/** Replicas 05: a replicated program is built into an image, so nothing installs, updates or removes code once it runs, and the update screen says how instead. */
#[CoversClass(AutoUpdateService::class)]
#[CoversClass(UpgradeCommand::class)]
class ImmutableProgramTest extends YesWikiTestCase
{
    protected function tearDown(): void
    {
        putenv(Replication::ENV);
        parent::tearDown();
    }

    public function testAReplicaRefusesToInstallUpdateOrRemoveBeforeTouchingAnything(): void
    {
        putenv(Replication::ENV . '=1');
        $updates = self::getWiki()->services->get(AutoUpdateService::class);
        foreach ([$updates->upgrade(...), $updates->delete(...)] as $action) {
            $messages = $action('helloworld');
            $this->assertTrue($messages->hasError());
            $this->assertSame(_t('AU_PROGRAM_IMMUTABLE'), $messages->toArray()[0]['text'] ?? null, 'it stops before the repository or a file is reached');
        }
    }

    public function testTheCommandRefusesOnAReplicaAndTheImageBuildRunsItWithoutOne(): void
    {
        putenv(Replication::ENV . '=1');
        $tester = new CommandTester(new UpgradeCommand(self::getWiki()->services));

        $this->assertSame(1, $tester->execute(['packages' => ['helloworld', 'lms']]));
        $this->assertStringContainsString(_t('AU_PROGRAM_IMMUTABLE'), $tester->getDisplay());

        putenv(Replication::ENV);
        $this->assertFalse(self::getWiki()->services->get(AutoUpdateService::class)->isImmutable(), 'an image is built without YESWIKI_REPLICATED');
    }

    public function testTheScreenShowsCommandsWhereTheButtonsWere(): void
    {
        $html = self::getWiki()->services->get(TemplateEngine::class)->render('@core/exts.twig', [
            'exts' => [
                ['name' => 'lms', 'installed' => true, 'updateAvailable' => true, 'localRelease' => '1', 'release' => '2', 'documentation' => '', 'description' => '', 'updateLink' => 'lms', 'deleteLink' => 'delete=lms'],
                ['name' => 'bazar-ical', 'installed' => false, 'updateAvailable' => false, 'localRelease' => '', 'release' => '1', 'documentation' => '', 'description' => '', 'updateLink' => 'bazar-ical', 'deleteLink' => ''],
            ],
            'showSwitch' => true,
            'forbidden' => [],
            'isAdmin' => true,
            'immutable' => true,
            'canTriggerExtensionUpdate' => false,
            'extensionBlockedReason' => '',
            'activeExtensions' => [],
            'activationLines' => ['lms' => 'ACTIVE_EXTENSIONS=\'["lms"]\''],
        ]);

        $this->assertStringNotContainsString('<form', $html, 'no button sends anything');
        $this->assertStringContainsString('./yeswicli upgrade lms', $html);
        $this->assertStringContainsString('./yeswicli upgrade bazar-ical', $html);
        $this->assertStringContainsString('ACTIVE_EXTENSIONS=', $html);
    }
}

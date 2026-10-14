<?php

namespace YesWiki\Test\Content;

use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use YesWiki\Content\Command\ReformatCommand;
use YesWiki\Content\Entity\PageBody;
use YesWiki\Content\Service\PageManager;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/** `content:reformat` fixes a page already in Markdown with a new revision, and writes nothing on a dry run. */
class ReformatCommandTest extends YesWikiTestCase
{
    private const TAG = 'ReformatCommandTestPage';
    private const BEFORE = "{{section class=\"cover\"}}**C'est :**\n- un\n{{end elem=\"section\"}}";
    private const AFTER = "{{section class=\"cover\"}}\n**C'est :**\n- un\n{{end elem=\"section\"}}";

    protected function tearDown(): void
    {
        self::getWiki()->services->get(PageManager::class)->deleteOrphaned(self::TAG);
        parent::tearDown();
    }

    /** @param array<string, mixed> $options */
    private function reformat(array $options): CommandTester
    {
        $application = new Application();
        $application->add(new ReformatCommand(self::getWiki()->services));
        $tester = new CommandTester($application->find('content:reformat'));
        $tester->execute(['command' => 'content:reformat', '--page' => self::TAG] + $options);

        return $tester;
    }

    private function content(): string
    {
        return PageBody::content(self::getWiki()->services->get(PageManager::class)->getOne(self::TAG, null, false, true)['body']);
    }

    public function testAPageIsReformattedAsANewRevision(): void
    {
        $pages = self::getWiki()->services->get(PageManager::class);
        $pages->save(self::TAG, [PageBody::CONTENT => self::BEFORE], '', true);

        $dry = $this->reformat(['--dry-run' => true]);
        $this->assertStringContainsString(self::TAG, $dry->getDisplay());
        $this->assertSame(self::BEFORE, $this->content(), 'a dry run writes nothing');

        $this->assertSame(0, $this->reformat([])->getStatusCode());
        $this->assertSame(self::AFTER, $this->content());
        $this->assertCount(2, $pages->getRevisions(self::TAG), 'the old text stays in the history');

        $this->assertStringContainsString('0 page(s)', $this->reformat([])->getDisplay());
    }
}

<?php

namespace YesWiki\Test\Content;

use Symfony\Component\HttpFoundation\Request;
use YesWiki\Content\Controller\EntryController;
use YesWiki\Content\Service\EntryManager;
use YesWiki\Content\Service\FormManager;
use YesWiki\Content\Service\PageManager;
use YesWiki\Identity\Service\AclService;
use YesWiki\Identity\Service\AuthenticationService;
use YesWiki\Kernel\Exception\ExitException;
use YesWiki\Kernel\Service\CurrentRequest;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/** BotGuard on entry submissions; its fields are never saved. */
class EntryBotGuardTest extends YesWikiTestCase
{
    private const FORM_ID = '999974';

    private EntryManager $entryManager;
    private FormManager $formManager;
    private CurrentRequest $currentRequest;
    private Request $previousRequest;
    /** @var list<string> */
    private array $createdTags = [];

    protected function setUp(): void
    {
        parent::setUp();
        $services = $this->getWiki()->services;
        $this->entryManager = $services->get(EntryManager::class);
        $this->formManager = $services->get(FormManager::class);
        $this->currentRequest = $services->get(CurrentRequest::class);
        $this->previousRequest = $this->currentRequest->get();
        $services->get(AuthenticationService::class)->logout();

        $this->formManager->create([
            'id' => self::FORM_ID,
            'label' => 'Entry bot guard test form',
            'entry_title_template' => '{{bf_titre}}',
            'template' => 'texte***bf_titre***Titre***60***255*** *** ***text***1*** *** *** * *** * *** *** *** ***',
        ]);
    }

    protected function tearDown(): void
    {
        $services = $this->getWiki()->services;
        self::restoreBotGuard($this->getWiki());
        $this->currentRequest->replace($this->previousRequest);
        foreach ($this->createdTags as $tag) {
            if ($this->entryManager->isEntry($tag)) {
                $this->entryManager->delete($tag, true);
            }
            $services->get(PageManager::class)->deleteOrphaned($tag);
            $services->get(AclService::class)->delete($tag);
        }
        $this->createdTags = [];
        $this->formManager->delete(self::FORM_ID);
        parent::tearDown();
    }

    /** @param array<string, string> $data */
    private function submit(array $data, string $tag): string
    {
        $request = new Request([], ['valider' => '1'] + $data);
        $request->setMethod('POST');
        $this->currentRequest->replace($request);
        $this->createdTags[] = $tag;
        try {
            return (string)$this->getWiki()->services->get(EntryController::class)->create(self::FORM_ID);
        } catch (ExitException) {
            return '';
        }
    }

    public function testTheGuardFieldsAreNotSavedInTheEntry(): void
    {
        $guardFields = self::validBotGuardFields($this->getWiki());
        $this->submit(['bf_titre' => 'Entry bot guard saved'] + $guardFields, 'entry-bot-guard-saved');

        $this->assertTrue($this->entryManager->isEntry('entry-bot-guard-saved'));
        $body = $this->entryManager->getUntranslated('entry-bot-guard-saved');
        $this->assertSame([], array_values(array_intersect(array_keys($guardFields), array_keys($body ?? []))));
    }

    public function testARefusedSubmissionKeepsWhatWasTypedAndDoesNotDecideForTheNextOne(): void
    {
        $refused = $this->submit(['bf_titre' => 'Entry bot guard refused'], 'entry-bot-guard-refused');
        $this->submit(['bf_titre' => 'Entry bot guard accepted'] + self::validBotGuardFields($this->getWiki()), 'entry-bot-guard-accepted');

        $this->assertStringContainsString(_t('BOT_GUARD_REFUSED'), html_entity_decode($refused, ENT_QUOTES));
        $this->assertStringContainsString('Entry bot guard refused', $refused, 'the visitor keeps what was typed');
        $this->assertStringContainsString('class="yw-bot-guard-fields"', $refused, 'and gets fresh guard fields to try again with');
        $this->assertFalse($this->entryManager->isEntry('entry-bot-guard-refused'));
        $this->assertTrue($this->entryManager->isEntry('entry-bot-guard-accepted'));
    }

    public function testTheFormCarriesTheGuardOnce(): void
    {
        $this->currentRequest->replace(new Request());
        $html = (string)$this->getWiki()->services->get(EntryController::class)->create(self::FORM_ID);

        $this->assertSame(1, substr_count($html, 'class="yw-bot-guard-fields"'));
        $this->assertMatchesRegularExpression('/<form[^>]*id="bazar-form-' . self::FORM_ID . '".*class="yw-bot-guard-fields".*<\/form>/s', $html);
    }
}

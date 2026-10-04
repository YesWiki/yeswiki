<?php

namespace YesWiki\Test\Content;

use Symfony\Component\HttpFoundation\Request;
use YesWiki\Content\Controller\EntryController;
use YesWiki\Content\Exception\EntryValidationException;
use YesWiki\Content\Service\EntryManager;
use YesWiki\Content\Service\FormManager;
use YesWiki\Kernel\Service\CurrentRequest;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/** A required field the browser hides behind a condition does not block the save, and its value is not kept. */
class ConditionalRequiredFieldTest extends YesWikiTestCase
{
    private const FORM_ID = '999907';

    private EntryManager $entryManager;
    private FormManager $formManager;
    /** @var list<string> */
    private array $tags = [];

    protected function setUp(): void
    {
        $wiki = $this->getWiki();
        $this->formManager = $wiki->services->get(FormManager::class);
        $this->entryManager = $wiki->services->get(EntryManager::class);
        $this->formManager->create([
            'id' => self::FORM_ID,
            'label' => 'Conditional required field test form',
            'entry_title_template' => '{{bf_titre}}',
            'template' => implode("\n", [
                'texte***bf_titre***Titre*** *** *** *** ***text***1*** *** *** * *** * *** *** *** ***',
                'texte***bf_choix***Choix*** *** *** *** ***text***0*** *** *** * *** * *** *** *** ***',
                'conditionschecking***bf_choix==oui*** *** *** *** *** *** *** *** *** *** *** *** *** *** ***',
                'texte***bf_detail***Detail*** *** *** *** ***text***1*** *** *** * *** * *** *** *** ***',
                'labelhtml***</div><!-- Fin de condition-->*** *** ***false*** *** *** *** *** *** *** *** *** *** *** ***',
            ]),
        ]);
    }

    protected function tearDown(): void
    {
        foreach ($this->tags as $tag) {
            $this->entryManager->delete($tag, true);
        }
        $this->formManager->delete(self::FORM_ID);
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    private function create(array $data): array
    {
        $entry = $this->entryManager->create(self::FORM_ID, array_merge(['antispam' => 1], $data));
        $this->tags[] = $entry['tag'];

        return $entry;
    }

    public function testAnEmptyRequiredFieldBehindAFalseConditionIsAccepted(): void
    {
        $entry = $this->create(['bf_titre' => 'Condition fausse', 'bf_choix' => 'non']);

        $this->assertArrayNotHasKey('bf_detail', $entry);
    }

    public function testAValueLeftBehindByAFalseConditionIsNotSaved(): void
    {
        $entry = $this->create(['bf_titre' => 'Valeur residuelle', 'bf_choix' => 'non', 'bf_detail' => 'saisi puis masque']);

        $this->assertArrayNotHasKey('bf_detail', $entry);
    }

    public function testAnEmptyRequiredFieldBehindATrueConditionIsRefused(): void
    {
        $this->expectException(EntryValidationException::class);
        $this->expectExceptionMessage('Detail');

        $this->create(['bf_titre' => 'Condition vraie', 'bf_choix' => 'oui', 'bf_detail' => '']);
    }

    public function testARefusedCreationShowsTheFormWithWhatWasTyped(): void
    {
        $currentRequest = $this->getWiki()->services->get(CurrentRequest::class);
        $before = $currentRequest->get();
        $currentRequest->replace(Request::create('/', 'POST', [
            'antispam' => 1,
            'valider' => 1,
            'bf_titre' => 'Saisie gardee apres refus',
            'bf_choix' => 'oui',
            'bf_detail' => '',
        ]));

        try {
            $html = $this->getWiki()->services->get(EntryController::class)->create(self::FORM_ID);
        } finally {
            $currentRequest->replace($before);
        }

        $this->assertStringContainsString('Detail', $html);
        $this->assertStringContainsString('value="Saisie gardee apres refus"', $html);
    }
}

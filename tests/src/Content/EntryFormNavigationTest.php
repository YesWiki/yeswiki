<?php

namespace YesWiki\Test\Content;

use Symfony\Component\HttpFoundation\Request;
use Tamtamchik\SimpleFlash\Flash;
use YesWiki\Content\Controller\EntryController;
use YesWiki\Content\Service\EntryManager;
use YesWiki\Content\Service\FormManager;
use YesWiki\Content\Service\PageManager;
use YesWiki\Identity\Service\AclService;
use YesWiki\Kernel\Exception\ExitException;
use YesWiki\Kernel\Service\CurrentRequest;
use YesWiki\Kernel\Service\UrlFormatter;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/** Entry form navigation and saved messages. */
class EntryFormNavigationTest extends YesWikiTestCase
{
    private const FORM_ID = '999972';
    private const LINKED_FORM_ID = '999973';

    private EntryController $controller;
    private EntryManager $entryManager;
    private FormManager $formManager;
    private UrlFormatter $urlFormatter;
    private CurrentRequest $currentRequest;
    private Request $previousRequest;
    /** @var array<string, mixed> */
    private array $server;
    /** @var list<string> */
    private array $createdTags = [];

    protected function setUp(): void
    {
        parent::setUp();
        $services = $this->getWiki()->services;
        $this->controller = $services->get(EntryController::class);
        $this->entryManager = $services->get(EntryManager::class);
        $this->formManager = $services->get(FormManager::class);
        $this->urlFormatter = $services->get(UrlFormatter::class);
        $this->currentRequest = $services->get(CurrentRequest::class);
        $this->previousRequest = $this->currentRequest->get();
        $this->server = $_SERVER;

        $_SERVER['HTTP_HOST'] = (string)parse_url($this->urlFormatter->getBaseUrl(), PHP_URL_HOST);
        $_SERVER['HTTPS'] = 'on';
        $_SERVER['REQUEST_URI'] = '/?BazaR&view=saisir&id=' . self::FORM_ID;

        $this->formManager->create([
            'id' => self::LINKED_FORM_ID,
            'label' => 'Entry navigation linked form',
            'entry_title_template' => '{{bf_titre}}',
            'template' => self::fieldLine(['texte', 'bf_titre', 'Titre']),
        ]);
        $this->formManager->create([
            'id' => self::FORM_ID,
            'label' => 'Entry navigation test form',
            'entry_title_template' => '{{bf_titre}}',
            'template' => implode("\n", [
                self::fieldLine(['texte', 'bf_titre', 'Titre']),
                self::fieldLine([0 => 'listefiches', 1 => self::LINKED_FORM_ID, 7 => 'Linked entries']),
            ]),
        ]);

        unset($_SESSION['user']);
        Flash::clear();
    }

    protected function tearDown(): void
    {
        Flash::clear();
        self::restoreBotGuard($this->getWiki());
        $this->currentRequest->replace($this->previousRequest);
        $_SERVER = $this->server;
        $services = $this->getWiki()->services;
        foreach ($this->createdTags as $tag) {
            if ($this->entryManager->isEntry($tag)) {
                $this->entryManager->delete($tag, true);
            }
            $services->get(PageManager::class)->deleteOrphaned($tag);
            $services->get(AclService::class)->delete($tag);
        }
        $this->createdTags = [];
        $this->formManager->delete(self::FORM_ID);
        $this->formManager->delete(self::LINKED_FORM_ID);
        parent::tearDown();
    }

    /**
     * @param array<int, string> $values
     */
    private static function fieldLine(array $values): string
    {
        $slots = array_fill(0, 16, ' ');
        foreach ($values as $index => $value) {
            $slots[$index] = $value;
        }

        return implode('***', $slots) . '***';
    }

    /**
     * @param array<string, string> $query
     * @param array<string, mixed>  $post
     */
    private function request(array $query = [], array $post = [], string $referer = ''): void
    {
        $request = new Request($query, $post, [], [], [], $referer === '' ? [] : ['HTTP_REFERER' => $referer]);
        if ($post !== []) {
            $request->setMethod('POST');
        }
        $this->currentRequest->replace($request);
    }

    private function cancelUrl(string $incomingUrl, ?string $entryId = null): string
    {
        return (new \ReflectionMethod($this->controller, 'getCancelUrl'))->invoke($this->controller, $incomingUrl, $entryId);
    }

    /** @param array<string, mixed> $extra */
    private function submit(string $title, array $extra = []): string
    {
        $this->request([], array_merge(['valider' => 1, 'bf_titre' => $title], self::validBotGuardFields($this->getWiki()), $extra));
        try {
            $this->controller->create(self::FORM_ID);
        } catch (ExitException) {
        }
        $tag = strtolower(str_replace(' ', '-', $title));
        $this->createdTags[] = $tag;

        return $tag;
    }

    public function testTheIncomingUrlComesFirst(): void
    {
        $this->request([], [], $this->urlFormatter->href('', 'SomeList', null, false));

        $this->assertSame('https://example.org/?Somewhere', $this->cancelUrl('https://example.org/?Somewhere'));
    }

    public function testThePageTheVisitorCameFromIsNext(): void
    {
        $referer = $this->urlFormatter->href('', 'SomeList', null, false);
        $this->request([], [], $referer);

        $this->assertSame($referer, $this->cancelUrl(''));
    }

    public function testARefererOutsideTheWikiIsIgnored(): void
    {
        $this->request([], [], 'https://elsewhere.example.org/?Phish');

        $this->assertSame($this->urlFormatter->href('', 'MyEntry', null, false), $this->cancelUrl('', 'MyEntry'));
    }

    public function testTheFormItselfIsNotAPlaceToGoBackTo(): void
    {
        $this->request([], [], 'https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'] . '#tab-2');

        $this->assertSame($this->urlFormatter->href('', 'MyEntry', null, false), $this->cancelUrl('', 'MyEntry'));
    }

    public function testAnIncomingUrlOnTheWikiIsKept(): void
    {
        $url = $this->urlFormatter->href('', 'PagePrincipale', ['course' => 'a', 'module' => 'b'], false);
        $this->request(['incomingurl' => $url]);

        $this->assertSame($url, $this->controller->getIncomingUrl());
    }

    public function testAnIncomingUrlOutsideTheWikiIsIgnored(): void
    {
        $this->request(['incomingurl' => 'https://elsewhere.example.org/?PagePrincipale']);

        $this->assertSame('', $this->controller->getIncomingUrl());
    }

    public function testACreatedEntryLeavesAMessageWithALinkBackToTheForm(): void
    {
        $tag = $this->submit('Saved message entry');

        $this->assertTrue($this->entryManager->isEntry($tag));
        $this->assertTrue(Flash::some('success'));
        $message = Flash::display('success');
        $this->assertStringContainsString(_t('BAZ_FICHE_ENREGISTREE'), $message);
        $this->assertStringContainsString('view=saisir', $message);
        $this->assertStringContainsString('id=' . self::FORM_ID, $message);
    }

    public function testTheFormPostsBackToTheUrlItWasServedFrom(): void
    {
        $this->request();

        $html = $this->controller->create(self::FORM_ID);

        $this->assertStringContainsString('id="bazar-form-' . self::FORM_ID . '"', $html);
        $this->assertStringContainsString('action="' . htmlspecialchars($this->currentRequest->get()->getRequestUri()) . '"', $html);
    }

    public function testALinkedEntriesFieldReachesTheCustomTemplate(): void
    {
        $entry = $this->entryManager->create(self::FORM_ID, ['bf_titre' => 'Custom template entry']);
        $this->createdTags[] = $entry['tag'];

        $values = (new \ReflectionMethod($this->controller, 'getValuesForCustomTemplate'))
            ->invoke($this->controller, $entry, $this->formManager->getOne(self::FORM_ID));

        $this->assertArrayHasKey('listefiches' . self::LINKED_FORM_ID, $values['html']);
        $this->assertArrayHasKey('bf_titre', $values['html']);
    }
}

<?php

namespace YesWiki\Test\Handlers;

use Symfony\Component\HttpFoundation\Request;
use YesWiki\Content\Entity\ContentTypeSchema;
use YesWiki\Content\Entity\PageBody;
use YesWiki\Content\Service\FormManager;
use YesWiki\Content\Service\PageManager;
use YesWiki\Identity\Service\AclService;
use YesWiki\Identity\Service\AuthenticationService;
use YesWiki\Identity\Service\UserManager;
use YesWiki\Kernel\Exception\ExitException;
use YesWiki\Kernel\Service\CurrentRequest;
use YesWiki\Kernel\Service\PageContext;
use YesWiki\Kernel\Service\RequestScope;
use YesWiki\Render\Service\Performer;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/**
 * A form's tag is its own screen (ticket 63): the bare tag lists what the form holds with a way to add to it, and `/edit` opens the designer.
 */
class FormScreenTest extends YesWikiTestCase
{
    private const FORM_LABEL = 'Form screen probe';
    private const FORM_TAG = 'form-screen-probe';
    private const PAGE_TAG = 'FormScreenProbePage';
    private const BAZAR_PAGE_TAG = 'FormScreenProbeBazar';
    private const PAGE_TITLE = 'Form screen probe page';
    private const MARKUP_SENTINEL = 'FormScreenSentinelMarkup';
    private const KEYWORD = 'form-screen-probe-keyword';

    private \YesWiki\Core\YesWikiRuntime $wiki;
    private FormManager $formManager;
    private ?Request $previousRequest = null;
    private string $previousTag = '';
    private string $previousMethod = '';

    /** @var array<string, mixed>|null */
    private ?array $form = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->wiki = $this->getWiki();
        $this->formManager = $this->wiki->services->get(FormManager::class);

        $pageContext = $this->wiki->services->get(PageContext::class);
        $this->previousRequest = $this->wiki->services->get(CurrentRequest::class)->get();
        $this->previousTag = $pageContext->getTag();
        $this->previousMethod = $pageContext->getRawMethod();

        if ($this->formManager->getOne(self::FORM_TAG) === null) {
            $this->formManager->create([
                'label' => self::FORM_LABEL,
                'template' => [
                    ['type' => 'texte', 'name' => 'bf_titre', 'label' => 'Titre', 'sub_type' => 'text', 'required' => '1'],
                ],
                'entry_title_template' => '{{bf_titre}}',
            ]);
        }
        $this->form = $this->formManager->getOne(self::FORM_TAG);

        $this->wiki->services->get(PageManager::class)->save(self::PAGE_TAG, [
            PageBody::TITLE => self::PAGE_TITLE,
            PageBody::CONTENT => self::MARKUP_SENTINEL . ' {{entrylist template="calendar" id="1"}}',
            PageBody::KEYWORDS => [self::KEYWORD],
        ], '', true);

        unset($_SESSION['user']);
    }

    protected function tearDown(): void
    {
        unset($_SESSION['user']);
        if ($this->form !== null) {
            $this->formManager->delete($this->form['id']);
        }
        $this->wiki->services->get(PageManager::class)->deleteOrphaned(self::PAGE_TAG);
        $this->wiki->services->get(PageManager::class)->deleteOrphaned(self::BAZAR_PAGE_TAG);

        if ($this->previousRequest !== null) {
            $this->wiki->services->get(CurrentRequest::class)->replace($this->previousRequest);
        }
        $pageContext = $this->wiki->services->get(PageContext::class);
        $pageContext->setTag($this->previousTag);
        $pageContext->setMethod($this->previousMethod);
        parent::tearDown();
    }

    public function testTheFixtureFormIsAForm(): void
    {
        $this->assertNotNull($this->form, 'the probe form was not created');
        $this->assertSame(self::FORM_LABEL, $this->form['label']);
    }

    public function testTheBareTagListsTheFormAsCardsWithAWayToAdd(): void
    {
        $output = $this->runHandler('show', self::FORM_TAG);

        $this->assertStringContainsString('form-screen__add', $output, 'no "add" button on the form screen');
        $this->assertStringContainsString('yw-list-toolbar', $output, 'no search over the form\'s entries');
        $this->assertStringContainsString('data-template="card.twig"', $output, 'the default display is not cards');
        $this->assertStringContainsString('name="display"', $output, 'no display switch');
        $this->assertStringContainsString('form-screen-display-list', $output, 'the list display is not offered');
        $this->assertStringNotContainsString('form-screen-display-calendar', $output, 'a form with no date field is offered a calendar');
        $this->assertStringNotContainsString('form-screen-display-map', $output, 'a form with no location field is offered a map');
        $this->assertStringNotContainsString('class="form-screen__id"', $output, 'the form id is still shown');
        $this->assertStringNotContainsString('form-screen__edit', $output, 'the header still carries an edit button; the floating one does that');
        $this->assertStringNotContainsString('form-screen__formats-label', $output, 'the formats still carry a label');
        $this->assertStringContainsString('icons.svg#rss', $output, 'the RSS link has no icon');
        $this->assertStringContainsString('yw-export-menu', $output, 'no export menu');
        $this->assertStringContainsString('view=saisir', $output, 'the add button does not open the entry form');
        $this->assertStringNotContainsString('form-builder-container', $output, 'the bare tag opened the designer');
    }

    public function testThePagesFormListsPagesWithoutRunningTheirMarkup(): void
    {
        $pagesForm = $this->formManager->getByContentType(ContentTypeSchema::TYPE_PAGE);
        if ($pagesForm === null) {
            $this->markTestSkipped('this wiki has no Pages form');
        }

        $cards = $this->runHandler('show', $this->tagOf($pagesForm));
        $this->assertStringContainsString(self::PAGE_TITLE, $cards, 'the probe page is missing from the cards');
        $this->assertStringNotContainsString(self::MARKUP_SENTINEL, $cards, 'the cards rendered a page\'s markup');

        $table = $this->runHandler('show', $this->tagOf($pagesForm), ['display' => 'table']);
        $this->assertStringContainsString('data-template="tableau.twig"', $table, 'display=table does not list pages as a table');
        $this->assertStringContainsString(self::PAGE_TITLE, $table, 'the probe page is missing from the table');
        $this->assertStringNotContainsString(self::MARKUP_SENTINEL, $table, 'the table rendered a page\'s markup');
        $this->assertStringContainsString('yw-list-toolbar', $table, 'a nested list took the search box away');
    }

    public function testAFacetOverAListValuedFieldMatchesItsValues(): void
    {
        $pagesForm = $this->formManager->getByContentType(ContentTypeSchema::TYPE_PAGE);
        if ($pagesForm === null) {
            $this->markTestSkipped('this wiki has no Pages form');
        }

        $output = $this->runHandler('show', $this->tagOf($pagesForm), ['facet' => ['keywords' => [self::KEYWORD]]]);

        $this->assertStringContainsString(self::PAGE_TITLE, $output, 'the page carrying the keyword is not in the filtered list');
        $this->assertStringContainsString('facet=' . urlencode('keywords=' . self::KEYWORD), $output, 'the export links do not carry the facet');
        $this->assertStringContainsString('form-screen-display-table', $output, 'the display switch is gone from the filtered list');
    }

    public function testTheDesignerSendsBackToTheList(): void
    {
        $output = $this->runHandler('show', self::FORM_TAG, ['view' => 'formulaire', 'msg' => 'BAZ_FORMULAIRE_MODIFIE']);

        $this->assertStringContainsString(_t('BAZ_FORMULAIRE_MODIFIE'), $output);
        $this->assertStringContainsString('yw-list-toolbar', $output, 'view=formulaire on a form tag showed something other than its list');
        $this->assertStringNotContainsString('forms-cards', $output, 'view=formulaire on a form tag listed every form');
    }

    public function testTheOldSearchViewOverOneFormLandsOnItsScreen(): void
    {
        $this->wiki->services->get(PageManager::class)->save(self::BAZAR_PAGE_TAG, [
            PageBody::TITLE => 'Form screen probe bazar',
            PageBody::CONTENT => '{{bazar}}',
        ], '', true);

        $this->assertNotNull($this->form);
        $output = $this->runHandlerOrRedirect('show', self::BAZAR_PAGE_TAG, ['view' => 'consulter', 'action' => 'recherche', 'id' => (string)$this->form['id']]);

        $this->assertNull($output, 'view=consulter&action=recherche over one form did not redirect to the form\'s own screen');
    }

    public function testEditRefusesASignedOutVisitor(): void
    {
        $this->assertNull($this->runHandlerOrRedirect('edit', self::FORM_TAG), 'a signed-out visitor was not redirected away from the designer');
    }

    public function testEditOpensTheDesignerForAnAdmin(): void
    {
        $this->loginAsAdmin();

        $output = $this->runHandler('edit', self::FORM_TAG);

        $this->assertStringContainsString('id="form-builder-container"', $output, '/edit on a form tag did not open the designer');
        $this->assertStringContainsString('name="label"', $output);
        $this->assertStringContainsString(self::FORM_LABEL, $output, 'the designer opened on the wrong form');
    }

    public function testTheEditButtonNamesWhatItEdits(): void
    {
        $pageScreen = $this->runHandler('show', self::PAGE_TAG);
        $this->assertStringContainsString('aria-label="' . _t('TEMPLATE_EDIT_THIS_PAGE') . '"', $pageScreen, 'a page\'s edit button does not say it edits a page');

        $this->loginAsAdmin();
        $formScreen = $this->runHandler('show', self::FORM_TAG);
        $this->assertStringContainsString('aria-label="' . _t('TEMPLATE_EDIT_THIS_FORM') . '"', $formScreen, 'a form\'s edit button does not say it edits a form');
        $this->assertStringNotContainsString('aria-label="' . _t('TEMPLATE_EDIT_THIS_PAGE') . '"', $formScreen);
    }

    public function testAnEnumFieldBecomesAFacet(): void
    {
        $pagesForm = $this->formManager->getByContentType(ContentTypeSchema::TYPE_PAGE);
        if ($pagesForm === null) {
            $this->markTestSkipped('this wiki has no Pages form');
        }

        $output = $this->runHandler('show', $this->tagOf($pagesForm));

        $this->assertStringContainsString('facette-container', $output, 'the keywords field of the Pages form gave no facet');
        $this->assertStringContainsString('yw-facet-select', $output, 'the facets are not laid out above the list');
    }

    /** @param array<string, mixed> $form */
    private function tagOf(array $form): string
    {
        return (string)($form['tag'] ?? '');
    }

    private function loginAsAdmin(): void
    {
        $aclService = $this->wiki->services->get(AclService::class);
        $admin = current(array_filter(
            $this->wiki->services->get(UserManager::class)->getAll(),
            fn ($user) => $aclService->isAdmin($user['name'])
        ));
        $this->assertNotFalse($admin, 'need an existing admin on this wiki');
        $this->wiki->services->get(AuthenticationService::class)->login($admin);
    }

    /** @param array<string, mixed> $query */
    private function runHandler(string $handler, string $tag, array $query = []): string
    {
        $output = $this->runHandlerOrRedirect($handler, $tag, $query);
        $this->assertNotNull($output, "the {$handler} handler redirected instead of rendering");

        return $output;
    }

    /**
     * @param array<string, mixed> $query
     *
     * @return string|null what the handler printed, or null when it ended the request with a redirect
     */
    private function runHandlerOrRedirect(string $handler, string $tag, array $query = []): ?string
    {
        $this->wiki->services->get(RequestScope::class)->startNewRequest();
        $this->wiki->services->get(CurrentRequest::class)->replace(
            Request::create('/?' . $tag . ($handler === 'show' ? '' : '/' . $handler), 'GET', $query)
        );
        $pageContext = $this->wiki->services->get(PageContext::class);
        $pageContext->setTag($tag);
        $pageContext->setMethod($handler);
        $pageContext->assignPage($this->wiki->services->get(PageManager::class)->getOne($tag));

        try {
            return (string)$this->wiki->services->get(Performer::class)->run($handler, 'handler', []);
        } catch (\Throwable $thrown) {
            $exit = ExitException::in($thrown);
            if ($exit === null) {
                throw $thrown;
            }

            return $exit->getMessage() === '' ? null : $exit->getMessage();
        }
    }
}

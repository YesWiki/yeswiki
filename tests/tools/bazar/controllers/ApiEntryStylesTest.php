<?php

namespace YesWiki\Test\Bazar\Controller;

use YesWiki\Bazar\Controller\ApiController;
use YesWiki\Bazar\Service\EntryManager;
use YesWiki\Bazar\Service\FormManager;
use YesWiki\Core\Controller\AuthController;
use YesWiki\Core\Service\AclService;
use YesWiki\Core\Service\PageManager;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/**
 * An entry rendered through the API carries the stylesheets its custom template asks for.
 */
class ApiEntryStylesTest extends YesWikiTestCase
{
    private $wiki;
    private $entryManager;
    private $formManager;
    private string $formId;
    private string $templateFile;
    private string $styleFile;
    private array $createdTags = [];

    protected function setUp(): void
    {
        $this->wiki = $this->getWiki();
        $GLOBALS['wiki'] = $this->wiki;
        $this->entryManager = $this->wiki->services->get(EntryManager::class);
        $this->formManager = $this->wiki->services->get(FormManager::class);
        $this->wiki->services->get(AuthController::class)->logout();

        $this->formId = $this->formManager->create([
            'bn_label_nature' => 'Api entry styles test form',
            'bn_template' => 'texte***bf_titre***Titre***60***255*** *** ***text***1*** *** *** * *** * *** *** *** ***',
            'bn_condition' => '',
        ]);

        $this->styleFile = 'custom/api-entry-styles-' . $this->formId . '.css';
        $this->templateFile = 'custom/templates/bazar/fiche-' . $this->formId . '.twig';
        $this->assertFileDoesNotExist($this->styleFile);
        $this->assertFileDoesNotExist($this->templateFile);
        if (!is_dir(dirname($this->templateFile))) {
            mkdir(dirname($this->templateFile), 0777, true);
        }
        file_put_contents($this->styleFile, '.api-entry-styles { color: red; }');
        file_put_contents($this->templateFile, "{{ include_css('" . $this->styleFile . "') }}<div class=\"api-entry-styles\">{{ html.bf_titre|raw }}</div>");
        self::forgetTemplateLookups($this->wiki);
    }

    protected function tearDown(): void
    {
        foreach ($this->createdTags as $tag) {
            if ($this->entryManager->isEntry($tag)) {
                $this->entryManager->delete($tag, true);
            }
            $this->wiki->services->get(PageManager::class)->deleteOrphaned($tag);
            $this->wiki->services->get(AclService::class)->delete($tag);
        }
        $this->createdTags = [];
        $this->formManager->delete($this->formId);
        foreach ([$this->templateFile, $this->styleFile] as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        $this->wiki->request->query->remove('fields');
    }

    public function testAnEntryViewCarriesItsTemplateStylesheet()
    {
        $entry = $this->entryManager->create($this->formId, ['antispam' => 1, 'bf_titre' => 'Api entry styles entry']);
        $this->createdTags[] = $entry['id_fiche'];
        $this->wiki->services->get(AclService::class)->save($entry['id_fiche'], 'read', '*');

        $this->wiki->request->query->set('fields', 'html_output');
        $response = $this->wiki->services->get(ApiController::class)->getAllEntries('html', $entry['id_fiche']);
        $html = json_decode($response->getContent(), true)[$entry['id_fiche']]['html_output'] ?? '';

        $this->assertStringContainsString('class="api-entry-styles"', $html);
        $this->assertMatchesRegularExpression('#<link rel="stylesheet" href="[^"]*' . preg_quote($this->styleFile, '#') . '#', $html);
    }
}

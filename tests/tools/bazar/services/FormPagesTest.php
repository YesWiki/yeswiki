<?php

namespace YesWiki\Test\Bazar\Service;

use PHPUnit\Framework\Attributes\DataProvider;
use YesWiki\Bazar\Service\FormManager;
use YesWiki\Bazar\Service\ListManager;
use YesWiki\Core\Service\AclService;
use YesWiki\Core\Service\PageManager;
use YesWiki\Core\Service\TripleStore;
use YesWiki\Test\Core\YesWikiTestCase;
use YesWiki\Wiki;

require_once 'tests/YesWikiTestCase.php';

/**
 * Forms stored as JSON pages: lossless storage, ids kept, translations applied per language and sanitised.
 */
class FormPagesTest extends YesWikiTestCase
{
    private const TITLE_ROW = 'texte***bf_titre***Titre*** *** *** *** ***text***1*** *** *** * *** * *** *** *** ***';

    private static array $formIds = [];
    private static array $lists = [];
    private $wiki;
    private FormManager $forms;
    private $savedLangs;

    protected function setUp(): void
    {
        $this->wiki = $this->getWiki();
        $GLOBALS['wiki'] = $this->wiki;
        $this->forms = $this->wiki->services->get(FormManager::class);
        $this->savedLangs = $this->wiki->config['supported_langs'] ?? null;
        $this->wiki->config['supported_langs'] = ['fr', 'en'];
    }

    protected function tearDown(): void
    {
        if ($this->savedLangs === null) {
            unset($this->wiki->config['supported_langs']);
        } else {
            $this->wiki->config['supported_langs'] = $this->savedLangs;
        }
        $this->wiki->lang = 'fr';
    }

    public static function tearDownAfterClass(): void
    {
        $wiki = self::getWiki();
        $GLOBALS['wiki'] = $wiki;
        foreach (self::$formIds as $id) {
            $wiki->services->get(FormManager::class)->delete($id);
        }
        foreach (self::$lists as $tag) {
            $wiki->services->get(PageManager::class)->deleteOrphaned($tag);
            $wiki->services->get(TripleStore::class)->delete($tag, TripleStore::TYPE_URI, null, '', '');
        }
    }

    public static function templates(): array
    {
        $cases = [];
        foreach (glob('tests/tools/bazar/services/form-templates/*.wiki') as $filename) {
            $cases[basename($filename)] = [file_get_contents($filename)];
        }

        return $cases;
    }

    private function describe(array $form): array
    {
        return array_values(array_map(function ($field) {
            $json = json_decode(json_encode($field), true);
            unset($json['options']);

            return [get_class($field), $json];
        }, $form['prepared']));
    }

    private function create(array $rows, string $title): string
    {
        $id = $this->forms->create(['bn_label_nature' => $title, 'bn_condition' => '', 'bn_description' => 'test', 'bn_template' => implode("\n", $rows)]);
        $this->assertNotFalse($id);
        self::$formIds[] = $id;

        return $id;
    }

    #[DataProvider('templates')]
    public function testAStoredFormRebuildsTheSameFields(string $template): void
    {
        $legacy = $this->forms->getFromRawData(['bn_id_nature' => '999', 'bn_label_nature' => 'Template', 'bn_template' => $template]);
        $body = $this->forms->toPageBody($legacy);
        $rebuilt = $this->forms->getFromRawData(['body' => json_encode($body)]);

        $this->assertNotEmpty($legacy['prepared']);
        $this->assertSame(count($legacy['prepared']), count($body['fields']), 'every field gets its own key');
        $this->assertEquals($this->describe($legacy), $this->describe($rebuilt));
    }

    public function testALegacyRowBecomesAnAdminOnlyPageUnderItsId(): void
    {
        $id = (string)$this->forms->findNewId();
        $row = ['bn_id_nature' => $id, 'bn_label_nature' => 'Imported legacy form', 'bn_description' => 'd', 'bn_condition' => '',
            'bn_sem_template' => '{"@type": "Note"}', 'bn_sem_reverse_template' => '', 'bn_activitypub_enable' => '1',
            'bn_only_one_entry' => 'Y', 'bn_only_one_entry_message' => 'Only one', 'bn_template' => file_get_contents('tests/tools/bazar/services/form-templates/every_field.wiki')];
        $tag = $this->forms->importLegacyForm($row);
        self::$formIds[] = $id;

        $form = $this->forms->getOne($id);
        $this->assertSame($tag, $form['tag']);
        $this->assertSame($id, $form['bn_id_nature']);
        $this->assertSame('{"@type": "Note"}', $form['bn_sem_template']);
        $this->assertSame('1', $form['bn_activitypub_enable']);
        $this->assertSame('Y', $form['bn_only_one_entry']);
        $this->assertSame('Only one', $form['bn_only_one_entry_message']);
        $this->assertEquals($this->describe($this->forms->getFromRawData($row)), $this->describe($form));
        $this->assertSame('@admins', $this->wiki->services->get(AclService::class)->load($tag, 'read', false)['list']);
        $this->assertContains($id, $this->forms->getAllIds());
        $this->assertSame('Imported legacy form', $this->forms->getAllTitles()[$id]);
    }

    public function testDeletingAFormRemovesItsPageAndEntries(): void
    {
        $id = $this->create([self::TITLE_ROW], 'Form to delete');
        $tag = $this->forms->getPageTagFromId($id);
        $this->assertNotNull($tag);

        $this->assertTrue($this->forms->delete($id));

        $this->assertNull($this->forms->getOne($id));
        $this->assertNull($this->forms->getPageTagFromId($id));
        $this->assertEmpty($this->wiki->services->get(PageManager::class)->getOne($tag, null, false, true));
        $this->assertFalse((bool)$this->wiki->services->get(TripleStore::class)->exist($tag, TripleStore::TYPE_URI, FormManager::TRIPLE_FORM_TYPE, '', ''));
    }

    public function testTranslationsApplyPerLanguageAndSurviveAnEdit(): void
    {
        $id = $this->create([self::TITLE_ROW, 'texte***bf_lieu***Lieu*** *** *** *** ***text***0*** *** ***Aide*** * *** *** *** ***'], 'Formulaire traduit');
        $this->assertTrue($this->forms->updateTranslations($id, [
            'en' => ['title' => 'Translated form', 'fields' => ['bf_lieu' => ['label' => 'Place<script>alert(1)</script>', 'name' => 'hijacked'], 'unknown' => ['label' => 'x']], 'sem_template' => 'hijacked'],
            'de' => ['title' => 'Unsupported'],
        ]));

        $english = $this->forms->getOne($id, 'en');
        $this->assertSame('Translated form', $english['bn_label_nature']);
        $place = array_values(array_filter($english['prepared'], fn ($field) => $field->getPropertyName() === 'bf_lieu'))[0];
        $this->assertSame('Place', $place->getLabel());
        $this->assertSame('bf_lieu', $place->getName());
        $this->assertSame('', $english['bn_sem_template']);
        $this->assertSame(['en'], array_keys($english['__translation']));

        $this->assertSame('Formulaire traduit', $this->forms->getOne($id)['bn_label_nature']);
        $this->wiki->lang = 'en';
        $this->forms->forgetCaches();
        $this->assertSame('Translated form', $this->forms->getOne($id)['bn_label_nature']);
        $this->wiki->lang = 'fr';

        $form = $this->forms->getOne($id, 'all');
        $form['bn_label_nature'] = 'Formulaire renommé';
        $this->assertTrue($this->forms->update($form));
        $this->assertSame('Translated form', $this->forms->getOne($id, 'en')['bn_label_nature']);
        $this->assertSame('Formulaire renommé', $this->forms->getOne($id, 'fr')['bn_label_nature']);
    }

    public function testCloneKeepsTheFieldsUnderANewId(): void
    {
        $id = $this->create([self::TITLE_ROW], 'Form to clone');
        $clone = $this->forms->clone($id);
        self::$formIds[] = $clone;

        $this->assertNotSame($id, $clone);
        $this->assertEquals($this->describe($this->forms->getOne($id)), $this->describe($this->forms->getOne($clone)));
    }

    public function testListTranslationsApplyAndSurviveAnEdit(): void
    {
        $lists = $this->wiki->services->get(ListManager::class);
        $tag = $lists->create('Couleurs', [['id' => 'r', 'label' => 'Rouge', 'children' => [['id' => 'rc', 'label' => 'Rouge clair']]], ['id' => 'b', 'label' => 'Bleu']]);
        self::$lists[] = $tag;

        $lists->updateTranslations($tag, ['en' => ['title' => 'Colours', 'nodes' => [['label' => 'Red', 'children' => [['label' => 'Light red', 'id' => 'hijacked']]]]], 'xx' => ['title' => 'no']]);
        $english = $lists->getOne($tag, null, 'en');
        $this->assertSame('Colours', $english['title']);
        $this->assertSame('Red', $english['nodes'][0]['label']);
        $this->assertSame('r', $english['nodes'][0]['id']);
        $this->assertSame('rc', $english['nodes'][0]['children'][0]['id']);
        $this->assertSame('Light red', $english['nodes'][0]['children'][0]['label']);
        $this->assertSame('Bleu', $english['nodes'][1]['label']);

        $lists->update($tag, 'Couleurs vives', [['id' => 'r', 'label' => 'Rouge'], ['id' => 'b', 'label' => 'Bleu']]);
        $this->assertSame('Colours', $lists->getOne($tag, null, 'en')['title']);
        $this->assertSame('Couleurs vives', $lists->getOne($tag, null, 'all')['title']);
    }

    public function testTranslatePageMergesLikeAJsonMergePatch(): void
    {
        $pageManager = $this->wiki->services->get(PageManager::class);
        $page = ['body' => json_encode(['a' => 1, 'b' => ['c' => 2, 'd' => 3], 'e' => 4, '__translation' => ['en' => ['b' => ['c' => 'two'], 'e' => null]]])];

        $this->assertSame(['a' => 1, 'b' => ['c' => 'two', 'd' => 3], '__translation' => ['en' => ['b' => ['c' => 'two'], 'e' => null]]], json_decode($pageManager->translatePage($page, 'en')['body'], true));
        $this->assertSame($page, $pageManager->translatePage($page, 'all'));
        $this->assertSame($page, $pageManager->translatePage($page, 'de'));
        $this->assertSame(['body' => 'not json'], $pageManager->translatePage(['body' => 'not json'], 'en'));
    }

    public function testTheRequestLanguageCannotCarrySql(): void
    {
        $this->assertSame('en', Wiki::sanitizeLang('en', 'fr'));
        $this->assertSame('pt-BR', Wiki::sanitizeLang('pt-BR#anchor', 'fr'));
        $this->assertSame('fr', Wiki::sanitizeLang('en") OR 1=1 -- ', 'fr'));
        $this->assertSame('fr', Wiki::sanitizeLang("en'.", 'fr'));
        $this->assertSame('fr', Wiki::sanitizeLang(null, 'fr'));
    }
}

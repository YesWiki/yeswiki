<?php

namespace YesWiki\Test\Bazar\Service;

use PHPUnit\Framework\Attributes\DataProvider;
use YesWiki\Bazar\Service\FormManager;
use YesWiki\Bazar\Service\ListManager;
use YesWiki\Core\Entity\Translations;
use YesWiki\Core\Service\AclService;
use YesWiki\Core\Service\PageManager;
use YesWiki\Core\Service\TripleStore;
use YesWiki\Test\Core\YesWikiTestCase;
use YesWiki\Wiki;

require_once 'tests/YesWikiTestCase.php';

/**
 * Forms stored as JSON pages in the shape Ectoplasme reads, still answering to Doryphore's names, with translations stored as Ectoplasme stores them.
 */
class FormPagesTest extends YesWikiTestCase
{
    private const TITLE_ROW = 'texte***bf_titre***Titre*** *** *** *** ***text***1*** *** *** * *** * *** *** *** ***';
    private const PLACE_ROW = 'texte***bf_lieu***Lieu*** *** *** *** ***text***0*** ***Aide sur le lieu*** * *** * *** *** *** ***';

    private static array $formIds = [];
    private static array $lists = [];
    private $wiki;
    private FormManager $forms;
    private array $savedConfig;

    protected function setUp(): void
    {
        $this->wiki = $this->getWiki();
        $GLOBALS['wiki'] = $this->wiki;
        $this->forms = $this->wiki->services->get(FormManager::class);
        $this->savedConfig = array_intersect_key($this->wiki->config, array_flip(['other_languages', 'supported_langs', 'default_language']));
        unset($this->wiki->config['supported_langs']);
        $this->wiki->config['default_language'] = 'fr';
        $this->wiki->config['other_languages'] = ['en'];
    }

    protected function tearDown(): void
    {
        foreach (['other_languages', 'supported_langs', 'default_language'] as $key) {
            unset($this->wiki->config[$key]);
        }
        $this->wiki->config = array_merge($this->wiki->config, $this->savedConfig);
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

    private function create(array $data): string
    {
        $id = $this->forms->create($data + ['bn_condition' => '', 'bn_description' => 'test']);
        $this->assertNotFalse($id);
        self::$formIds[] = $id;

        return $id;
    }

    private function storedBody(string $id): array
    {
        $page = $this->wiki->services->get(PageManager::class)->getOne($this->forms->getPageTagFromId($id), null, false, true);

        return json_decode($page['body'], true);
    }

    #[DataProvider('templates')]
    public function testAStoredFormRebuildsTheSameFields(string $template): void
    {
        $legacy = $this->forms->getFromRawData(['bn_id_nature' => '999', 'bn_label_nature' => 'Template', 'bn_template' => $template]);
        $body = $this->forms->toPageBody($legacy);
        $rebuilt = $this->forms->getFromRawData(['body' => json_encode($body)]);

        $this->assertNotEmpty($legacy['prepared']);
        $this->assertCount(count($legacy['template']), $body['template']);
        $this->assertEquals($this->describe($legacy), $this->describe($rebuilt));
        $this->assertSame($legacy['template'], $rebuilt['template']);
    }

    public function testAPageStoresTheShapeEctoplasmeReads(): void
    {
        $id = $this->create(['bn_label_nature' => 'Forme stockée', 'bn_template' => self::TITLE_ROW . "\n" . self::PLACE_ROW]);
        $body = $this->storedBody($id);

        $this->assertSame(['id', 'lang', 'label', 'template', 'description', 'condition', 'sem_template', 'sem_reverse_template', 'only_one_entry', 'only_one_entry_message', 'activitypub_enable'], array_keys($body));
        $this->assertSame('Forme stockée', $body['label']);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{8}$/', $body['template'][1]['handle']);
        $this->assertSame(['type' => 'texte', 'name' => 'bf_lieu', 'label' => 'Lieu', 'sub_type' => 'text', 'required' => '0', 'hint' => 'Aide sur le lieu', 'read_access' => '*', 'write_access' => '*'], array_diff_key($body['template'][1], ['handle' => 0]));
    }

    public function testDoryphoreNamesAndEctoplasmeNamesDescribeTheSameForm(): void
    {
        $doryphore = $this->create(['bn_label_nature' => 'Par les noms de Doryphore', 'bn_template' => self::TITLE_ROW . "\n" . self::PLACE_ROW]);
        $json = json_encode($this->storedBody($doryphore)['template']);
        $ectoplasme = $this->create(['label' => 'Par les noms d\'Ectoplasme', 'template' => $json, 'description' => 'test']);
        $rows = $this->create(['bn_label_nature' => 'Par des lignes', 'template' => $this->forms->getOne($doryphore)['template']]);

        $form = $this->forms->getOne($ectoplasme);
        $this->assertSame('Par les noms d\'Ectoplasme', $form['bn_label_nature']);
        $this->assertSame($form['bn_label_nature'], $form['label']);
        $this->assertSame($this->forms->getOne($doryphore)['bn_template'], $form['bn_template']);
        $this->assertStringContainsString('texte***bf_lieu***Lieu***', $form['bn_template']);
        $this->assertSame('bf_lieu', $form['template'][1][1]);
        $this->assertEquals($this->describe($this->forms->getOne($doryphore)), $this->describe($form));
        $this->assertEquals($this->describe($this->forms->getOne($doryphore)), $this->describe($this->forms->getOne($rows)));
        $this->assertSame($this->forms->parseTemplate(self::TITLE_ROW), $this->forms->parseTemplate(json_encode([$this->storedBody($doryphore)['template'][0]])));
    }

    public function testALegacyRowBecomesAnAdminOnlyPageUnderItsIdKeepingItsActivityPubSwitch(): void
    {
        $id = (string)$this->forms->findNewId();
        $row = ['bn_id_nature' => $id, 'bn_label_nature' => 'Imported legacy form', 'bn_description' => 'd', 'bn_condition' => 'Conditions',
            'bn_sem_template' => '{"@type": "Note"}', 'bn_sem_reverse_template' => '', 'bn_activitypub_enable' => '1',
            'bn_only_one_entry' => 'Y', 'bn_only_one_entry_message' => 'Only one', 'bn_template' => file_get_contents('tests/tools/bazar/services/form-templates/every_field.wiki')];
        $tag = $this->forms->importLegacyForm($row);
        self::$formIds[] = $id;

        $form = $this->forms->getOne($id);
        $this->assertSame($tag, $form['tag']);
        $this->assertSame($id, $form['bn_id_nature']);
        $this->assertSame('Conditions', $form['bn_condition']);
        $this->assertSame('{"@type": "Note"}', $form['bn_sem_template']);
        $this->assertSame('1', $form['bn_activitypub_enable']);
        $this->assertSame('1', $this->storedBody($id)['activitypub_enable']);
        $this->assertArrayNotHasKey('activitypub', $this->wiki->services->get(PageManager::class)->getMetadata($tag) ?? []);
        $this->assertSame('Y', $form['bn_only_one_entry']);
        $this->assertSame('Only one', $form['bn_only_one_entry_message']);
        $this->assertEquals($this->describe($this->forms->getFromRawData($row)), $this->describe($form));
        $this->assertSame('@admins', $this->wiki->services->get(AclService::class)->load($tag, 'read', false)['list']);
        $this->assertContains($id, $this->forms->getAllIds());
        $this->assertSame('Imported legacy form', $this->forms->getAllTitles()[$id]);

        $form['bn_activitypub_enable'] = '0';
        $this->forms->update($form);
        $this->assertSame('0', $this->forms->getOne($id)['bn_activitypub_enable']);
    }

    public function testAPageWithoutATemplateIsRebuiltFromItsRowUnderTheSameTag(): void
    {
        $id = (string)$this->forms->findNewId();
        $row = ['bn_id_nature' => $id, 'bn_label_nature' => 'Rebuilt legacy form', 'bn_template' => self::TITLE_ROW];
        $tag = $this->forms->importLegacyForm($row);
        self::$formIds[] = $id;
        $this->wiki->services->get(PageManager::class)->save($tag, json_encode(['id' => $id, 'title' => 'Shape without template', 'fields' => []]), '', true);
        $this->forms->forgetCaches();
        $this->assertEmpty($this->forms->getOne($id)['prepared']);

        $this->assertSame($tag, $this->forms->importLegacyForm($row, $tag));

        $this->assertCount(1, $this->forms->getOne($id)['prepared']);
        $this->assertSame('Rebuilt legacy form', $this->forms->getOne($id)['bn_label_nature']);
    }

    public function testDeletingAFormRemovesItsPageAndEntries(): void
    {
        $id = $this->create(['bn_label_nature' => 'Form to delete', 'bn_template' => self::TITLE_ROW]);
        $tag = $this->forms->getPageTagFromId($id);
        $this->assertNotNull($tag);

        $this->assertTrue($this->forms->delete($id));

        $this->assertNull($this->forms->getOne($id));
        $this->assertNull($this->forms->getPageTagFromId($id));
        $this->assertEmpty($this->wiki->services->get(PageManager::class)->getOne($tag, null, false, true));
        $this->assertFalse((bool)$this->wiki->services->get(TripleStore::class)->exist($tag, TripleStore::TYPE_URI, FormManager::TRIPLE_FORM_TYPE, '', ''));
    }

    public function testTranslationsAreStoredAsEctoplasmeStoresThemAndSurviveAnEdit(): void
    {
        $id = $this->create(['bn_label_nature' => 'Formulaire traduit', 'bn_template' => self::TITLE_ROW . "\n" . self::PLACE_ROW]);
        $this->assertTrue($this->forms->updateTranslations($id, [
            'en' => ['title' => 'Translated form', 'fields' => ['bf_lieu' => ['label' => 'Place<script>alert(1)</script>', 'helper' => 'Where it happens', 'name' => 'hijacked'], 'unknown' => ['label' => 'x']], 'sem_template' => 'hijacked'],
            'de' => ['title' => 'Unsupported'],
        ]));

        $this->assertSame(['en' => ['title' => 'Translated form', 'fields' => ['bf_lieu' => ['label' => 'Place', 'helper' => 'Where it happens']]]], $this->storedBody($id)[Translations::BODY_KEY]);

        $english = $this->forms->getOne($id, 'en');
        $this->assertSame('Translated form', $english['bn_label_nature']);
        $place = array_values(array_filter($english['prepared'], fn ($field) => $field->getPropertyName() === 'bf_lieu'))[0];
        $this->assertSame('Place', $place->getLabel());
        $this->assertSame('Where it happens', $place->getHint());
        $this->assertSame('bf_lieu', $place->getName());
        $this->assertSame('', $english['bn_sem_template']);

        $this->assertSame('Formulaire traduit', $this->forms->getOne($id)['bn_label_nature']);
        $this->wiki->lang = 'en';
        $this->forms->forgetCaches();
        $this->assertSame('Translated form', $this->forms->getOne($id)['bn_label_nature']);
        $this->wiki->lang = 'fr';

        $form = $this->forms->getOne($id, 'all');
        $form['bn_label_nature'] = 'Formulaire renommé';
        $form['bn_template'] = self::PLACE_ROW . "\n" . self::TITLE_ROW;
        $this->assertTrue($this->forms->update($form));
        $this->assertSame('Translated form', $this->forms->getOne($id, 'en')['bn_label_nature']);
        $this->assertSame('Place', $this->forms->getOne($id, 'en')['prepared'][0]->getLabel());
        $this->assertSame('Formulaire renommé', $this->forms->getOne($id, 'fr')['bn_label_nature']);
    }

    public function testFieldsKeepTheirHandleThroughAnEditFromTheFormBuilder(): void
    {
        $label = 'labelhtml***<h3>Bienvenue</h3>*** ***<h3>Fiche</h3>*** *** ***';
        $notice = 'labelhtml***<p>Remarque</p>*** *** *** *** ***';
        $tabs = 'tabs***Premier,Second*** ***Un,Deux*** *** *** *** *** *** *** *** *** *** *** *** ***';
        $id = $this->create(['bn_label_nature' => 'Poignées', 'bn_template' => implode("\n", [$tabs, $label, self::TITLE_ROW, $notice])]);
        $before = array_column($this->storedBody($id)['template'], 'handle');
        $this->assertCount(4, array_unique($before));

        $edited = str_replace('Bienvenue', 'Bienvenue à tous', $label);
        $this->forms->update(['bn_id_nature' => $id, 'bn_label_nature' => 'Poignées', 'bn_template' => implode("\n", [$notice, self::TITLE_ROW, $tabs, $edited])]);

        $after = array_column($this->storedBody($id)['template'], 'handle');
        $this->assertSame([$before[3], $before[2], $before[0], $before[1]], $after);
        $this->assertStringNotContainsString($before[0], $this->forms->getOne($id)['bn_template']);
    }

    public function testFieldsWithoutANameAreTranslatedThroughTheirHandle(): void
    {
        $label = 'labelhtml***<h3>Bienvenue</h3>*** ***<h3>Fiche</h3>*** *** ***';
        $tabs = 'tabs***Premier,Second*** ***Un,Deux*** *** *** *** *** *** *** *** *** *** *** *** ***';
        $id = $this->create(['bn_label_nature' => 'Sans nom', 'bn_template' => implode("\n", [$tabs, $label, self::TITLE_ROW])]);
        [$tabsHandle, $labelHandle] = array_column($this->storedBody($id)['template'], 'handle');

        $offered = $this->forms->translatableFields($id);
        $this->assertSame(['form_text' => '<h3>Bienvenue</h3>', 'view_text' => '<h3>Fiche</h3>'], $offered[$labelHandle]['attributes']);
        $this->assertSame(['form_titles' => 'Premier,Second', 'view_titles' => 'Un,Deux'], $offered[$tabsHandle]['attributes']);
        $this->assertSame(['label' => 'Titre'], $offered['bf_titre']['attributes']);

        $this->forms->updateTranslations($id, ['en' => ['fields' => [
            $labelHandle => ['form_text' => '<h3>Welcome</h3>', 'label' => 'not offered'],
            $tabsHandle => ['form_titles' => 'First,Second'],
        ]]]);
        $this->assertEquals(['fields' => [$tabsHandle => ['form_titles' => 'First,Second'], $labelHandle => ['form_text' => '<h3>Welcome</h3>']]], $this->storedBody($id)[Translations::BODY_KEY]['en']);

        $english = $this->forms->getOne($id, 'en');
        $this->assertSame('<h3>Welcome</h3>', $english['prepared'][1]->getFormText());
        $this->assertSame(['First', 'Second'], $english['prepared'][0]->getFormTitles());
        $this->assertSame('<h3>Bienvenue</h3>', $this->forms->getOne($id)['prepared'][1]->getFormText());

        $this->forms->update(['bn_id_nature' => $id, 'bn_label_nature' => 'Sans nom', 'bn_template' => implode("\n", [self::TITLE_ROW, $label, $tabs])]);
        $english = $this->forms->getOne($id, 'en');
        $this->assertSame('<h3>Welcome</h3>', $english['prepared'][1]->getFormText());
        $this->assertSame(['First', 'Second'], $english['prepared'][2]->getFormTitles());
    }

    public function testCloneKeepsTheFieldsUnderANewIdWithoutTheTranslations(): void
    {
        $id = $this->create(['bn_label_nature' => 'Form to clone', 'bn_template' => self::TITLE_ROW]);
        $this->forms->updateTranslations($id, ['en' => ['title' => 'Cloned']]);
        $clone = $this->forms->clone($id);
        self::$formIds[] = $clone;

        $this->assertNotSame($id, $clone);
        $this->assertEquals($this->describe($this->forms->getOne($id)), $this->describe($this->forms->getOne($clone)));
        $this->assertArrayNotHasKey(Translations::BODY_KEY, $this->storedBody($clone));
    }

    public function testListTranslationsFollowTheirNodesWhenTheListIsReordered(): void
    {
        $lists = $this->wiki->services->get(ListManager::class);
        $tag = $lists->create('Couleurs', [['id' => 'r', 'label' => 'Rouge', 'children' => [['id' => 'rc', 'label' => 'Rouge clair']]], ['id' => 'b', 'label' => 'Bleu']]);
        self::$lists[] = $tag;

        $lists->updateTranslations($tag, ['en' => ['title' => 'Colours', 'nodes' => [['label' => 'Red', 'children' => [['label' => 'Light red', 'id' => 'hijacked']]], 5 => ['label' => 'Nowhere']]], 'xx' => ['title' => 'no']]);
        $english = $lists->getOne($tag, null, 'en');
        $this->assertSame('Colours', $english['title']);
        $this->assertSame('Red', $english['nodes'][0]['label']);
        $this->assertSame('r', $english['nodes'][0]['id']);
        $this->assertSame('rc', $english['nodes'][0]['children'][0]['id']);
        $this->assertSame('Light red', $english['nodes'][0]['children'][0]['label']);
        $this->assertSame('Bleu', $english['nodes'][1]['label']);
        $stored = json_decode($this->wiki->services->get(PageManager::class)->getOne($tag, null, false, true)['body'], true);
        $this->assertSame(['en' => ['title' => 'Colours', 'nodes' => [['label' => 'Red', 'children' => [['label' => 'Light red']]]]]], $stored[Translations::BODY_KEY]);

        $lists->update($tag, 'Couleurs vives', [['id' => 'b', 'label' => 'Bleu'], ['id' => 'r', 'label' => 'Rouge']]);
        $english = $lists->getOne($tag, null, 'en');
        $this->assertSame('Colours', $english['title']);
        $this->assertSame('Bleu', $english['nodes'][0]['label']);
        $this->assertSame('Red', $english['nodes'][1]['label']);
        $this->assertSame('Couleurs vives', $lists->getOne($tag, null, 'all')['title']);
    }

    public function testTranslatePageOverlaysAReaderLanguageAndKeepsTheStore(): void
    {
        $pageManager = $this->wiki->services->get(PageManager::class);
        $page = ['body' => json_encode(['bf_titre' => 'Le titre', 'bf_texte' => 'Le texte', Translations::BODY_KEY => ['en' => ['bf_titre' => 'The title']]])];

        $read = json_decode($pageManager->translatePage($page, 'en')['body'], true);
        $this->assertSame('The title', $read['bf_titre']);
        $this->assertSame('Le texte', $read['bf_texte']);
        $this->assertSame(['en' => ['bf_titre' => 'The title']], $read[Translations::BODY_KEY]);
        $this->assertSame($page, $pageManager->translatePage($page, 'all'));
        $this->assertSame('Le titre', json_decode($pageManager->translatePage($page, 'de')['body'], true)['bf_titre']);
        $this->assertSame(['body' => 'not json'], $pageManager->translatePage(['body' => 'not json'], 'en'));
    }

    public function testTranslationLanguagesComeFromOtherLanguagesOrTheOlderSupportedLangs(): void
    {
        $this->assertSame(['en'], $this->wiki->translationLanguages());
        $this->assertSame(['fr', 'en'], $this->wiki->availableLanguages());

        unset($this->wiki->config['other_languages']);
        $this->wiki->config['supported_langs'] = ['fr', 'en', 'es', "en') OR 1=1"];
        $this->assertSame(['en', 'es'], $this->wiki->translationLanguages());

        $this->wiki->config['supported_langs'] = "['fr','nl']";
        $this->assertSame(['nl'], $this->wiki->translationLanguages());

        $this->wiki->config['supported_langs'] = ['de', 'xx', 'en'];
        $this->assertSame(['en'], $this->wiki->translationLanguages(), 'a language without a catalogue is left out, as Ectoplasme leaves it out');

        $this->wiki->config['default_language'] = 'auto';
        $this->assertSame('fr', $this->wiki->defaultLanguage());
    }

    public function testARegionalSourceLanguageIsNotItsOwnTarget(): void
    {
        $this->wiki->config['other_languages'] = ['en'];

        $this->assertSame(['en'], $this->wiki->translationTargets('fr-FR'));
        $this->assertSame(['fr'], $this->wiki->translationTargets('en_GB'));
    }

    public function testAFormLanguageIsReadAndStoredWithoutItsRegion(): void
    {
        $id = $this->create(['bn_label_nature' => 'Langue régionale', 'bn_template' => self::TITLE_ROW, 'bn_ce_i18n' => 'fr-FR']);

        $this->assertSame('fr', $this->forms->getOne($id, 'all')['lang']);
        $this->assertSame('fr', $this->storedBody($id)['lang']);
    }

    public function testNothingIsTranslatedWhileNoTranslationLanguageIsTurnedOn(): void
    {
        $id = $this->create(['bn_label_nature' => 'Sans langue', 'bn_template' => self::TITLE_ROW]);
        unset($this->wiki->config['other_languages']);
        $before = $this->storedBody($id);

        $this->assertSame([], $this->wiki->translationTargets('en'));
        $this->assertFalse($this->forms->updateTranslations($id, ['fr' => ['title' => 'Sneaked in'], 'en' => ['title' => 'Sneaked in']]));
        $this->assertSame($before, $this->storedBody($id));

        $form = $this->forms->getOne($id, 'all');
        $form['lang'] = 'en';
        $this->forms->update($form);
        $this->wiki->services->get(PageManager::class)->save($this->forms->getPageTagFromId($id), json_encode(['lang' => 'en'] + $this->storedBody($id)), '', true);
        $this->forms->forgetCaches();
        $this->assertFalse($this->forms->updateTranslations($id, ['fr' => ['title' => 'Vers la langue par défaut']]));
        $this->assertArrayNotHasKey(Translations::BODY_KEY, $this->storedBody($id));
    }

    public function testALanguageNoLongerTurnedOnKeepsItsTranslations(): void
    {
        $this->wiki->config['other_languages'] = ['en', 'es'];
        $id = $this->create(['bn_label_nature' => 'Plusieurs langues', 'bn_template' => self::TITLE_ROW]);
        $this->forms->updateTranslations($id, ['en' => ['title' => 'Several'], 'es' => ['title' => 'Varios']]);

        $this->wiki->config['other_languages'] = ['en'];
        $this->assertTrue($this->forms->updateTranslations($id, ['en' => ['title' => 'Many'], 'es' => ['title' => 'Ignored']]));
        $this->assertEquals(['en' => ['title' => 'Many'], 'es' => ['title' => 'Varios']], $this->storedBody($id)[Translations::BODY_KEY]);

        $this->assertTrue($this->forms->updateTranslations($id, []));
        $this->assertEquals(['es' => ['title' => 'Varios']], $this->storedBody($id)[Translations::BODY_KEY], 'a target language left blank is cleared');

        $lists = $this->wiki->services->get(ListManager::class);
        $this->wiki->config['other_languages'] = ['en', 'es'];
        $tag = $lists->create('Saisons', [['id' => 'e', 'label' => 'Été']]);
        self::$lists[] = $tag;
        $lists->updateTranslations($tag, ['en' => ['title' => 'Seasons'], 'es' => ['title' => 'Estaciones']]);
        $this->wiki->config['other_languages'] = ['en'];
        $lists->updateTranslations($tag, ['en' => ['title' => 'The seasons'], 'es' => ['title' => 'Ignored']]);
        $stored = json_decode($this->wiki->services->get(PageManager::class)->getOne($tag, null, false, true)['body'], true);
        $this->assertEquals(['en' => ['title' => 'The seasons'], 'es' => ['title' => 'Estaciones']], $stored[Translations::BODY_KEY]);
        unset($this->wiki->config['other_languages']);
        $lists->updateTranslations($tag, []);
        $stored = json_decode($this->wiki->services->get(PageManager::class)->getOne($tag, null, false, true)['body'], true);
        $this->assertEquals(['en' => ['title' => 'The seasons'], 'es' => ['title' => 'Estaciones']], $stored[Translations::BODY_KEY], 'with no target nothing is written');
    }

    public function testTheRequestLanguageCannotCarrySql(): void
    {
        $this->assertSame('en', Wiki::sanitizeLang('en', 'fr'));
        $this->assertSame('pt', Wiki::sanitizeLang('pt-BR#anchor', 'fr'));
        $this->assertSame('fr', Wiki::sanitizeLang('FR_fr', 'en'));
        $this->assertSame('fr', Wiki::sanitizeLang('en") OR 1=1 -- ', 'fr'));
        $this->assertSame('fr', Wiki::sanitizeLang("en'.", 'fr'));
        $this->assertSame('fr', Wiki::sanitizeLang(null, 'fr'));
    }
}

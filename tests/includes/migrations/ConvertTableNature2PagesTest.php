<?php

require_once 'tests/YesWikiTestCase.php';

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Depends;
use YesWiki\Core\Service\DbService;
use YesWiki\Core\Service\PageManager;
use YesWiki\Core\Service\TripleStore;
use YesWiki\Bazar\Service\FormManager;
use YesWiki\Test\Core\YesWikiTestCase;
use function PHPUnit\Framework\assertEquals;
use function PHPUnit\Framework\assertStringContainsString;

require_once 'includes/migrations/20260424165958_ConvertTableNature2Pages.php';

class ConvertTableNature2PagesTest extends YesWikiTestCase
{
    public function testConvertTableNatureClassExists(): ConvertTableNature2Pages
    {
        $instance = new ConvertTableNature2Pages();
        $wiki = $this->getWiki();
        $instance->setWiki($wiki);
        $instance->setDbService($wiki->services->get(DbService::class));
        $instance->setParams($wiki->services->getParameterBag());
        assertEquals(get_class($instance), 'ConvertTableNature2Pages');

        return $instance;
    }

    public static function dataProviderForms()
    {
        $id_nature = 1;
        $forms = [];
        foreach(glob('tests/includes/migrations/wikis_nature/*.wiki') as $filename) {
            $form = [];
            $form['bn_template'] = file_get_contents($filename);
            $form['bn_id_nature'] = $id_nature++;
            $form['bn_label_nature'] = substr($filename, 0, -5);
            $form['bn_description'] = 'description for the form' . substr($filename, 0, -5);
            $form['bn_condition'] = '';
            $form['bn_only_one_entry'] = 'No';
            $form['bn_only_one_entry_message'] = '';
            $forms[] = [
                $form,
                []
            ];
        }

        return $forms;
    }

    #[Depends('testConvertTableNatureClassExists')]
    #[DataProvider('dataProviderForms')]
    public function testFormMigrationNoChange($form, $fiche, ConvertTableNature2Pages $migrator)
    {
        $wiki = $this->getWiki();
        $formManager = $wiki->services->get(FormManager::class);
        $pageManager = $wiki->services->get(PageManager::class);
        $tripleStore = $wiki->services->get(TripleStore::class);

        $result = $migrator->convertform($pageManager, $tripleStore, $form, $fiche);

        $roundtrip = $formManager->getFromRawData(['body' => json_encode($result)]);
        $roundtrip = explode("\n", $roundtrip['bn_template']);
        foreach (explode("\n", $form['bn_template']) as $index => $expected_line) {
            $expected_line = \preg_replace('/\r\n|\r|\n/', "\n", $expected_line);
            $roundtrip_line = \preg_replace('/\r/', '', $roundtrip[$index]);
            // ignore conversion texte to text
            $expected_line = \preg_replace('/texte\*/', 'text*', $expected_line);
            // ignore conversion champs_mail to email
            $expected_line = \preg_replace('/champs_mail\*/', 'email*', $expected_line);
            // ignore conversion lien_internet to link
            $expected_line = \preg_replace('/lien_internet\*/', 'link*', $expected_line);
            error_log("\n" . $expected_line . "\n");
            error_log("\n" . $roundtrip[$index] . "\n");
            assertStringContainsString($expected_line, $roundtrip_line);
        }
    }



}

<?php

namespace YesWiki\Test\Bazar\Service;

use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use YesWiki\Bazar\Field\CheckboxListField;
use YesWiki\Bazar\Field\DateField;
use YesWiki\Bazar\Field\EmailField;
use YesWiki\Bazar\Field\FileField;
use YesWiki\Bazar\Field\LinkField;
use YesWiki\Bazar\Field\SelectEntryField;
use YesWiki\Bazar\Field\SelectListField;
use YesWiki\Bazar\Field\TagsField;
use YesWiki\Bazar\Field\TextareaField;
use YesWiki\Bazar\Field\TextField;
use YesWiki\Bazar\Service\EntryChecker;
use YesWiki\Bazar\Service\EntryManager;
use YesWiki\Bazar\Service\ExternalBazarService;
use YesWiki\Bazar\Service\FormManager;
use YesWiki\Bazar\Service\ListManager;
use YesWiki\Bazar\Service\UrlReachability;
use YesWiki\Core\Service\PageManager;
use YesWiki\Core\Service\TripleStore;
use YesWiki\Security\Controller\SecurityController;
use YesWiki\Wiki;

require_once 'includes/autoload.inc.php';
require_once 'includes/constants.php';
require_once 'includes/i18n.inc.php';

class EntryCheckerTest extends TestCase
{
    private $services;
    private $uploadDir;
    private $probeResults;
    private $entryManager;
    private $formManager;
    private $pageManager;
    private $savedBodies;

    protected function setUp(): void
    {
        $this->uploadDir = sys_get_temp_dir() . '/checkcontent-' . getmypid() . '-' . uniqid();
        mkdir($this->uploadDir, 0777, true);
        $this->probeResults = [];

        $this->useList([
            ['id' => 'rouge', 'label' => 'Rouge', 'children' => []],
            ['id' => 'vert', 'label' => 'Vert', 'children' => []],
        ]);

        $this->entryManager = $this->createStub(EntryManager::class);
        $this->entryManager->method('getAllEntriesTags')->willReturn(['FicheVivante']);
        $this->formManager = $this->createStub(FormManager::class);
        $this->pageManager = $this->createStub(PageManager::class);
        $this->savedBodies = [];
    }

    protected function tearDown(): void
    {
        foreach (glob($this->uploadDir . '/*') ?: [] as $path) {
            chmod($path, 0644);
            unlink($path);
        }
        rmdir($this->uploadDir);
    }

    private function checker(): EntryChecker
    {
        $security = $this->createStub(SecurityController::class);
        $security->method('isWikiHibernated')->willReturn(false);
        $urlReachability = $this->createStub(UrlReachability::class);
        $urlReachability->method('probe')->willReturnCallback(function (array $urls) {
            return array_intersect_key($this->probeResults, array_flip($urls));
        });

        return new EntryChecker($this->entryManager, $this->formManager, $this->pageManager, $security, $urlReachability);
    }

    private function fileField(string $name): FileField
    {
        return new FileField($this->values([0 => 'fichier', 1 => $name]), $this->services);
    }

    private function values(array $overrides): array
    {
        return array_replace(array_fill(0, 20, ''), $overrides);
    }

    private function givenForm(array $fields, array $entries): void
    {
        $this->formManager->method('getOne')->willReturn(['bn_id_nature' => '1', 'prepared' => $fields]);
        $this->entryManager->method('search')->willReturn($entries);
    }

    private function useList(array $nodes): void
    {
        $listManager = $this->createStub(ListManager::class);
        $listManager->method('getOne')->willReturn(['nodes' => $nodes]);

        $wiki = new class() {
            public $config = [
                'BAZ_MAX_CHECKBOXLIST_WITHOUT_FILTER' => false,
                'BAZ_MAX_CHECKBOXLIST_WITHOUT_SELECTALL' => false,
                'BAZ_MAX_CHECKBOXLIST_DISPLAY_MODE' => 'div',
            ];

            public function GetConfigValue($key)
            {
                return $this->config[$key] ?? null;
            }
        };

        $externalBazarService = $this->createStub(ExternalBazarService::class);
        $externalBazarService->method('getJSONCachedUrlContent')->willReturn('');

        $params = $this->createStub(ParameterBagInterface::class);
        $params->method('get')->willReturnCallback(function (string $key) {
            return match ($key) {
                'max-upload-size' => 1000000,
                'attach_config' => ['upload_path' => $this->uploadDir],
                default => null,
            };
        });

        $tripleStore = $this->createStub(TripleStore::class);
        $tripleStore->method('getMatching')->willReturn([
            ['value' => 'velo'],
            ['value' => 'atelier'],
        ]);

        $stubs = [
            ListManager::class => $listManager,
            TripleStore::class => $tripleStore,
            Wiki::class => $wiki,
            ParameterBagInterface::class => $params,
            ExternalBazarService::class => $externalBazarService,
        ];
        $this->services = new class($stubs) implements ContainerInterface {
            private $stubs;

            public function __construct(array $stubs)
            {
                $this->stubs = $stubs;
            }

            public function get(string $id)
            {
                return $this->stubs[$id] ?? null;
            }

            public function has(string $id): bool
            {
                return isset($this->stubs[$id]);
            }
        };
    }

    private function captureSave(array $body): object
    {
        $this->pageManager->method('getOne')->willReturn(['tag' => $body['id_fiche'], 'body' => json_encode($body)]);
        $saved = new class() {
            public $body = [];
            public $calls = 0;
        };
        $this->pageManager->method('save')->willReturnCallback(function ($tag, $newBody) use ($saved) {
            $saved->body = json_decode($newBody, true);
            $saved->calls++;

            return 0;
        });

        return $saved;
    }

    private function textField(string $name, bool $required = false): TextField
    {
        return new TextField($this->values([1 => $name, 8 => $required ? 1 : 0]), $this->services);
    }

    public function testRequiredFieldLeftEmptyIsReportedAndNotRepairable()
    {
        $this->givenForm(
            [$this->textField('bf_titre', true), $this->textField('bf_note')],
            ['FicheUne' => ['id_fiche' => 'FicheUne', 'bf_titre' => '', 'bf_note' => '']]
        );

        $problems = $this->checker()->check('1')['problems'];

        $this->assertArrayHasKey(EntryChecker::REQUIRED_EMPTY, $problems);
        $this->assertCount(1, $problems[EntryChecker::REQUIRED_EMPTY]);
        $row = $problems[EntryChecker::REQUIRED_EMPTY][0];
        $this->assertSame('bf_titre', $row['propertyName']);
        $this->assertNull($row['fix']);
    }

    public function testAFieldReadFromAnotherServerIsListedAsUnchecked()
    {
        $remote = new SelectEntryField($this->values([0 => 'listefiche', 1 => '2', 6 => 'https://autre.wiki/?api/forms/3/entries']), $this->services);
        $local = new SelectEntryField($this->values([0 => 'listefiche', 1 => '2', 6 => 'listefiche2']), $this->services);
        $this->givenForm(
            [$remote, $local],
            ['FicheUne' => ['id_fiche' => 'FicheUne', 'https://autre.wiki/?api/forms/3/entries' => 'FicheLointaine']]
        );

        $unchecked = $this->checker()->check('1')['unchecked'];

        $this->assertSame([EntryChecker::REMOTE_OPTIONS], array_keys($unchecked));
        $this->assertCount(1, $unchecked[EntryChecker::REMOTE_OPTIONS]);
        $this->assertSame('https://autre.wiki/?api/forms/3/entries', $unchecked[EntryChecker::REMOTE_OPTIONS][0]['source']);
        $this->assertSame('listefiche', $unchecked[EntryChecker::REMOTE_OPTIONS][0]['type']);
    }

    public function testARequiredTextFieldOffersTheStandInTextAndWritesThePickedOne()
    {
        $body = ['id_fiche' => 'FicheUne', 'id_typeannonce' => '1', 'bf_resume' => ''];
        $this->givenForm(
            [new TextareaField($this->values([0 => 'textelong', 1 => 'bf_resume', 8 => 1]), $this->services)],
            ['FicheUne' => $body]
        );
        $saved = $this->captureSave($body);

        $rows = $this->checker()->check('1', 'à compléter')['problems'][EntryChecker::REQUIRED_EMPTY];
        $this->assertSame('any', $rows[0]['freeText']);
        $this->assertSame('à compléter', $rows[0]['suggested']);
        $this->assertSame([], $rows[0]['options']);

        $key = $rows[0]['key'];
        $result = $this->checker()->repair('1', [$key], [$key => 'à compléter'], 'à compléter');

        $this->assertSame(1, $result['repaired']);
        $this->assertSame('à compléter', $saved->body['bf_resume']);
    }

    public function testAnAdminCanReplaceTheStandInWithARealValue()
    {
        $body = ['id_fiche' => 'FicheUne', 'id_typeannonce' => '1'];
        $this->givenForm(
            [$this->textField('bf_note', true)],
            ['FicheUne' => $body]
        );
        $saved = $this->captureSave($body);

        $key = EntryChecker::REQUIRED_EMPTY . '::FicheUne::bf_note';
        $result = $this->checker()->repair('1', [$key], [$key => '  Atelier du jeudi  '], 'à compléter');

        $this->assertSame(1, $result['repaired']);
        $this->assertSame('Atelier du jeudi', $saved->body['bf_note']);
    }

    public function testABlankedStandInWritesNothing()
    {
        $body = ['id_fiche' => 'FicheUne', 'id_typeannonce' => '1'];
        $this->givenForm([$this->textField('bf_note', true)], ['FicheUne' => $body]);
        $saved = $this->captureSave($body);

        $key = EntryChecker::REQUIRED_EMPTY . '::FicheUne::bf_note';
        $result = $this->checker()->repair('1', [$key], [$key => '   '], 'à compléter');

        $this->assertSame(0, $result['repaired']);
        $this->assertSame(0, $saved->calls);
    }

    public function testAnEmptyTextreplaceOffersNothing()
    {
        $this->givenForm(
            [$this->textField('bf_note', true)],
            ['FicheUne' => ['id_fiche' => 'FicheUne', 'bf_note' => '']]
        );

        $rows = $this->checker()->check('1', '')['problems'][EntryChecker::REQUIRED_EMPTY];

        $this->assertSame('', $rows[0]['freeText']);
        $this->assertNull($rows[0]['fix']);
    }

    public function testATypedTextFieldGetsNoStandInBecauseItCannotHoldASentence()
    {
        $email = new TextField($this->values([0 => 'texte', 1 => 'bf_contact', 7 => 'email', 8 => 1]), $this->services);
        $date = new TextField($this->values([0 => 'texte', 1 => 'bf_quand', 7 => 'date', 8 => 1]), $this->services);
        $this->givenForm(
            [$email, $date],
            ['FicheUne' => ['id_fiche' => 'FicheUne', 'bf_contact' => '', 'bf_quand' => '']]
        );

        $rows = $this->checker()->check('1', 'à compléter')['problems'][EntryChecker::REQUIRED_EMPTY];

        $this->assertCount(2, $rows);
        $this->assertSame('', $rows[0]['freeText']);
        $this->assertSame('', $rows[1]['freeText']);
    }

    public function testATagsFieldIsLeftAloneRatherThanCheckedAgainstItsOwnValues()
    {
        $tags = new TagsField($this->values([0 => 'tags', 1 => 'bf_tags']), $this->services);
        $this->givenForm(
            [$tags],
            [
                'FicheUne' => ['id_fiche' => 'FicheUne', 'bf_tags' => 'velo,atelier'],
                'FicheDeux' => ['id_fiche' => 'FicheDeux', 'bf_tags' => 'nouveautag'],
            ]
        );

        $result = $this->checker()->check('1');

        $this->assertSame([], $result['problems']);
        $this->assertSame([], $result['unchecked']);
    }

    public function testARequiredTagsFieldGetsNoPickerBecauseTagsAreFreeText()
    {
        $tags = new TagsField($this->values([0 => 'tags', 1 => 'bf_tags', 8 => 1]), $this->services);
        $this->givenForm([$tags], ['FicheUne' => ['id_fiche' => 'FicheUne', 'bf_tags' => '']]);

        $rows = $this->checker()->check('1')['problems'][EntryChecker::REQUIRED_EMPTY];

        $this->assertCount(1, $rows);
        $this->assertSame([], $rows[0]['options']);
        $this->assertNull($rows[0]['fix']);
    }

    public function testAFieldPointingAtAMissingListIsListedAsUnchecked()
    {
        $this->useList([]);
        $orphanList = new SelectListField($this->values([1 => 'ListeDisparue', 6 => 'listeListeDisparue']), $this->services);
        $this->givenForm(
            [$orphanList],
            ['FicheUne' => ['id_fiche' => 'FicheUne', 'listeListeDisparue' => 'rouge']]
        );

        $result = $this->checker()->check('1');

        $this->assertSame([], $result['problems']);
        $this->assertSame('ListeDisparue', $result['unchecked'][EntryChecker::NO_OPTIONS][0]['source']);
    }

    public function testAHealthyFormReportsNothingUnchecked()
    {
        $this->givenForm(
            [new SelectListField($this->values([1 => 'ListeCouleurs', 6 => 'listeListeCouleurs']), $this->services)],
            ['FicheUne' => ['id_fiche' => 'FicheUne', 'listeListeCouleurs' => 'rouge']]
        );

        $this->assertSame([], $this->checker()->check('1')['unchecked']);
    }

    public function testRequiredEnumFieldOffersTheListOptionsWithItsDefaultPreselected()
    {
        $select = new SelectListField($this->values([1 => 'ListeCouleurs', 5 => 'vert', 6 => 'listeListeCouleurs', 8 => 1]), $this->services);
        $this->givenForm(
            [$select, $this->textField('bf_note', true)],
            ['FicheUne' => ['id_fiche' => 'FicheUne', 'listeListeCouleurs' => '', 'bf_note' => '']]
        );

        $rows = $this->checker()->check('1')['problems'][EntryChecker::REQUIRED_EMPTY];

        $this->assertSame(['rouge' => 'Rouge', 'vert' => 'Vert'], $rows[0]['options']);
        $this->assertSame('vert', $rows[0]['suggested']);
        $this->assertFalse($rows[0]['multiple']);
        $this->assertSame([], $rows[1]['options']);
    }

    public function testAnOverlongListIsNotOfferedAsAPicker()
    {
        $nodes = [];
        for ($i = 0; $i <= 200; $i++) {
            $nodes[] = ['id' => "opt$i", 'label' => "Option $i", 'children' => []];
        }
        $this->useList($nodes);
        $select = new SelectListField($this->values([1 => 'ListeLongue', 6 => 'listeListeLongue', 8 => 1]), $this->services);
        $this->givenForm([$select], ['FicheUne' => ['id_fiche' => 'FicheUne', 'listeListeLongue' => '']]);

        $rows = $this->checker()->check('1')['problems'][EntryChecker::REQUIRED_EMPTY];

        $this->assertSame([], $rows[0]['options']);
    }

    public function testRequiredEnumRepairWritesThePickedOption()
    {
        $body = ['id_fiche' => 'FicheUne', 'id_typeannonce' => '1', 'listeListeCouleurs' => ''];
        $this->givenForm(
            [new SelectListField($this->values([1 => 'ListeCouleurs', 6 => 'listeListeCouleurs', 8 => 1]), $this->services)],
            ['FicheUne' => $body]
        );
        $saved = $this->captureSave($body);

        $key = EntryChecker::REQUIRED_EMPTY . '::FicheUne::listeListeCouleurs';
        $result = $this->checker()->repair('1', [$key], [$key => 'rouge']);

        $this->assertSame(1, $result['repaired']);
        $this->assertSame('rouge', $saved->body['listeListeCouleurs']);
    }

    public function testRequiredEnumRepairRefusesAValueOutsideTheList()
    {
        $body = ['id_fiche' => 'FicheUne', 'id_typeannonce' => '1', 'listeListeCouleurs' => ''];
        $this->givenForm(
            [new SelectListField($this->values([1 => 'ListeCouleurs', 6 => 'listeListeCouleurs', 8 => 1]), $this->services)],
            ['FicheUne' => $body]
        );
        $saved = $this->captureSave($body);

        $key = EntryChecker::REQUIRED_EMPTY . '::FicheUne::listeListeCouleurs';
        $result = $this->checker()->repair('1', [$key], [$key => 'fuchsia']);

        $this->assertSame(0, $result['repaired']);
        $this->assertSame(0, $saved->calls);
    }

    public function testRequiredCheckboxRepairJoinsThePickedOptions()
    {
        $body = ['id_fiche' => 'FicheUne', 'id_typeannonce' => '1'];
        $this->givenForm(
            [new CheckboxListField($this->values([1 => 'ListeCouleurs', 6 => 'checkboxListeCouleurs', 8 => 1]), $this->services)],
            ['FicheUne' => $body]
        );
        $saved = $this->captureSave($body);

        $key = EntryChecker::REQUIRED_EMPTY . '::FicheUne::checkboxListeCouleurs';
        $result = $this->checker()->repair('1', [$key], [$key => ['rouge', 'fuchsia', 'vert']]);

        $this->assertSame(1, $result['repaired']);
        $this->assertSame('rouge,vert', $saved->body['checkboxListeCouleurs']);
    }

    public function testUnknownCheckboxOptionsAreDroppedAndValidOnesKept()
    {
        $checkbox = new CheckboxListField($this->values([1 => 'ListeCouleurs', 6 => 'checkboxListeCouleurs']), $this->services);
        $this->givenForm(
            [$checkbox],
            ['FicheUne' => ['id_fiche' => 'FicheUne', 'checkboxListeCouleurs' => 'rouge,fuchsia,vert']]
        );

        $rows = $this->checker()->check('1')['problems'][EntryChecker::UNKNOWN_OPTION];

        $this->assertCount(1, $rows);
        $this->assertSame('fuchsia', $rows[0]['detail']);
        $this->assertSame(['set' => 'rouge,vert'], $rows[0]['fix']);
    }

    public function testUnknownSingleValueOptionIsCleared()
    {
        $select = new SelectListField($this->values([1 => 'ListeCouleurs', 6 => 'listeListeCouleurs']), $this->services);
        $this->givenForm(
            [$select],
            ['FicheUne' => ['id_fiche' => 'FicheUne', 'listeListeCouleurs' => 'fuchsia']]
        );

        $rows = $this->checker()->check('1')['problems'][EntryChecker::UNKNOWN_OPTION];

        $this->assertSame(['set' => ''], $rows[0]['fix']);
    }

    public function testMalformedEmailIsNormalisedWhenPossibleAndClearedOtherwise()
    {
        $this->givenForm(
            [new EmailField($this->values([1 => 'bf_mail']), $this->services)],
            [
                'FicheUne' => ['id_fiche' => 'FicheUne', 'bf_mail' => ' Bob@Example.COM '],
                'FicheDeux' => ['id_fiche' => 'FicheDeux', 'bf_mail' => "pas d'email"],
                'FicheTrois' => ['id_fiche' => 'FicheTrois', 'bf_mail' => 'ok@example.com'],
            ]
        );

        $rows = $this->checker()->check('1')['problems'][EntryChecker::INVALID_EMAIL];

        $this->assertCount(2, $rows);
        $this->assertSame(['set' => 'bob@example.com'], $rows[0]['fix']);
        $this->assertSame(['set' => ''], $rows[1]['fix']);
    }

    public function testAnUploadedFileGoneFromDiskIsReportedAndClearable()
    {
        file_put_contents($this->uploadDir . '/present.pdf', 'x');
        $field = $this->fileField('bf_doc');
        $this->givenForm(
            [$field],
            [
                'FicheUne' => ['id_fiche' => 'FicheUne', 'fichierbf_doc' => 'disparu.pdf'],
                'FicheDeux' => ['id_fiche' => 'FicheDeux', 'fichierbf_doc' => 'present.pdf'],
            ]
        );

        $rows = $this->checker()->check('1')['problems'][EntryChecker::MISSING_FILE];

        $this->assertCount(1, $rows);
        $this->assertSame('FicheUne', $rows[0]['entryId']);
        $this->assertSame('disparu.pdf', $rows[0]['detail']);
        $this->assertSame(['set' => ''], $rows[0]['fix']);
    }

    public function testAFileTheServerCannotReadIsReportedButNotRepairable()
    {
        $path = $this->uploadDir . '/verrouille.pdf';
        file_put_contents($path, 'x');
        chmod($path, 0000);
        if (is_readable($path)) {
            $this->markTestSkipped('this user bypasses file permissions');
        }
        $this->givenForm(
            [$this->fileField('bf_doc')],
            ['FicheUne' => ['id_fiche' => 'FicheUne', 'fichierbf_doc' => 'verrouille.pdf']]
        );

        $problems = $this->checker()->check('1')['problems'];

        $this->assertArrayNotHasKey(EntryChecker::MISSING_FILE, $problems);
        $row = $problems[EntryChecker::UNREADABLE_FILE][0];
        $this->assertNull($row['fix']);
        $this->assertSame('BAZ_CHECKCONTENT_FIX_PERMISSIONS', $row['fixLabel']);
    }

    public function testAnExternalFileIsProbedAndOnlyABadAnswerIsReported()
    {
        $this->probeResults = [
            'https://ok.example/logo.png' => ['fetched' => true, 'status' => 200, 'error' => null],
            'https://gone.example/logo.png' => ['fetched' => true, 'status' => 404, 'error' => null],
            'https://down.example/logo.png' => ['fetched' => true, 'status' => null, 'error' => 'Connection timed out'],
        ];
        $this->givenForm(
            [$this->fileField('bf_doc')],
            [
                'FicheUne' => ['id_fiche' => 'FicheUne', 'fichierbf_doc' => 'https://ok.example/logo.png'],
                'FicheDeux' => ['id_fiche' => 'FicheDeux', 'fichierbf_doc' => 'https://gone.example/logo.png'],
                'FicheTrois' => ['id_fiche' => 'FicheTrois', 'fichierbf_doc' => 'https://down.example/logo.png'],
            ]
        );

        $rows = $this->checker()->check('1')['problems'][EntryChecker::UNREACHABLE_URL];

        $this->assertCount(2, $rows);
        $this->assertSame('https://gone.example/logo.png — 404', $rows[0]['detail']);
        $this->assertStringContainsString('Connection timed out', $rows[1]['detail']);
        $this->assertNull($rows[0]['fix']);
    }

    public function testAnExternalFileLeftAloneSaysWhy()
    {
        $this->probeResults = [
            'http://vieux.example/logo.png' => ['fetched' => false, 'reason' => 'not_https'],
        ];
        $this->givenForm(
            [$this->fileField('bf_doc')],
            ['FicheUne' => ['id_fiche' => 'FicheUne', 'fichierbf_doc' => 'http://vieux.example/logo.png']]
        );

        $problems = $this->checker()->check('1')['problems'];

        $this->assertArrayNotHasKey(EntryChecker::UNREACHABLE_URL, $problems);
        $row = $problems[EntryChecker::UNFETCHED_URL][0];
        $this->assertStringContainsString(_t('BAZ_CHECKCONTENT_UNFETCHED_NOT_HTTPS'), $row['detail']);
        $this->assertSame('BAZ_CHECKCONTENT_FIX_NOTHING', $row['fixLabel']);
    }

    public function testUnreadableDateIsReportedButParsableOneIsNot()
    {
        $this->givenForm(
            [new DateField($this->values([1 => 'bf_date_debut_evenement']), $this->services)],
            [
                'FicheUne' => ['id_fiche' => 'FicheUne', 'bf_date_debut_evenement' => 'bientôt'],
                'FicheDeux' => ['id_fiche' => 'FicheDeux', 'bf_date_debut_evenement' => '2026-08-19'],
            ]
        );

        $rows = $this->checker()->check('1')['problems'][EntryChecker::INVALID_DATE];

        $this->assertCount(1, $rows);
        $this->assertSame('FicheUne', $rows[0]['entryId']);
        $this->assertSame(['set' => ''], $rows[0]['fix']);
    }

    public function testPlaceholderUrlIsClearedAndSurroundingSpacesAreTrimmed()
    {
        $this->givenForm(
            [new LinkField($this->values([1 => 'bf_site']), $this->services)],
            [
                'FicheUne' => ['id_fiche' => 'FicheUne', 'bf_site' => 'https://'],
                'FicheDeux' => ['id_fiche' => 'FicheDeux', 'bf_site' => ' https://example.com '],
                'FicheTrois' => ['id_fiche' => 'FicheTrois', 'bf_site' => 'https://example.org'],
            ]
        );

        $rows = $this->checker()->check('1')['problems'][EntryChecker::INVALID_URL];

        $this->assertCount(2, $rows);
        $this->assertSame(['set' => ''], $rows[0]['fix']);
        $this->assertSame(['set' => 'https://example.com'], $rows[1]['fix']);
    }

    public function testAnAddressCarryingAccentsOrEmojiIsNotMalformed()
    {
        $this->givenForm(
            [new LinkField($this->values([1 => 'bf_url1']), $this->services)],
            [
                'FicheUne' => ['id_fiche' => 'FicheUne', 'bf_url1' => 'https://www.linkedin.com/in/géraldine-louis/'],
                'FicheDeux' => ['id_fiche' => 'FicheDeux', 'bf_url1' => 'https://www.linkedin.com/in/julie-chabaud-💚-b9923126'],
                'FicheTrois' => ['id_fiche' => 'FicheTrois', 'bf_url1' => 'https://www.linkedin.com/in/laurent-marseault-⚖-114b221b'],
            ]
        );

        $this->assertSame([], $this->checker()->check('1')['problems']);
    }

    public function testAnAddressWithAnAccentedHostIsNotMalformed()
    {
        $this->givenForm(
            [new LinkField($this->values([1 => 'bf_url1']), $this->services)],
            [
                'FicheUne' => ['id_fiche' => 'FicheUne', 'bf_url1' => 'https://exämple.com/chemin/café'],
                'FicheDeux' => ['id_fiche' => 'FicheDeux', 'bf_url1' => 'https://café.fr'],
            ]
        );

        $this->assertSame([], $this->checker()->check('1')['problems']);
    }

    public function testTwoAddressesInOneFieldAreEditedRatherThanDeleted()
    {
        $crammed = 'https://www.linkedin.com/in/pwillemarck/ ; https://www.facebook.com/pwillemarck/';
        $body = ['id_fiche' => 'FicheUne', 'id_typeannonce' => '1', 'bf_url1' => $crammed];
        $this->givenForm([new LinkField($this->values([1 => 'bf_url1']), $this->services)], ['FicheUne' => $body]);
        $saved = $this->captureSave($body);

        $rows = $this->checker()->check('1')['problems'][EntryChecker::INVALID_URL];
        $this->assertNull($rows[0]['fix']);
        $this->assertSame('url', $rows[0]['freeText']);
        $this->assertSame($crammed, $rows[0]['suggested']);

        $key = $rows[0]['key'];
        $this->checker()->repair('1', [$key], [$key => 'https://www.linkedin.com/in/pwillemarck/']);
        $this->assertSame('https://www.linkedin.com/in/pwillemarck/', $saved->body['bf_url1']);
    }

    public function testAnEditedAddressThatIsStillNotAnAddressIsRefused()
    {
        $body = ['id_fiche' => 'FicheUne', 'id_typeannonce' => '1', 'bf_url1' => 'a ; b'];
        $this->givenForm([new LinkField($this->values([1 => 'bf_url1']), $this->services)], ['FicheUne' => $body]);
        $saved = $this->captureSave($body);

        $key = EntryChecker::INVALID_URL . '::FicheUne::bf_url1';
        $result = $this->checker()->repair('1', [$key], [$key => 'toujours pas une adresse']);

        $this->assertSame(0, $result['repaired']);
        $this->assertSame(0, $saved->calls);
    }

    public function testAnEmailWrittenWithAccentsIsNotMalformed()
    {
        $this->givenForm(
            [new EmailField($this->values([1 => 'bf_mail']), $this->services)],
            [
                'FicheUne' => ['id_fiche' => 'FicheUne', 'bf_mail' => 'josé@exemple.fr'],
                'FicheDeux' => ['id_fiche' => 'FicheDeux', 'bf_mail' => 'pas un mail'],
            ]
        );

        $rows = $this->checker()->check('1')['problems'][EntryChecker::INVALID_EMAIL];

        $this->assertCount(1, $rows);
        $this->assertSame('FicheDeux', $rows[0]['entryId']);
    }

    public function testReferenceToDeletedEntryIsReported()
    {
        $field = new SelectEntryField($this->values([1 => '2', 6 => 'listefiche2']), $this->services);
        $this->givenForm(
            [$field],
            [
                'FicheUne' => ['id_fiche' => 'FicheUne', 'listefiche2' => 'FicheMorte'],
                'FicheDeux' => ['id_fiche' => 'FicheDeux', 'listefiche2' => 'FicheVivante'],
            ]
        );

        $rows = $this->checker()->check('1')['problems'][EntryChecker::BROKEN_ENTRY];

        $this->assertCount(1, $rows);
        $this->assertSame('FicheMorte', $rows[0]['detail']);
        $this->assertSame(['set' => ''], $rows[0]['fix']);
    }

    public function testOrphanKeysAreReportedButDerivedAndReservedOnesAreNot()
    {
        $this->givenForm(
            [$this->textField('bf_titre'), new DateField($this->values([1 => 'bf_date']), $this->services)],
            ['FicheUne' => [
                'id_fiche' => 'FicheUne',
                'id_typeannonce' => '1',
                'date_maj_fiche' => '2026-08-19 10:00:00',
                'owner' => 'WikiAdmin',
                'bf_titre' => 'Une fiche',
                'bf_date' => '2026-08-19',
                'bf_date_data' => ['other' => []],
                'bf_ancien_champ' => 'valeur oubliée',
            ]]
        );

        $rows = $this->checker()->check('1')['problems'][EntryChecker::ORPHAN_FIELD];

        $this->assertCount(1, $rows);
        $this->assertSame('bf_ancien_champ', $rows[0]['propertyName']);
        $this->assertSame(['unset' => true], $rows[0]['fix']);
    }

    public function testRepairWritesOnlyTheSelectedProblems()
    {
        $body = [
            'id_fiche' => 'FicheUne',
            'id_typeannonce' => '1',
            'bf_mail' => "pas d'email",
            'bf_site' => 'https://',
            'bf_ancien_champ' => 'valeur oubliée',
        ];
        $this->givenForm(
            [
                new EmailField($this->values([1 => 'bf_mail']), $this->services),
                new LinkField($this->values([1 => 'bf_site']), $this->services),
            ],
            ['FicheUne' => $body]
        );
        $this->pageManager->method('getOne')->willReturn(['tag' => 'FicheUne', 'body' => json_encode($body)]);
        $saved = null;
        $this->pageManager->method('save')->willReturnCallback(function ($tag, $newBody) use (&$saved) {
            $saved = json_decode($newBody, true);

            return 0;
        });

        $result = $this->checker()->repair('1', [
            EntryChecker::INVALID_EMAIL . '::FicheUne::bf_mail',
            EntryChecker::ORPHAN_FIELD . '::FicheUne::bf_ancien_champ',
        ]);

        $this->assertSame(2, $result['repaired']);
        $this->assertSame(1, $result['entries']);
        $this->assertSame('', $saved['bf_mail']);
        $this->assertArrayNotHasKey('bf_ancien_champ', $saved);
        $this->assertSame('https://', $saved['bf_site']);
    }

    public function testRepairIgnoresAKeyThatNoLongerMatchesAProblem()
    {
        $this->givenForm(
            [new EmailField($this->values([1 => 'bf_mail']), $this->services)],
            ['FicheUne' => ['id_fiche' => 'FicheUne', 'bf_mail' => 'ok@example.com']]
        );
        $saves = 0;
        $this->pageManager->method('save')->willReturnCallback(function () use (&$saves) {
            $saves++;

            return 0;
        });

        $result = $this->checker()->repair('1', [EntryChecker::INVALID_EMAIL . '::FicheUne::bf_mail']);

        $this->assertSame(0, $result['repaired']);
        $this->assertSame(0, $saves);
    }
}

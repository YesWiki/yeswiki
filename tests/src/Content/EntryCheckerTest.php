<?php

namespace YesWiki\Test\Content;

use Psr\Container\ContainerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use YesWiki\Content\Field\CheckboxListField;
use YesWiki\Content\Field\DateField;
use YesWiki\Content\Field\EmailField;
use YesWiki\Content\Field\FileField;
use YesWiki\Content\Field\LinkField;
use YesWiki\Content\Field\SelectEntryField;
use YesWiki\Content\Field\SelectListField;
use YesWiki\Content\Field\TagsField;
use YesWiki\Content\Field\TextareaField;
use YesWiki\Content\Field\TextField;
use YesWiki\Content\Service\ConditionsChecker;
use YesWiki\Content\Service\EntryChecker;
use YesWiki\Content\Service\EntryManager;
use YesWiki\Content\Service\FormManager;
use YesWiki\Content\Service\ListManager;
use YesWiki\Content\Service\PageManager;
use YesWiki\Content\Service\UrlReachability;
use YesWiki\Files\Service\AttachedFilePaths;
use YesWiki\Files\Service\Storage;
use YesWiki\Kernel\Service\HibernationService;
use YesWiki\Kernel\Service\RuntimeConfig;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/** Ported from doryphore-dev's EntryCheckerTest: same cases, ectoplasme's key names (`tag`, `form_id`, `updated_at`) and its Storage-backed FileField. */
class EntryCheckerTest extends YesWikiTestCase
{
    private ContainerInterface $services;
    private string $uploadDir;
    /** @var array<string, array<string, mixed>> */
    private array $probeResults = [];
    /** @var EntryManager&\PHPUnit\Framework\MockObject\Stub */
    private $entryManager;
    /** @var FormManager&\PHPUnit\Framework\MockObject\Stub */
    private $formManager;
    /** @var PageManager&\PHPUnit\Framework\MockObject\Stub */
    private $pageManager;

    protected function setUp(): void
    {
        parent::setUp();
        // the wiki is booted for `_t()` alone: every collaborator this test gives EntryChecker is a stub
        self::getWiki();
        $this->uploadDir = sys_get_temp_dir() . '/checkcontent-' . getmypid() . '-' . uniqid();
        mkdir($this->uploadDir, 0777, true);
        $this->probeResults = [];

        $this->useList([
            ['id' => 'rouge', 'label' => 'Rouge', 'children' => []],
            ['id' => 'vert', 'label' => 'Vert', 'children' => []],
        ]);

        $this->entryManager = $this->createStub(EntryManager::class);
        $this->entryManager->method('getAllEntriesTags')->willReturn(['FicheVivante']);
        $this->entryManager->method('decode')->willReturnCallback(fn ($body) => $body);
        $this->formManager = $this->createStub(FormManager::class);
        $this->pageManager = $this->createStub(PageManager::class);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->uploadDir . '/*') ?: [] as $path) {
            @chmod($path, 0644);
            unlink($path);
        }
        rmdir($this->uploadDir);
        parent::tearDown();
    }

    private function checker(): EntryChecker
    {
        $hibernation = $this->createStub(HibernationService::class);
        $hibernation->method('isWikiHibernated')->willReturn(false);
        $urlReachability = $this->createStub(UrlReachability::class);
        $urlReachability->method('probe')->willReturnCallback(function (array $urls) {
            return array_intersect_key($this->probeResults, array_flip($urls));
        });

        return new EntryChecker(
            $this->entryManager,
            $this->formManager,
            $this->pageManager,
            $hibernation,
            $urlReachability,
            new ConditionsChecker()
        );
    }

    private function fileField(string $name): FileField
    {
        return new FileField($this->values([0 => 'fichier', 1 => $name]), $this->services);
    }

    /**
     * @param array<int, mixed> $overrides
     *
     * @return array<int, mixed>
     */
    private function values(array $overrides): array
    {
        return array_replace(array_fill(0, 20, ''), $overrides);
    }

    /**
     * @param array<int, mixed>                        $fields
     * @param array<string, array<string, mixed>>      $entries
     */
    private function givenForm(array $fields, array $entries): void
    {
        $this->formManager->method('getOne')->willReturn(['id' => '1', 'prepared' => $fields]);
        $this->entryManager->method('search')->willReturn($entries);
    }

    /** @param list<array<string, mixed>> $nodes */
    private function useList(array $nodes): void
    {
        $listManager = $this->createStub(ListManager::class);
        $listManager->method('getOne')->willReturn(['nodes' => $nodes]);

        $runtimeConfig = $this->createStub(RuntimeConfig::class);
        $runtimeConfig->method('offsetGet')->willReturnCallback(function (string $key) {
            return match ($key) {
                'BAZ_MAX_CHECKBOXLIST_DISPLAY_MODE' => 'div',
                default => false,
            };
        });

        $params = $this->createStub(ParameterBagInterface::class);
        $params->method('get')->willReturnCallback(function (string $key) {
            return match ($key) {
                'max-upload-size' => 1000000,
                default => null,
            };
        });

        $paths = $this->createStub(AttachedFilePaths::class);
        $paths->method('uploadPath')->willReturn($this->uploadDir);
        $paths->method('isSafeMode')->willReturn(false);

        $storage = $this->createStub(Storage::class);
        $storage->method('exists')->willReturnCallback(fn (string $path) => is_file($path));

        $stubs = [
            ListManager::class => $listManager,
            RuntimeConfig::class => $runtimeConfig,
            ParameterBagInterface::class => $params,
            AttachedFilePaths::class => $paths,
            Storage::class => $storage,
        ];
        $this->services = new class($stubs) implements ContainerInterface {
            /** @var array<string, mixed> */
            private array $stubs;

            /** @param array<string, mixed> $stubs */
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

    /** @param array<string, mixed> $body */
    private function captureSave(array $body): SavedEntry
    {
        $this->pageManager->method('getOne')->willReturn(['tag' => $body['tag'], 'body' => $body]);
        $saved = new SavedEntry();
        $this->pageManager->method('save')->willReturnCallback(function ($tag, $newBody) use ($saved) {
            $saved->body = $newBody;
            ++$saved->calls;

            return 0;
        });

        return $saved;
    }

    private function textField(string $name, bool $required = false): TextField
    {
        return new TextField($this->values([1 => $name, 8 => $required ? 1 : 0]), $this->services);
    }

    public function testRequiredFieldLeftEmptyIsReportedAndNotRepairable(): void
    {
        $this->givenForm(
            [$this->textField('bf_titre', true), $this->textField('bf_note')],
            ['FicheUne' => ['tag' => 'FicheUne', 'bf_titre' => '', 'bf_note' => '']]
        );

        $problems = $this->checker()->check('1')['problems'];

        $this->assertArrayHasKey(EntryChecker::REQUIRED_EMPTY, $problems);
        $this->assertCount(1, $problems[EntryChecker::REQUIRED_EMPTY]);
        $row = $problems[EntryChecker::REQUIRED_EMPTY][0];
        $this->assertSame('bf_titre', $row['propertyName']);
        $this->assertNull($row['fix']);
    }

    public function testARequiredTextFieldOffersTheStandInTextAndWritesThePickedOne(): void
    {
        $body = ['tag' => 'FicheUne', 'form_id' => '1', 'bf_resume' => ''];
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

    public function testAnAdminCanReplaceTheStandInWithARealValue(): void
    {
        $body = ['tag' => 'FicheUne', 'form_id' => '1'];
        $this->givenForm([$this->textField('bf_note', true)], ['FicheUne' => $body]);
        $saved = $this->captureSave($body);

        $key = EntryChecker::REQUIRED_EMPTY . '::FicheUne::bf_note';
        $result = $this->checker()->repair('1', [$key], [$key => '  Atelier du jeudi  '], 'à compléter');

        $this->assertSame(1, $result['repaired']);
        $this->assertSame('Atelier du jeudi', $saved->body['bf_note']);
    }

    public function testABlankedStandInWritesNothing(): void
    {
        $body = ['tag' => 'FicheUne', 'form_id' => '1'];
        $this->givenForm([$this->textField('bf_note', true)], ['FicheUne' => $body]);
        $saved = $this->captureSave($body);

        $key = EntryChecker::REQUIRED_EMPTY . '::FicheUne::bf_note';
        $result = $this->checker()->repair('1', [$key], [$key => '   '], 'à compléter');

        $this->assertSame(0, $result['repaired']);
        $this->assertSame(0, $saved->calls);
    }

    public function testAnEmptyTextreplaceOffersNothing(): void
    {
        $this->givenForm(
            [$this->textField('bf_note', true)],
            ['FicheUne' => ['tag' => 'FicheUne', 'bf_note' => '']]
        );

        $rows = $this->checker()->check('1', '')['problems'][EntryChecker::REQUIRED_EMPTY];

        $this->assertSame('', $rows[0]['freeText']);
        $this->assertNull($rows[0]['fix']);
    }

    public function testATypedTextFieldGetsNoStandInBecauseItCannotHoldASentence(): void
    {
        $email = new TextField($this->values([0 => 'texte', 1 => 'bf_contact', 7 => 'email', 8 => 1]), $this->services);
        $date = new TextField($this->values([0 => 'texte', 1 => 'bf_quand', 7 => 'date', 8 => 1]), $this->services);
        $this->givenForm(
            [$email, $date],
            ['FicheUne' => ['tag' => 'FicheUne', 'bf_contact' => '', 'bf_quand' => '']]
        );

        $rows = $this->checker()->check('1', 'à compléter')['problems'][EntryChecker::REQUIRED_EMPTY];

        $this->assertCount(2, $rows);
        $this->assertSame('', $rows[0]['freeText']);
        $this->assertSame('', $rows[1]['freeText']);
    }

    public function testATagsFieldIsLeftAloneRatherThanCheckedAgainstItsOwnValues(): void
    {
        $tags = new TagsField($this->values([0 => 'tags', 1 => 'bf_tags']), $this->services);
        $this->givenForm(
            [$tags],
            [
                'FicheUne' => ['tag' => 'FicheUne', 'bf_tags' => 'velo,atelier'],
                'FicheDeux' => ['tag' => 'FicheDeux', 'bf_tags' => 'nouveautag'],
            ]
        );

        $result = $this->checker()->check('1');

        $this->assertSame([], $result['problems']);
        $this->assertSame([], $result['unchecked']);
    }

    public function testARequiredTagsFieldGetsNoPickerBecauseTagsAreFreeText(): void
    {
        $tags = new TagsField($this->values([0 => 'tags', 1 => 'bf_tags', 8 => 1]), $this->services);
        $this->givenForm([$tags], ['FicheUne' => ['tag' => 'FicheUne', 'bf_tags' => '']]);

        $rows = $this->checker()->check('1')['problems'][EntryChecker::REQUIRED_EMPTY];

        $this->assertCount(1, $rows);
        $this->assertSame([], $rows[0]['options']);
        $this->assertNull($rows[0]['fix']);
    }

    public function testAFieldPointingAtAMissingListIsListedAsUnchecked(): void
    {
        $this->useList([]);
        $orphanList = new SelectListField($this->values([1 => 'ListeDisparue', 6 => 'listeListeDisparue']), $this->services);
        $this->givenForm(
            [$orphanList],
            ['FicheUne' => ['tag' => 'FicheUne', 'listeListeDisparue' => 'rouge']]
        );

        $result = $this->checker()->check('1');

        $this->assertSame([], $result['problems']);
        $this->assertSame('ListeDisparue', $result['unchecked'][EntryChecker::NO_OPTIONS][0]['source']);
    }

    public function testAHealthyFormReportsNothingUnchecked(): void
    {
        $this->givenForm(
            [new SelectListField($this->values([1 => 'ListeCouleurs', 6 => 'listeListeCouleurs']), $this->services)],
            ['FicheUne' => ['tag' => 'FicheUne', 'listeListeCouleurs' => 'rouge']]
        );

        $this->assertSame([], $this->checker()->check('1')['unchecked']);
    }

    public function testRequiredEnumFieldOffersTheListOptionsWithItsDefaultPreselected(): void
    {
        $select = new SelectListField($this->values([1 => 'ListeCouleurs', 5 => 'vert', 6 => 'listeListeCouleurs', 8 => 1]), $this->services);
        $this->givenForm(
            [$select, $this->textField('bf_note', true)],
            ['FicheUne' => ['tag' => 'FicheUne', 'listeListeCouleurs' => '', 'bf_note' => '']]
        );

        $rows = $this->checker()->check('1')['problems'][EntryChecker::REQUIRED_EMPTY];

        $this->assertSame(['rouge' => 'Rouge', 'vert' => 'Vert'], $rows[0]['options']);
        $this->assertSame('vert', $rows[0]['suggested']);
        $this->assertFalse($rows[0]['multiple']);
        $this->assertSame([], $rows[1]['options']);
    }

    public function testAnOverlongListIsNotOfferedAsAPicker(): void
    {
        $nodes = [];
        for ($i = 0; $i <= 200; ++$i) {
            $nodes[] = ['id' => "opt$i", 'label' => "Option $i", 'children' => []];
        }
        $this->useList($nodes);
        $select = new SelectListField($this->values([1 => 'ListeLongue', 6 => 'listeListeLongue', 8 => 1]), $this->services);
        $this->givenForm([$select], ['FicheUne' => ['tag' => 'FicheUne', 'listeListeLongue' => '']]);

        $rows = $this->checker()->check('1')['problems'][EntryChecker::REQUIRED_EMPTY];

        $this->assertSame([], $rows[0]['options']);
    }

    public function testRequiredEnumRepairWritesThePickedOption(): void
    {
        $body = ['tag' => 'FicheUne', 'form_id' => '1', 'listeListeCouleurs' => ''];
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

    public function testRequiredEnumRepairRefusesAValueOutsideTheList(): void
    {
        $body = ['tag' => 'FicheUne', 'form_id' => '1', 'listeListeCouleurs' => ''];
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

    public function testRequiredCheckboxRepairJoinsThePickedOptions(): void
    {
        $body = ['tag' => 'FicheUne', 'form_id' => '1'];
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

    public function testUnknownCheckboxOptionsAreDroppedAndValidOnesKept(): void
    {
        $checkbox = new CheckboxListField($this->values([1 => 'ListeCouleurs', 6 => 'checkboxListeCouleurs']), $this->services);
        $this->givenForm(
            [$checkbox],
            ['FicheUne' => ['tag' => 'FicheUne', 'checkboxListeCouleurs' => 'rouge,fuchsia,vert']]
        );

        $rows = $this->checker()->check('1')['problems'][EntryChecker::UNKNOWN_OPTION];

        $this->assertCount(1, $rows);
        $this->assertSame('fuchsia', $rows[0]['detail']);
        $this->assertSame(['set' => 'rouge,vert'], $rows[0]['fix']);
        $this->assertTrue($rows[0]['multiple']);
    }

    public function testUnknownSingleValueOptionIsCleared(): void
    {
        $select = new SelectListField($this->values([1 => 'ListeCouleurs', 6 => 'listeListeCouleurs']), $this->services);
        $this->givenForm(
            [$select],
            ['FicheUne' => ['tag' => 'FicheUne', 'listeListeCouleurs' => 'fuchsia']]
        );

        $rows = $this->checker()->check('1')['problems'][EntryChecker::UNKNOWN_OPTION];

        $this->assertSame(['set' => ''], $rows[0]['fix']);
        $this->assertFalse($rows[0]['multiple']);
    }

    public function testMalformedEmailIsNormalisedWhenPossibleAndClearedOtherwise(): void
    {
        $this->givenForm(
            [new EmailField($this->values([1 => 'bf_mail']), $this->services)],
            [
                'FicheUne' => ['tag' => 'FicheUne', 'bf_mail' => ' Bob@Example.COM '],
                'FicheDeux' => ['tag' => 'FicheDeux', 'bf_mail' => "pas d'email"],
                'FicheTrois' => ['tag' => 'FicheTrois', 'bf_mail' => 'ok@example.com'],
            ]
        );

        $rows = $this->checker()->check('1')['problems'][EntryChecker::INVALID_EMAIL];

        $this->assertCount(2, $rows);
        $this->assertSame(['set' => 'bob@example.com'], $rows[0]['fix']);
        $this->assertSame(['set' => ''], $rows[1]['fix']);
    }

    public function testAnUploadedFileGoneFromDiskIsReportedAndClearable(): void
    {
        file_put_contents($this->uploadDir . '/present.pdf', 'x');
        $field = $this->fileField('bf_doc');
        $this->givenForm(
            [$field],
            [
                'FicheUne' => ['tag' => 'FicheUne', 'fichierbf_doc' => 'disparu.pdf'],
                'FicheDeux' => ['tag' => 'FicheDeux', 'fichierbf_doc' => 'present.pdf'],
            ]
        );

        $rows = $this->checker()->check('1')['problems'][EntryChecker::MISSING_FILE];

        $this->assertCount(1, $rows);
        $this->assertSame('FicheUne', $rows[0]['entryId']);
        $this->assertSame('disparu.pdf', $rows[0]['detail']);
        $this->assertSame(['set' => ''], $rows[0]['fix']);
    }

    public function testAnExternalFileIsProbedAndOnlyABadAnswerIsReported(): void
    {
        $this->probeResults = [
            'https://ok.example/logo.png' => ['fetched' => true, 'status' => 200, 'error' => null],
            'https://gone.example/logo.png' => ['fetched' => true, 'status' => 404, 'error' => null],
            'https://down.example/logo.png' => ['fetched' => true, 'status' => null, 'error' => 'Connection timed out'],
        ];
        $this->givenForm(
            [$this->fileField('bf_doc')],
            [
                'FicheUne' => ['tag' => 'FicheUne', 'fichierbf_doc' => 'https://ok.example/logo.png'],
                'FicheDeux' => ['tag' => 'FicheDeux', 'fichierbf_doc' => 'https://gone.example/logo.png'],
                'FicheTrois' => ['tag' => 'FicheTrois', 'fichierbf_doc' => 'https://down.example/logo.png'],
            ]
        );

        $rows = $this->checker()->check('1')['problems'][EntryChecker::UNREACHABLE_URL];

        $this->assertCount(2, $rows);
        $this->assertSame('https://gone.example/logo.png — 404', $rows[0]['detail']);
        $this->assertStringContainsString('Connection timed out', $rows[1]['detail']);
        $this->assertNull($rows[0]['fix']);
    }

    public function testAnExternalFileLeftAloneSaysWhy(): void
    {
        $this->probeResults = [
            'http://vieux.example/logo.png' => ['fetched' => false, 'reason' => 'not_https'],
        ];
        $this->givenForm(
            [$this->fileField('bf_doc')],
            ['FicheUne' => ['tag' => 'FicheUne', 'fichierbf_doc' => 'http://vieux.example/logo.png']]
        );

        $problems = $this->checker()->check('1')['problems'];

        $this->assertArrayNotHasKey(EntryChecker::UNREACHABLE_URL, $problems);
        $row = $problems[EntryChecker::UNFETCHED_URL][0];
        $this->assertStringContainsString(_t('BAZ_CHECKCONTENT_UNFETCHED_NOT_HTTPS'), $row['detail']);
        $this->assertSame('BAZ_CHECKCONTENT_FIX_NOTHING', $row['fixLabel']);
    }

    public function testUnreadableDateIsReportedButParsableOneIsNot(): void
    {
        $this->givenForm(
            [new DateField($this->values([1 => 'bf_date_debut_evenement']), $this->services)],
            [
                'FicheUne' => ['tag' => 'FicheUne', 'bf_date_debut_evenement' => 'bientôt'],
                'FicheDeux' => ['tag' => 'FicheDeux', 'bf_date_debut_evenement' => '2026-08-19'],
            ]
        );

        $rows = $this->checker()->check('1')['problems'][EntryChecker::INVALID_DATE];

        $this->assertCount(1, $rows);
        $this->assertSame('FicheUne', $rows[0]['entryId']);
        $this->assertSame(['set' => ''], $rows[0]['fix']);
    }

    public function testPlaceholderUrlIsClearedAndSurroundingSpacesAreTrimmed(): void
    {
        $this->givenForm(
            [new LinkField($this->values([1 => 'bf_site']), $this->services)],
            [
                'FicheUne' => ['tag' => 'FicheUne', 'bf_site' => 'https://'],
                'FicheDeux' => ['tag' => 'FicheDeux', 'bf_site' => ' https://example.com '],
                'FicheTrois' => ['tag' => 'FicheTrois', 'bf_site' => 'https://example.org'],
            ]
        );

        $rows = $this->checker()->check('1')['problems'][EntryChecker::INVALID_URL];

        $this->assertCount(2, $rows);
        $this->assertSame(['set' => ''], $rows[0]['fix']);
        $this->assertSame(['set' => 'https://example.com'], $rows[1]['fix']);
    }

    public function testAnAddressCarryingAccentsOrEmojiIsNotMalformed(): void
    {
        $this->givenForm(
            [new LinkField($this->values([1 => 'bf_url1']), $this->services)],
            [
                'FicheUne' => ['tag' => 'FicheUne', 'bf_url1' => 'https://www.linkedin.com/in/géraldine-louis/'],
                'FicheDeux' => ['tag' => 'FicheDeux', 'bf_url1' => 'https://www.linkedin.com/in/julie-chabaud-💚-b9923126'],
                'FicheTrois' => ['tag' => 'FicheTrois', 'bf_url1' => 'https://www.linkedin.com/in/laurent-marseault-⚖-114b221b'],
            ]
        );

        $this->assertSame([], $this->checker()->check('1')['problems']);
    }

    public function testAnAddressWithAnAccentedHostIsNotMalformed(): void
    {
        $this->givenForm(
            [new LinkField($this->values([1 => 'bf_url1']), $this->services)],
            [
                'FicheUne' => ['tag' => 'FicheUne', 'bf_url1' => 'https://exämple.com/chemin/café'],
                'FicheDeux' => ['tag' => 'FicheDeux', 'bf_url1' => 'https://café.fr'],
            ]
        );

        $this->assertSame([], $this->checker()->check('1')['problems']);
    }

    public function testTwoAddressesInOneFieldAreEditedRatherThanDeleted(): void
    {
        $crammed = 'https://www.linkedin.com/in/pwillemarck/ ; https://www.facebook.com/pwillemarck/';
        $body = ['tag' => 'FicheUne', 'form_id' => '1', 'bf_url1' => $crammed];
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

    public function testAnEditedAddressThatIsStillNotAnAddressIsRefused(): void
    {
        $body = ['tag' => 'FicheUne', 'form_id' => '1', 'bf_url1' => 'a ; b'];
        $this->givenForm([new LinkField($this->values([1 => 'bf_url1']), $this->services)], ['FicheUne' => $body]);
        $saved = $this->captureSave($body);

        $key = EntryChecker::INVALID_URL . '::FicheUne::bf_url1';
        $result = $this->checker()->repair('1', [$key], [$key => 'toujours pas une adresse']);

        $this->assertSame(0, $result['repaired']);
        $this->assertSame(0, $saved->calls);
    }

    public function testAnEmailWrittenWithAccentsIsNotMalformed(): void
    {
        $this->givenForm(
            [new EmailField($this->values([1 => 'bf_mail']), $this->services)],
            [
                'FicheUne' => ['tag' => 'FicheUne', 'bf_mail' => 'josé@exemple.fr'],
                'FicheDeux' => ['tag' => 'FicheDeux', 'bf_mail' => 'pas un mail'],
            ]
        );

        $rows = $this->checker()->check('1')['problems'][EntryChecker::INVALID_EMAIL];

        $this->assertCount(1, $rows);
        $this->assertSame('FicheDeux', $rows[0]['entryId']);
    }

    public function testReferenceToDeletedEntryIsReported(): void
    {
        $field = new SelectEntryField($this->values([1 => '2', 6 => 'listefiche2']), $this->services);
        $this->givenForm(
            [$field],
            [
                'FicheUne' => ['tag' => 'FicheUne', 'listefiche2' => 'FicheMorte'],
                'FicheDeux' => ['tag' => 'FicheDeux', 'listefiche2' => 'FicheVivante'],
            ]
        );

        $rows = $this->checker()->check('1')['problems'][EntryChecker::BROKEN_ENTRY];

        $this->assertCount(1, $rows);
        $this->assertSame('FicheMorte', $rows[0]['detail']);
        $this->assertSame(['set' => ''], $rows[0]['fix']);
    }

    public function testOrphanKeysAreReportedButDerivedAndReservedOnesAreNot(): void
    {
        $this->givenForm(
            [$this->textField('bf_titre'), new DateField($this->values([1 => 'bf_date']), $this->services)],
            ['FicheUne' => [
                'tag' => 'FicheUne',
                'form_id' => '1',
                'updated_at' => '2026-08-19 10:00:00',
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

    public function testRepairWritesOnlyTheSelectedProblems(): void
    {
        $body = [
            'tag' => 'FicheUne',
            'form_id' => '1',
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
        $saved = $this->captureSave($body);

        $result = $this->checker()->repair('1', [
            EntryChecker::INVALID_EMAIL . '::FicheUne::bf_mail',
            EntryChecker::ORPHAN_FIELD . '::FicheUne::bf_ancien_champ',
        ]);

        $this->assertSame(2, $result['repaired']);
        $this->assertSame(1, $result['entries']);
        $this->assertSame('', $saved->body['bf_mail']);
        $this->assertArrayNotHasKey('bf_ancien_champ', $saved->body);
        $this->assertSame('https://', $saved->body['bf_site']);
    }

    public function testAForcedValuePreselectsTheListOptionAndTicksTheRow(): void
    {
        $select = new SelectListField($this->values([1 => 'ListeCouleurs', 5 => 'vert', 6 => 'listeListeCouleurs', 8 => 1]), $this->services);
        $this->givenForm([$select], ['FicheUne' => ['tag' => 'FicheUne', 'listeListeCouleurs' => '']]);

        $rows = $this->checker()->check('1', '', ['listeListeCouleurs' => 'rouge'])['problems'][EntryChecker::REQUIRED_EMPTY];

        $this->assertSame('rouge', $rows[0]['suggested']);
        $this->assertTrue($rows[0]['forced']);
    }

    public function testAForcedValueOutsideTheListLeavesTheDefaultAlone(): void
    {
        $select = new SelectListField($this->values([1 => 'ListeCouleurs', 5 => 'vert', 6 => 'listeListeCouleurs', 8 => 1]), $this->services);
        $this->givenForm([$select], ['FicheUne' => ['tag' => 'FicheUne', 'listeListeCouleurs' => '']]);

        $rows = $this->checker()->check('1', '', ['listeListeCouleurs' => 'fuchsia'])['problems'][EntryChecker::REQUIRED_EMPTY];

        $this->assertSame('vert', $rows[0]['suggested']);
        $this->assertFalse($rows[0]['forced']);
    }

    public function testAForcedValueKeepsEveryOptionAMultipleFieldOffers(): void
    {
        $checkbox = new CheckboxListField($this->values([1 => 'ListeCouleurs', 6 => 'checkboxListeCouleurs', 8 => 1]), $this->services);
        $this->givenForm([$checkbox], ['FicheUne' => ['tag' => 'FicheUne']]);

        $rows = $this->checker()->check('1', '', ['checkboxListeCouleurs' => 'rouge,fuchsia,vert'])['problems'][EntryChecker::REQUIRED_EMPTY];

        $this->assertSame('rouge,vert', $rows[0]['suggested']);
        $this->assertTrue($rows[0]['multiple']);
    }

    public function testAForcedValueFillsAFieldNoStandInTextCouldFill(): void
    {
        $body = ['tag' => 'FicheUne', 'form_id' => '1', 'bf_quand' => ''];
        $date = new TextField($this->values([0 => 'texte', 1 => 'bf_quand', 7 => 'date', 8 => 1]), $this->services);
        $this->givenForm([$date], ['FicheUne' => $body]);
        $saved = $this->captureSave($body);

        $forced = ['bf_quand' => '2014-12-23'];
        $rows = $this->checker()->check('1', 'à compléter', $forced)['problems'][EntryChecker::REQUIRED_EMPTY];
        $this->assertSame('any', $rows[0]['freeText']);
        $this->assertSame('2014-12-23', $rows[0]['suggested']);
        $this->assertTrue($rows[0]['forced']);

        $key = $rows[0]['key'];
        $result = $this->checker()->repair('1', [$key], [$key => '2014-12-23'], 'à compléter', $forced);

        $this->assertSame(1, $result['repaired']);
        $this->assertSame('2014-12-23', $saved->body['bf_quand']);
    }

    public function testAForcedValueLeavesAFieldItDoesNotNameUntouched(): void
    {
        $this->givenForm(
            [$this->textField('bf_note', true)],
            ['FicheUne' => ['tag' => 'FicheUne', 'bf_note' => '']]
        );

        $rows = $this->checker()->check('1', '', ['bf_autre' => 'oui'])['problems'][EntryChecker::REQUIRED_EMPTY];

        $this->assertSame('', $rows[0]['freeText']);
        $this->assertFalse($rows[0]['forced']);
    }

    public function testAForcedValueDoesNotTouchAFieldThatHoldsAValue(): void
    {
        $this->givenForm(
            [new SelectListField($this->values([1 => 'ListeCouleurs', 6 => 'listeListeCouleurs', 8 => 1]), $this->services)],
            ['FicheUne' => ['tag' => 'FicheUne', 'listeListeCouleurs' => 'vert']]
        );

        $problems = $this->checker()->check('1', '', ['listeListeCouleurs' => 'rouge'])['problems'];

        $this->assertArrayNotHasKey(EntryChecker::REQUIRED_EMPTY, $problems);
    }

    public function testRepairIgnoresAKeyThatNoLongerMatchesAProblem(): void
    {
        $this->givenForm(
            [new EmailField($this->values([1 => 'bf_mail']), $this->services)],
            ['FicheUne' => ['tag' => 'FicheUne', 'bf_mail' => 'ok@example.com']]
        );
        $saves = 0;
        $this->pageManager->method('save')->willReturnCallback(function () use (&$saves) {
            ++$saves;

            return 0;
        });

        $result = $this->checker()->repair('1', [EntryChecker::INVALID_EMAIL . '::FicheUne::bf_mail']);

        $this->assertSame(0, $result['repaired']);
        $this->assertSame(0, $saves);
    }
}

/** What the page manager was asked to write, so a test can read it back. */
class SavedEntry
{
    /** @var array<string, mixed> */
    public array $body = [];
    public int $calls = 0;
}

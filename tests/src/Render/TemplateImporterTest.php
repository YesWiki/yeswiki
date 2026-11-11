<?php

namespace YesWiki\Test\Render;

use YesWiki\Render\Service\TemplateImporter;
use YesWiki\Render\Service\TemplateOverrides;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/** Ticket 18: files become `template` Contents, invalid ones stay where they are, and the database's overrides go back out as files. */
class TemplateImporterTest extends YesWikiTestCase
{
    private string $folder = '';

    private function importer(): TemplateImporter
    {
        return self::getWiki()->services->get(TemplateImporter::class);
    }

    private function overrides(): TemplateOverrides
    {
        return self::getWiki()->services->get(TemplateOverrides::class);
    }

    private function file(string $path, string $twig): void
    {
        @mkdir(\dirname($this->folder . '/' . $path), 0o755, true);
        file_put_contents($this->folder . '/' . $path, $twig);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->folder = sys_get_temp_dir() . '/yw-template-import-' . bin2hex(random_bytes(4));
        mkdir($this->folder, 0o755, true);
        $this->file('core/yw-test-import/good.twig', 'good {{ word }}');
        $this->file('core/yw-test-import/broken.twig', '{% if %}');
        $this->file('yw-test-import-top.twig', 'top');
    }

    protected function tearDown(): void
    {
        exec('chmod -R u+w ' . escapeshellarg($this->folder) . '; rm -rf ' . escapeshellarg($this->folder));
        foreach (['core/yw-test-import/good.twig', 'core/yw-test-import/broken.twig', 'yw-test-import-top.twig', 'core/yw-test-import-legacy.twig', 'bazar/yw-test-import-legacy.twig'] as $path) {
            $this->overrides()->delete($path);
        }
        parent::tearDown();
    }

    public function testValidFilesBecomeOverridesAndLeaveTheFolder(): void
    {
        $report = $this->importer()->importFrom($this->folder);

        $this->assertSame(['core/yw-test-import/good.twig', 'yw-test-import-top.twig'], $report['imported']);
        $this->assertSame(['core/yw-test-import/broken.twig'], array_keys($report['invalid']));
        $this->assertSame('good {{ word }}', $this->overrides()->read('core/yw-test-import/good.twig'));
        $this->assertNull($this->overrides()->read('core/yw-test-import/broken.twig'));
        $this->assertFileDoesNotExist($this->folder . '/core/yw-test-import/good.twig');
        $this->assertFileExists($this->folder . '/core/yw-test-import/broken.twig', 'an invalid file stays, to be fixed');
    }

    /** Doryphore kept bazar's overrides in custom/templates/bazar/; bazar's templates are core's now, so they come in under core/. */
    public function testABazarFolderComesInUnderCore(): void
    {
        $this->file('bazar/yw-test-import-legacy.twig', 'from doryphore');

        $report = $this->importer()->importFrom($this->folder, keepFiles: true);

        $this->assertContains('core/yw-test-import-legacy.twig', $report['imported']);
        $this->assertSame('from doryphore', $this->overrides()->read('core/yw-test-import-legacy.twig'));
        $this->assertNull($this->overrides()->read('bazar/yw-test-import-legacy.twig'));
    }

    public function testWhatTheDatabaseHoldsIsSkippedUnlessForced(): void
    {
        $this->overrides()->save('yw-test-import-top.twig', 'edited from the screen');

        $report = $this->importer()->importFrom($this->folder, keepFiles: true);
        $this->assertContains('yw-test-import-top.twig', $report['skipped']);
        $this->assertSame('edited from the screen', $this->overrides()->read('yw-test-import-top.twig'));

        $this->importer()->importFrom($this->folder, force: true, keepFiles: true);
        $this->assertSame('top', $this->overrides()->read('yw-test-import-top.twig'));
    }

    public function testADryRunWritesNothing(): void
    {
        $report = $this->importer()->importFrom($this->folder, dryRun: true);

        $this->assertSame(['core/yw-test-import/good.twig', 'yw-test-import-top.twig'], $report['imported']);
        $this->assertSame(['core/yw-test-import/good.twig', 'yw-test-import-top.twig'], $report['deleted']);
        $this->assertNull($this->overrides()->read('core/yw-test-import/good.twig'));
        $this->assertFileExists($this->folder . '/core/yw-test-import/good.twig');
    }

    public function testAReadOnlyFolderIsImportedAndItsFilesKept(): void
    {
        chmod($this->folder . '/core/yw-test-import', 0o555);

        $report = $this->importer()->importFrom($this->folder);

        $this->assertContains('core/yw-test-import/good.twig', $report['kept']);
        $this->assertFileExists($this->folder . '/core/yw-test-import/good.twig');
        $this->assertSame('good {{ word }}', $this->overrides()->read('core/yw-test-import/good.twig'));
    }

    public function testExportingWritesTheDatabasesOverridesAsFiles(): void
    {
        $this->overrides()->save('core/yw-test-import/good.twig', 'from the database');
        $out = $this->folder . '/out';

        $written = $this->importer()->exportTo($out);

        $this->assertContains('core/yw-test-import/good.twig', $written);
        $this->assertSame('from the database', file_get_contents($out . '/core/yw-test-import/good.twig'));
    }
}

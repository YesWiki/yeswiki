<?php

namespace YesWiki\Test\Kernel;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use YesWiki\Kernel\Entity\ExtensionFolders;
use YesWiki\Kernel\Entity\ExtensionManifest;
use YesWiki\Kernel\Entity\PlatformConstraint;

/** What an extension is (ADR-0029): a folder, switched on per Instance, describing itself in a composer.json. */
class ExtensionContractTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/extension-contract-' . bin2hex(random_bytes(4));
        foreach (['program/extensions/lms', 'program/extensions/qrcards', 'program/extensions/.git', 'instance/custom/extensions/lms', 'instance/custom/extensions/contrib'] as $dir) {
            mkdir($this->root . '/' . $dir, 0755, true);
        }
        file_put_contents($this->root . '/program/extensions/README.md', 'not an extension');
    }

    protected function tearDown(): void
    {
        exec('rm -rf ' . escapeshellarg($this->root));
    }

    public function testEveryVisibleFolderIsAnExtensionAndTheInstancesOwnShadowsTheSharedOne(): void
    {
        $visible = ExtensionFolders::visible($this->root . '/program', $this->root . '/instance');

        $this->assertSame(['lms', 'qrcards', 'contrib'], array_keys($visible));
        $this->assertSame($this->root . '/instance/custom/extensions/lms/', $visible['lms']);
    }

    public function testAnExtensionRunsOnlyOnceTheInstanceNamesIt(): void
    {
        $visible = ExtensionFolders::visible($this->root . '/program', $this->root . '/instance');

        $this->assertSame([], ExtensionFolders::active($visible, null), 'no list, nothing runs');
        $this->assertSame(['qrcards'], array_keys(ExtensionFolders::active($visible, ['qrcards', 'gone'])));
    }

    public function testTheManifestGivesLabelsPerLanguageAndNoVersionUntilOneIsInjected(): void
    {
        $dir = $this->root . '/program/extensions/qrcards';
        $this->assertNull(ExtensionManifest::of('qrcards', $dir)->version());
        $this->assertSame('qrcards', ExtensionManifest::of('qrcards', $dir)->label('fr'), 'no manifest: the folder names it');

        file_put_contents($dir . '/composer.json', json_encode([
            'name' => 'yeswiki/extension-qrcards',
            'description' => 'Cards with QR codes',
            'version' => '5.1.0',
            'require' => ['php' => '>=8.3', 'ext-json' => '*', 'ext-nonexistent' => '*', 'vendor/lib' => '^2'],
            'extra' => ['yeswiki' => [
                'label' => ['fr' => 'Cartes QR', 'en' => 'QR cards'],
                'requires-extensions' => ['lms'],
            ]],
        ]));
        $manifest = ExtensionManifest::of('qrcards', $dir);

        $this->assertSame('5.1.0', $manifest->version());
        $this->assertSame('Cartes QR', $manifest->label('fr'));
        $this->assertSame('QR cards', $manifest->label('de'), 'an untranslated language falls back to English');
        $this->assertSame('Cards with QR codes', $manifest->description('fr'));
        $this->assertSame(['php' => '>=8.3', 'ext-json' => '*', 'ext-nonexistent' => '*'], $manifest->platformRequirements());
        $this->assertSame(['PHP extension ext-nonexistent', 'extension lms active'], $manifest->unmetRequirements([]));
        $this->assertSame(['PHP extension ext-nonexistent'], $manifest->unmetRequirements(['lms']));
    }

    /** @return array<string, array{string, string, bool}> */
    public static function constraints(): array
    {
        return [
            'any' => ['*', '8.3.4', true],
            'floor met' => ['>=8.3', '8.3.0', true],
            'floor missed' => ['>=8.4', '8.3.9', false],
            'caret same major' => ['^8.3', '8.5.1', true],
            'caret next major' => ['^8.3', '9.0.0', false],
            'tilde two parts' => ['~8.3', '8.9.0', true],
            'tilde three parts' => ['~8.3.1', '8.4.0', false],
            'wildcard' => ['8.3.*', '8.3.12', true],
            'bare minor' => ['8.3', '8.3.5', true],
            'range' => ['>=8.3 <8.5', '8.5.0', false],
            'alternatives' => ['^7.4 || ^8.3', '8.4.0', true],
        ];
    }

    #[DataProvider('constraints')]
    public function testThePhpConstraintsAnExtensionStates(string $constraint, string $version, bool $allowed): void
    {
        $this->assertSame($allowed, PlatformConstraint::allows($constraint, $version));
    }
}

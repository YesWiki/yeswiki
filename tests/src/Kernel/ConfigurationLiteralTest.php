<?php

namespace YesWiki\Test\Kernel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use YesWiki\Admin\Service\ArchiveService;
use YesWiki\Kernel\Entity\ConfigurationFile;
use YesWiki\Kernel\Service\ConfigurationLiteral;
use YesWiki\Kernel\Service\ConfigurationService;

/** An archived configuration is read as data, never run. */
#[CoversClass(ConfigurationLiteral::class)]
class ConfigurationLiteralTest extends TestCase
{
    public function testWhatTheWikiWritesIsReadBackUnchanged(): void
    {
        $settings = [
            'base_url' => 'https://example.org/?',
            'quote' => "l'été \\ \"cité\" \$x {\$y}",
            'nul' => "a\0b",
            'count' => 365,
            'negative' => -3,
            'ratio' => 0.5,
            'huge' => 1.0E+25,
            'debug' => false,
            'on' => true,
            'nothing' => null,
            'list' => ['a', 'b'],
            'nested' => ['acls' => ['read' => '*', 'write' => '+'], 7 => 'seven'],
            'empty' => [],
        ];
        $file = new ConfigurationFile('unused');
        foreach ($settings as $key => $value) {
            $file[$key] = $value;
        }

        $this->assertSame($settings, ConfigurationLiteral::parse((new ConfigurationService())->getContentToWrite($file)));
    }

    public function testAnOldWakkaConfigWithLongArraysIsRead(): void
    {
        $old = "<?php\n// written by an older YesWiki\n\$wakkaConfig = array(\n  'wakka_version' => '0.1.1',\n  \"name\" => \"Mon \\x41\\u{e9}\\101\\n\",\n  'menu' => array ( 0 => 'A', 1 => 'B', ),\n  'flag' => TRUE,\n);\n?>\n";

        $this->assertSame(
            ['wakka_version' => '0.1.1', 'name' => "Mon A\u{e9}A\n", 'menu' => ['A', 'B'], 'flag' => true],
            ConfigurationLiteral::parse($old)
        );
    }

    /** @return array<string, array{string}> */
    public static function codeThatIsNotData(): array
    {
        return [
            'a call' => ["<?php \$yeswikiConfig = ['a' => system('id')];"],
            'a call before' => ["<?php system('id'); \$yeswikiConfig = [];"],
            'a statement after' => ["<?php \$yeswikiConfig = []; file_put_contents('x', 'y');"],
            'a constant' => ["<?php \$yeswikiConfig = ['a' => PHP_EOL];"],
            'a magic constant' => ["<?php \$yeswikiConfig = ['a' => __DIR__];"],
            'a variable' => ["<?php \$yeswikiConfig = ['a' => \$_SERVER['HOME']];"],
            'interpolation' => ["<?php \$yeswikiConfig = ['a' => \"{\$x}\"];"],
            'a backtick' => ["<?php \$yeswikiConfig = ['a' => `id`];"],
            'a heredoc' => ["<?php \$yeswikiConfig = ['a' => <<<EOT\nx\nEOT];"],
            'arithmetic' => ["<?php \$yeswikiConfig = ['a' => 1 + 1];"],
            'a new object' => ["<?php \$yeswikiConfig = ['a' => new \\ArrayObject()];"],
            'another variable' => ['<?php $config = [];'],
            'not an array' => ["<?php \$yeswikiConfig = 'x';"],
            'text around' => ["#!/bin/sh\n<?php \$yeswikiConfig = [];"],
            'unfinished' => ["<?php \$yeswikiConfig = ['a' => 1"],
        ];
    }

    #[DataProvider('codeThatIsNotData')]
    public function testCodeIsRefused(string $source): void
    {
        $this->expectException(\UnexpectedValueException::class);

        ConfigurationLiteral::parse($source);
    }

    public function testARestoreRefusesAnArchivedConfigurationThatIsCode(): void
    {
        $this->expectExceptionMessage('The archived configuration was not read');

        ArchiveService::archivedSettings("<?php \$yeswikiConfig = ['a' => exec('touch /tmp/pwned')];");
    }

    public function testARestoreKeepsOnlyNamedSettings(): void
    {
        $this->assertSame(['a' => 1], ArchiveService::archivedSettings("<?php\n\$yeswikiConfig = ['a' => 1, 0 => 'b'];\n"));
    }
}

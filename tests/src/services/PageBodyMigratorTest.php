<?php

namespace YesWiki\Test\Content\Service;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use YesWiki\Content\Service\PageBodyMigrator;

require_once 'tests/YesWikiTestCase.php';

/**
 * Ticket 09's migration rewrites every revision of the central table on installs the maintainer does not control, so its decision function is a pure static and gets tested exhaustively here -- no wiki, no database, no fixtures to drift.
 */
class PageBodyMigratorTest extends TestCase
{
    /** The single most dangerous case. */
    public function testMarkupOpeningWithAnActionCallIsTreatedAsMarkup(): void
    {
        $markup = '{{entrylist id="1" template="map"}}';

        $result = PageBodyMigrator::classify($markup, false);

        $this->assertSame(PageBodyMigrator::STATUS_CONVERTED, $result['status']);
        $this->assertSame(['content' => $markup], $result['body']);
    }

    /**
     * A page whose entire text is a JSON object decodes cleanly, so "does it decode?" is not enough either: without the `content` check its markup would be swallowed and the page would render as whatever those keys happened to be.
     */
    public function testAPageWhoseMarkupIsItselfValidJsonIsStillWrapped(): void
    {
        $markup = '{"note": "this is a page about JSON"}';

        $result = PageBodyMigrator::classify($markup, false);

        $this->assertSame(PageBodyMigrator::STATUS_CONVERTED, $result['status']);
        $this->assertSame(['content' => $markup], $result['body']);
    }

    public function testAnAlreadyConvertedPageIsLeftAlone(): void
    {
        $result = PageBodyMigrator::classify('{"content":"# Title","keywords":["a"]}', false);

        $this->assertSame(PageBodyMigrator::STATUS_ALREADY_JSON, $result['status']);
        $this->assertSame(['content' => '# Title', 'keywords' => ['a']], $result['body']);
    }

    public function testStructuredContentKeepsItsFieldMap(): void
    {
        $entry = '{"bf_titre":"Bordeaux","form_id":"2","tag":"Bordeaux"}';

        $result = PageBodyMigrator::classify($entry, true);

        $this->assertSame(PageBodyMigrator::STATUS_ALREADY_JSON, $result['status']);
        $this->assertSame(['bf_titre' => 'Bordeaux', 'form_id' => '2', 'tag' => 'Bordeaux'], $result['body']);
    }

    /** A structured body that will not decode is corrupt, not markup. */
    public function testCorruptStructuredContentIsPreservedRatherThanDropped(): void
    {
        $result = PageBodyMigrator::classify('{"bf_titre":"unterminated', true);

        $this->assertSame(PageBodyMigrator::STATUS_CONVERTED, $result['status']);
        $this->assertSame(['content' => '{"bf_titre":"unterminated'], $result['body']);
    }

    /** File-type Content stored '' before its attributes moved into the body. */
    #[DataProvider('emptyBodies')]
    public function testAnEmptyBodyBecomesAnEmptyObject(?string $stored): void
    {
        foreach ([true, false] as $isStructured) {
            $result = PageBodyMigrator::classify($stored, $isStructured);

            $this->assertSame(PageBodyMigrator::STATUS_EMPTY, $result['status']);
            $this->assertSame([], $result['body']);
        }
    }

    /**
     * @return array<string, array{?string}>
     */
    public static function emptyBodies(): array
    {
        return [
            'empty string' => [''],
            'whitespace' => ["  \n "],
            'null' => [null],
        ];
    }

    /** Running the migration twice must be a no-op. */
    public function testClassifyIsIdempotent(): void
    {
        foreach ([['# markup', false], ['{"bf_titre":"x"}', true], ['', false]] as [$stored, $structured]) {
            $once = PageBodyMigrator::classify($stored, $structured);
            $encoded = json_encode((object)$once['body'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $twice = PageBodyMigrator::classify((string)$encoded, $structured);

            $this->assertSame($once['body'], $twice['body'], "second pass changed: {$stored}");
            $this->assertSame(PageBodyMigrator::STATUS_ALREADY_JSON, $twice['status'], "second pass rewrote: {$stored}");
        }
    }

    /** Markup is preserved byte for byte -- only its container changes. */
    #[DataProvider('trickyMarkup')]
    public function testMarkupSurvivesVerbatim(string $label, string $markup): void
    {
        $result = PageBodyMigrator::classify($markup, false);

        $this->assertSame($markup, $result['body']['content'], $label);
    }

    /**
     * @return array<int, array{string, string}>
     */
    public static function trickyMarkup(): array
    {
        return [
            ['accents', "# Bac à sable\nécrire dans cette page"],
            ['double quotes in an action', '{{nav links="A, B" titles="S\'inscrire"}}'],
            ['backslashes', 'a \\ backslash and a \\n that is not a newline'],
            ['blank lines', "one\n\n\nfour"],
            ['html', '<div class="x">&amp;</div>'],
        ];
    }
}

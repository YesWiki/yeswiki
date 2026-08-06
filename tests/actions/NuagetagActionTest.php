<?php

namespace YesWiki\Test\Actions;

use PHPUnit\Framework\Attributes\DataProvider;
use YesWiki\Content\Service\TripleStore;
use YesWiki\Render\Service\ActionRunner;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/**
 * Regression tests for the SQL injection in the {{tagcloud tags="..."}} action :
 * the "tags" parameter was concatenated unescaped into "AND value IN (...)",
 * allowing a UNION-based injection (e.g. via a trailing-backslash token that
 * flips MySQL string-quote parity) to exfiltrate arbitrary table data.
 */
class NuagetagActionTest extends YesWikiTestCase
{
    private const TAG_PROPERTY = 'http://outils-reseaux.org/_vocabulary/tag';
    private const SECRET_PROPERTY = 'http://example.org/_vocabulary/not-a-tag';
    private const LEGIT_TAG_VALUE = 'regressiontesttag';
    private const LEGIT_RESOURCE = 'NuagetagRegressionTestPage';
    private const SECRET_VALUE = 'SECRET_LEAK_MARKER_VALUE';
    private const SECRET_RESOURCE = 'SECRET_LEAK_MARKER_RESOURCE';

    private static ?TripleStore $tripleStore = null;

    public static function setUpBeforeClass(): void
    {
        $wiki = self::getWiki();
        self::$tripleStore = $wiki->services->get(TripleStore::class);
        // a legitimate tag, reachable through the normal (non-malicious) code path
        self::$tripleStore->create(self::LEGIT_RESOURCE, '', self::LEGIT_TAG_VALUE, '', self::TAG_PROPERTY);
        // a "secret" triple under a *different* property ; it must never be reachable
        // through the tags="..." parameter, only a successful SQL injection could leak it
        self::$tripleStore->create(self::SECRET_RESOURCE, '', self::SECRET_VALUE, '', self::SECRET_PROPERTY);
    }

    public static function tearDownAfterClass(): void
    {
        self::$tripleStore->delete(self::LEGIT_RESOURCE, '', self::LEGIT_TAG_VALUE, '', self::TAG_PROPERTY);
        self::$tripleStore->delete(self::SECRET_RESOURCE, '', self::SECRET_VALUE, '', self::SECRET_PROPERTY);
    }

    #[DataProvider('dataProviderTestInjectionPayloadsDoNotLeakData')]
    public function testInjectionPayloadsDoNotLeakData(string $payload)
    {
        $wiki = $this->getWiki();
        $html = $wiki->services->get(ActionRunner::class)->action('tagcloud', ['tags' => $payload]);

        $this->assertStringNotContainsString(self::SECRET_VALUE, $html, "payload leaked secret data: $payload");
        $this->assertStringNotContainsString(self::SECRET_RESOURCE, $html, "payload leaked secret data: $payload");
    }

    public static function dataProviderTestInjectionPayloadsDoNotLeakData(): array
    {
        $tablePrefix = self::getWiki()->config['table_prefix'];

        // exact reported technique : a lone-backslash token (split off by the code's own
        // explode(' ', $tags)), followed by ONE real space, then a comma-free JOIN-based
        // UNION (using /**/ in place of spaces, since literal commas get corrupted by the
        // code's str_replace(',', '","', ...) step), ending in '#' to comment out the tail.
        // Verified end-to-end against a real MariaDB 11.4 connection before/after the fix.
        $joinUnion = static function (string $col1, string $table1, string $col2, string $table2, string $whereProperty): string {
            return ')union/**/select/**/*/**/from/**/(select/**/' . $col1 . '/**/as/**/a/**/from/**/'
                . $table1 . '/**/where/**/property="' . $whereProperty . '")x/**/join/**/(select/**/'
                . $col2 . '/**/as/**/b/**/from/**/' . $table2 . '/**/where/**/property="' . $whereProperty . '")y#';
        };

        return [
            'reported PoC technique (lone-backslash token + comma-free JOIN UNION)' => [
                '\\ ' . $joinUnion('value', $tablePrefix . 'triples', 'resource', $tablePrefix . 'triples', self::SECRET_PROPERTY),
            ],
            'bare trailing backslash' => [
                'sometag\\',
            ],
        ];
    }

    public function testLegitimateTagStillWorks()
    {
        // sanity check : the fix must not break normal, non-malicious usage, and this also
        // proves the test fixture/seeding actually exercises the query (a query that always
        // failed would make the injection assertions above vacuous)
        $wiki = $this->getWiki();
        $html = $wiki->services->get(ActionRunner::class)->action('tagcloud', ['tags' => self::LEGIT_TAG_VALUE]);

        $this->assertStringContainsString(self::LEGIT_RESOURCE, $html);
    }

    public function testTagValueAndResourceAreEscapedInOutput()
    {
        // stored XSS : "value" (tag name) and "resource" (page tag) come straight from the
        // triples table and were echoed unescaped, both directly as page HTML and inside
        // data-title/data-content attributes rendered as HTML by Bootstrap's popover (tag.js
        // initializes it with html:true).
        $xssTagValue = '<script>alert(document.domain)</script>';
        $xssResource = '"><img src=x onerror=alert(document.domain)>';
        $tripleStore = $this->getWiki()->services->get(TripleStore::class);
        $tripleStore->create($xssResource, '', $xssTagValue, '', self::TAG_PROPERTY);

        try {
            $html = $this->getWiki()->services->get(ActionRunner::class)->action('tagcloud', ['tags' => $xssTagValue]);

            // no raw <script>/<img onerror=...> anywhere in the page HTML
            $this->assertDoesNotMatchRegularExpression('/<script\b/i', $html);
            $this->assertDoesNotMatchRegularExpression('/<img\b[^>]*\son[a-z]+\s*=/i', $html);

            // the popover attributes are rendered as HTML client-side (html:true) once the
            // browser HTML-attribute-decodes them ; simulate that one decoding step and check
            // the result is still inert (i.e. was double-escaped), not raw markup
            foreach (['data-title', 'data-content'] as $attr) {
                if (preg_match('/' . $attr . '="([^"]*)"/', $html, $matches)) {
                    $decodedOnce = html_entity_decode($matches[1], ENT_QUOTES);
                    $this->assertDoesNotMatchRegularExpression(
                        '/<script\b/i',
                        $decodedOnce,
                        "$attr is exploitable via popover html:true rendering: $decodedOnce"
                    );
                    $this->assertDoesNotMatchRegularExpression(
                        '/<img\b[^>]*\son[a-z]+\s*=/i',
                        $decodedOnce,
                        "$attr is exploitable via popover html:true rendering: $decodedOnce"
                    );
                }
            }
        } finally {
            $tripleStore->delete($xssResource, '', $xssTagValue, '', self::TAG_PROPERTY);
        }
    }
}

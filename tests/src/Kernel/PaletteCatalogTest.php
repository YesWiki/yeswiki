<?php

namespace YesWiki\Test\Kernel;

use PHPUnit\Framework\TestCase;

require_once 'tests/YesWikiTestCase.php';

/**
 * Ticket 36 folded `docs/actions/lang/` into the main catalog. That directory was never
 * checked against anything -- three of its palette entries described actions that had been
 * retired and nothing noticed -- and the merge carried 92 keys nothing asks for any more:
 * the retired `textsearch` / `myfavorites` / `bazarrecordsindex` entries, and older spellings
 * of settings that the ported Components renamed (`AB_templates_section_bgcolor_label` became
 * `..._bgcolor_full_label`, and so on).
 *
 * The keys live in the catalog now, so the catalog's own rule applies to them: a key exists
 * because something asks for it.
 */
class PaletteCatalogTest extends TestCase
{
    /**
     * The one place a palette `_t()` key is not a literal: `EntryListAction` names a webmaster's
     * own `custom/templates/bazar/*.twig` after `AB_<file>_label` if the catalog carries one.
     * The file is user data, so there is no literal to scan for.
     */
    private const DYNAMIC_KEY_PATTERN = '/^AB_bazar[a-z0-9]+_label$/';

    /** @return list<string> */
    private function catalogKeys(): array
    {
        $keys = [];
        foreach (glob(dirname(__DIR__, 3) . '/src/lang/yeswiki_*.php') ?: [] as $path) {
            foreach (array_keys((array)require $path) as $key) {
                if (is_string($key) && str_starts_with($key, 'AB_')) {
                    $keys[$key] = true;
                }
            }
        }
        ksort($keys);

        return array_keys($keys);
    }

    /**
     * @return array<string, true>
     *
     * `src` whole, not a list of module directories. The list was enumerated when this test
     * was written and went stale the first time a module moved: ticket 39 lifted the contact
     * actions into `src/Contact/`, and every `AB_contact_*` key immediately looked orphaned
     * because nothing was reading the directory they had moved to. A test that has to be
     * edited whenever a module is added is a test that will one day be edited wrongly.
     */
    private function keysAskedForInCode(): array
    {
        $root = dirname(__DIR__, 3);
        $asked = [];
        foreach (['src', 'javascripts', 'templates'] as $dir) {
            $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root . '/' . $dir));
            foreach ($files as $file) {
                if (!$file->isFile() || !in_array($file->getExtension(), ['php', 'js', 'twig'], true)) {
                    continue;
                }
                // vendored bundles are minified third-party code, not this catalog's readers;
                // src/lang is the catalog itself, and would make every key look like its own reader
                if (str_contains($file->getPathname(), '/javascripts/vendor/')
                    || str_contains($file->getPathname(), '/src/lang/')) {
                    continue;
                }
                // the prefix must start a word (`TAB_SIZE` is not `AB_SIZE`), and a key may
                // carry a hyphen (`AB_attach_class_displaylink_new-window`)
                if (preg_match_all('/(?<![A-Za-z0-9_])AB_[A-Za-z0-9_-]+/', (string)file_get_contents($file->getPathname()), $matches)) {
                    foreach ($matches[0] as $key) {
                        $asked[$key] = true;
                    }
                }
            }
        }

        return $asked;
    }

    public function testEveryPaletteKeyIsAskedForSomewhere(): void
    {
        $asked = $this->keysAskedForInCode();

        $orphans = array_values(array_filter(
            $this->catalogKeys(),
            fn (string $key) => !isset($asked[$key]) && !preg_match(self::DYNAMIC_KEY_PATTERN, $key)
        ));

        $this->assertSame([], $orphans, 'these AB_* keys are in the catalog but nothing reads them');
    }

    /**
     * The other direction: a key a Component names but no catalog defines renders as its own
     * name in the rail, which is how `AB_advanced_action_textsearch_label` looked fine for as
     * long as it did. French is the reference catalog -- it is the only complete one.
     */
    public function testEveryKeyAComponentNamesIsInTheReferenceCatalog(): void
    {
        $french = (array)require dirname(__DIR__, 3) . '/src/lang/yeswiki_fr.php';

        $undefined = array_values(array_filter(
            array_keys($this->keysAskedForInCode()),
            fn (string $key) => !isset($french[$key])
        ));
        sort($undefined);

        $this->assertSame([], $undefined, 'these AB_* keys would render as their own name');
    }
}

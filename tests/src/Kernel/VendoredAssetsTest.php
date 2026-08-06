<?php

namespace YesWiki\Test\Kernel;

use PHPUnit\Framework\TestCase;

/**
 * Everything a module imports out of `javascripts/vendor/` must be produced by the script
 * that fills that directory.
 *
 * `javascripts/vendor/` is **gitignored**. Nothing in it is shipped by a checkout: it is
 * rebuilt on every deploy by `src/extract-files-from-node-modules.sh`. So a file copied in by
 * hand works perfectly on the machine that copied it and exists nowhere else -- and the
 * failure lands on the *instance*, as a `<script type="module">` fetching a path the server
 * answers with the wiki's own HTML ("blocked due to a disallowed MIME type (text/html)").
 *
 * That is how `mode-css.js` and `mode-twig.js` reached two releases missing: both were
 * vendored by copying the file, which is the visible half of the procedure, while the half
 * that matters is the line in the script.
 *
 * A file comparison, no wiki and no database -- the same shape as SeededMigrationListTest,
 * which exists because a hand-maintained list went stale three times.
 */
class VendoredAssetsTest extends TestCase
{
    private const ROOT = __DIR__ . '/../../..';

    private const SCRIPT = self::ROOT . '/src/extract-files-from-node-modules.sh';

    /** Modules that import siblings out of `javascripts/vendor/` by relative path. */
    private const IMPORTERS = [
        '/javascripts/ace-wrapper.js',
    ];

    public function testEveryVendoredModuleImportIsProducedByTheExtractionScript(): void
    {
        $script = (string)file_get_contents(self::SCRIPT);
        $missing = [];

        foreach ($this->vendorImports() as $importer => $paths) {
            foreach ($paths as $path) {
                // the script names its destination as the second argument of copy_js/cp
                if (!str_contains($script, $path)) {
                    $missing[] = "{$path} (imported by {$importer})";
                }
            }
        }

        $this->assertSame(
            [],
            $missing,
            'These files are imported from javascripts/vendor/ but nothing in '
            . basename(self::SCRIPT) . ' creates them. javascripts/vendor/ is gitignored, so '
            . 'they exist only where someone copied them by hand and every deployment 404s on '
            . 'them. Add a copy_js line for each.'
        );
    }

    /**
     * ...and the files are actually there right now, which is a different question: the line
     * can name a source path that the installed package does not have.
     */
    public function testEveryVendoredModuleImportExistsOnDisk(): void
    {
        foreach ($this->vendorImports() as $importer => $paths) {
            foreach ($paths as $path) {
                $this->assertFileExists(
                    self::ROOT . '/' . $path,
                    "{$path} is imported by {$importer} -- run src/extract-files-from-node-modules.sh"
                );
            }
        }
    }

    /**
     * @return array<string, list<string>> importer => the vendor paths it imports
     */
    private function vendorImports(): array
    {
        $found = [];
        foreach (self::IMPORTERS as $importer) {
            $source = (string)file_get_contents(self::ROOT . $importer);
            // `import './vendor/ace/mode-twig.js'` -- side-effect imports, relative to
            // javascripts/, which is where every one of these modules lives
            preg_match_all("~import\s+'\./(vendor/[^']+)'~", $source, $matches);
            // array_values after array_unique: unique preserves keys, so dropping a duplicate
            // leaves a gap and the result stops being a list
            $found[$importer] = array_values(array_map(
                fn (string $relative) => 'javascripts/' . $relative,
                array_unique($matches[1])
            ));
        }

        return $found;
    }
}

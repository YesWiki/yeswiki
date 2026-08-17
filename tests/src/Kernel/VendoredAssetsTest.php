<?php

namespace YesWiki\Test\Kernel;

use PHPUnit\Framework\TestCase;

/**
 * Everything a module imports out of `javascripts/vendor/` must be produced by the script that fills that directory.
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
     * ...and the files are actually there right now, which is a different question: the line can name a source path that the installed package does not have.
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

            preg_match_all("~import\s+'\./(vendor/[^']+)'~", $source, $matches);

            $found[$importer] = array_values(array_map(
                fn (string $relative) => 'javascripts/' . $relative,
                array_unique($matches[1])
            ));
        }

        return $found;
    }
}

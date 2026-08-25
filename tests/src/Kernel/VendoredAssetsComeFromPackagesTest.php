<?php

namespace YesWiki\Test\Kernel;

use PHPUnit\Framework\TestCase;

/** `javascripts/vendor/` and `styles/vendor/` hold only what postinstall writes from package.json. */
class VendoredAssetsComeFromPackagesTest extends TestCase
{
    private const ROOT = __DIR__ . '/../../..';

    /** Directories postinstall owns. */
    private const GENERATED = ['javascripts/vendor', 'styles/vendor'];

    public function testNothingUnderVendorIsCommitted(): void
    {
        $tracked = $this->trackedUnder(self::GENERATED);

        $this->assertSame([], $tracked, "These are committed under a directory postinstall rewrites.\n"
            . "If a package provides it, add the package to package.json and copy it in\n"
            . "src/extract-files-from-node-modules.sh. If nothing does, it belongs in\n"
            . 'styles/third-party/ with a comment saying where it came from.');
    }

    /** Every library the extract script reads out of node_modules is a declared dependency. */
    public function testEveryPackageTheScriptReadsIsDeclared(): void
    {
        $script = (string)file_get_contents(self::ROOT . '/src/extract-files-from-node-modules.sh');
        $manifest = json_decode((string)file_get_contents(self::ROOT . '/package.json'), true);
        $declared = array_merge(
            array_keys($manifest['dependencies'] ?? []),
            array_keys($manifest['devDependencies'] ?? [])
        );

        preg_match_all('~node_modules/((?:@[^/\s"]+/)?[^/\s"]+)~', $script, $matches);
        $used = array_values(array_unique($matches[1]));

        $undeclared = array_values(array_diff($used, $declared));
        sort($undeclared);

        $this->assertSame([], $undeclared, 'The extract script reads these out of node_modules, '
            . 'and nothing puts them there: add them to package.json.');
    }

    /**
     * @param list<string> $directories
     *
     * @return list<string>
     */
    private function trackedUnder(array $directories): array
    {
        $root = (string)realpath(self::ROOT);
        $command = 'git -C ' . escapeshellarg($root) . ' ls-files -- '
            . implode(' ', array_map(static fn (string $d) => escapeshellarg($d), $directories)) . ' 2>/dev/null';

        $output = shell_exec($command);
        if (!is_string($output) || trim($output) === '') {
            return [];
        }

        $files = array_values(array_filter(array_map('trim', explode("\n", $output))));
        sort($files);

        return $files;
    }
}

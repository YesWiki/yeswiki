<?php

namespace YesWiki\Test\Kernel;

use PHPUnit\Framework\TestCase;

/**
 * Ticket 47: composer.json names every PHP extension core calls into.
 *
 * `ext-openssl` was used by `HttpSignatureService` and `KeyPairGenerator` from the day they were
 * written and was never declared. Nobody noticed because every distribution ships it -- but a
 * fully static musl build contains exactly the extensions it was compiled with and can never load
 * another, so an undeclared extension is a feature that silently does not exist (ADR-0023).
 *
 * The audit is mechanical rather than a list somebody maintains: every symbol core names is
 * resolved through reflection to the extension that defines it, and an extension nothing declares
 * fails here.
 *
 * **What this cannot see**: an extension the running PHP has not loaded. `imap_open()` resolves to
 * nothing when ext-imap is absent, so it reads as an ordinary undefined function and is skipped.
 * ALWAYS_OPTIONAL below is the answer to that: those are declared because the audit found them
 * once, and this test asserts the declaration stays whether or not the extension is loaded today.
 */
class ExtensionManifestTest extends TestCase
{
    private const ROOT = __DIR__ . '/../../..';

    /**
     * Extensions that are part of the language and have no `ext-` package to require.
     *
     * `Core`, `standard` and `SPL` are PHP itself. `date`, `random` and `Reflection` cannot be
     * built without since 8.0, and composer resolves no package for them.
     *
     * @var list<string>
     */
    private const NOT_REQUIRABLE = ['Core', 'standard', 'SPL', 'date', 'random', 'Reflection', 'pcre'];

    /**
     * Optional by construction: core checks for them at run time and does without.
     *
     * The value is the feature that stops existing, which is what a `suggest` line has to say.
     *
     * @var array<string, string>
     */
    private const ALWAYS_OPTIONAL = [
        'ext-imap' => 'IMAP importer',
        'ext-pdo_mysql' => 'MySQL',
        'ext-pdo_pgsql' => 'PostgreSQL',
        'ext-pdo_sqlite' => 'SQLite',
        'ext-zend-opcache' => 'configuration writes invalidate',
    ];

    /** @return list<string> */
    private function sourceFiles(): array
    {
        $files = [];
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(self::ROOT . '/src', \FilesystemIterator::SKIP_DOTS)
        );
        foreach ($it as $file) {
            if (!$file instanceof \SplFileInfo || !$file->isFile()) {
                continue;
            }
            if ($file->getExtension() === 'php' || $file->getFilename() === 'console') {
                $files[] = $file->getPathname();
            }
        }
        sort($files);

        return $files;
    }

    /**
     * Every extension `src/` names, mapped to one file that names it.
     *
     * @return array<string, string>
     */
    private function extensionsUsed(): array
    {
        $skip = [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT];
        $used = [];

        foreach ($this->sourceFiles() as $file) {
            $tokens = token_get_all((string)file_get_contents($file));
            $count = count($tokens);
            for ($i = 0; $i < $count; $i++) {
                $token = $tokens[$i];
                if (!is_array($token)) {
                    continue;
                }
                $isName = in_array($token[0], [T_STRING, T_NAME_FULLY_QUALIFIED, T_NAME_QUALIFIED], true);
                if (!$isName) {
                    continue;
                }
                $name = ltrim($token[1], '\\');

                $p = $i - 1;
                while ($p >= 0 && is_array($tokens[$p]) && in_array($tokens[$p][0], $skip, true)) {
                    $p--;
                }
                $q = $i + 1;
                while ($q < $count && is_array($tokens[$q]) && in_array($tokens[$q][0], $skip, true)) {
                    $q++;
                }
                $prev = $p >= 0 ? $tokens[$p] : null;
                $next = $q < $count ? $tokens[$q] : null;

                // a declaration, a property or a method call names nothing global
                if (is_array($prev) && in_array($prev[0], [T_FUNCTION, T_CONST, T_CLASS, T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR, T_DOUBLE_COLON], true)) {
                    continue;
                }

                $extension = null;
                if (is_array($prev) && $prev[0] === T_NEW) {
                    $extension = $this->extensionOfClass($name);
                } elseif ($next === '(') {
                    $extension = $this->extensionOfFunction($name);
                } elseif (is_array($next) && $next[0] === T_DOUBLE_COLON) {
                    $extension = $this->extensionOfClass($name);
                } else {
                    $extension = $this->extensionOfConstant($name);
                }

                if ($extension !== null && !isset($used[$extension])) {
                    $used[$extension] = str_replace(self::ROOT . '/', '', $file);
                }
            }
        }
        ksort($used);

        return $used;
    }

    private function extensionOfFunction(string $name): ?string
    {
        if (!function_exists($name)) {
            return null;
        }
        $extension = (new \ReflectionFunction($name))->getExtensionName();

        return $extension === false ? null : $extension;
    }

    private function extensionOfClass(string $name): ?string
    {
        if (!class_exists($name) && !interface_exists($name)) {
            return null;
        }
        try {
            $extension = (new \ReflectionClass($name))->getExtensionName();
        } catch (\ReflectionException) {
            return null;
        }

        return $extension === false ? null : $extension;
    }

    /** @var array<string, string>|null */
    private static ?array $constantOwners = null;

    private function extensionOfConstant(string $name): ?string
    {
        if (self::$constantOwners === null) {
            self::$constantOwners = [];
            foreach (get_loaded_extensions() as $extension) {
                foreach ((new \ReflectionExtension($extension))->getConstants() as $constant => $ignored) {
                    self::$constantOwners[$constant] ??= $extension;
                }
            }
        }

        return self::$constantOwners[$name] ?? null;
    }

    /** `Zend OPcache` is `ext-zend-opcache` to composer; the rest lowercase cleanly. */
    private function composerName(string $extension): string
    {
        return 'ext-' . str_replace(' ', '-', strtolower($extension));
    }

    /** @return array{require: array<string, string>, suggest: array<string, string>} */
    private function manifest(): array
    {
        $manifest = json_decode((string)file_get_contents(self::ROOT . '/composer.json'), true);
        $this->assertIsArray($manifest);

        return [
            'require' => is_array($manifest['require'] ?? null) ? $manifest['require'] : [],
            'suggest' => is_array($manifest['suggest'] ?? null) ? $manifest['suggest'] : [],
        ];
    }

    public function testEveryExtensionCoreCallsIsDeclared(): void
    {
        $manifest = $this->manifest();
        $declared = array_merge(array_keys($manifest['require']), array_keys($manifest['suggest']));

        $undeclared = [];
        foreach ($this->extensionsUsed() as $extension => $whereFirstSeen) {
            if (in_array($extension, self::NOT_REQUIRABLE, true)) {
                continue;
            }
            $package = $this->composerName($extension);
            if (!in_array($package, $declared, true)) {
                $undeclared[] = "{$package} (first seen in {$whereFirstSeen})";
            }
        }

        $this->assertSame(
            [],
            $undeclared,
            "composer.json does not name every extension core calls into.\n"
            . "Add it to `require`, or to `suggest` with the feature it enables when core checks for it at run time.\n"
            . 'A static build ships what the manifest names and can never load anything else.'
        );
    }

    /**
     * The other direction, for the ones reflection cannot see on a PHP that has not loaded them.
     */
    public function testTheOptionalExtensionsStayDeclared(): void
    {
        $manifest = $this->manifest();
        $declared = array_merge(array_keys($manifest['require']), array_keys($manifest['suggest']));

        foreach (self::ALWAYS_OPTIONAL as $package => $feature) {
            $this->assertContains(
                $package,
                $declared,
                "{$package} is optional but still used: without it there is no {$feature}, so the manifest has to say so."
            );
        }
    }

    /**
     * The phpunit workflow installs what the manifest requires.
     *
     * Its own comment already claimed "everything composer.json declares under ext-*", and the
     * two drifted anyway -- which is how `ext-openssl` stayed undeclared while every leg of the
     * matrix happened to ship it. A claim nothing checks is a comment.
     */
    public function testTheCiWorkflowInstallsWhatTheManifestRequires(): void
    {
        $workflow = (string)file_get_contents(self::ROOT . '/.github/workflows/phpunit.yml');
        if (preg_match('/^ *extensions: (.+)$/m', $workflow, $matches) !== 1) {
            $this->fail('the phpunit workflow names no extension set');
        }
        $installed = array_map('trim', explode(',', $matches[1]));

        $missing = [];
        foreach (array_keys($this->manifest()['require']) as $package) {
            if (!str_starts_with($package, 'ext-')) {
                continue;
            }
            $extension = substr($package, strlen('ext-'));
            if (!in_array($extension, $installed, true)) {
                $missing[] = $extension;
            }
        }

        $this->assertSame([], $missing, 'the phpunit workflow runs a PHP that cannot run YesWiki: add these to its `extensions:` line');
    }

    /**
     * INSTALL.md lists what a server needs, and lists the same thing composer.json requires.
     *
     * A webmaster on shared hosting reads the doc, not the manifest, so a doc that has drifted
     * sends them to install a wiki that cannot boot -- and finding out which extension is missing
     * from a white page is not a thing anybody should have to do.
     */
    public function testTheInstallDocListsWhatTheManifestRequires(): void
    {
        $doc = (string)file_get_contents(self::ROOT . '/INSTALL.md');
        if (preg_match('/\*\*Required\*\*[^`]*```\n(.+?)```/s', $doc, $matches) !== 1) {
            $this->fail('INSTALL.md has no block of required extensions');
        }
        $documented = array_values(array_filter(array_map('trim', explode("\n", $matches[1]))));

        $required = [];
        foreach (array_keys($this->manifest()['require']) as $package) {
            if (str_starts_with($package, 'ext-')) {
                $required[] = substr($package, strlen('ext-'));
            }
        }
        sort($required);
        sort($documented);

        $this->assertSame($required, $documented, 'INSTALL.md and composer.json disagree about what a server needs');
    }

    /** Nothing is declared twice, and nothing sits in both lists saying two different things. */
    public function testNoExtensionIsBothRequiredAndSuggested(): void
    {
        $manifest = $this->manifest();
        $both = array_intersect(array_keys($manifest['require']), array_keys($manifest['suggest']));

        $this->assertSame([], array_values($both), 'an extension is either required or optional, not both');
    }
}

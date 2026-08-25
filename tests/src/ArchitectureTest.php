<?php

namespace YesWiki\Test;

use PHPUnit\Framework\TestCase;

/** Enforces the module boundaries ticket 05 created. */
class ArchitectureTest extends TestCase
{
    private const SRC = __DIR__ . '/../../src';

    /** Kernel is infrastructure: it may be depended upon, and may depend on no feature. */
    private const FEATURES = ['Content', 'Identity', 'Render', 'Search', 'Admin'];

    private const MODULES = ['Kernel', 'Content', 'Identity', 'Render', 'Search', 'Admin', 'Import', 'Contact', 'Files', 'Federation', 'Social'];

    /**
     * Pre-existing breaches, recorded when the rule was introduced. There are none left.
     *
     * The list held seven. Four were `Kernel\Service\Mailer` importing Content, Identity and
     * Render, which is what happens when a service that composes an email lives next to the one
     * that sends it -- the composing half is `Content\Service\ContentNotifier` now, beside the
     * three callers it always had. One was `Performer`, which ran actions from Kernel while every
     * action it ran was a render; it lives in `Render\Service` now, and no Kernel code ever
     * referred to it. The last was `TemplateHelperService` reaching into `EntryController` for a
     * meta description, which now goes through `EntryDisplay` -- a service, in the module that
     * owns entries.
     *
     * Keep it empty. An entry here is a rule with an exception, and the exceptions were the part
     * nobody read.
     *
     * @var list<string>
     */
    private const KNOWN_VIOLATIONS = [];

    /**
     * Ticket 41: files go through `Storage`, so the functions that address a path directly are banned.
     *
     * @var list<string>
     */
    private const FS_ALLOWED = [
        'bootstrap_paths.php',
        'ComposerScriptsHelper.php',
        'Files/Service/Storage',
        // Source paths only: `src/<Module>/<Convention>/` and an extension's own folder, which
        // ADR-0022 puts outside the tiers because they hold code rather than data. One place
        // reads them, so fields and template-data preparers no longer each carry their own
        // glob and scandir (ticket 49).
        'Files/Service/LocalFiles.php',
        'Files/Service/ProgramFiles',
        'Files/Service/RuntimeLock.php',
        'Render/Service/TwigSearchPath.php',
        'Kernel/Service/ClassDirectoryScanner.php',
        'Admin/Service/InstallationService.php',

        // Loaded by index.php and worker.php with `require_once`, before there is a container to
        // ask for Storage. `AssetPublisher` answers an asset request and returns without ever
        // booting the wiki -- that is the whole point of it, and it is why serving a woff2 costs
        // nothing in worker mode. A rule it cannot obey is not a rule it is breaking.
        'Kernel/Service/AssetPublisher.php',

        // Boot: these run before there is a container to ask for `Storage`, and two of them run
        // before `vendor/autoload.php` has been required at all. `YesWikiLoader` decides whether
        // the autoloader is current, `autoload.inc.php` is the autoloader, `YesWikiKernel` and
        // `YesWikiRuntime` build the container and compile the routes, `YesWikiPlugins` and
        // `YesWikiInit` are what the wiki reads to know it is a wiki, and
        // `ConfigurationFileProvider` decides which file that is. Storage cannot be the answer to
        // a question asked to work out whether Storage can be constructed.
        'YesWikiLoader.php',
        'YesWikiRuntime.php',
        'YesWikiKernel.php',
        'YesWikiPlugins.php',
        'YesWikiInit.php',
        'autoload.inc.php',
        'Kernel/Service/ConfigurationFileProvider.php',
        'Kernel/Entity/ConfigurationFile.php',

        // A singleton with a `getInstance()`, initialised before the container so that `_t()`
        // works while the container is still being built. Its catalogues are `require`d PHP in the
        // Program tree, which is why it reads them itself rather than asking a service it cannot
        // reach yet.
        'Kernel/Service/LanguageService.php',

        // Installing a package writes the Program: a downloaded zip is extracted and copied into
        // `src/`, `themes/`, `vendor/` or an extension's own folder. That is code, not a wiki's
        // data, `ZipArchive` ignores stream wrappers (ADR-0022 rejects `yeswiki://` for exactly
        // that), and what lands is PHP that gets included -- the same reason `custom/extensions/`
        // is Runtime rather than Public. `PackageCore`, `PackageExt` and `Package` extend this and
        // ask it rather than the filesystem.
        'Admin/Service/PackageTree.php',

        // Standalone scripts, run by a person or a webserver with no wiki around them.
        // `build-js-lang-keys` is a build step somebody runs from a terminal, and
        // `javascript-keys-builder` is the function it calls; `pdf-viewer` is served straight from
        // the assets directory and never loads the application. None of them has a container, an
        // Instance, or a reason to.
        'build-js-lang-keys.php',
        'lang/javascript-keys-builder.php',
        'assets/pdf-viewer.php',

        // Creating an Instance, which is the one moment there is no Instance for Storage to be
        // rooted at. `core:create-instance` makes the directory, its data folders and its entry
        // points; everything it writes is the thing Storage would need to already exist.
        'Kernel/Command/CreateInstanceCommand.php',
    ];

    /**
     * What each file has yet to convert. Nothing.
     *
     * Seeded at 82 files and 478 calls when ticket 41 introduced the rule, and empty now. Getting
     * here needed the rule to say something true rather than something strict: a call is exempt if
     * it addresses the Program tree, a stream, or somebody else's URL, and a handful of files are
     * exempt as a whole because they run before the container exists or because a library demands
     * a real path. Those are in FS_ALLOWED, each with the reason next to it.
     *
     * Keep it empty. An entry here is a file allowed to reach a wiki's data without going through
     * the service that knows whether that data is on a disk or in a bucket, and the whole point of
     * ADR-0022 is that nothing gets to decide that for itself.
     *
     * @var array<string, int>
     */
    private const FS_REMAINING = [];

    /** Functions that take a path. */
    private const FS_FUNCTIONS = [
        'file_put_contents', 'file_get_contents', 'fopen', 'unlink', 'mkdir', 'rmdir', 'rename',
        'copy', 'touch', 'move_uploaded_file', 'file_exists', 'is_file', 'is_dir', 'is_readable',
        'is_writable', 'glob', 'scandir', 'opendir', 'readfile', 'file', 'filesize', 'filemtime',
        'getimagesize', 'chmod', 'symlink', 'realpath', 'disk_free_space', 'tempnam', 'umask',
    ];

    /** Every direct call to a path-taking function in $file, by name. */
    private function rawFileCallsIn(string $file): int
    {
        $tokens = token_get_all((string)file_get_contents($file));
        $count = 0;
        $total = count($tokens);

        for ($i = 0; $i < $total; $i++) {
            $token = $tokens[$i];
            if (!is_array($token) || $token[0] !== T_STRING) {
                continue;
            }
            if (!in_array(strtolower($token[1]), self::FS_FUNCTIONS, true)) {
                continue;
            }
            $before = $this->meaningfulToken($tokens, $i, -1);
            if (is_array($before) && in_array($before[0], [T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR, T_DOUBLE_COLON, T_FUNCTION, T_NEW, T_CONST], true)) {
                continue;
            }
            $after = $this->meaningfulToken($tokens, $i, 1);
            if ($after !== '(') {
                continue;
            }
            // A stream is not a path. `php://input` is the request body, and `$url . '/html'` is
            // somebody else's website -- neither is this instance's filesystem, and neither has a
            // storage tier to go through. The exemption used to be `fopen`-only, so
            // `file_get_contents('php://input')` counted as reaching the disk.
            if ($this->addressesAStream($tokens, $i + 1)) {
                continue;
            }
            if ($this->addressesTheProgram($tokens, $i + 1)) {
                continue;
            }
            $count++;
        }

        return $count;
    }

    /**
     * Whether this call addresses a stream or a remote URL rather than a path on disk.
     *
     * @param list<array{int, string, int}|string> $tokens
     */
    private function addressesAStream(array $tokens, int $openParen): bool
    {
        $argument = $this->firstArgument($tokens, $openParen);

        foreach (["'php://", '"php://', "'http://", '"http://', "'https://", '"https://'] as $scheme) {
            if (str_contains($argument, $scheme)) {
                return true;
            }
        }

        // `$url . '/html'`: the scheme is in the variable, and the name is the only clue there is.
        return (bool)preg_match('/\$(url|uri|endpoint|address|remote)\b/i', $argument);
    }

    /**
     * Whether this call addresses the Program tree rather than an Instance's data.
     *
     * ADR-0022's tiers describe what a *wiki* owns -- its uploads, its cache, its configuration --
     * and `Storage` is rooted at an Instance. The Program is the code the release ships: themes,
     * templates, javascripts, the source tree. It is not in any tier, it is the same bytes for
     * every wiki on the host, and on an S3 deployment it is emphatically not in the bucket.
     * Asking Storage for it would be asking the wrong filesystem.
     *
     * This used to be handled by exempting whole files, which is why the ratchet stalled: a file
     * like ThemeManager reads themes from the Program *and* custom/ from the Instance, so a
     * per-file exemption either excused the wrong half or counted work that was never owed.
     * Deciding per call is what lets the remaining number mean "still to convert".
     *
     * @param list<array{int, string, int}|string> $tokens
     */
    private function addressesTheProgram(array $tokens, int $openParen): bool
    {
        $argument = $this->firstArgument($tokens, $openParen);

        foreach (['YESWIKI_PROGRAM_DIR', '__DIR__', 'YESWIKI_PROGRAM_ROOT'] as $namesTheProgram) {
            if (str_contains($argument, $namesTheProgram)) {
                return true;
            }
        }

        return false;
    }

    /**
     * The source text of a call's first argument, which is the one that names the path.
     *
     * @param list<array{int, string, int}|string> $tokens
     */
    private function firstArgument(array $tokens, int $openParen): string
    {
        $depth = 0;
        $argument = '';

        for ($i = $openParen; isset($tokens[$i]); $i++) {
            $text = is_array($tokens[$i]) ? $tokens[$i][1] : $tokens[$i];

            if ($text === '(') {
                $depth++;
            } elseif ($text === ')') {
                $depth--;
                if ($depth === 0) {
                    break;
                }
            }
            if ($text === ',' && $depth === 1) {
                break;
            }
            $argument .= $text;
        }

        return $argument;
    }

    /**
     * @param list<array{int, string, int}|string> $tokens
     *
     * @return array{int, string, int}|string|null
     */
    private function meaningfulToken(array $tokens, int $from, int $step)
    {
        for ($i = $from + $step; isset($tokens[$i]); $i += $step) {
            if (is_array($tokens[$i]) && in_array($tokens[$i][0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }

            return $tokens[$i];
        }

        return null;
    }

    public function testFilesGoThroughStorage(): void
    {
        $over = [];
        $gone = [];

        foreach ($this->phpFilesIn(self::SRC) as $file) {
            $relative = substr($file, strlen(self::SRC) + 1);
            foreach (self::FS_ALLOWED as $allowed) {
                if (str_starts_with($relative, $allowed)) {
                    continue 2;
                }
            }
            $found = $this->rawFileCallsIn($file);
            /** @var array<string, int> $remaining */
            $remaining = self::FS_REMAINING;
            $budget = $remaining[$relative] ?? 0;
            if ($found > $budget) {
                $over[] = "$relative: $found raw calls, $budget allowed";
            }
            if ($found < $budget) {
                $gone[] = "$relative: $found raw calls, $budget still budgeted";
            }
        }

        $this->assertSame([], $over, "A file must not reach the filesystem directly.\n"
            . "A wiki's own data goes through YesWiki\\Files\\Service\\Storage, which knows whether it is on a disk or in a bucket.\n"
            . "The release's own files go through ProgramFiles. A path a library insists on opening itself goes through LocalFiles,\n"
            . "and that is a decision to argue for in FS_ALLOWED rather than take here.\n"
            . 'Reads count as much as writes -- on object storage a read that answers false is a file that silently vanished.');
        $this->assertSame([], $gone, "FS_REMAINING is empty and has to stay that way: these entries are for files that no longer need them.\n"
            . 'A budget nobody spends is a rule nobody enforces.');
    }

    /**
     * @return list<string>
     */
    private function phpFilesIn(string $dir): array
    {
        if (!is_dir($dir)) {
            return [];
        }
        $out = [];
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS));
        foreach ($it as $f) {
            if ($f->isFile() && $f->getExtension() === 'php') {
                $out[] = $f->getPathname();
            }
        }
        sort($out);

        return $out;
    }

    /**
     * @return list<array{string, string, string, string}> [relativePath, module, layer, class]
     */
    private function moduleImports(): array
    {
        $edges = [];
        foreach (self::MODULES as $module) {
            foreach ($this->phpFilesIn(self::SRC . '/' . $module) as $file) {
                $text = (string)file_get_contents($file);
                $rel = $module . substr($file, strlen(self::SRC . '/' . $module));
                preg_match_all('/^use YesWiki\\\\(\w+)\\\\(\w+)\\\\(\w+);/m', $text, $m, PREG_SET_ORDER);
                foreach ($m as $hit) {
                    if (!in_array($hit[1], self::MODULES, true) || $hit[1] === $module) {
                        continue;
                    }
                    $edges[] = [str_replace('\\', '/', $rel), $hit[1], $hit[2], $hit[3]];
                }
            }
        }

        return $edges;
    }

    public function testKernelDependsOnNoFeatureModule(): void
    {
        $found = [];
        foreach ($this->moduleImports() as [$rel, $mod, $layer, $cls]) {
            if (str_starts_with($rel, 'Kernel/') && in_array($mod, self::FEATURES, true)) {
                $found[] = "$rel -> $mod\\$layer\\$cls";
            }
        }
        $new = array_diff(array_unique($found), self::KNOWN_VIOLATIONS);

        $this->assertSame([], array_values($new), "Kernel must not depend on a feature module.\n"
            . 'If this is deliberate, the class probably belongs in that feature rather than in Kernel.');
    }

    public function testNoServiceDependsOnAController(): void
    {
        $found = [];
        foreach ($this->moduleImports() as [$rel, $mod, $layer, $cls]) {
            if (str_contains($rel, '/Service/') && $layer === 'Controller') {
                $found[] = "$rel -> $mod\\$layer\\$cls";
            }
        }
        $new = array_diff(array_unique($found), self::KNOWN_VIOLATIONS);

        $this->assertSame([], array_values($new), 'A service must not depend on a controller: '
            . 'that is the layering inversion ticket 03 removed and ticket 04 is finishing.');
    }

    public function testKnownViolationsListHasNotGrownAndContainsNoStaleEntries(): void
    {
        $all = [];
        foreach ($this->moduleImports() as [$rel, $mod, $layer, $cls]) {
            $all[] = "$rel -> $mod\\$layer\\$cls";
        }
        $stale = array_diff(self::KNOWN_VIOLATIONS, $all);

        $this->assertSame(
            [],
            array_values($stale),
            'These KNOWN_VIOLATIONS no longer exist -- delete them from the list so it keeps '
            . 'telling the truth about what is left.'
        );
    }

    /** Route discovery is directory-driven (Wiki::buildRouteCollection scans src/<Module>/Controller). */
    public function testEveryRouteLivesInAControllerDirectory(): void
    {
        $misplaced = [];
        $routes = 0;
        foreach (self::MODULES as $module) {
            foreach ($this->phpFilesIn(self::SRC . '/' . $module) as $file) {
                $count = substr_count((string)file_get_contents($file), '#[Route(');
                if ($count === 0) {
                    continue;
                }
                $routes += $count;
                $normalized = str_replace('\\', '/', $file);
                if (!str_contains($normalized, '/Controller/') && !str_contains($normalized, '/Api/')) {
                    $misplaced[] = substr($file, strlen(self::SRC) + 1);
                }
            }
        }

        $this->assertSame([], $misplaced, 'Routes declared outside a <Module>/Controller/ or '
            . '<Module>/Api/ directory are never discovered -- they vanish silently.');
        $this->assertGreaterThan(
            60,
            $routes,
            'The API surface collapsed. Route discovery scans directories, so moving a '
            . 'controller out of <Module>/Controller/ removes its routes with no error.'
        );
    }

    /**
     * Ticket 08 split the monolithic ApiController into per-resource controllers: every /api/* route must be declared in src/<Module>/Api/<Resource>ApiController.php, so the resource an endpoint belongs to is readable from the file that declares it.
     */
    public function testEveryApiRouteLivesInAResourceApiController(): void
    {
        $misplaced = [];

        $rootFiles = glob(self::SRC . '/*.php') ?: [];
        foreach ($rootFiles as $file) {
            if (preg_match("/#\\[Route\\('\\/?api(?:\\/|')/", (string)file_get_contents($file))) {
                $misplaced[] = substr($file, strlen(self::SRC) + 1);
            }
        }
        foreach (self::MODULES as $module) {
            foreach ($this->phpFilesIn(self::SRC . '/' . $module) as $file) {
                $text = (string)file_get_contents($file);
                if (!preg_match("/#\\[Route\\('\\/?api(?:\\/|')/", $text)) {
                    continue;
                }
                $normalized = str_replace('\\', '/', $file);
                if (!preg_match('#/' . $module . '/Api/\\w+ApiController\\.php$#', $normalized)) {
                    $misplaced[] = substr($file, strlen(self::SRC) + 1);
                }
            }
        }

        $this->assertSame([], $misplaced, 'Every /api/* route must live in a '
            . '<Module>/Api/<Resource>ApiController.php controller.');
    }

    public function testEveryModuleNamespaceIsRegisteredForPsr4Autoloading(): void
    {
        $composer = json_decode((string)file_get_contents(self::SRC . '/../composer.json'), true);
        $psr4 = $composer['autoload']['psr-4'] ?? [];

        foreach (self::MODULES as $module) {
            $this->assertArrayHasKey(
                "YesWiki\\{$module}\\",
                $psr4,
                "Module {$module} exists on disk but is not PSR-4 registered; its classes "
                . 'would fall through to the legacy autoloader and fail to load.'
            );
            $this->assertSame("src/{$module}/", $psr4["YesWiki\\{$module}\\"]);
        }
    }
}

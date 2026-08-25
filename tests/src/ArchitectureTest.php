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
        'Kernel/Service/ClassDirectoryScanner.php',
        'Admin/Service/InstallationService.php',

        // Loaded by index.php and worker.php with `require_once`, before there is a container to
        // ask for Storage. `AssetPublisher` answers an asset request and returns without ever
        // booting the wiki -- that is the whole point of it, and it is why serving a woff2 costs
        // nothing in worker mode. A rule it cannot obey is not a rule it is breaking.
        'Kernel/Service/AssetPublisher.php',
    ];

    /**
     * What each file has yet to convert.
     *
     * Seeded at 82 files and 478 calls, and this number now means something narrower than it did:
     * a call that names the Program tree is no longer counted (see addressesTheProgram), because
     * Storage is rooted at an Instance and the Program is not an Instance's to own. What is left
     * is a wiki's own data reached without going through the service that knows where it lives.
     *
     * @var array<string, int>
     */
    private const FS_REMAINING = [
        'Admin/Action/ConfigurationAction.php' => 1,
        'Admin/Action/EditConfigAction.php' => 1,
        'Admin/Api/DocumentationApiController.php' => 1,
        'Admin/Command/CloneCommand.php' => 5,
        'Admin/Command/DestroyCommand.php' => 3,
        'Admin/Controller/DocumentationController.php' => 2,
        'Admin/Controller/InstallationController.php' => 2,
        'Admin/Entity/Package.php' => 5,
        'Admin/Entity/PackageCore.php' => 8,
        'Admin/Entity/PackageExt.php' => 8,
        'Admin/Entity/Repository.php' => 2,
        'Admin/Service/ArchiveService.php' => 16,
        'Admin/Service/AutoUpdateService.php' => 1,
        'Admin/Service/RemoteWikiArchive.php' => 3,
        'Contact/Api/ContactApiController.php' => 2,
        'Content/Action/EntryListAction.php' => 2,
        'Content/Action/FiltertagsAction.php' => 1,
        'Content/Action/SyndicationAction.php' => 2,
        'Content/Action/ValueAction.php' => 1,
        'Content/Entity/Files.php' => 27,
        'Content/Field/TextareaField.php' => 3,
        'Content/Service/ActionCallRewriter.php' => 1,
        'Content/Service/ActionsBuilderService.php' => 2,
        'Content/Service/BazarListService.php' => 1,
        'Content/Service/DuplicationManager.php' => 9,
        'Content/Service/FormManager.php' => 10,
        'Content/Service/PageSummary.php' => 2,
        'Files/Service/AttachedFilePaths.php' => 7,
        'Files/Service/ImageResizer.php' => 4,
        'Files/Service/RemoteFile.php' => 2,
        'Identity/Service/AvatarService.php' => 2,
        'Identity/Service/HashCashService.php' => 4,
        'Import/Action/AdminImportersAction.php' => 1,
        'Import/Service/ImapImporter.php' => 2,
        'Import/Service/ImportFilesManager.php' => 6,
        'Import/Service/ImportService.php' => 8,
        'Import/Service/ImporterManager.php' => 7,
        'Kernel/Command/CreateInstanceCommand.php' => 11,
        'Kernel/Command/DbCommand.php' => 2,
        'Kernel/Command/GenerateMigrationCommand.php' => 4,
        'Kernel/Command/ImageOptimizerCommand.php' => 5,
        'Kernel/Command/TestConsoleServiceCommand.php' => 2,
        'Kernel/Entity/ConfigurationFile.php' => 2,
        'Kernel/Service/AssetRegistry.php' => 3,
        'Kernel/Service/ConfigurationFileProvider.php' => 2,
        'Kernel/Service/ConfigurationService.php' => 1,
        'Kernel/Service/ConsoleService.php' => 1,
        'Kernel/Service/HtmlPurifierService.php' => 6,
        'Kernel/Service/LanguageService.php' => 3,
        'Kernel/Service/MigrationService.php' => 2,
        'Render/Action/FaviconAction.php' => 1,
        'Render/Action/SetWikiDefaultThemeAction.php' => 1,
        'Render/Action/TranslationAction.php' => 1,
        'Render/Service/CoreAssets.php' => 5,
        'Render/Service/CustomTemplateService.php' => 6,
        'Render/Service/LayoutService.php' => 1,
        'Render/Service/Performer.php' => 2,
        'Render/Service/PresetService.php' => 6,
        'Render/Service/TemplateEngine.php' => 7,
        'Render/Service/TemplateHelperService.php' => 15,
        'Render/Service/ThemeManager.php' => 1,
        'Render/Service/ThemeSelectorRenderer.php' => 3,
        'Search/Command/ReindexCommand.php' => 1,
        'Social/Service/ReactionsFormatter.php' => 4,
        'YesWikiInit.php' => 5,
        'YesWikiKernel.php' => 3,
        'YesWikiLoader.php' => 5,
        'YesWikiPlugins.php' => 4,
        'YesWikiRuntime.php' => 10,
        'assets/pdf-viewer.php' => 2,
        'autoload.inc.php' => 4,
        'build-js-lang-keys.php' => 1,
        'lang/javascript-keys-builder.php' => 1,
        'migrations/20260726000000_MigrateAttachmentsToPages.php' => 7,
        'migrations/20260802130000_RewriteRetiredSearchActions.php' => 2,
        'migrations/20260811120000_RenameActionsAndParametersInBodies.php' => 5,
        'migrations/20260816100000_PresetsBecomeTokenSets.php' => 3,
        'migrations/20260817120000_PresetsLoseTheirDerivedTokens.php' => 3,
        'migrations/20260817160000_ADarkBarMatchesItsDarkPage.php' => 3,
    ];

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
            if (strtolower($token[1]) === 'fopen') {
                $argument = $this->meaningfulToken($tokens, $i + 1, 1);
                if (is_array($argument) && $argument[0] === T_CONSTANT_ENCAPSED_STRING
                    && str_starts_with(trim($argument[1], '\'"'), 'php://')) {
                    continue;
                }
            }
            if ($this->addressesTheProgram($tokens, $i + 1)) {
                continue;
            }
            $count++;
        }

        return $count;
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
        $depth = 0;
        $argument = '';

        for ($i = $openParen; isset($tokens[$i]); $i++) {
            $token = $tokens[$i];
            $text = is_array($token) ? $token[1] : $token;

            if ($text === '(') {
                $depth++;
            } elseif ($text === ')') {
                $depth--;
                if ($depth === 0) {
                    break;
                }
            }
            // The first argument is the path; a comma at depth 1 ends it.
            if ($text === ',' && $depth === 1) {
                break;
            }
            $argument .= $text;
        }

        foreach (['YESWIKI_PROGRAM_DIR', '__DIR__', 'YESWIKI_PROGRAM_ROOT'] as $namesTheProgram) {
            if (str_contains($argument, $namesTheProgram)) {
                return true;
            }
        }

        return false;
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
            $budget = self::FS_REMAINING[$relative] ?? 0;
            if ($found > $budget) {
                $over[] = "$relative: $found raw calls, $budget allowed";
            }
            if ($found < $budget) {
                $gone[] = "$relative: $found raw calls, $budget still budgeted";
            }
        }

        $this->assertSame([], $over, "A file must not reach the filesystem directly: use YesWiki\\Files\\Service\\Storage.\n"
            . 'Reads count as much as writes -- on object storage a read that answers false is a file that silently vanished.');
        $this->assertSame([], $gone, "The ratchet may only shrink, and these files have: lower their number in FS_REMAINING.\n"
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

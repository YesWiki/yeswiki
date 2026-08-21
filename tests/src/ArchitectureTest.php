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
     * Pre-existing breaches, recorded when the rule was introduced.
     *
     * @var list<string>
     */
    private const KNOWN_VIOLATIONS = [
        'Kernel/Service/Mailer.php -> Content\Controller\EntryController',
        'Kernel/Service/Mailer.php -> Identity\Service\AuthenticationService',
        'Kernel/Service/Mailer.php -> Identity\Service\UserManager',
        'Kernel/Service/Mailer.php -> Render\Service\TemplateEngine',

        'Kernel/Service/Performer.php -> Render\Service\TemplateEngine',

        'Render/Service/TemplateHelperService.php -> Content\Controller\EntryController',
    ];

    /**
     * Ticket 41: files go through `Storage`, so the functions that address a path directly are banned. These three may keep calling them: two run before, or entirely without, a container, and the third is the implementation.
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
    ];

    /**
     * What each file has yet to convert, counted the day the rule was seeded. A number may only shrink and a new file may not appear -- reads count as much as writes, because on object storage a `file_exists('custom/styles/custom.css')` that answers false makes a stylesheet vanish with nobody told.
     *
     * Two entries the ticket listed as Data are Runtime by ADR-0022's own rule and stay here: `Content/Entity/Files.php` copies release trees into `custom/extensions/` and over the Program tree, and `Admin/Service/ArchiveService.php` walks and rewrites that source tree on restore. Its backups themselves are Data and went through Storage in ticket 42, which also removed `archive[privatePath]` -- the setting that used to be the reason this file was deferred.
     *
     * @var array<string, int>
     */
    private const FS_REMAINING = [
        'Admin/Action/ConfigurationAction.php' => 1,
        'Admin/Action/EditConfigAction.php' => 1,
        'Admin/Api/DocumentationApiController.php' => 1,
        'Admin/Controller/DocumentationController.php' => 2,
        'Admin/Controller/InstallationController.php' => 10,
        'Admin/Entity/Package.php' => 5,
        'Admin/Entity/PackageCore.php' => 8,
        'Admin/Entity/PackageExt.php' => 8,
        'Admin/Entity/PackageTool.php' => 2,
        'Admin/Entity/Repository.php' => 2,
        'Admin/Service/ArchiveService.php' => 16,
        'Admin/Service/AutoUpdateService.php' => 2,
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
        // tags.functions.php's budget, following its code: `yeswiki_thumbnail_tag()` became
        // PageSummary::thumbnail() when ticket 50 folded that file into services. The same two
        // `file_exists` calls on an uploaded image, in a class that can now be given Storage.
        'Content/Service/PageSummary.php' => 2,
        // bazar.functions.php's budget of 6, following its code when ticket 50 folded that
        // file into services: four `file_exists`/`unlink` in `resizeImage()`, which is
        // ImageResizer::cached() now, and two in the remote-file download.
        'Files/Service/ImageResizer.php' => 4,
        'Files/Service/RemoteFile.php' => 2,
        'Files/Service/AttachedFilePaths.php' => 7,
        'Identity/Service/AvatarService.php' => 2,
        'Identity/Service/HashCashService.php' => 4,
        'Import/Action/AdminImportersAction.php' => 1,
        'Import/Service/ImapImporter.php' => 2,
        'Import/Service/ImportFilesManager.php' => 6,
        'Import/Service/ImportService.php' => 8,
        'Import/Service/ImporterManager.php' => 7,
        'Kernel/Command/CreateInstanceCommand.php' => 14,
        'Kernel/Command/DbCommand.php' => 2,
        'Kernel/Command/GenerateMigrationCommand.php' => 4,
        'Kernel/Command/ImageOptimizerCommand.php' => 5,
        'Kernel/Command/TestConsoleServiceCommand.php' => 2,
        'Kernel/Entity/ConfigurationFile.php' => 2,
        'Kernel/Service/AssetPublisher.php' => 51,
        'Kernel/Service/AssetRegistry.php' => 4,
        'Kernel/Service/ConfigurationFileProvider.php' => 2,
        'Kernel/Service/ConfigurationService.php' => 1,
        'Kernel/Service/ConsoleService.php' => 1,
        'Kernel/Service/HtmlPurifierService.php' => 6,
        'Kernel/Service/LanguageService.php' => 5,
        'Kernel/Service/Mailer.php' => 3,
        'Kernel/Service/MigrationService.php' => 2,
        'Kernel/Service/Performer.php' => 2,
        'Kernel/Service/ThrowableFormatter.php' => 1,
        'Render/Action/FaviconAction.php' => 1,
        'Render/Action/SetWikiDefaultThemeAction.php' => 1,
        'Render/Action/TranslationAction.php' => 1,
        'Render/Service/CoreAssets.php' => 5,
        'Render/Service/CustomTemplateService.php' => 6,
        'Render/Service/LayoutService.php' => 1,
        'Render/Service/PresetService.php' => 7,
        'Render/Service/TemplateEngine.php' => 8,
        'Render/Service/TemplateHelperService.php' => 15,
        'Render/Service/ThemeManager.php' => 16,
        'Render/Service/ThemeSelectorRenderer.php' => 3,
        'Search/Command/ReindexCommand.php' => 1,
        'Social/Service/ReactionsFormatter.php' => 6,
        'YesWikiInit.php' => 5,
        'YesWikiKernel.php' => 3,
        'YesWikiLoader.php' => 6,
        'YesWikiPlugins.php' => 4,
        'YesWikiRuntime.php' => 17,
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

    /** Functions that take a path. Handle-takers (`fwrite`, `fgetcsv`) are not here: the seam is the path, not the stream. */
    private const FS_FUNCTIONS = [
        'file_put_contents', 'file_get_contents', 'fopen', 'unlink', 'mkdir', 'rmdir', 'rename',
        'copy', 'touch', 'move_uploaded_file', 'file_exists', 'is_file', 'is_dir', 'is_readable',
        'is_writable', 'glob', 'scandir', 'opendir', 'readfile', 'file', 'filesize', 'filemtime',
        'getimagesize', 'chmod', 'symlink', 'realpath', 'disk_free_space', 'tempnam', 'umask',
    ];

    /**
     * Every direct call to a path-taking function in $file, by name.
     *
     * `fopen('php://…')` is not one: it addresses no file.
     */
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
            $count++;
        }

        return $count;
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

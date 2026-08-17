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

<?php

/**
 * Wave-two progress counters (ectoplasme coherent core, ticket 01).
 *
 * Tickets 01-08 ship nothing a user can see, so "is the refactor on track" needs an
 * answer that isn't a feeling. These two numbers are that answer. Both must fall
 * monotonically and both must read 0 once ticket 08 deletes the Wiki class.
 *
 *   1. $this->wiki-> call sites      -- how much code still reaches through the god object
 *   2. latent dependency cycles      -- how many cycles would appear if every service-locator
 *                                       edge became constructor injection, i.e. how much of
 *                                       the locator is load-bearing rather than lazy
 *
 * Usage:  php phpstan/wave-counters.php  [--json]
 *         make wave-counters
 */
$root = \dirname(__DIR__);
$asJson = \in_array('--json', $argv, true);

/**
 * Is $offset inside a closure body rather than directly in a method body?
 *
 * Counts closures opened before $offset whose brace is still unclosed at it.
 */
function isInsideClosure(string $text, int $offset): bool
{
    $depth = 0;
    if (preg_match_all('/function\s*\(|fn\s*\(/', substr($text, 0, $offset), $m, PREG_OFFSET_CAPTURE)) {
        foreach ($m[0] as [$match, $start]) {
            $tail = substr($text, $start + \strlen($match), $offset - $start - \strlen($match));
            if (substr_count($tail, '{') > substr_count($tail, '}')) {
                $depth++;
            }
        }
    }

    return $depth > 0;
}

// ---------------------------------------------------------------- counter 1

$scanRoots = ['src', 'actions', 'handlers', 'formatters'];
$phpFiles = [];
foreach ($scanRoots as $dir) {
    $path = $root . '/' . $dir;
    if (!is_dir($path)) {
        continue; // directories disappear as the wave progresses; that is the point
    }
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $file) {
        if ($file->isFile() && $file->getExtension() === 'php') {
            $phpFiles[] = $file->getPathname();
        }
    }
}

$wikiCallSites = 0;
foreach ($phpFiles as $file) {
    $wikiCallSites += substr_count((string)file_get_contents($file), '$this->wiki->');
}

// ---------------------------------------------------------------- counter 2

// Same file set plus extensions/, minus the hand-vendored libraries, which are not ours.
$graphFiles = $phpFiles;
$extPath = $root . '/extensions';
if (is_dir($extPath)) {
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($extPath, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $file) {
        if ($file->isFile() && $file->getExtension() === 'php' && !str_contains($file->getPathname(), '/node_modules/')) {
            $graphFiles[] = $file->getPathname();
        }
    }
}
$graphFiles = array_values(array_filter(
    $graphFiles,
    static fn (string $f): bool => !str_contains($f, '/src/vendor/')
));

$classes = [];
$sources = [];
foreach ($graphFiles as $file) {
    $text = (string)file_get_contents($file);
    if (preg_match('/^(?:final |abstract )?class (\w+)/m', $text, $m) === 1) {
        $classes[$m[1]] = true;
        $sources[$m[1]] = $text;
    }
}

/** @var array<string, array<string, true>> $edges */
$edges = [];
foreach ($sources as $class => $text) {
    $deps = [];

    // constructor-injected dependencies
    if (preg_match('/function __construct\((.*?)\)\s*\{/s', $text, $ctor) === 1) {
        preg_match_all('/([A-Z]\w+)\s+\$/', $ctor[1], $found);
        foreach ($found[1] as $dep) {
            $deps[$dep] = true;
        }
    }

    // Service-locator edges -- what would become constructor injection.
    //
    // Lookups written inside a closure are skipped on purpose (ticket 04): they resolve
    // when the closure runs, not when the class is built, so they cannot close a
    // construction cycle and must NOT be converted to constructor injection. TemplateEngine's
    // Twig helpers are the main example -- converting those would create cycles, not remove
    // them. Counting them made the headline number 68 when the figure that actually blocks
    // constructor injection was 26.
    preg_match_all('/(?:services->get|getService)\(\s*([A-Z]\w+)::class/', $text, $found, PREG_OFFSET_CAPTURE);
    foreach ($found[1] as [$dep, $offset]) {
        if (isInsideClosure($text, $offset)) {
            continue;
        }
        $deps[$dep] = true;
    }

    foreach (array_keys($deps) as $dep) {
        if ($dep !== $class && isset($classes[$dep])) {
            $edges[$class][$dep] = true;
        }
    }
}

// Count back-edges found by DFS: each one closes a cycle.
$countBackEdges = static function (array $graph): int {
    $colour = [];
    $backEdges = 0;
    $visit = static function (string $node) use (&$visit, &$colour, &$backEdges, $graph): void {
        $colour[$node] = 1;
        $next = array_keys($graph[$node] ?? []);
        sort($next);
        foreach ($next as $dep) {
            if (($colour[$dep] ?? 0) === 1) {
                $backEdges++;
            } elseif (!isset($colour[$dep])) {
                $visit($dep);
            }
        }
        $colour[$node] = 2;
    };
    $nodes = array_keys($graph);
    sort($nodes);
    foreach ($nodes as $node) {
        if (!isset($colour[$node])) {
            $visit($node);
        }
    }

    return $backEdges;
};
$backEdges = $countBackEdges($edges);

// ---------------------------------------------------------------- report

// Cycles that survive once Wiki is gone (ticket 08) -- the portion ticket 04 owns.
$edgesWithoutWiki = [];
foreach ($edges as $from => $tos) {
    if ($from === 'Wiki') {
        continue;
    }
    foreach ($tos as $to => $_) {
        if ($to !== 'Wiki') {
            $edgesWithoutWiki[$from][$to] = true;
        }
    }
}
$backEdgesWithoutWiki = $countBackEdges($edgesWithoutWiki);

// ---------------------------------------------------------------- counter 3 & 4 (ticket 14)

// Assets accumulated in a global rather than declared by the render that needs them.
// Ticket 14 replaced the mechanism; ticket 15 removes the last two emission points.
$assetGlobals = 0;
foreach ($phpFiles as $file) {
    foreach (explode("\n", (string)file_get_contents($file)) as $line) {
        // comments describing what the globals used to do are not uses of them
        $trimmed = ltrim($line);
        if ($trimmed === '' || str_starts_with($trimmed, '//') || str_starts_with($trimmed, '*')) {
            continue;
        }
        $assetGlobals += substr_count($line, "\$GLOBALS['css']") + substr_count($line, "\$GLOBALS['js']");
    }
}

// Initialisers that only ever run at DOMContentLoaded cannot see content inserted later, so
// anything they set up is dead on any page reached by an htmx navigation (ticket 16). ywInit
// is the one convention that replaces them, and javascripts/yw-init.js defines it -- hence the
// exclusion.
//
// **Counted in PHP and Twig as well as in .js**, and that is the whole point of this counter.
// It used to look at javascripts/** only, so it read 0 while four initialisers were broken:
// BazarListeAction's leaflet map, admin-content-action.twig's table, gogocarto.twig and the
// mermaid rule in ContentAssetScanner all emit their JavaScript from a PHP string or a Twig
// template, where no linter and no earlier version of this script could see it. The wave-two
// spec called that gap out; this closes it.
//
// A line counts when it contains *both* `addEventListener` and `DOMContentLoaded`, which
// distinguishes a registration from prose about one -- the comments explaining these very
// conversions mention `DOMContentLoaded` and must not be counted.
$countDomContentLoaded = function (string $dir, array $extensions) use ($root): int {
    $path = $root . '/' . $dir;
    if (!is_dir($path)) {
        return 0;
    }

    $found = 0;
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $file) {
        if (!$file->isFile() || !\in_array($file->getExtension(), $extensions, true)) {
            continue;
        }
        $filePath = $file->getPathname();
        if (str_contains($filePath, '/vendor/') || str_ends_with($filePath, 'yw-init.js')) {
            continue;
        }
        foreach (explode("\n", (string)file_get_contents($filePath)) as $line) {
            $trimmed = ltrim($line);
            // a comment about the convention is not a use of it
            if (str_starts_with($trimmed, '//') || str_starts_with($trimmed, '*') || str_starts_with($trimmed, '{#')) {
                continue;
            }
            if (str_contains($line, 'addEventListener') && str_contains($line, 'DOMContentLoaded')) {
                $found++;
            }
        }
    }

    return $found;
};

$domContentLoadedInJs = $countDomContentLoaded('javascripts', ['js']);
$domContentLoadedInMarkup = $countDomContentLoaded('src', ['php'])
    + $countDomContentLoaded('templates', ['twig'])
    + $countDomContentLoaded('themes', ['twig']);
$domContentLoadedInits = $domContentLoadedInJs + $domContentLoadedInMarkup;

$result = [
    'wiki_call_sites' => $wikiCallSites,
    'asset_globals' => $assetGlobals,
    'domcontentloaded_initialisers' => $domContentLoadedInits,
    'domcontentloaded_initialisers_in_js' => $domContentLoadedInJs,
    'domcontentloaded_initialisers_in_markup' => $domContentLoadedInMarkup,
    'latent_dependency_cycles' => $backEdges,
    'latent_dependency_cycles_without_wiki' => $backEdgesWithoutWiki,
    'wave_start' => [
        'wiki_call_sites' => 924,
        'latent_dependency_cycles' => 80,
        'asset_globals' => 28,
        'domcontentloaded_initialisers' => 30,
    ],
];

if ($asJson) {
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR), "\n";
    exit(0);
}

printf("wave-two counters (target: 0 / 0 after ticket 08)\n\n");
printf("  %-28s %6d   (wave start: %d)\n", '$this->wiki-> call sites', $wikiCallSites, 924);
printf("  %-28s %6d   (wave start: %d)\n", 'latent dependency cycles', $backEdges, 80);
printf(
    "  %-28s %6d   (ticket 04 owns these; the rest go with Wiki in ticket 08)\n",
    '  of which not via Wiki',
    $backEdgesWithoutWiki
);
printf("\n  ticket 14/15 (assets are declared, not accumulated)\n\n");
printf("  %-28s %6d   (ticket 14 start: %d; ticket 15 takes it to 0)\n", '$GLOBALS css/js uses', $assetGlobals, 28);
printf(
    "  %-28s %6d   (ticket 14 start: %d; %d in js, %d in php/twig)\n",
    'DOMContentLoaded initialisers',
    $domContentLoadedInits,
    30,
    $domContentLoadedInJs,
    $domContentLoadedInMarkup
);
echo "\n";

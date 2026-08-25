<?php

namespace YesWiki\Render\Service;

use YesWiki\Content\Service\ActionCallRewriter;
use YesWiki\Files\Service\LocalFiles;
use YesWiki\Kernel\Health\HealthCheck;
use YesWiki\Kernel\Health\ProvidesHealthChecks;

/**
 * What the files on disk still say, which a migration can find but never fix (ticket 53).
 *
 * These used to be written into the administrative log by the migrations that found them, where
 * they stayed true-looking for years after somebody edited the files. They are claims about the
 * present, so they are re-derived instead: fixing the files makes them disappear with no second
 * run and nothing to acknowledge (ADR-0026).
 */
class ThemeHealthChecks implements ProvidesHealthChecks
{
    /** Where a theme or an override can live. Program paths, walked as they stand on disk. */
    private const SEARCHED = ['themes', 'custom', 'extensions'];

    private const TEMPLATE_EXTENSIONS = ['twig', 'html', 'php'];

    private LocalFiles $localFiles;

    private ActionCallRewriter $rewriter;

    public function __construct(LocalFiles $localFiles, ActionCallRewriter $rewriter)
    {
        $this->localFiles = $localFiles;
        $this->rewriter = $rewriter;
    }

    public function healthChecks(): array
    {
        return [
            HealthCheck::named('themes-call-retired-search-actions')
                ->label(_t('HEALTH_RETIRED_SEARCH_ACTIONS'))
                ->degraded()
                ->says(_t('HEALTH_RETIRED_SEARCH_ACTIONS_SAYS'))
                ->runs(fn (): ?string => $this->filesMatching(
                    '/\{\{\s*(searchform|newtextsearch)\b/i',
                    ['themes', 'custom']
                )),

            HealthCheck::named('files-name-renamed-actions')
                ->label(_t('HEALTH_RENAMED_ACTIONS'))
                ->degraded()
                ->says(_t('HEALTH_RENAMED_ACTIONS_SAYS'))
                ->runs(function (): ?string {
                    $names = array_keys($this->rewriter->actionRenames());
                    if ($names === []) {
                        return null;
                    }
                    $alternation = implode('|', array_map('preg_quote', $names));

                    return $this->filesMatching(
                        '/(\{\{\s*(' . $alternation . ')\b)|(\baction\s*\(\s*[\'"](' . $alternation . ')\b)/i',
                        self::SEARCHED
                    );
                }),
        ];
    }

    /**
     * The template files under $directories whose text matches, as one sentence.
     *
     * Reads the local filesystem rather than Storage on purpose: `themes/`, `extensions/` and an
     * override are the Program's own files, and the point is to name the ones the operator still
     * has to edit by hand. On a wiki whose `custom/` is in a bucket this sees the Program half
     * only, which is a limit of the warning rather than of the wiki.
     *
     * @param list<string> $directories
     */
    private function filesMatching(string $pattern, array $directories): ?string
    {
        $found = [];
        foreach ($directories as $directory) {
            if (!$this->localFiles->isDirectory($directory)) {
                continue;
            }
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory));
            foreach ($iterator as $file) {
                if (!$file->isFile() || !in_array($file->getExtension(), self::TEMPLATE_EXTENSIONS, true)) {
                    continue;
                }
                if (preg_match($pattern, $this->localFiles->read($file->getPathname())) === 1) {
                    $found[] = $file->getPathname();
                }
            }
        }

        return $found === [] ? null : implode(', ', $found);
    }
}

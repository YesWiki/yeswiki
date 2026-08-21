<?php

use YesWiki\Admin\Service\AdministrativeLogService;
use YesWiki\Content\Entity\PageBody;
use YesWiki\Content\Service\ActionCallRewriter;
use YesWiki\Core\YesWikiMigration;
use YesWiki\Kernel\Database\SqlFragment;
use YesWiki\Kernel\Database\SqlParameters;
use YesWiki\Kernel\Service\DbService;
use YesWiki\Search\Service\SearchIndexer;

/** Ticket 33: the rename migration tickets 22 and 23 deliberately deferred. */
class RenameActionsAndParametersInBodies extends YesWikiMigration
{
    public function run()
    {
        $db = $this->getService(DbService::class);
        $log = $this->getService(AdministrativeLogService::class);
        $rewriter = $this->getService(ActionCallRewriter::class);
        $pages = $db->prefixTable('pages');

        $candidates = $this->candidatePredicate($rewriter, $db->jsonAsText('body'));
        $rows = $db->loadAll(
            "SELECT id, tag, body FROM {$pages} WHERE " . $candidates->sql,
            $candidates->params
        );

        $rewritten = [];
        foreach ($rows as $row) {
            $body = PageBody::decode((string)$row['body']);
            $changed = $rewriter->rewriteBody($body);
            if ($changed === null) {
                continue;
            }

            $db->query(
                "UPDATE {$pages} SET body = ? WHERE id = ?",
                [PageBody::encode($changed), (string)$row['id']]
            );
            $rewritten[(string)$row['tag']] = true;
        }

        $this->getService(SearchIndexer::class)->enqueue(array_keys($rewritten));

        if ($rewritten !== []) {
            $log->log(
                'migration',
                'Ticket 33: renamed French action and parameter names in the stored bodies of '
                . count($rewritten) . ' page(s), across all revisions: '
                . implode(', ', array_keys($rewritten))
                . '. Parameter values and template filenames were left unchanged.'
            );
        }

        $this->reportFilesStillUsingFrenchNames($log, $rewriter);
        $this->reportLeftoverToolsDirectory($log);
    }

    /** Narrow the sweep to rows that could possibly contain something to rewrite. */
    private function candidatePredicate(ActionCallRewriter $rewriter, string $bodyAsText): SqlFragment
    {
        $clauses = array_map(
            static fn (string $needle): SqlFragment => SqlFragment::of(
                $bodyAsText . ' LIKE ?' . SqlParameters::LIKE_CLAUSE_SUFFIX,
                [SqlParameters::likeContains($needle)]
            ),
            $rewriter->candidateNeedles()
        );

        return SqlFragment::all(' OR ', ...$clauses)->wrappedIn('(', ')');
    }

    /** Templates and squelettes are files, not rows. */
    private function reportFilesStillUsingFrenchNames(AdministrativeLogService $log, ActionCallRewriter $rewriter): void
    {
        $names = array_keys($rewriter->actionRenames());
        if ($names === []) {
            return;
        }
        $alternation = implode('|', array_map('preg_quote', $names));
        $pattern = '/(\{\{\s*(' . $alternation . ')\b)|(\baction\s*\(\s*[\'"](' . $alternation . ')\b)/i';

        $found = [];
        foreach (['themes', 'custom', 'extensions'] as $dir) {
            if (!is_dir($dir)) {
                continue;
            }
            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
            foreach ($iterator as $file) {
                if (!$file->isFile() || !in_array($file->getExtension(), ['twig', 'html', 'php'], true)) {
                    continue;
                }
                if (preg_match($pattern, (string)@file_get_contents($file->getPathname())) === 1) {
                    $found[] = $file->getPathname();
                }
            }
        }

        if ($found !== []) {
            $log->log(
                'migration',
                'Ticket 33: these files still name a renamed French action and were NOT rewritten '
                . '-- files on disk are yours to edit, see docs/action-name-renames.json for the '
                . 'mapping: ' . implode(', ', $found),
                ''
            );
        }
    }

    /** The other silent upgrade failure: extensions left in `tools/`. */
    private function reportLeftoverToolsDirectory(AdministrativeLogService $log): void
    {
        if (!is_dir('tools')) {
            return;
        }
        $entries = array_values(array_filter(
            (array)scandir('tools'),
            fn ($entry) => !in_array($entry, ['.', '..'], true) && is_dir('tools/' . $entry)
        ));
        if ($entries === []) {
            return;
        }

        $log->log(
            'migration',
            'Extensions are loaded from extensions/ and custom/extensions/ now; tools/ is not '
            . 'scanned, so these are being silently ignored and their features no longer exist. '
            . 'Move them to custom/extensions/ and check each is Ectoplasme-compatible: '
            . implode(', ', $entries),
            ''
        );
    }
}

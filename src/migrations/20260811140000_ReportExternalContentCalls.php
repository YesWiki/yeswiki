<?php

use YesWiki\Admin\Service\AdministrativeLogService;
use YesWiki\Core\YesWikiMigration;
use YesWiki\Kernel\Database\SqlFragment;
use YesWiki\Kernel\Database\SqlParameters;
use YesWiki\Kernel\Service\DbService;

/** Ticket 34: name every page that asked another wiki for its content. */
class ReportExternalContentCalls extends YesWikiMigration
{
    /**
     * An action parameter whose value is a url followed by `|` and one or more remote form ids -- the syntax BazarListService::getIDs() parses into its `externals`.
     */
    private const EXTERNAL_ID = '/\b(?:id|form_id|formid)\s*=\s*"\s*(https?:\/\/[^"|]+)\|([^"]*)"/i';

    public function run()
    {
        $db = $this->getService(DbService::class);
        $log = $this->getService(AdministrativeLogService::class);
        $pages = $db->prefixTable('pages');

        $bodyAsText = $db->jsonAsText('body');
        $candidates = SqlFragment::all(
            ' OR ',
            SqlFragment::of(
                $bodyAsText . ' LIKE ?' . SqlParameters::LIKE_CLAUSE_SUFFIX,
                [SqlParameters::likeContains('http://')]
            ),
            SqlFragment::of(
                $bodyAsText . ' LIKE ?' . SqlParameters::LIKE_CLAUSE_SUFFIX,
                [SqlParameters::likeContains('https://')]
            )
        )->wrappedIn('(', ')');

        $rows = $db->loadAll(
            "SELECT tag, body FROM {$pages} WHERE latest = ? AND " . $candidates->sql,
            ['Y', ...$candidates->params]
        );

        $found = [];
        foreach ($rows as $row) {
            if (preg_match_all(self::EXTERNAL_ID, (string)$row['body'], $matches, PREG_SET_ORDER) === 0) {
                continue;
            }
            foreach ($matches as $match) {
                $found[(string)$row['tag']][] = trim($match[2]) . ' @ ' . trim($match[1]);
            }
        }

        if ($found === []) {
            return;
        }

        $described = [];
        foreach ($found as $tag => $sources) {
            $described[] = $tag . ' (' . implode(', ', array_unique($sources)) . ')';
        }

        $log->log(
            'migration',
            'Ticket 34: these pages ask another wiki for entries at display time, which no longer '
            . 'happens -- they now show an explanation instead of a list. Import the content '
            . 'instead (admin importers screen), then point the list at the local form. '
            . 'Pages, with the remote form id and wiki each one asked for: '
            . implode('; ', $described)
        );
    }

    /** The pattern, exposed so a test can pin it against real syntax rather than re-deriving it. */
    public static function externalIdPattern(): string
    {
        return self::EXTERNAL_ID;
    }
}

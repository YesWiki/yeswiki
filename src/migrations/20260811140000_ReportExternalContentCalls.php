<?php

use YesWiki\Admin\Service\AdministrativeLogService;
use YesWiki\Core\YesWikiMigration;
use YesWiki\Kernel\Database\SqlFragment;
use YesWiki\Kernel\Database\SqlParameters;
use YesWiki\Kernel\Service\DbService;

/**
 * Ticket 34: name every page that asked another wiki for its content.
 *
 * `{{entrylist id="https://other.wiki|4"}}` fetched that wiki's entries over HTTP on every page
 * view. Content from elsewhere is imported now, so such a call renders an explanation instead of a
 * list -- and this migration turns that from a surprise into a to-do list, because a webmaster
 * cannot fix what they have not been told about.
 *
 * ## Why it reports rather than rewrites
 *
 * Nothing here can be automated honestly. Configuring the replacement needs a decision this
 * migration is not in a position to make: which local form the remote one maps onto, whether the
 * local copy may diverge (`syncMode`), whether files are downloaded or linked (`filesMode`), and
 * -- for a form that is not publicly readable -- credentials on the remote wiki. Stripping the url
 * to leave a local form id would make the page render *a different list*, silently, which is worse
 * than an explained absence.
 *
 * So: the pages are listed, with the url and remote form id each one asked for, and the fix is a
 * human decision made once per source in the importers admin screen.
 *
 * Idempotent, and re-runnable on purpose: it changes nothing, so running it again after moving
 * some of the lists over just produces a shorter list.
 */
class ReportExternalContentCalls extends YesWikiMigration
{
    /**
     * An action parameter whose value is a url followed by `|` and one or more remote form ids --
     * the syntax BazarListService::getIDs() parses into its `externals`. Deliberately not anchored
     * on `id=`: the same value reaches `{{entrylist}}`, `{{entrymap}}`, `{{entrytable}}`,
     * `{{bazar}}`, `{{calendar}}` and the export/import actions, and some of them spell the
     * parameter `form_id`.
     */
    private const EXTERNAL_ID = '/\b(?:id|form_id|formid)\s*=\s*"\s*(https?:\/\/[^"|]+)\|([^"]*)"/i';

    public function run()
    {
        $db = $this->getService(DbService::class);
        $log = $this->getService(AdministrativeLogService::class);
        $pages = $db->prefixTable('pages');

        // only the current revision: this is a to-do list for a human, and an old revision of a
        // page is not something anybody is being asked to go and fix
        $candidates = SqlFragment::all(
            ' OR ',
            SqlFragment::of(
                'body LIKE ?' . SqlParameters::LIKE_CLAUSE_SUFFIX,
                [SqlParameters::likeContains('http://')]
            ),
            SqlFragment::of(
                'body LIKE ?' . SqlParameters::LIKE_CLAUSE_SUFFIX,
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

        // The action names are written without braces on purpose: the log is itself a wiki page,
        // so `{{entrylist}}` in it would not be a record of anything, it would BE an entry list.
        $log->log(
            'migration',
            'Ticket 34: these pages ask another wiki for entries at display time, which no longer '
            . 'happens -- they now show an explanation instead of a list. Import the content '
            . 'instead (admin importers screen), then point the list at the local form. '
            . 'Pages, with the remote form id and wiki each one asked for: '
            . implode('; ', $described)
        );
    }

    /**
     * The pattern, exposed so a test can pin it against real syntax rather than re-deriving it.
     */
    public static function externalIdPattern(): string
    {
        return self::EXTERNAL_ID;
    }
}

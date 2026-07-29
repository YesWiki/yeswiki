<?php

use YesWiki\Content\Service\PageBodyMigrator;
use YesWiki\Core\YesWikiMigration;

/**
 * Ticket 09: `pages.body` becomes a JSON object for every Content type, for every
 * revision -- not just `latest`.
 *
 * Converting only current revisions was considered and rejected: it would leave the old
 * shape alive in history forever and force a read-time normalization branch in the
 * hottest read path in the codebase, which is the thing this ticket exists to remove.
 * Wiki pages and comments gain a `content` attribute holding their markup verbatim;
 * entries, forms, users and lists were already JSON field-maps and are left alone.
 *
 * All the logic (including the pure `classify()`) lives in PageBodyMigrator so it can be
 * unit-tested without a wiki and dry-run from the console before anyone commits to it:
 *
 *     ./yeswicli content:migrate-bodies --dry-run
 *
 * The conversion is idempotent, which is what makes it safe here: the migration runner
 * has no transaction, and it swallows a failing migration's exception without recording
 * completion, so a partial run is finished by simply running again.
 */
class PageBodyToJson extends YesWikiMigration
{
    public function run(): void
    {
        $migrator = $this->getService(PageBodyMigrator::class);
        $counts = $migrator->apply();

        $failures = $migrator->verify();
        if (!empty($failures)) {
            $first = array_slice($failures, 0, 5);
            $detail = implode(', ', array_map(
                fn (array $f) => "{$f['tag']}#{$f['id']} ({$f['reason']})",
                $first
            ));

            throw new Exception('Body migration left ' . count($failures) . ' row(s) unconverted: ' . $detail . '. Nothing is recorded as migrated, so re-running is safe once the cause is fixed.');
        }

        // the runner records completion itself; nothing consumes a return value
        unset($counts);
    }
}

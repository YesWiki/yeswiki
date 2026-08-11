<?php

use YesWiki\Admin\Service\AdministrativeLogService;
use YesWiki\Content\Entity\PageBody;
use YesWiki\Core\YesWikiMigration;
use YesWiki\Kernel\Service\DbService;
use YesWiki\Search\Service\SearchIndexer;

/**
 * The login modal is gone; the navbar links to `/user` instead.
 *
 * `modal.twig` is deleted in this release, and `PageRapideHaut` on every existing wiki says
 * `{{login template="modal.twig"}}`. LoginAction falls back to `default.twig` when a
 * template is missing, so nothing would *break* -- it would render the entire login form,
 * inline, in the navigation bar of every page. Hence a rewrite rather than a fallback.
 *
 * Also rewritten: `#LoginModal` anchors. A link to a modal that no longer exists is a link
 * to nowhere, and wikis put "sign in to comment" links in their own prose.
 *
 * Bodies are JSON, so this goes through PageBody rather than a string replace on the column
 * (ticket 25's defect 3). Every revision is swept, not just the latest: reverting a page
 * must not bring the modal back. Idempotent -- once nothing mentions either, it does
 * nothing.
 */
class ReplaceLoginModalWithAccountLink extends YesWikiMigration
{
    private const REWRITES = [
        // the template parameter, however it was quoted
        '/(\{\{\s*login\b[^}]*template\s*=\s*["\'])modal\.twig(["\'])/i' => '$1account-link.twig$2',
        // and any anchor that pointed at the modal itself, in markup or in wiki syntax
        '/#LoginModal\b/' => '?user',
    ];

    public function run()
    {
        $db = $this->getService(DbService::class);
        $log = $this->getService(AdministrativeLogService::class);
        $pages = $db->prefixTable('pages');

        $rows = $db->loadAll(
            "SELECT id, tag, body FROM {$pages}"
            . " WHERE body LIKE '%modal.twig%' OR body LIKE '%LoginModal%'"
        );

        $rewritten = [];
        foreach ($rows as $row) {
            $body = PageBody::decode((string)$row['body']);
            $changed = $this->rewriteBody($body);
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

        foreach (array_keys($rewritten) as $tag) {
            // no third argument: the log goes to the daily log page, not into the body just
            // rewritten (see RewriteRetiredSearchActions)
            $log->log(
                'migration',
                "the login modal is replaced by the /user route; page '{$tag}' was rewritten to "
                . 'the account link template. A custom modal template or an anchor to '
                . 'LoginModal could not be carried over.'
            );
        }
    }

    /**
     * @param array<string, mixed> $body
     *
     * @return array<string, mixed>|null null when nothing in it changed
     */
    private function rewriteBody(array $body): ?array
    {
        $changed = false;
        array_walk_recursive($body, function (&$value) use (&$changed): void {
            if (!is_string($value)) {
                return;
            }
            $before = $value;
            foreach (self::REWRITES as $pattern => $replacement) {
                $value = (string)preg_replace($pattern, $replacement, $value);
            }
            if ($value !== $before) {
                $changed = true;
            }
        });

        return $changed ? $body : null;
    }
}

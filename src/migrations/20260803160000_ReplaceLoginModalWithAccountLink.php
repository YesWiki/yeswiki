<?php

use YesWiki\Admin\Service\AdministrativeLogService;
use YesWiki\Content\Entity\PageBody;
use YesWiki\Core\YesWikiMigration;
use YesWiki\Kernel\Service\DbService;
use YesWiki\Search\Service\SearchIndexer;

/** The login modal is gone; the navbar links to `/user` instead. */
class ReplaceLoginModalWithAccountLink extends YesWikiMigration
{
    private const REWRITES = [
        '/(\{\{\s*login\b[^}]*template\s*=\s*["\'])modal\.twig(["\'])/i' => '$1account-link.twig$2',

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

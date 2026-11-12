<?php

use YesWiki\Content\Entity\PageBody;
use YesWiki\Core\YesWikiMigration;
use YesWiki\Identity\Service\AuthenticationService;
use YesWiki\Kernel\Service\DbService;
use YesWiki\Search\Service\SearchIndexer;

/**
 * loginsso's `{{linktouserprofil}}` and `{{userentries}}`, which it seeded into PageMenuUser and UserEntries, become links to `/user` and
 * `/user/entries` (spec section 11). The links are written as Markdown, `[label](target)`: the form LegacyMarkupBecomesMarkdown gave
 * loginsso's own `[[target label]]`, which Ectoplasme no longer renders. The labels are translated now, in the wiki's language.
 *
 * Only the latest revision is read, and each page it changes gets one new revision, written with SQL rather than PageManager::save():
 * the older revisions stay as history, the change shows in the page's history like any edit and can be reverted, and nothing else
 * happens -- save() would also write a `content.update` to the Journal, where a migration writes only `migration.applied` (README),
 * and tell webhooks the page was edited.
 */
class LoginssoActionsBecomeLinks extends YesWikiMigration
{
    /** One call, with its parameters, spelt any way: `{{ UserEntries template="…" }}` too. */
    private const CALL = '/\{\{\s*(linktouserprofil|userentries)(?![\w-])((?:(?!\}\}).)*)\}\}/si';

    private const TARGETS = ['linktouserprofil' => 'user', 'userentries' => 'user/entries'];

    private const LABELS = ['linktouserprofil' => 'SIGN_IN_UPGRADE_PROFILE_LINK', 'userentries' => 'SIGN_IN_UPGRADE_ENTRIES_LINK'];

    /** @var array<string, true> the pages where `{{userentries}}` named someone else, whose entries the link no longer shows */
    private array $namedSomeone = [];

    public function run()
    {
        $tags = $this->rewrite($this->getService(DbService::class));
        if ($tags === []) {
            return;
        }

        $this->getService(SearchIndexer::class)->enqueue($tags);
        $this->say(
            "loginsso's {{linktouserprofil}} and {{userentries}} became links to the account screens /user and /user/entries, "
            . 'in one new revision of ' . count($tags) . ' page(s): ' . implode(', ', $tags) . '.'
        );
        if ($this->namedSomeone !== []) {
            $this->say(
                '{{userentries username="…"}} listed another person\'s entries; the link shows the reader their own. Pages to check: '
                . implode(', ', array_keys($this->namedSomeone)) . '.'
            );
        }
    }

    /**
     * Gives each page whose latest revision calls one of the actions a new revision with links in their place.
     *
     * @param ?list<string> $onlyTags the pages to look at, every page when null: the tests look only at the ones they made
     *
     * @return list<string> the pages given a new revision
     */
    public function rewrite(DbService $db, ?array $onlyTags = null): array
    {
        if ($onlyTags === []) {
            return [];
        }
        $pages = $db->prefixTable('pages');
        $text = 'LOWER(' . $db->jsonAsText('body') . ')';
        $sql = "SELECT * FROM {$pages} WHERE latest = 'Y' AND ({$text} LIKE '%linktouserprofil%' OR {$text} LIKE '%userentries%')";
        $params = [];
        if ($onlyTags !== null) {
            $sql .= ' AND tag IN (' . implode(', ', array_fill(0, count($onlyTags), '?')) . ')';
            $params = $onlyTags;
        }

        $user = $this->getService(AuthenticationService::class)->getLoggedUserName();
        $rewritten = [];
        foreach ($db->loadAll($sql . ' ORDER BY tag ASC', $params) as $row) {
            $tag = (string)$row['tag'];
            $body = $this->rewriteBody(PageBody::decode((string)$row['body']), $tag);
            if ($body === null) {
                continue;
            }
            $db->transactional(fn () => $this->addRevision($db, $row, $body, $user));
            $rewritten[] = $tag;
        }

        return $rewritten;
    }

    /**
     * The latest revision stops being it, and a copy with the new body takes its place: what PageManager::save() writes, minus its side effects.
     *
     * @param array<string, mixed>    $latest
     * @param array<array-key, mixed> $body
     */
    private function addRevision(DbService $db, array $latest, array $body, string $user): void
    {
        $pages = $db->prefixTable('pages');
        $db->query("UPDATE {$pages} SET latest = 'N' WHERE tag = ?", [(string)$latest['tag']]);

        $columns = [
            'tag' => (string)$latest['tag'],
            'owner' => (string)$latest['owner'],
            // the revision is the upgrade's, made by whoever ran it: nobody, from the command line
            $db->quoteIdentifier('user') => $user,
            'latest' => 'Y',
            'body' => PageBody::encode($body),
            $db->quoteIdentifier('type') => (string)$latest['type'],
            'parent' => (string)($latest['parent'] ?? ''),
            'metadata' => $latest['metadata'] ?? null,
        ];
        $names = array_keys($columns);
        $placeholders = array_fill(0, count($columns), '?');
        $names[] = $db->quoteIdentifier('time');
        $placeholders[] = $db->now();

        $db->query(
            "INSERT INTO {$pages} (" . implode(', ', $names) . ') VALUES (' . implode(', ', $placeholders) . ')',
            array_values($columns)
        );
    }

    /**
     * Every text of the body, its translations included, with the calls replaced.
     *
     * @param array<array-key, mixed> $body
     *
     * @return array<array-key, mixed>|null null when nothing in it changed
     */
    private function rewriteBody(array $body, string $tag): ?array
    {
        $changed = false;
        array_walk_recursive($body, function (&$value) use (&$changed, $tag): void {
            if (!is_string($value) || stripos($value, '{{') === false) {
                return;
            }
            $rewritten = (string)preg_replace_callback(self::CALL, fn (array $call): string => $this->link($call, $tag), $value);
            if ($rewritten !== $value) {
                $value = $rewritten;
                $changed = true;
            }
        });

        return $changed ? $body : null;
    }

    /** @param array<int, string> $call */
    private function link(array $call, string $tag): string
    {
        $action = strtolower($call[1]);
        if ($action === 'userentries' && preg_match('/(^|\s)username\s*=\s*(["\'])\s*\S/i', $call[2]) === 1) {
            $this->namedSomeone[$tag] = true;
        }
        // loginsso's `dash` drew " - " before the link: a Markdown line starting with it would be a list item
        $label = str_replace(['\\', '[', ']'], ['\\\\', '\\[', '\\]'], _t(self::LABELS[$action]));

        return '[' . $label . '](' . self::TARGETS[$action] . ')';
    }
}

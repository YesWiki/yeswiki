<?php

namespace YesWiki\Content\Service;

use Psr\Container\ContainerInterface;
use YesWiki\Content\Entity\PageType;
use YesWiki\Identity\Service\AuthenticationService;
use YesWiki\Kernel\Service\DbService;
use YesWiki\Kernel\Service\EventDispatcher;
use YesWiki\Kernel\Service\HibernationService;
use YesWiki\Kernel\Service\Journal;
use YesWiki\Kernel\Service\TripleStore;

/**
 * Deleted content kept for `trash_keep_days` with every revision (end-users 13): its rows stay, none of them latest, its triples
 * move into one `PageManager::TRASH_PROPERTY` triple that says when, by whom and what came along, so every reader of the latest row already treats it as absent.
 */
class Trash
{
    public const DEFAULT_KEEP_DAYS = 30;

    public function __construct(
        private readonly DbService $db,
        private readonly PageManager $pages,
        private readonly TripleStore $triples,
        private readonly HibernationService $hibernation,
        private readonly ContainerInterface $container,
    ) {
    }

    /** @param list<string> $with the tags that go along, restored and erased with it; moves the content and its comments to the trash, false when it has no current version */
    public function trash(string $tag, array $with = [], string $partOf = ''): bool
    {
        if ($this->hibernation->isWikiHibernated()) {
            throw new \Exception(_t('WIKI_IN_HIBERNATION'));
        }
        $page = $this->pages->getOne($tag, null, false, true);
        if ($page === null || $this->pages->isTrashed($tag)) {
            return false;
        }
        $type = (string)($page['type'] ?? PageType::DEFAULT);
        $record = [
            'at' => date('Y-m-d H:i:s'),
            'by' => $this->container->get(AuthenticationService::class)->getLoggedUserName(),
            'type' => $type,
            'title' => $this->container->get(PageSummary::class)->title($page),
            'comments' => $this->commentTagsOf($tag),
            'triples' => $this->triplesOf($tag),
            'with' => array_values(array_diff($with, [$tag])),
            'partOf' => $partOf,
        ];

        $this->db->transactional(function () use ($tag, $record): void {
            $this->setLatest([$tag, ...$record['comments']], false);
            $this->triples->deleteAll($tag, '');
            $this->triples->create($tag, PageManager::TRASH_PROPERTY, (string)json_encode($record, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), '', '');
        });
        $this->forget([$tag, ...$record['comments']]);

        $this->container->get(Journal::class)->audit('content.trash', $tag, ['type' => $type]);
        $this->container->get(EventDispatcher::class)->yesWikiDispatch('page.deleted', ['id' => $tag]);

        return true;
    }

    /** Puts trashed content back as it was, with its comments, its triples and what came along; false when it is not in the trash or came along with something else. */
    public function restore(string $tag): bool
    {
        $record = $this->record($tag);
        if ($record === null || $record['partOf'] !== '') {
            return false;
        }

        return $this->restoreRecord($tag, $record);
    }

    /** Deletes trashed content for good, with what came along. */
    public function erase(string $tag): bool
    {
        $record = $this->record($tag);
        if ($record === null) {
            return false;
        }
        foreach ($record['with'] as $alongWith) {
            $this->erase($alongWith);
        }
        foreach (array_reverse($record['comments']) as $comment) {
            $this->pages->deleteOrphaned($comment);
        }
        $this->pages->deleteOrphaned($tag);

        return true;
    }

    /** @return list<array{tag: string, at: string, by: string, type: string, title: string, with: list<string>}> what is in the trash, newest first, only what that user deleted unless null, never what came along */
    public function list(?string $deletedBy = null): array
    {
        $items = [];
        foreach ($this->triples->getMatching(null, PageManager::TRASH_PROPERTY, null, '=', '=', '=') as $row) {
            $record = self::decode((string)($row['value'] ?? ''));
            if ($record === null || $record['partOf'] !== '' || ($deletedBy !== null && $record['by'] !== $deletedBy)) {
                continue;
            }
            $items[] = ['tag' => (string)$row['resource']] + array_intersect_key($record, array_flip(['at', 'by', 'type', 'title', 'with']));
        }
        usort($items, static fn (array $a, array $b): int => strcmp($b['at'], $a['at']));

        return $items;
    }

    /** @return array{at: string, by: string, type: string, title: string, comments: list<string>, triples: list<array{0: string, 1: string}>, with: list<string>, partOf: string}|null what the trash says of a tag */
    public function record(string $tag): ?array
    {
        $value = $this->triples->getOne($tag, PageManager::TRASH_PROPERTY, '', '');

        return $value === null ? null : self::decode($value);
    }

    /** Whether that user may put it back or erase it: an admin, or whoever deleted it. */
    public function mayHandle(string $tag): bool
    {
        $record = $this->record($tag);
        if ($record === null) {
            return false;
        }
        $user = $this->container->get(AuthenticationService::class)->getLoggedUserName();

        return $this->container->get(\YesWiki\Identity\Service\AclService::class)->isAdmin() || ($user !== '' && $user === $record['by']);
    }

    /** Erases what has been in the trash longer than that many days; how many items went. */
    public function purgeOlderThan(int $days): int
    {
        if ($days <= 0 || $this->hibernation->isWikiHibernated()) {
            return 0;
        }
        $limit = date('Y-m-d H:i:s', time() - $days * 86400);
        $erased = 0;
        foreach ($this->list() as $item) {
            if ($item['at'] < $limit && $this->erase($item['tag'])) {
                $erased++;
            }
        }

        return $erased;
    }

    /** @param array{at: string, by: string, type: string, title: string, comments: list<string>, triples: list<array{0: string, 1: string}>, with: list<string>, partOf: string} $record */
    private function restoreRecord(string $tag, array $record): bool
    {
        if ($this->hibernation->isWikiHibernated()) {
            throw new \Exception(_t('WIKI_IN_HIBERNATION'));
        }
        $this->db->transactional(function () use ($tag, $record): void {
            $this->setLatest([$tag, ...$record['comments']], true);
            $this->triples->deleteAll($tag, '');
            foreach ($record['triples'] as [$property, $value]) {
                $this->triples->create($tag, $property, $value, '', '');
            }
        });
        $this->forget([$tag, ...$record['comments']]);
        foreach ($record['with'] as $alongWith) {
            $alongRecord = $this->record($alongWith);
            if ($alongRecord !== null) {
                $this->restoreRecord($alongWith, $alongRecord);
            }
        }

        $this->container->get(Journal::class)->audit('content.restore', $tag, ['type' => $record['type']]);
        $this->announceRestored($tag, $record['type']);

        return true;
    }

    /** Tells the index, the caches, webhooks and ActivityPub that the content exists again, as they were told it went. */
    private function announceRestored(string $tag, string $type): void
    {
        $events = $this->container->get(EventDispatcher::class);
        $events->yesWikiDispatch('page.created', ['id' => $tag, 'data' => ['tag' => $tag]]);
        if ($type === PageType::ENTRY) {
            $entry = $this->container->get(EntryManager::class)->getOne($tag, false, null, false, true);
            if (is_array($entry)) {
                $events->yesWikiDispatch('entry.created', [
                    'id' => $tag,
                    'data' => $entry,
                    'form' => $this->container->get(FormManager::class)->getOne($entry['form_id'] ?? '') ?? [],
                    'imported' => false,
                ]);
            }
        }
        if ($type === PageType::FORM) {
            $page = $this->pages->getOne($tag, null, false, true);
            $formId = is_array($page['body'] ?? null) ? (string)($page['body']['id'] ?? '') : '';
            if ($formId !== '') {
                $events->yesWikiDispatch('form.updated', ['id' => $formId, 'data' => ['form_id' => $formId, 'tag' => $tag]]);
            }
        }
    }

    /** @param list<string> $tags */
    private function setLatest(array $tags, bool $latest): void
    {
        $pages = $this->db->prefixTable('pages');
        foreach ($tags as $tag) {
            if (!$latest) {
                $this->db->query("UPDATE {$pages} SET latest = 'N' WHERE tag = ?", [$tag]);

                continue;
            }
            $newest = $this->db->loadSingle("SELECT MAX(id) AS id FROM {$pages} WHERE tag = ?", [$tag]);
            if ($newest !== null && $newest['id'] !== null) {
                $this->db->query("UPDATE {$pages} SET latest = 'Y' WHERE id = ?", [(int)$newest['id']]);
            }
        }
    }

    /** @return list<string> the comments on a tag and the answers to them, parents first */
    private function commentTagsOf(string $tag): array
    {
        $pages = $this->db->prefixTable('pages');
        $found = [];
        $parents = [$tag];
        while ($parents !== []) {
            $placeholders = implode(', ', array_fill(0, count($parents), '?'));
            $children = array_column($this->db->loadAll("SELECT DISTINCT tag FROM {$pages} WHERE latest = 'Y' AND parent <> '' AND parent IN ({$placeholders})", $parents), 'tag');
            $parents = array_values(array_diff(array_map('strval', $children), $found, [$tag]));
            $found = [...$found, ...$parents];
        }

        return $found;
    }

    /** @return list<array{0: string, 1: string}> */
    private function triplesOf(string $tag): array
    {
        return array_map(
            static fn (array $row): array => [(string)$row['property'], (string)$row['value']],
            $this->triples->getMatching($tag, null, null, '=', '=', '=')
        );
    }

    /** @param list<string> $tags */
    private function forget(array $tags): void
    {
        foreach ($tags as $tag) {
            $this->pages->forget($tag);
            $this->triples->forget($tag, '');
        }
        $this->container->get(\YesWiki\Identity\Service\AclService::class)->forget($tags[0] ?? '');
    }

    /** @return array{at: string, by: string, type: string, title: string, comments: list<string>, triples: list<array{0: string, 1: string}>, with: list<string>, partOf: string}|null */
    private static function decode(string $value): ?array
    {
        $record = json_decode($value, true);
        if (!is_array($record)) {
            return null;
        }

        return [
            'at' => (string)($record['at'] ?? ''),
            'by' => (string)($record['by'] ?? ''),
            'type' => (string)($record['type'] ?? PageType::DEFAULT),
            'title' => (string)($record['title'] ?? ''),
            'comments' => array_values(array_map('strval', (array)($record['comments'] ?? []))),
            'triples' => array_values(array_map(static fn ($pair): array => [(string)($pair[0] ?? ''), (string)($pair[1] ?? '')], array_filter((array)($record['triples'] ?? []), 'is_array'))),
            'with' => array_values(array_map('strval', (array)($record['with'] ?? []))),
            'partOf' => (string)($record['partOf'] ?? ''),
        ];
    }
}

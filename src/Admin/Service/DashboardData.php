<?php

namespace YesWiki\Admin\Service;

use YesWiki\Content\Entity\PageBody;
use YesWiki\Content\Service\PageManager;
use YesWiki\Identity\Entity\Avatar;
use YesWiki\Identity\Service\AclService;
use YesWiki\Identity\Service\AvatarService;
use YesWiki\Identity\Entity\User;
use YesWiki\Identity\Service\UserManager;
use YesWiki\Kernel\Service\DbService;
use YesWiki\Kernel\Service\TripleStore;
use YesWiki\Kernel\Service\UrlFormatter;
use YesWiki\Search\Service\TagsManager;
use YesWiki\Social\Service\CommentService;

/** What the dashboard shows. */
class DashboardData
{
    /** Pages listed in the A-Z index before it says how many it left out. */
    private const INDEX_CAP = 500;

    /** Rows read per row shown, so a list still fills after the unreadable ones are dropped. */
    private const ACL_OVERFETCH = 4;

    public function __construct(
        private readonly DbService $dbService,
        private readonly PageManager $pageManager,
        private readonly UserManager $userManager,
        private readonly CommentService $commentService,
        private readonly AvatarService $avatarService,
        private readonly AclService $aclService,
        private readonly TagsManager $tagsManager,
        private readonly UrlFormatter $urlFormatter,
    ) {
    }

    /**
     * @return list<array{tag: string, time: string, user: string, avatar: Avatar, url: string, history: string}>
     */
    public function recentPages(int $max): array
    {
        $pages = $this->readableOnly(
            $this->pageManager->getRecentlyChanged($max * self::ACL_OVERFETCH) ?? [],
            'tag',
            $max
        );

        return array_map(fn (array $page): array => [
            'tag' => (string)$page['tag'],
            'time' => (string)$page['time'],
            'user' => (string)$page['user'],
            'avatar' => $this->avatarService->forName((string)$page['user']),
            'url' => $this->urlFormatter->href('', (string)$page['tag'], null, false),
            'history' => $this->urlFormatter->href('revisions', (string)$page['tag'], null, false),
        ], $pages);
    }

    /**
     * The newest accounts.
     *
     * @return list<array{name: string, signuptime: string, avatar: Avatar, url: string}>
     */
    public function newestAccounts(int $max): array
    {
        $users = $this->userManager->getAll(['name', 'signuptime']);
        usort($users, fn (User $a, User $b) => strcmp((string)$b['signuptime'], (string)$a['signuptime']));

        return array_map(fn (User $user): array => [
            'name' => $user->getName(),
            'signuptime' => (string)$user['signuptime'],
            'avatar' => $this->avatarService->forName($user->getName()),
            'url' => $this->urlFormatter->href('', $user->getName(), null, false),
        ], array_slice($users, 0, $max));
    }

    /**
     * @return list<array{tag: string, parent: string, time: string, user: string, avatar: Avatar, excerpt: string, url: string}>
     */
    public function recentComments(int $max): array
    {
        return array_map(function (array $comment): array {
            $author = (string)($comment['user'] ?? '');

            return [
                'tag' => (string)$comment['tag'],
                'parent' => (string)$comment['parent'],
                'time' => (string)$comment['time'],
                'user' => $author,
                'avatar' => $this->avatarService->forName($author),
                'excerpt' => $this->excerpt(PageBody::content(PageBody::decode($comment['body'] ?? null))),
                'url' => $this->urlFormatter->href('', (string)$comment['parent'], 'show_comments=1', false)
                    . '#' . (string)$comment['tag'],
            ];
        }, $this->readableOnly(
            $this->commentService->getRecentComments($max * self::ACL_OVERFETCH),
            'parent',
            $max
        ));
    }

    /**
     * @return list<array{value: string, total: int, url: string}>
     */
    public function keywords(int $max): array
    {
        return array_map(fn (array $keyword): array => $keyword + [
            'url' => $this->urlFormatter->href('', 'search', ['tags' => $keyword['value']], false),
        ], $this->tagsManager->mostUsed($max));
    }

    /**
     * Every page by first letter, capped.
     *
     * @return array{letters: array<string, list<array{tag: string, url: string}>>, total: int, shown: int}
     */
    public function pageIndex(): array
    {
        $rows = $this->dbService->loadAll(
            'SELECT tag FROM' . $this->dbService->prefixTable('pages')
            . "WHERE latest = 'Y' AND parent = '' ORDER BY tag"
        );

        $letters = [];
        foreach (array_slice($rows, 0, self::INDEX_CAP) as $row) {
            $tag = (string)$row['tag'];
            $first = mb_strtoupper(mb_substr($tag, 0, 1));
            $letters[preg_match('/^\p{L}$/u', $first) === 1 ? $first : '#'][] = [
                'tag' => $tag,
                'url' => $this->urlFormatter->href('', $tag, null, false),
            ];
        }
        ksort($letters);

        return [
            'letters' => $letters,
            'total' => count($rows),
            'shown' => min(count($rows), self::INDEX_CAP),
        ];
    }

    /**
     * The remote wikis this one has imported Content from, grouped by origin.
     *
     * @return list<array{origin: string, total: int, lastImport: string, entries: list<array{tag: string, source: string, time: string}>}>
     */
    public function sources(): array
    {
        $rows = $this->dbService->loadAll(
            "SELECT t.resource AS tag, t.value AS source, p.{$this->dbService->quoteIdentifier('time')} AS time
             FROM {$this->dbService->prefixTable('triples')} t
             INNER JOIN {$this->dbService->prefixTable('pages')} p ON p.tag = t.resource AND p.latest = 'Y'
             WHERE t.property = ?
             ORDER BY t.value",
            [TripleStore::SOURCE_URL_URI]
        );

        $grouped = [];
        foreach ($rows as $row) {
            $origin = $this->originOf((string)$row['source']);
            $grouped[$origin]['origin'] = $origin;
            $grouped[$origin]['entries'][] = [
                'tag' => (string)$row['tag'],
                'source' => (string)$row['source'],
                'time' => (string)$row['time'],
            ];
        }

        $sources = [];
        foreach ($grouped as $origin => $source) {
            $times = array_column($source['entries'], 'time');
            sort($times);
            $sources[] = [
                'origin' => $origin,
                'total' => count($source['entries']),
                'lastImport' => (string)end($times),
                'entries' => $source['entries'],
            ];
        }
        usort($sources, fn ($a, $b) => $b['total'] <=> $a['total']);

        return $sources;
    }

    /**
     * The feeds and API links the dashboard offers.
     *
     * @return array<string, mixed>
     */
    public function exportLinks(): array
    {
        return [
            'feeds' => [
                [
                    'label' => _t('DASHBOARD_EXPORT_FEED_CHANGES'),
                    'url' => $this->urlFormatter->href('', 'DerniersChangementsRSS', null, false),
                ],
                [
                    'label' => _t('DASHBOARD_EXPORT_FEED_ENTRIES'),
                    'url' => $this->urlFormatter->href('', 'api/entries/rss', null, false),
                ],
            ],
            'fileLinks' => [
                [
                    'icon' => 'paperclip',
                    'label' => _t('DASHBOARD_EXPORT_FILES_LIST'),
                    'url' => $this->urlFormatter->href('', 'api/files', null, false),
                ],
                [
                    'icon' => 'code',
                    'label' => _t('DASHBOARD_EXPORT_API'),
                    'url' => $this->urlFormatter->href('', 'api', null, false),
                ],
            ],
        ];
    }

    /**
     * The rows whose Content the reader may read, capped at $max.
     *
     * @param array<mixed> $rows
     *
     * @return list<array<string, mixed>>
     */
    private function readableOnly(array $rows, string $key, int $max): array
    {
        $readable = [];
        foreach ($rows as $row) {
            if (count($readable) === $max) {
                break;
            }
            if ($this->aclService->hasAccess('read', (string)($row[$key] ?? ''))) {
                $readable[] = $row;
            }
        }

        return $readable;
    }

    /** Where a source_url points, without the page it points at: `https://host/path`. */
    private function originOf(string $sourceUrl): string
    {
        $parts = parse_url($sourceUrl);
        if (!is_array($parts) || empty($parts['host'])) {
            return $sourceUrl;
        }
        $origin = ($parts['scheme'] ?? 'https') . '://' . $parts['host'];
        if (!empty($parts['port'])) {
            $origin .= ':' . $parts['port'];
        }

        return $origin . rtrim($parts['path'] ?? '', '/');
    }

    /** The first line of a comment, short enough to sit on one row. */
    private function excerpt(string $markup): string
    {
        $text = trim((string)preg_replace('/\s+/u', ' ', strip_tags($markup)));

        return mb_strlen($text) > 140 ? mb_substr($text, 0, 139) . '…' : $text;
    }
}

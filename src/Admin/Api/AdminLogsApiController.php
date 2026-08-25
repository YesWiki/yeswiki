<?php

namespace YesWiki\Admin\Api;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use YesWiki\Core\YesWikiController;
use YesWiki\Kernel\Entity\JournalChannel;
use YesWiki\Kernel\Service\DbService;
use YesWiki\Kernel\Service\JournalSchema;

/** The Journal, read (ticket 51 / ADR-0025). */
class AdminLogsApiController extends YesWikiController
{
    private const ALLOWED_SORTS = ['at', 'actor', 'action', 'channel', 'level'];
    private const ALLOWED_PERPAGES = [50, 100, 200, 500];
    private const DEFAULT_PERPAGE = 100;

    /** PSR-3, worst first, which is also the order a filter offers them in. */
    private const LEVELS = ['emergency', 'alert', 'critical', 'error', 'warning', 'notice', 'info', 'debug'];

    #[Route('/api/admin/logs', methods: ['GET'], options: ['acl' => ['@admins']])]
    public function getEntries(Request $request): Response
    {
        $this->denyAccessUnlessAdmin();

        $db = $this->getService(DbService::class);
        $table = $db->quoteIdentifier($this->getService(JournalSchema::class)->table());

        $page = max(1, (int)$request->query->get('page', 1));
        $perpage = in_array((int)$request->query->get('perpage'), self::ALLOWED_PERPAGES, true)
            ? (int)$request->query->get('perpage')
            : self::DEFAULT_PERPAGE;
        $sort = in_array((string)$request->query->get('sort'), self::ALLOWED_SORTS, true)
            ? (string)$request->query->get('sort')
            : 'at';
        $dir = strtolower((string)$request->query->get('dir')) === 'asc' ? 'ASC' : 'DESC';

        [$where, $params] = $this->buildWhere($db, $request);

        $rows = $db->loadAll(
            "SELECT * FROM {$table} WHERE {$where} ORDER BY {$db->quoteIdentifier($sort)} {$dir} LIMIT ? OFFSET ?",
            [...$params, $perpage, ($page - 1) * $perpage]
        );

        $total = (int)$db->scalar("SELECT COUNT(*) FROM {$table} WHERE {$where}", 0, $params);

        return new Response($this->render('@core/admin/logs-table.twig', [
            'entries' => array_map(fn (array $row): array => $this->present($row), $rows),
            'currentPage' => $page,
            'perpage' => $perpage,
            'total' => $total,
            'totalPages' => max(1, (int)ceil($total / $perpage)),
            'sort' => $sort,
            'dir' => strtolower($dir),
        ]));
    }

    /**
     * @return array{0: string, 1: list<mixed>}
     */
    private function buildWhere(DbService $db, Request $request): array
    {
        $clauses = ['1 = 1'];
        $params = [];

        $channel = (string)$request->query->get('channel', '');
        if (JournalChannel::tryFrom($channel) !== null) {
            $clauses[] = $db->quoteIdentifier('channel') . ' = ?';
            $params[] = $channel;
        }

        $level = (string)$request->query->get('level', '');
        if (in_array($level, self::LEVELS, true)) {
            $clauses[] = $db->quoteIdentifier('level') . ' = ?';
            $params[] = $level;
        }

        foreach (['actor', 'action'] as $column) {
            $value = trim((string)$request->query->get($column, ''));
            if ($value !== '') {
                $clauses[] = $db->quoteIdentifier($column) . ' = ?';
                $params[] = $value;
            }
        }

        // A date range, not a timestamp one: the filter above the table is two date pickers, and
        // "to" means the whole of that day.
        $from = $this->dateOrNull((string)$request->query->get('from', ''));
        if ($from !== null) {
            $clauses[] = $db->quoteIdentifier('at') . ' >= ?';
            $params[] = $from . ' 00:00:00';
        }
        $to = $this->dateOrNull((string)$request->query->get('to', ''));
        if ($to !== null) {
            $clauses[] = $db->quoteIdentifier('at') . ' <= ?';
            $params[] = $to . ' 23:59:59';
        }

        return [implode(' AND ', $clauses), $params];
    }

    private function dateOrNull(string $value): ?string
    {
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', trim($value)) === 1 ? trim($value) : null;
    }

    /**
     * One row as the screen wants it.
     *
     * The phrase is built here, from a translation key, because the stored `action` is a dotted
     * code -- which is what keeps a wiki that changed language from having a bilingual trail. An
     * `action` with no key of its own falls through to the code itself, which is how a legacy
     * entry and an exception class both read sensibly.
     *
     * @param array<string, mixed> $row
     *
     * @return array<string, mixed>
     */
    private function present(array $row): array
    {
        $context = json_decode((string)($row['context'] ?? ''), true);
        $context = is_array($context) ? $context : [];
        $action = (string)($row['action'] ?? '');
        $key = 'JOURNAL_ACTION_' . strtoupper(str_replace('.', '_', $action));
        $phrase = _t($key);

        return [
            'at' => (string)($row['at'] ?? ''),
            'last_at' => (string)($row['last_at'] ?? ''),
            'repeat' => (int)($row['repeat'] ?? 1),
            'channel' => (string)($row['channel'] ?? ''),
            'level' => (string)($row['level'] ?? ''),
            'actor' => (string)($row['actor'] ?? ''),
            'action' => $action,
            'phrase' => $phrase === $key ? $action : $phrase,
            'target' => (string)($row['target'] ?? ''),
            'message' => (string)($context['message'] ?? ''),
            'frames' => array_map('strval', is_array($context['frames'] ?? null) ? $context['frames'] : []),
        ];
    }

    /**
     * The levels the filter offers, for the screen that renders it.
     *
     * @return list<string>
     */
    public static function levels(): array
    {
        return self::LEVELS;
    }
}

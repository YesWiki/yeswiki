<?php

namespace YesWiki\Content\Service;

use Carbon\Carbon;
use YesWiki\Content\Controller\EntryController;
use YesWiki\Content\Entity\PageBody;
use YesWiki\Content\Entity\PageType;
use YesWiki\Kernel\Service\LanguageService;
use YesWiki\Render\Service\MarkdownFormatterService;

/** What the history of a page shows of one revision: its rendering, its code, and its differences with the one before and with the current one. */
class RevisionView
{
    public const VIEWS = ['current', 'commit_diff', 'diff'];

    public function __construct(
        private readonly PageManager $pageManager,
        private readonly EntryManager $entryManager,
        private readonly EntryController $entryController,
        private readonly DiffService $diffService,
        private readonly MarkdownFormatterService $markdown,
        private readonly LanguageService $languages,
    ) {
    }

    /**
     * @param array<string, mixed> $page a revision as PageManager reads it
     *
     * @return array{html: string, code: string}
     */
    public function rendering(array $page): array
    {
        if (($page['type'] ?? null) === PageType::TEMPLATE) {
            $code = DiffService::templateCode($page);

            return ['html' => '<pre>' . $code . '</pre>', 'code' => $code];
        }
        if ($this->entryManager->isEntry((string)$page['tag'])) {
            return [
                'html' => (string)$this->entryController->view($page['tag'], ($page['latest'] ?? 'Y') === 'Y' ? '' : (int)$page['id'], false),
                'code' => $this->diffService->formatJsonCodeIntoHtmlTable($page),
            ];
        }
        $code = PageBody::content($page['body']);

        return ['html' => $this->markdown->format($code), 'code' => $code];
    }

    /**
     * @param array<string, mixed> $page
     *
     * @return array{commit_diff_html: string, commit_diff_code: string, diff_html: string, diff_code: string}
     */
    public function differences(array $page): array
    {
        $tag = (string)$page['tag'];
        $previous = $this->pageManager->getPreviousRevision($page) ?: ['tag' => $tag, 'body' => [], 'time' => null];
        $latest = $this->pageManager->getOne($tag) ?? ['tag' => $tag, 'body' => [], 'time' => null];

        return [
            'commit_diff_html' => (string)$this->diffService->getPageDiff($previous, $page, true),
            'commit_diff_code' => (string)$this->diffService->getPageDiff($previous, $page, false),
            'diff_html' => (string)$this->diffService->getPageDiff($latest, $page, true),
            'diff_code' => (string)$this->diffService->getPageDiff($latest, $page, false),
        ];
    }

    /**
     * One view of a revision, as HTML ready to show: `current`, `commit_diff` or `diff`, rendered or as code.
     *
     * @param array<string, mixed> $page
     */
    public function show(array $page, string $view, bool $code): string
    {
        if ($view === 'current') {
            $rendering = $this->rendering($page);
            if (!$code) {
                return $rendering['html'];
            }
            $isPlainText = ($page['type'] ?? null) !== PageType::TEMPLATE && !$this->entryManager->isEntry((string)$page['tag']);

            return $isPlainText ? htmlspecialchars($rendering['code'], ENT_QUOTES) : $rendering['code'];
        }
        $previous = $view === 'commit_diff'
            ? ($this->pageManager->getPreviousRevision($page) ?: ['tag' => $page['tag'], 'body' => [], 'time' => null])
            : ($this->pageManager->getOne((string)$page['tag']) ?? ['tag' => $page['tag'], 'body' => [], 'time' => null]);

        return (string)$this->diffService->getPageDiff($previous, $page, !$code);
    }

    /**
     * The revisions numbered and placed along a line from the first to the last, close ones pushed apart so each stays clickable.
     *
     * @param list<array<string, mixed>> $revisions newest first
     *
     * @return list<array{id: int, number: int, label: string, place: float, user: string}>
     */
    public function timeline(array $revisions, int $count): array
    {
        if ($revisions === []) {
            return [];
        }
        $moments = array_map(static fn (array $revision): Carbon => Carbon::parse((string)$revision['time']), $revisions);
        $first = end($moments)->getTimestamp();
        $span = max(1, $moments[0]->getTimestamp() - $first);
        $locale = $this->languages->preferredLanguage();

        $places = [];
        foreach ($moments as $index => $moment) {
            $place = (float)(($moment->getTimestamp() - $first) / $span * 100);
            $places[] = $index === 0 ? $place : min($place, $places[$index - 1] - self::gap($moment, $moments[$index - 1]));
        }
        $last = count($places) - 1;
        if ($places[$last] < 0) {
            $places[$last] = 0.0;
            for ($index = $last - 1; $index >= 0; $index--) {
                $places[$index] = max($places[$index], $places[$index + 1] + self::gap($moments[$index], $moments[$index + 1]));
            }
        }

        $line = [];
        foreach ($revisions as $index => $revision) {
            $line[] = [
                'id' => (int)$revision['id'],
                'number' => $count - $index,
                'label' => self::label($moments[$index], $locale),
                'place' => $places[$index],
                'user' => (string)($revision['user'] ?? ''),
            ];
        }

        return $line;
    }

    private static function label(Carbon $moment, string $locale): string
    {
        $local = $moment->copy();
        $local->locale($locale);

        return $local->isoFormat('D MMM YY LT');
    }

    private static function gap(Carbon $one, Carbon $other): float
    {
        return $one->isSameDay($other) ? 1.3 : 3.0;
    }
}

<?php

namespace YesWiki\Content\Service;

use YesWiki\Content\Entity\PageBody;
use YesWiki\Content\Entity\PageType;
use YesWiki\Kernel\Service\DbService;
use YesWiki\Kernel\Service\RequestScopedState;
use YesWiki\Kernel\Service\UrlFormatter;

/**
 * How far this wiki has been translated, Content by Content, and where each translation is written.
 * It counts by TranslatableContent::translationProgress, so the screen and an editor never disagree.
 *
 * @phpstan-type CoverageProgress array{state: string, done: int, wanted: int, href: string}
 * @phpstan-type CoverageRow array{tag: string, title: string, type: string, group: string, groupLabel: string, fields: int, sourceHref: string, languages: array<string, CoverageProgress>}
 * @phpstan-type CoverageGroup array{key: string, type: string, label: string, total: int, languages: array<string, array{full: int, partial: int, empty: int, done: int, wanted: int}>}
 */
class TranslationCoverage implements RequestScopedState
{
    /** The Content types with something to translate: a comment has no translatable path. */
    public const TYPES = [
        PageType::PAGE,
        PageType::ENTRY,
        PageType::FORM,
        PageType::LIST,
        PageType::MENU,
        PageType::USER,
        PageType::FILE,
    ];

    /** How many rows are decoded at a time, so a wiki of any size costs the same memory. */
    private const CHUNK = 500;

    /** @var array<string, list<array{path: string, label: string, multiline: bool}>> */
    private array $pathsByGroup = [];

    /** @var array<string, string> */
    private array $groupLabels = [];

    public function __construct(
        private readonly DbService $dbService,
        private readonly FormManager $formManager,
        private readonly ListManager $listManager,
        private readonly TranslatableContent $translatable,
        private readonly UrlFormatter $urlFormatter,
    ) {
    }

    public function startNewRequest(): void
    {
        $this->pathsByGroup = [];
        $this->groupLabels = [];
    }

    /** The language every Content body is written in unless it says otherwise. */
    public function sourceLanguage(): string
    {
        return $this->translatable->wikiLanguage();
    }

    /**
     * The languages a translator may fill in.
     *
     * @return list<string>
     */
    public function targetLanguages(): array
    {
        return $this->translatable->targetLanguages($this->sourceLanguage());
    }

    /**
     * Everything the screen draws, in one pass over the wiki.
     *
     * @param array{group?: string, language?: string, state?: string, page?: int, perPage?: int} $filters
     *
     * @return array{source: string, languages: list<string>, groups: list<CoverageGroup>, rows: list<CoverageRow>, matched: int, scanned: int, page: int, perPage: int}
     */
    public function report(array $filters = []): array
    {
        $languages = $this->targetLanguages();
        $group = (string)($filters['group'] ?? '');
        $language = (string)($filters['language'] ?? '');
        $state = (string)($filters['state'] ?? '');
        $perPage = max(10, min(500, (int)($filters['perPage'] ?? 100)));
        $page = max(1, (int)($filters['page'] ?? 1));
        $from = ($page - 1) * $perPage;

        $groups = [];
        $rows = [];
        $matched = 0;
        $scanned = 0;

        foreach ($this->scan($languages) as $content) {
            $scanned++;

            $key = $content['group'];
            if (!isset($groups[$key])) {
                $groups[$key] = [
                    'key' => $key,
                    'type' => $content['type'],
                    'label' => $content['groupLabel'],
                    'total' => 0,
                    'languages' => array_fill_keys($languages, ['full' => 0, 'partial' => 0, 'empty' => 0, 'done' => 0, 'wanted' => 0]),
                ];
            }
            $groups[$key]['total']++;
            foreach ($languages as $code) {
                $groups[$key]['languages'][$code] = self::tallied(
                    $groups[$key]['languages'][$code],
                    $content['languages'][$code]
                );
            }

            if (!$this->matches($content, $group, $language, $state)) {
                continue;
            }
            $matched++;
            if ($matched > $from && count($rows) < $perPage) {
                $rows[] = $content;
            }
        }

        return [
            'source' => $this->sourceLanguage(),
            'languages' => $languages,
            'groups' => array_values($groups),
            'rows' => $rows,
            'matched' => $matched,
            'scanned' => $scanned,
            'page' => $page,
            'perPage' => $perPage,
        ];
    }

    /**
     * One more Content on a group's line.
     *
     * @param array{full: int, partial: int, empty: int, done: int, wanted: int} $tally
     * @param CoverageProgress                                                   $progress
     *
     * @return array{full: int, partial: int, empty: int, done: int, wanted: int}
     */
    private static function tallied(array $tally, array $progress): array
    {
        return [
            'full' => $tally['full'] + ($progress['state'] === 'full' ? 1 : 0),
            'partial' => $tally['partial'] + ($progress['state'] === 'partial' ? 1 : 0),
            'empty' => $tally['empty'] + ($progress['state'] === 'empty' ? 1 : 0),
            'done' => $tally['done'] + $progress['done'],
            'wanted' => $tally['wanted'] + $progress['wanted'],
        ];
    }

    /**
     * @param CoverageRow $content
     */
    private function matches(array $content, string $group, string $language, string $state): bool
    {
        if ($group !== '' && $content['group'] !== $group) {
            return false;
        }
        if ($state === '') {
            return true;
        }

        $states = $language === ''
            ? array_column($content['languages'], 'state')
            : [$content['languages'][$language]['state'] ?? null];

        return in_array($state, $states, true);
    }

    /**
     * Every translatable Content, read a chunk at a time.
     *
     * @param list<string> $languages
     *
     * @return \Generator<int, CoverageRow>
     */
    private function scan(array $languages): \Generator
    {
        $pages = $this->dbService->prefixTable('pages');
        $type = $this->dbService->quoteIdentifier('type');
        $placeholders = implode(', ', array_fill(0, count(self::TYPES), '?'));
        $offset = 0;

        do {
            $chunk = $this->dbService->loadAll(
                "SELECT tag, {$type} AS content_type, body FROM {$pages}"
                . " WHERE latest = 'Y' AND {$type} IN ({$placeholders})"
                . ' ORDER BY tag LIMIT ? OFFSET ?',
                [...self::TYPES, self::CHUNK, $offset]
            );

            foreach ($chunk as $row) {
                $content = $this->contentOf((string)$row['tag'], (string)$row['content_type'], $row['body'], $languages);
                if ($content !== null) {
                    yield $content;
                }
            }

            $offset += self::CHUNK;
        } while (count($chunk) === self::CHUNK);
    }

    /**
     * One row of the report, or null for a Content whose form has gone missing.
     *
     * @param list<string> $languages
     *
     * @return CoverageRow|null
     */
    private function contentOf(string $tag, string $contentType, mixed $rawBody, array $languages): ?array
    {
        $body = PageBody::decode(is_string($rawBody) ? $rawBody : null);

        [$group, $paths] = $this->groupAndPathsOf($tag, $contentType, $body);
        if ($group === null) {
            return null;
        }

        $states = [];
        foreach ($languages as $code) {
            $progress = $this->translatable->translationProgress($body, $code, $paths);
            $progress['href'] = $this->editHref($tag, $contentType, $code);
            $states[$code] = $progress;
        }

        return [
            'tag' => $tag,
            'title' => $this->titleOf($tag, $contentType, $body),
            'type' => $contentType,
            'group' => $group,
            'groupLabel' => $this->groupLabels[$group] ?? $group,
            'fields' => count($paths),
            'sourceHref' => $this->editHref($tag, $contentType, $this->sourceLanguage()),
            'languages' => $states,
        ];
    }

    /**
     * Which line of the summary this Content belongs on -- entries by their form -- and what it has to translate.
     *
     * @param array<string, mixed> $body
     *
     * @return array{0: string|null, 1: list<array{path: string, label: string, multiline: bool}>}
     */
    private function groupAndPathsOf(string $tag, string $contentType, array $body): array
    {
        if ($contentType === PageType::FORM) {
            $this->groupLabels['form'] ??= _t('TRANSLATIONS_GROUP_FORM');
            $form = $this->formManager->getUntranslated($tag);

            return ['form', $form === null ? [] : $this->translatable->formPaths($form)];
        }

        if ($contentType === PageType::LIST) {
            $this->groupLabels['list'] ??= _t('TRANSLATIONS_GROUP_LIST');
            $list = $this->listManager->getUntranslated($tag);

            return ['list', $list === null ? [] : $this->translatable->listPaths($list)];
        }

        if ($contentType === PageType::MENU) {
            $this->groupLabels['menu'] ??= _t('TRANSLATIONS_GROUP_MENU');

            return ['menu', $this->translatable->menuPaths($body)];
        }

        if ($contentType === PageType::ENTRY) {
            $formId = (string)($body['form_id'] ?? '');
            if ($formId === '') {
                return [null, []];
            }
            $group = 'entry:' . $formId;
            if (!isset($this->pathsByGroup[$group])) {
                $form = $this->formManager->getOne($formId);
                if ($form === null) {
                    return [null, []];
                }
                $this->pathsByGroup[$group] = $this->translatable->entryPaths($form);
                $this->groupLabels[$group] = (string)($form['label'] ?? $formId);
            }

            return [$group, $this->pathsByGroup[$group]];
        }

        if (!isset($this->pathsByGroup[$contentType])) {
            $form = $this->formManager->getByContentType($contentType);
            if ($form === null) {
                return [null, []];
            }
            $this->pathsByGroup[$contentType] = $this->translatable->entryPaths($form);
            $this->groupLabels[$contentType] = (string)($form['label'] ?? $contentType);
        }

        return [$contentType, $this->pathsByGroup[$contentType]];
    }

    /** Where this Content is written in $language: its own editor, which `EditHandler` picks by type, or the list screen. */
    private function editHref(string $tag, string $contentType, string $language): string
    {
        if ($contentType === PageType::LIST) {
            return $this->urlFormatter->href('', 'admin/lists', [
                'view' => 'listes',
                'action' => 'modif_liste',
                'listid' => $tag,
                'editlang' => $language,
            ], false);
        }

        if ($contentType === PageType::MENU) {
            return $this->urlFormatter->href('', 'admin/menus', [
                'menu' => $tag,
                'editlang' => $language,
            ], false);
        }

        return $this->urlFormatter->href('edit', $tag, ['editlang' => $language], false);
    }

    /**
     * @param array<string, mixed> $body
     */
    private function titleOf(string $tag, string $contentType, array $body): string
    {
        $named = match ($contentType) {
            PageType::FORM => $body['label'] ?? '',
            PageType::LIST, PageType::MENU => $body['title'] ?? '',
            PageType::USER => $body['username'] ?? '',
            PageType::FILE => $body['original_filename'] ?? $body['title'] ?? '',
            default => $body[PageBody::TITLE] ?? '',
        };

        $named = is_scalar($named) ? trim((string)$named) : '';

        return $named === '' ? $tag : $named;
    }
}

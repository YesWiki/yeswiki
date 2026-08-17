<?php

namespace YesWiki\Search\Service;

use YesWiki\Content\Entity\ContentTypeSchema;
use YesWiki\Kernel\Database\SqlParameters;
use YesWiki\Kernel\Service\DbService;
use YesWiki\Kernel\Service\UrlFormatter;

/** Turns index rows into what a result list shows (ticket 26). */
class SearchResultPresenter
{
    /** Characters of indexed text shown around the first matching word. */
    private const EXCERPT_WINDOW = 180;

    private DbService $dbService;
    private UrlFormatter $urlFormatter;
    private SearchIndexQuery $query;
    private FormOptionTranslator $translator;

    public function __construct(
        DbService $dbService,
        UrlFormatter $urlFormatter,
        SearchIndexQuery $query,
        FormOptionTranslator $translator,
    ) {
        $this->dbService = $dbService;
        $this->urlFormatter = $urlFormatter;
        $this->query = $query;
        $this->translator = $translator;
    }

    /**
     * @param list<array{tag: string, title: string, content_type: string, form_id: string, updated_at: string}> $rows
     *
     * @return list<array{tag: string, title: string, url: string, content_type: string, excerpt: string, parent: string}>
     */
    public function present(array $rows, string $phrase): array
    {
        $parents = $this->parentsOf($rows);

        $results = [];
        foreach ($rows as $row) {
            $type = $row['content_type'];
            $parent = $parents[$row['tag']] ?? '';

            $results[] = [
                'tag' => $row['tag'],
                'title' => $this->titleOf($row, $parent),
                'url' => $this->urlOf($row),
                'content_type' => $type,
                'excerpt' => $this->excerpt($row['tag'], $phrase),
                'parent' => $parent,
            ];
        }

        return $results;
    }

    /**
     * Where a result goes when clicked.
     *
     * @param array{tag: string, title: string, content_type: string, form_id: string, updated_at: string} $row
     */
    private function urlOf(array $row): string
    {
        if ($row['content_type'] === SearchableTextExtractor::TYPE_FORM && $row['form_id'] !== '') {
            return $this->urlFormatter->href('', 'BazaR', ['id' => $row['form_id']], false);
        }

        return $this->urlFormatter->href('', $row['tag'], null, false);
    }

    /**
     * A comment goes by its parent, not by `comment1754...`, which is a timestamp and tells a reader nothing.
     *
     * @param array{tag: string, title: string, content_type: string, form_id: string, updated_at: string} $row
     */
    private function titleOf(array $row, string $parent): string
    {
        if ($row['content_type'] === SearchableTextExtractor::TYPE_COMMENT) {
            return $parent === ''
                ? _t('SEARCH_RESULT_A_COMMENT')
                : _t('SEARCH_RESULT_COMMENT_ON', ['page' => $parent]);
        }

        return $row['title'];
    }

    /**
     * `parent` for the comments in this page of results, in one query.
     *
     * @param list<array{tag: string, title: string, content_type: string, form_id: string, updated_at: string}> $rows
     *
     * @return array<string, string> tag => parent tag
     */
    private function parentsOf(array $rows): array
    {
        $commentTags = [];
        foreach ($rows as $row) {
            if ($row['content_type'] === SearchableTextExtractor::TYPE_COMMENT) {
                $commentTags[] = $row['tag'];
            }
        }
        if ($commentTags === []) {
            return [];
        }

        $found = $this->dbService->loadAll(
            "SELECT tag, parent FROM {$this->dbService->prefixTable('pages')}"
            . " WHERE latest = 'Y' AND tag IN (" . SqlParameters::placeholders(count($commentTags)) . ')',
            $commentTags
        );

        $parents = [];
        foreach ($found as $row) {
            $parents[(string)$row['tag']] = (string)$row['parent'];
        }

        return $parents;
    }

    /** A window of the indexed text around the first matching word. */
    public function excerpt(string $tag, string $phrase): string
    {
        $text = $this->labelled($this->query->textFor($tag));
        if ($text === '') {
            return '';
        }

        foreach (preg_split('/\s+/u', trim($phrase)) ?: [] as $word) {
            $word = trim($word);
            if ($word === '') {
                continue;
            }
            $at = mb_stripos($text, $word);
            if ($at === false) {
                continue;
            }
            $from = max(0, $at - (int)(self::EXCERPT_WINDOW / 2));

            return $this->window($text, $from);
        }

        return $this->window($text, 0);
    }

    private function window(string $text, int $from): string
    {
        $extract = mb_substr($text, $from, self::EXCERPT_WINDOW);

        return ($from > 0 ? '… ' : '')
            . $extract
            . (mb_strlen($text) > $from + self::EXCERPT_WINDOW ? ' …' : '');
    }

    /** Stored enum keys swapped for their labels, word by word. */
    private function labelled(string $text): string
    {
        if ($text === '') {
            return '';
        }

        $words = preg_split('/\s+/u', $text) ?: [];
        foreach ($words as $i => $word) {
            $label = $this->translator->labelForKey($word);
            if ($label !== null && $label !== '') {
                $words[$i] = $label;
            }
        }

        return implode(' ', $words);
    }

    /**
     * The content types a filter can offer, in the order they should appear.
     *
     * @return list<string>
     */
    public static function filterableTypes(): array
    {
        return [
            ContentTypeSchema::TYPE_PAGE,
            ContentTypeSchema::TYPE_ENTRY,
            SearchableTextExtractor::TYPE_COMMENT,
            ContentTypeSchema::TYPE_FILE,
            ContentTypeSchema::TYPE_USER,
            SearchableTextExtractor::TYPE_FORM,
        ];
    }
}

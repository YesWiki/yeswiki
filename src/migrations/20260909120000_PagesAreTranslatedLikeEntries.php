<?php

use YesWiki\Content\Entity\PageBody;
use YesWiki\Content\Entity\Translations;
use YesWiki\Content\Service\PageManager;
use YesWiki\Core\YesWikiMigration;
use YesWiki\Kernel\Service\DbService;
use YesWiki\Kernel\Service\RuntimeConfig;
use YesWiki\Search\Service\SearchIndexer;

/**
 * A page is translated the way an entry is, so the inline `{{lang="xx"}}` sections become translations.
 *
 * The sections put every language in one body, which meant a reader whose language was missing got
 * the whole thing, the title was never translated at all, and nothing could tell a translator what
 * was still to do. Whatever a webmaster wrote in them is carried onto the page's `__translations`,
 * the section before the first marker stays the page's own text, and the markers are gone.
 */
class PagesAreTranslatedLikeEntries extends YesWikiMigration
{
    public function run(): void
    {
        $pageManager = $this->getService(PageManager::class);
        $default = $this->defaultLanguage();

        $carried = 0;
        $switches = 0;
        $rewritten = [];

        foreach ($this->pagesHoldingASection() as $tag) {
            $page = $pageManager->getOne($tag, null, false, true);
            if ($page === null) {
                continue;
            }

            $body = is_array($page['body'] ?? null) ? $page['body'] : [];
            $content = PageBody::content($body);

            $withoutCalls = self::switchBecomesOneCall($content, $switches);
            $sections = self::readSections($withoutCalls);

            if ($sections === []) {
                if ($withoutCalls === $content) {
                    continue;
                }
                $body[PageBody::CONTENT] = $withoutCalls;
            } else {
                $body[PageBody::CONTENT] = $sections['before'] . ($sections['languages'][$default] ?? '');
                unset($sections['languages'][$default]);

                foreach ($sections['languages'] as $language => $text) {
                    $body = Translations::with($body, $language, array_merge(
                        Translations::of($body, $language),
                        [PageBody::CONTENT => $sections['before'] . $text]
                    ));
                    $carried++;
                }
            }

            $pageManager->save($tag, $body, '', true);
            $rewritten[] = $tag;
        }

        if ($rewritten !== []) {
            $this->getService(SearchIndexer::class)->enqueue($rewritten);
        }

        $this->say(
            count($rewritten) . ' page(s) were rewritten: ' . $carried . ' translation(s) moved into '
            . 'the body, where the translation screen can now edit them, and ' . $switches
            . ' {{translation}} call(s) became {{languages}}.'
        );
    }

    /**
     * The run of one-flag-per-language `{{translation}}` calls, replaced by the single call that
     * offers every language this wiki has.
     */
    private static function switchBecomesOneCall(string $content, int &$switches): string
    {
        $seen = 0;
        $rewritten = (string)preg_replace_callback(
            '/^(\\s*-\\s*)?\\{\\{translation[^}]*\\}\\}[ \\t]*\\r?\\n?/mi',
            function (array $matches) use (&$seen): string {
                $seen++;

                return $seen === 1 ? ($matches[1] ?? '') . '{{languages}}' . "\n" : '';
            },
            $content
        );
        $switches += $seen;

        return $rewritten;
    }

    /**
     * A body split into what came before the first marker and what each language said.
     *
     * @return array{before: string, languages: array<string, string>}|array{}
     */
    private static function readSections(string $content): array
    {
        $chunks = preg_split('/\{\{lang="([a-zA-Z]{2})"\}\}/ms', $content, -1, PREG_SPLIT_DELIM_CAPTURE);
        if ($chunks === false || count($chunks) < 3) {
            return [];
        }

        $languages = [];
        for ($index = 1; $index < count($chunks); $index += 2) {
            $languages[strtolower($chunks[$index])] = $chunks[$index + 1] ?? '';
        }

        return ['before' => $chunks[0], 'languages' => $languages];
    }

    private function defaultLanguage(): string
    {
        $configured = (string)$this->getService(RuntimeConfig::class)->getValue('default_language', 'fr');

        return strtolower(explode('-', trim($configured))[0]) ?: 'fr';
    }

    /**
     * @return list<string>
     */
    private function pagesHoldingASection(): array
    {
        $dbService = $this->getService(DbService::class);
        $pages = trim($dbService->prefixTable('pages'));
        $bodyAsText = $dbService->jsonAsText('body');

        $rows = $dbService->loadAll(
            "SELECT tag FROM {$pages} WHERE latest = 'Y' AND ({$bodyAsText} LIKE ? OR {$bodyAsText} LIKE ?)",
            ['%{{lang=%', '%{{translation%']
        );

        return array_values(array_unique(array_map(static fn (array $row): string => (string)$row['tag'], $rows)));
    }
}

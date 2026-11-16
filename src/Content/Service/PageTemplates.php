<?php

namespace YesWiki\Content\Service;

use YesWiki\Content\Entity\PageBody;
use YesWiki\Content\Entity\PageType;
use YesWiki\Content\Entity\Translations;
use YesWiki\Identity\Service\AclService;
use YesWiki\Kernel\Service\TripleStore;

/** Pages an admin marked as templates, which a new page can start from (end-users 22): content, page fields and look, never rights, owner or keywords. */
class PageTemplates
{
    public const PROPERTY = 'http://outils-reseaux.org/_vocabulary/pageTemplate';
    public const LOOK = ['theme', 'style', 'squelette', 'bgimg', 'favorite_preset', 'PageHeader', 'PageFooter'];
    private const SUMMARY_LENGTH = 160;

    public function __construct(
        private readonly TripleStore $triples,
        private readonly PageManager $pages,
        private readonly AclService $acls,
        private readonly PageSummary $summary,
        private readonly DuplicationManager $duplication,
    ) {
    }

    /** Marks or unmarks a page as a template; false when the user is not an admin or the page is not an ordinary page. */
    public function mark(string $tag, bool $on): bool
    {
        if (!$this->acls->isAdmin()) {
            return false;
        }
        if (!$on) {
            $this->triples->delete($tag, self::PROPERTY, null, '', '');

            return true;
        }
        $page = $this->pages->getOne($tag, null, false, true);
        if ($page === null || ($page['type'] ?? PageType::DEFAULT) !== PageType::DEFAULT || ($page['parent'] ?? '') !== '') {
            return false;
        }
        if (!$this->isTemplate($tag)) {
            $this->triples->create($tag, self::PROPERTY, '1', '', '');
        }

        return true;
    }

    public function isTemplate(string $tag): bool
    {
        return $this->triples->getOne($tag, self::PROPERTY, '', '') !== null;
    }

    /** @return list<array{tag: string, title: string, summary: string}> the templates the current user may read, by title */
    public function available(): array
    {
        $templates = [];
        foreach ($this->triples->getMatching(null, self::PROPERTY, null, '=', '=', '=') as $row) {
            $template = $this->readable((string)$row['resource']);
            if ($template !== null) {
                $templates[] = [
                    'tag' => $template['tag'],
                    'title' => trim($this->summary->title($template)),
                    'summary' => self::summaryOf(PageBody::content(Translations::strip($template['body'] ?? []))),
                ];
            }
        }
        usort($templates, static fn (array $a, array $b): int => strnatcasecmp($a['title'], $b['title']));

        return $templates;
    }

    /** @return array<string, mixed>|null the body a new page starts from, without keywords, or null when the user may not use that template */
    public function bodyOf(string $tag): ?array
    {
        $template = $this->readable($tag);
        if ($template === null) {
            return null;
        }
        $body = Translations::strip(is_array($template['body'] ?? null) ? $template['body'] : []);
        unset($body[PageBody::KEYWORDS]);

        return $body;
    }

    /** @return array<string, string> the theme, layout and special pages of a template, as the editor's query parameters */
    public function lookOf(string $tag): array
    {
        if ($this->readable($tag) === null) {
            return [];
        }
        $metadata = $this->pages->getMetadata($tag) ?? [];
        $look = [];
        foreach (self::LOOK as $key) {
            if (is_string($metadata[$key] ?? null) && $metadata[$key] !== '') {
                $look[$key] = $metadata[$key];
            }
        }
        if (isset($look['favorite_preset'])) {
            $look['preset'] = $look['favorite_preset'];
        }

        return $look;
    }

    /** Copies the files a template's body attaches to the page made from it, when the user may use that template. */
    public function copyFiles(string $template, string $newTag): void
    {
        if ($template !== $newTag && $this->readable($template) !== null) {
            $this->duplication->duplicateFiles($template, $newTag);
        }
    }

    /** @return array<string, mixed>|null */
    private function readable(string $tag): ?array
    {
        if ($tag === '' || !$this->isTemplate($tag) || !$this->acls->hasAccess('read', $tag)) {
            return null;
        }
        $page = $this->pages->getOne($tag, null, true, true);

        return $page === null || $this->pages->isTrashed($tag) ? null : $page;
    }

    private static function summaryOf(string $content): string
    {
        $text = (string)preg_replace(['/\{\{.*?\}\}/s', '/<[^>]*>/', '/[=*#_\[\]|>`~-]{2,}|^[#>*-]\s*/m', '/\s+/'], ['', '', '', ' '], $content);
        $text = trim($text);

        return mb_strlen($text) > self::SUMMARY_LENGTH ? rtrim(mb_substr($text, 0, self::SUMMARY_LENGTH)) . '…' : $text;
    }
}

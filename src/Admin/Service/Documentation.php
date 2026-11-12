<?php

namespace YesWiki\Admin\Service;

use League\CommonMark\Environment\Environment;
use League\CommonMark\Environment\EnvironmentInterface;
use League\CommonMark\Event\DocumentParsedEvent;
use League\CommonMark\Extension\Attributes\AttributesExtension;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\CommonMark\Node\Block\FencedCode;
use League\CommonMark\Extension\CommonMark\Node\Block\Heading;
use League\CommonMark\Extension\CommonMark\Node\Block\HtmlBlock;
use League\CommonMark\Extension\CommonMark\Node\Inline\HtmlInline;
use League\CommonMark\Extension\CommonMark\Node\Inline\Image;
use League\CommonMark\Extension\CommonMark\Node\Inline\Link;
use League\CommonMark\Extension\Footnote\FootnoteExtension;
use League\CommonMark\Extension\GithubFlavoredMarkdownExtension;
use League\CommonMark\Extension\HeadingPermalink\HeadingPermalinkExtension;
use League\CommonMark\Extension\HeadingPermalink\HeadingPermalinkProcessor;
use League\CommonMark\Node\Block\AbstractBlock;
use League\CommonMark\Node\Block\Document;
use League\CommonMark\Node\Inline\Newline;
use League\CommonMark\Node\Node;
use League\CommonMark\Node\StringContainerInterface;
use League\CommonMark\Parser\MarkdownParser;
use League\CommonMark\Renderer\HtmlRenderer;
use YesWiki\Files\Service\LocalFiles;
use YesWiki\Kernel\Service\AssetRegistry;
use YesWiki\Kernel\Service\ExtensionRegistry;
use YesWiki\Kernel\Service\LanguageService;
use YesWiki\Kernel\Service\RuntimeConfig;
use YesWiki\Kernel\Service\UrlFormatter;
use YesWiki\Render\Formatter\AlertExtension;

/** The documentation under `/doc`: the Markdown files of the core's `docs/` and of each active extension's, rendered here and searched here, without a client-side engine. */
class Documentation
{
    public const FALLBACK_LOCALE = 'fr';

    public const HOME = 'README';

    public const MENU = '_menu';

    public const EDIT_URL = 'https://github.com/YesWiki/yeswiki/edit/ectoplasme-dev/';

    private const PAGE_NAME = '/^[A-Za-z0-9][A-Za-z0-9_-]*$/';

    private const RESULTS = 20;

    private const EXCERPT = 90;

    /** @var array<string, list<array{id: string, title: string, text: string, folded: string, foldedTitle: string}>> */
    private static array $sections = [];

    private ?EnvironmentInterface $environment = null;

    private string $renderingBook = '';

    private string $currentUrl = '';

    public function __construct(
        private readonly LanguageService $languages,
        private readonly ExtensionRegistry $extensions,
        private readonly AssetRegistry $assets,
        private readonly UrlFormatter $urls,
        private readonly RuntimeConfig $config,
        private readonly LocalFiles $files,
    ) {
    }

    /** @return array<string, array{dir: string, web: string, name: string}> the books to read, the core's first: each one's folder on disk, its path as the browser fetches its images, and its name */
    public function books(): array
    {
        $locale = $this->locale();
        $books = ['' => ['dir' => YESWIKI_PROGRAM_DIR . "/docs/{$locale}/", 'web' => "docs/{$locale}/", 'name' => 'YesWiki']];
        foreach ($this->extensions->all() as $name => $path) {
            foreach (["docs/{$locale}/", 'docs/' . self::FALLBACK_LOCALE . '/', 'docs/'] as $sub) {
                if ($this->files->isFile($path . $sub . self::HOME . '.md')) {
                    $books['extensions/' . $name] = ['dir' => $path . $sub, 'web' => "extensions/{$name}/{$sub}", 'name' => (string)$name];
                    break;
                }
            }
        }

        return $books;
    }

    /** @return array{book: string, name: string, title: string, html: string, headings: list<array{level: int, id: string, title: string}>, url: string, edit: ?string}|null the page a `/doc/...` path names, rendered, or null when there is none */
    public function page(string $path): ?array
    {
        [$book, $name] = $this->locate($path);
        $books = $this->books();
        if ($book === null || !isset($books[$book])) {
            return null;
        }
        $file = $books[$book]['dir'] . $name . '.md';
        if (!$this->files->isFile($file)) {
            return null;
        }
        $url = $this->url($book, $name);
        $document = $this->parse($this->files->read($file), $book, $url);
        $headings = $this->headings($document);
        $title = '';
        foreach ($headings as $heading) {
            if ($heading['level'] === 1) {
                $title = $heading['title'];
                break;
            }
        }

        return [
            'book' => $book,
            'name' => $name,
            'title' => $title !== '' ? $title : $name,
            'html' => (new HtmlRenderer($this->environment()))->renderDocument($document)->getContent(),
            'headings' => array_values(array_filter($headings, static fn (array $h): bool => $h['level'] === 2 || $h['level'] === 3)),
            'url' => $url,
            'edit' => $book === '' ? self::EDIT_URL . $books['']['web'] . $name . '.md' : null,
        ];
    }

    /** The core's menu, its links pointing into `/doc` and the one to the page shown marked active. */
    public function menu(string $currentUrl = ''): string
    {
        $file = $this->books()['']['dir'] . self::MENU . '.md';
        if (!$this->files->isFile($file)) {
            return '';
        }
        $document = $this->parse($this->files->read($file), '', $currentUrl);

        return (new HtmlRenderer($this->environment()))->renderDocument($document)->getContent();
    }

    /** @return list<array{title: string, page: string, url: string, excerpt: string}> the sections whose title or text hold every word of the query, ignoring case and accents, best first */
    public function search(string $query): array
    {
        $words = array_values(array_filter(preg_split('/\s+/u', self::fold(trim($query))) ?: [], static fn (string $w): bool => $w !== ''));
        if ($words === []) {
            return [];
        }
        $found = [];
        foreach ($this->books() as $book => $info) {
            foreach ($this->files->matching($info['dir'] . '*.md') as $file) {
                $name = basename($file, '.md');
                if (preg_match(self::PAGE_NAME, $name) !== 1) {
                    continue;
                }
                $sections = $this->sectionsOf($file, $book);
                $pageTitle = $sections[0]['title'] ?? $name;
                foreach ($sections as $section) {
                    $score = 0;
                    foreach ($words as $word) {
                        $inTitle = substr_count($section['foldedTitle'], $word);
                        $inText = substr_count($section['folded'], $word);
                        if ($inTitle + $inText === 0) {
                            continue 2;
                        }
                        $score += 10 * $inTitle + $inText;
                    }
                    $found[] = [
                        'score' => $score,
                        'title' => $section['title'],
                        'page' => $pageTitle,
                        'url' => $this->url($book, $name, $section['id']),
                        'excerpt' => self::excerpt($section['text'], $section['folded'], $words),
                    ];
                }
            }
        }
        usort($found, static fn (array $a, array $b): int => $b['score'] <=> $a['score']);

        return array_map(static fn (array $r): array => ['title' => $r['title'], 'page' => $r['page'], 'url' => $r['url'], 'excerpt' => $r['excerpt']], array_slice($found, 0, self::RESULTS));
    }

    /** The address of a page of a book, with the heading it should open at. */
    public function url(string $book, string $name = self::HOME, string $anchor = ''): string
    {
        $tag = 'doc' . ($book !== '' ? '/' . $book : '') . ($name !== self::HOME ? '/' . $name : '');

        return $this->urls->href('', $tag) . ($anchor !== '' ? '#' . $anchor : '');
    }

    /** @return array{0: ?string, 1: string} which book and page a `/doc/...` path names: the core's for a bare name, an extension's under `extensions/{name}/` */
    private function locate(string $path): array
    {
        $parts = $path === '' ? [] : explode('/', trim($path, '/'));
        if (($parts[0] ?? '') === 'extensions') {
            if (count($parts) < 2 || count($parts) > 3) {
                return [null, ''];
            }
            $book = 'extensions/' . $parts[1];
            $name = $parts[2] ?? self::HOME;
        } elseif (count($parts) > 1) {
            return [null, ''];
        } else {
            $book = '';
            $name = $parts[0] ?? self::HOME;
        }

        return preg_match(self::PAGE_NAME, $name) === 1 ? [$book, $name] : [null, ''];
    }

    private function locale(): string
    {
        $preferred = $this->languages->preferredLanguage();

        return preg_match('/^[a-z]{2}$/', $preferred) === 1 && $this->files->isFile(YESWIKI_PROGRAM_DIR . "/docs/{$preferred}/" . self::HOME . '.md') ? $preferred : self::FALLBACK_LOCALE;
    }

    private function parse(string $markdown, string $book, string $currentUrl): Document
    {
        $this->renderingBook = $book;
        $this->currentUrl = $currentUrl;

        return (new MarkdownParser($this->environment()))->parse(self::docsifyCallouts($markdown));
    }

    /** Extensions' docs written for Docsify keep their `?>` and `!>` paragraphs, read as the `::: info` and `::: warning` blocks the core's docs use. */
    private static function docsifyCallouts(string $markdown): string
    {
        $lines = [];
        $fence = false;
        $open = false;
        foreach (explode("\n", $markdown) as $line) {
            if (preg_match('/^\s*(```|~~~)/', $line) === 1) {
                $fence = !$fence;
            }
            if (!$fence && preg_match('/^([?!])>\s?(.*)$/', $line, $match) === 1) {
                if ($open) {
                    $lines[] = ':::';
                }
                array_push($lines, '::: ' . ($match[1] === '?' ? 'info' : 'warning'), $match[2]);
                $open = true;
                continue;
            }
            if ($open && trim($line) === '') {
                $lines[] = ':::';
                $open = false;
            }
            $lines[] = $line;
        }
        if ($open) {
            $lines[] = ':::';
        }

        return implode("\n", $lines);
    }

    private function environment(): EnvironmentInterface
    {
        if ($this->environment === null) {
            $environment = new Environment([
                'html_input' => 'allow',
                'allow_unsafe_links' => false,
                'heading_permalink' => [
                    'apply_id_to_heading' => true,
                    'insert' => HeadingPermalinkProcessor::INSERT_AFTER,
                    'id_prefix' => '',
                    'fragment_prefix' => '',
                    'html_class' => 'yw-doc__anchor',
                    'symbol' => '#',
                    'aria_hidden' => true,
                ],
            ]);
            $environment->addExtension(new CommonMarkCoreExtension());
            $environment->addExtension(new GithubFlavoredMarkdownExtension());
            $environment->addExtension(new HeadingPermalinkExtension());
            $environment->addExtension(new AttributesExtension());
            $environment->addExtension(new FootnoteExtension());
            $environment->addExtension(new AlertExtension());
            $environment->addEventListener(DocumentParsedEvent::class, fn (DocumentParsedEvent $event) => $this->rewrite($event->getDocument()), -100);
            $this->environment = $environment;
        }

        return $this->environment;
    }

    /** Points links between Markdown files at their `/doc` pages and images at their files, and puts a live preview under each `yeswiki preview` code block. */
    private function rewrite(Document $document): void
    {
        $previews = [];
        foreach ($document->iterator() as $node) {
            if ($node instanceof Link) {
                $node->setUrl($this->linkTarget($node->getUrl()));
                if ($this->currentUrl !== '' && $node->getUrl() === $this->currentUrl) {
                    $node->data->set('attributes/class', trim($node->data->get('attributes/class', '') . ' active'));
                }
            } elseif ($node instanceof Image) {
                $node->setUrl($this->imageSource($node->getUrl()));
                $node->data->set('attributes/loading', 'lazy');
            } elseif ($node instanceof FencedCode && preg_match('/^yeswiki\b.*\bpreview(?:=(\d+))?/', (string)$node->getInfo(), $match) === 1) {
                $previews[] = [$node, (int)($match[1] ?? 0) ?: 200];
            }
        }
        foreach ($previews as [$code, $height]) {
            $src = $this->urls->href('render', (string)$this->config->getValue('root_page', 'PagePrincipale'), ['content' => $code->getLiteral()]);
            $code->insertAfter(new HtmlBlock(HtmlBlock::TYPE_7_MISC_ELEMENT));
            $iframe = $code->next();
            if ($iframe instanceof HtmlBlock) {
                $iframe->setLiteral('<iframe class="yw-doc__preview" src="' . $src . '" height="' . $height . '" loading="lazy" title="preview"></iframe>');
            }
        }
    }

    private function linkTarget(string $url): string
    {
        if (preg_match('/^(?:\.\/)?([A-Za-z0-9][A-Za-z0-9_-]*)\.md(?:#(.*))?$/', $url, $match) !== 1) {
            return $url;
        }

        return $this->url($this->renderingBook, $match[1], rawurldecode($match[2] ?? ''));
    }

    private function imageSource(string $url): string
    {
        if ($url === '' || preg_match('#^([a-z][a-z0-9+.-]*:|/|\#)#i', $url) === 1) {
            return $url;
        }
        $path = ($this->books()[$this->renderingBook]['web'] ?? '') . preg_replace('#^\./#', '', $url);

        return $this->assets->urlFor($path) ?? $path;
    }

    /** @return list<array{level: int, id: string, title: string}> */
    private function headings(Document $document): array
    {
        $headings = [];
        foreach ($document->iterator() as $node) {
            if ($node instanceof Heading) {
                $id = $node->data->get('attributes/id', '');
                if (is_string($id) && $id !== '') {
                    $headings[] = ['level' => $node->getLevel(), 'id' => $id, 'title' => self::textOf($node)];
                }
            }
        }

        return $headings;
    }

    /** @return list<array{id: string, title: string, text: string, folded: string, foldedTitle: string}> */
    private function sectionsOf(string $file, string $book): array
    {
        $key = $file . ':' . (string)$this->files->modifiedAt($file);
        if (isset(self::$sections[$key])) {
            return self::$sections[$key];
        }
        $document = $this->parse($this->files->read($file), $book, '');
        $sections = [];
        $current = ['id' => '', 'title' => '', 'text' => ''];
        foreach ($document->children() as $block) {
            if ($block instanceof Heading) {
                $sections[] = $current;
                $current = ['id' => (string)$block->data->get('attributes/id', ''), 'title' => self::textOf($block), 'text' => ''];
            } elseif ($block instanceof AbstractBlock) {
                $current['text'] .= ' ' . self::textOf($block);
            }
        }
        $sections[] = $current;
        $sections = array_values(array_filter($sections, static fn (array $s): bool => $s['title'] !== '' || trim($s['text']) !== ''));
        foreach ($sections as &$section) {
            $section['text'] = trim((string)preg_replace('/\s+/u', ' ', $section['text']));
            $section['folded'] = self::fold($section['text']);
            $section['foldedTitle'] = self::fold($section['title']);
        }
        unset($section);

        return self::$sections[$key] = $sections;
    }

    private static function textOf(Node $node): string
    {
        $text = '';
        foreach ($node->iterator() as $child) {
            if ($child instanceof AbstractBlock && $child !== $node) {
                $text .= ' ';
            }
            if ($child instanceof StringContainerInterface && !$child instanceof HtmlBlock && !$child instanceof HtmlInline) {
                $text .= $child->getLiteral();
            } elseif ($child instanceof Newline) {
                $text .= ' ';
            }
        }

        return trim((string)preg_replace('/\s+/u', ' ', $text));
    }

    /** Lower case without accents, one character for one, so a position in the folded text is a position in the original. */
    private static function fold(string $text): string
    {
        static $map = [];
        $folded = '';
        foreach (mb_str_split($text) as $char) {
            if (!isset($map[$char])) {
                $plain = mb_strtolower((string)preg_replace('/\p{Mn}/u', '', (string)\Normalizer::normalize($char, \Normalizer::FORM_D)));
                $map[$char] = mb_strlen($plain) === 1 ? $plain : mb_strtolower($char);
            }
            $folded .= $map[$char];
        }

        return $folded;
    }

    /** @param list<string> $words the query's folded words; returns a stretch of the section around the first one found, escaped, the words marked */
    private static function excerpt(string $text, string $folded, array $words): string
    {
        $at = false;
        foreach ($words as $word) {
            $at = mb_strpos($folded, $word);
            if ($at !== false) {
                break;
            }
        }
        if ($at === false) {
            $at = 0;
        }
        $start = max(0, $at - (int)(self::EXCERPT / 2));
        $length = self::EXCERPT * 2;
        $piece = mb_substr($text, $start, $length);
        $foldedPiece = mb_substr($folded, $start, $length);
        $marks = array_fill(0, mb_strlen($piece), false);
        foreach ($words as $word) {
            $offset = 0;
            while (($found = mb_strpos($foldedPiece, $word, $offset)) !== false) {
                for ($i = $found; $i < $found + mb_strlen($word); $i++) {
                    $marks[$i] = true;
                }
                $offset = $found + 1;
            }
        }
        $html = '';
        $open = false;
        foreach (mb_str_split($piece) ?: [] as $i => $char) {
            if ($marks[$i] !== $open) {
                $html .= $marks[$i] ? '<mark>' : '</mark>';
                $open = $marks[$i];
            }
            $html .= htmlspecialchars($char, ENT_QUOTES);
        }
        if ($open) {
            $html .= '</mark>';
        }

        return ($start > 0 ? '…' : '') . $html . ($start + $length < mb_strlen($text) ? '…' : '');
    }
}

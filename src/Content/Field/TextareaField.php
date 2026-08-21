<?php

namespace YesWiki\Content\Field;

use Psr\Container\ContainerInterface;
use YesWiki\Content\Entity\PageBody;
use YesWiki\Files\Service\AttachedFilePaths;
use YesWiki\Kernel\Service\AssetRegistry;
use YesWiki\Kernel\Service\DbService;
use YesWiki\Kernel\Service\HtmlPurifierService;
use YesWiki\Kernel\Service\LanguageService;
use YesWiki\Kernel\Service\StringUtilService;

#[\Field(['textelong'])]
class TextareaField extends BazarField
{
    /** @var int|string rows of the textarea, as the form definition spells it */
    protected $numRows;

    /** @var string one of the SYNTAX_* constants */
    protected $syntax;

    /** @var string */
    protected $placeholder;

    protected const FIELD_NUM_ROWS = 4;
    protected const FIELD_MAX_CHARS = 6;
    protected const FIELD_SYNTAX = 7;
    protected const FIELD_PLACEHOLDER = 15;

    protected const ACCEPTED_TAGS = '<h1><h2><h3><h4><h5><h6><hr><hr/><br><br/><span><blockquote><i><u><b><strong><ol><ul><li><small><div><p><a><table><tr><th><td><img><figure><caption><iframe>';

    public const SYNTAX_WIKI = 'wiki-textarea';
    public const SYNTAX_HTML = 'html';
    public const SYNTAX_PLAIN = 'nohtml';

    /**
     * @param array<int|string, mixed> $values one field's line of the form definition, by positional index
     */
    public function __construct(array $values, ContainerInterface $services)
    {
        parent::__construct($values, $services);

        $this->numRows = empty($values[self::FIELD_NUM_ROWS]) ? 3 : $values[self::FIELD_NUM_ROWS];
        $this->syntax = $values[self::FIELD_SYNTAX] ?? self::SYNTAX_WIKI;

        $this->placeholder = $values[self::FIELD_PLACEHOLDER];

        $this->maxChars = $values[self::FIELD_MAX_CHARS];

        if ($this->syntax === 'wiki') {
            $this->syntax = self::SYNTAX_WIKI;
        }
    }

    private const VDITOR_LANG_MAP = [
        'en' => 'en_US',
        'es' => 'es_ES',
        'fr' => 'fr_FR',
        'pt' => 'pt_BR',
    ];

    /** The prose, with markup and `{{action}}` calls taken out (ticket 18 / ADR-0015). */
    public function searchableText($entry): string
    {
        return self::stripMarkupForIndex(parent::searchableText($entry));
    }

    /** Wiki/HTML markup reduced to the words in it. */
    public static function stripMarkupForIndex(string $text): string
    {
        if (trim($text) === '') {
            return '';
        }

        $text = (string)preg_replace('/\{\{.*?\}\}/su', ' ', $text);

        $text = (string)preg_replace('/\[\[\s*([^\s\]]+)\s*([^\]]*)\]\]/u', ' $2 $1 ', $text);
        $text = (string)preg_replace('/\[([^\]]*)\]\([^)]*\)/u', ' $1 ', $text);

        $text = str_replace('""', ' ', $text);
        $text = strip_tags($text);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        $text = (string)preg_replace('/[*_#|=~\[\]{}`>]+/u', ' ', $text);
        $text = (string)preg_replace('/(^|\s)-{2,}(\s|$)/u', ' ', $text);

        return trim((string)preg_replace('/\s+/u', ' ', $text));
    }

    protected function renderInput($entry)
    {
        $output = '';
        $vditorLang = null;

        if ($this->syntax === self::SYNTAX_HTML) {
            $this->getService(AssetRegistry::class)->addCssFile('styles/vendor/vditor/index.css');
            $this->getService(AssetRegistry::class)->addJsFile('javascripts/vendor/vditor/index.min.js');

            $this->getService(AssetRegistry::class)->addJsFile('javascripts/vditor-textarea.js', false, true);

            $vditorLang = self::VDITOR_LANG_MAP[strtolower($this->getService(LanguageService::class)->preferredLanguage())] ?? 'en_US';
        }

        $tempTag = !isset($entry['tag']) ? ($this->getService(\YesWiki\Kernel\Service\RuntimeConfig::class)['temp_tag_for_entry_creation'] ?? null) : null;
        if ($tempTag) {
            $tempTag .= '_' . bin2hex(random_bytes(10));
        }

        return $output . $this->render('@core/inputs/textarea.twig', [
            'value' => $this->getValue($entry),
            'entryId' => $entry['tag'] ?? null,
            'tempTag' => $tempTag,
            'vditorLang' => $vditorLang,
        ]);
    }

    public function formatValuesBeforeSave($entry)
    {
        $value = $this->getValue($entry);

        if ($this->syntax === self::SYNTAX_HTML) {
            $value = strip_tags($value, self::ACCEPTED_TAGS);
            $value = $this->sanitizeBase64Img($value, $entry ?? []);
            $value = $this->sanitizeHTML($value);
        } elseif ($this->syntax === self::SYNTAX_WIKI) {
            $value = $this->sanitizeAttach($value, $entry ?? []);
            $value = $this->sanitizeHTMLInWikiCode($value);
        } else {
            $value = $this->sanitizeHTML($value);
        }

        return [$this->propertyName => $value];
    }

    protected function renderStatic($entry)
    {
        $value = $this->getValue($entry);
        if (!$value) {
            return '';
        }

        switch ($this->syntax) {
            case self::SYNTAX_WIKI:
                $pageContext = $this->getService(\YesWiki\Kernel\Service\PageContext::class);
                $oldPage = $pageContext->getTag();
                $oldPageArray = $pageContext->getPage();
                $pageContext->setTag($entry['tag'] ?? null);
                $pageContext->setPage($this->getService(\YesWiki\Content\Service\PageManager::class)->getOne($pageContext->getTag()));
                $pageContext->setPageField('body', [PageBody::CONTENT => $value]);

                $value = $this->getService(\YesWiki\Render\Service\MarkdownFormatterService::class)->format($value);

                $pageContext->setTag($oldPage);
                $pageContext->setPage($oldPageArray);
                break;

            case self::SYNTAX_PLAIN:
                $value = nl2br(htmlentities($value, ENT_QUOTES, YW_CHARSET));
                break;

            case self::SYNTAX_HTML:
                break;
        }

        return $this->render('@core/fields/textarea.twig', [
            'value' => $value,
        ]);
    }

    /**
     * @return int|string
     */
    public function getNumRows()
    {
        return $this->numRows;
    }

    /**
     * @return string
     */
    public function getPlaceholder()
    {
        return $this->placeholder;
    }

    /**
     * @return string one of the SYNTAX_* constants
     */
    public function getSyntax()
    {
        return $this->syntax;
    }

    /**
     * @param array<string, mixed> $entry
     */
    private function sanitizeAttach(string $text, array $entry): string
    {
        $temp_tag_for_entry_creation = $this->getService(\YesWiki\Kernel\Service\RuntimeConfig::class)['temp_tag_for_entry_creation'];

        if (preg_match_all("/({{attach[^}]*file=\")(({$temp_tag_for_entry_creation}_[A-Fa-f0-9]+)\/([^\"]*))(\"[^}]*}})/m", $text, $matches)) {
            $entryCreationTime = $this->getEntryCreationTime($entry);
            foreach ($matches[0] as $key => $value) {
                $paths = $this->getService(AttachedFilePaths::class);
                $previousFile = $matches[2][$key];
                $previousTag = $this->getService(\YesWiki\Kernel\Service\PageContext::class)->getTag();
                $previousPage = $this->getService(\YesWiki\Kernel\Service\PageContext::class)->getPage();
                $this->getService(\YesWiki\Kernel\Service\PageContext::class)->setTag($matches[3][$key]);
                $this->getService(\YesWiki\Kernel\Service\PageContext::class)->setPage([
                    'tag' => $matches[3][$key],
                    'body' => [PageBody::CONTENT => '{##}'],
                    'time' => date('YmdHis'),
                    'owner' => '',
                    'user' => '',
                ]);
                $previousFileName = $paths->fullFilename($previousFile);
                $newFile = $matches[4][$key];
                $this->getService(\YesWiki\Kernel\Service\PageContext::class)->setTag($entry['tag']);
                $this->getService(\YesWiki\Kernel\Service\PageContext::class)->setPage([
                    'tag' => $entry['tag'],
                    'body' => $entry,
                    'time' => $entryCreationTime,
                    'owner' => '',
                    'user' => '',
                ]);
                $newFileName = $paths->fullFilename($newFile, true);
                $dirRealPath = realpath(dirname($previousFileName));
                if (rename(
                    $dirRealPath . DIRECTORY_SEPARATOR . basename($previousFileName),
                    $dirRealPath . DIRECTORY_SEPARATOR . basename($newFileName)
                )) {
                    $text = str_replace($matches[0][$key], $matches[1][$key] . $matches[4][$key] . $matches[5][$key], $text);
                }
                $this->getService(\YesWiki\Kernel\Service\PageContext::class)->setTag($previousTag);
                $this->getService(\YesWiki\Kernel\Service\PageContext::class)->setPage($previousPage);
            }
        }

        return $text;
    }

    /**
     * @param array<string, mixed> $entry
     */
    private function sanitizeBase64Img(string $text, array $entry): string
    {
        $regExpSearch = '(<img(?>\s*style="[^"]*")?\s*)src="data:image\/(gif|jpeg|png|jpg|svg|webp);base64,([^"]*)"[^>]*>';
        if (preg_match_all("/$regExpSearch/", $text, $matches)) {
            $entryCreationTime = $this->getEntryCreationTime($entry);
            $previousTag = $this->getService(\YesWiki\Kernel\Service\PageContext::class)->getTag();
            $previousPage = $this->getService(\YesWiki\Kernel\Service\PageContext::class)->getPage();
            foreach ($matches[0] as $index => $textToReplace) {
                $imageType = $matches[2][$index];
                $imageContent = base64_decode($matches[3][$index]);
                $fileName = preg_match('/data-filename="([^"]*)"/', $textToReplace, $nameMatch)
                    ? $nameMatch[1]
                    : '';
                if (empty(trim($fileName))) {
                    $fileName = bin2hex(random_bytes(10)) . '.' . $imageType;
                }
                if (preg_match('/^(.*)(\.[A-Za-z0-9]+)$/m', $fileName, $matchesForFile)) {
                    $fileNameWithoutExtension = $matchesForFile[1];
                    $fileExtension = $matchesForFile[2];
                    $fileName = $this->sanitizeFileName($fileNameWithoutExtension) . $fileExtension;
                } else {
                    $fileName = $this->sanitizeFileName($fileName);
                }

                $paths = $this->getService(AttachedFilePaths::class);

                $this->getService(\YesWiki\Kernel\Service\PageContext::class)->setTag($entry['tag']);
                $this->getService(\YesWiki\Kernel\Service\PageContext::class)->setPage([
                    'tag' => $entry['tag'],
                    'page' => (string)json_encode($entry),
                    'time' => $entryCreationTime,
                    'owner' => '',
                    'user' => '',
                ]);
                $newFilePath = $paths->fullFilename($fileName, true);

                if (!empty($newFilePath)) {
                    file_put_contents($newFilePath, $imageContent);

                    $newText = $matches[1][$index];
                    $newText .= "src=\"$newFilePath\">";

                    $text = str_replace($textToReplace, $newText, $text);
                }
            }
            $this->getService(\YesWiki\Kernel\Service\PageContext::class)->setTag($previousTag);
            $this->getService(\YesWiki\Kernel\Service\PageContext::class)->setPage($previousPage);
        }

        return $text;
    }

    /**
     * @param array<string, mixed>|null $entry
     */
    private function getEntryCreationTime(?array $entry): string
    {
        $dbTz = $this->getService(DbService::class)->getDbTimeZone();
        $sqlTimeFormat = 'Y-m-d H:i:s';
        $entryCreationTime = !empty($entry['updated_at'])
            ? $entry['created_at']
            : (
                !empty($dbTz)
                ? (new \DateTime())->setTimezone(new \DateTimeZone($dbTz))->format($sqlTimeFormat)
                : date($sqlTimeFormat)
            );

        return $entryCreationTime;
    }

    /**
     * sanitize file name.
     *
     * @return string $outputString
     */
    private function sanitizeFileName(string $inputString): string
    {
        return StringUtilService::withoutDiacritics((string)preg_replace('/--+/u', '-', (string)preg_replace('/[[:punct:]]/', '-', $inputString)));
    }

    /** sanitize html to prevent xss. */
    private function sanitizeHTMLInWikiCode(string $value): string
    {
        $preformattedDirtyHTML = str_replace(['@@', '""'], ['\\@\\@\\', '@@'], $value);
        $preformattedCleanHTML = $this->getService(HtmlPurifierService::class)->cleanHTML($preformattedDirtyHTML);

        return str_replace(['""', '@@', '\\@\\@\\'], ['\'\'', '""', '@@'], $preformattedCleanHTML);
    }

    /** sanitize html to prevent xss. */
    private function sanitizeHTML(string $value): string
    {
        return $this->getService(HtmlPurifierService::class)->cleanHTML($value);
    }
}

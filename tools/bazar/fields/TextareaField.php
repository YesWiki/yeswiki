<?php

namespace YesWiki\Bazar\Field;

use DateTime;
use DateTimeZone;
use Psr\Container\ContainerInterface;
use YesWiki\Core\Service\DbService;
use YesWiki\Core\Service\HtmlPurifierService;

/**
 * @Field({"textelong"})
 */
class TextareaField extends BazarField
{
    protected $numRows;
    protected $syntax;

    protected const FIELD_NUM_ROWS = 4;
    protected const FIELD_SYNTAX = 7;

    protected const ACCEPTED_TAGS = '<h1><h2><h3><h4><h5><h6><hr><hr/><br><br/><span><blockquote><i><u><b><strong><ol><ul><li><small><div><p><a><table><tr><th><td><img><figure><caption><iframe>';

    public const SYNTAX_WIKI = 'wiki-textarea';
    public const SYNTAX_HTML = 'html';
    public const SYNTAX_PLAIN = 'nohtml';

    public function __construct(array $values, ContainerInterface $services)
    {
        parent::__construct($values, $services);

        $this->numRows = empty($values[self::FIELD_NUM_ROWS]) ? 3 : $values[self::FIELD_NUM_ROWS];
        $this->syntax = $values[self::FIELD_SYNTAX] ?? self::SYNTAX_WIKI;

        // For this field, max chars are defined in the 6th column, instead of the already-used 4th
        $this->maxChars = $values[6];

        // Retro-compatibility
        if ($this->syntax === 'wiki') {
            $this->syntax = self::SYNTAX_WIKI;
        }
    }

    protected function renderInput($entry)
    {
        $output = '';
        $wiki = $this->getWiki();
        // If HTML syntax, load editor's JS and CSS
        if ($this->syntax === self::SYNTAX_HTML) {
            $wiki->AddJavascriptFile('tools/bazar/libs/vendor/summernote/summernote.min.js');
            $wiki->AddCSSFile('tools/bazar/libs/vendor/summernote/summernote.css');

            $langKey = strtolower($GLOBALS['prefered_language']) . '-' . strtoupper($GLOBALS['prefered_language']);
            $langFile = 'tools/bazar/libs/vendor/summernote/lang/summernote-' . $langKey . '.js';
            if (file_exists($langFile)) {
                $wiki->AddJavascriptFile($langFile);
                $langOptions = 'lang: "' . $langKey . '",';
            } else {
                $langOptions = '';
            }

            $script = '$(document).ready(function() {
              $(".summernote").summernote({
                ' . $langOptions . '
                height: ' . $this->numRows * 30 . ',    // set editor height
                minHeight: 100, // set minimum height of editor
                maxHeight: 350,                // set maximum height of editor
                focus: false,                   // set focus to editable area after initializing summernote
                toolbar: [
                    //[groupname, [button list]]
                    //[\'style\', [\'style\', \'well\']],
                    [\'style\', [\'style\']],
                    [\'textstyle\', [\'bold\', \'italic\', \'underline\', \'strikethrough\', \'clear\']],
                    [\'color\', [\'color\']],
                    [\'para\', [\'ul\', \'ol\', \'paragraph\']],
                    [\'insert\', [\'hr\', \'link\', \'table\']], // \'picture\', \'video\' removed because of the storage in the field
                    [\'misc\', [\'codeview\']]
                ],
                isNotSplitEdgePoint : true,
                styleTags: [\'h3\', \'h4\', \'h5\', \'h6\', \'p\', \'blockquote\', \'pre\'],
                oninit: function() {
                  //$(\'button[data-original-title=Style]\').prepend("Style").find("i").remove();
                },
                callbacks: {
                    onPaste: function (e) {
                        var bufferText = ((e.originalEvent || e).clipboardData || window.clipboardData).getData(\'Text\');
                        e.preventDefault();
                        document.execCommand(\'insertText\', false, bufferText);
                    }
                }
              });
            });';

            $wiki->AddJavascript($script);
        }

        $tempTag = !isset($entry['id_fiche']) ? ($wiki->config['temp_tag_for_entry_creation'] ?? null) : null;
        if ($tempTag) {
            $tempTag .= '_' . bin2hex(random_bytes(10));
        }

        return $output . $this->render('@bazar/inputs/textarea.twig', [
            'value' => $this->getValue($entry),
            'entryId' => $entry['id_fiche'] ?? null,
            'tempTag' => $tempTag,
        ]);
    }

    public function formatValuesBeforeSave($entry)
    {
        $value = $this->getValue($entry);

        if ($this->syntax === self::SYNTAX_HTML) {
            $value = strip_tags($value, self::ACCEPTED_TAGS);
            $value = $this->sanitizeBase64Img($value, $entry);
            $value = $this->sanitizeHTML($value);
        } elseif ($this->syntax === self::SYNTAX_WIKI) {
            $value = $this->sanitizeAttach($value, $entry);
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
                // Do the page change in any case (useful for attach or grid)
                $oldPage = $GLOBALS['wiki']->GetPageTag();
                $oldPageArray = $GLOBALS['wiki']->page;
                $GLOBALS['wiki']->tag = $entry['id_fiche'];
                $GLOBALS['wiki']->page = $GLOBALS['wiki']->LoadPage($GLOBALS['wiki']->tag);
                $GLOBALS['wiki']->page['body'] = $value;

                $value = $GLOBALS['wiki']->Format($value);
                // if the textarea have some actions which return "", they are replaced by '' otherwise it crashed
                // because they're by wakka as a beginning of HTML code
                // the user still insert HTML code in the textarea with "" because the "" block is interpretated before
                // the replacement
                $value = str_replace('""', '\'\'', $value);

                $GLOBALS['wiki']->tag = $oldPage;
                $GLOBALS['wiki']->page = $oldPageArray;
                break;

            case self::SYNTAX_PLAIN:
                $value = nl2br(htmlentities($value, ENT_QUOTES, YW_CHARSET));
                break;

            case self::SYNTAX_HTML:
                // if the user type "", it's replaced by '' otherwise it crashes the output because it's interpretated
                // by wakka as a beginning of HTML code
                $value = str_replace('""', '\'\'', $value);
                break;
        }

        return $this->render('@bazar/fields/textarea.twig', [
            'value' => $value,
        ]);
    }

    // GETTERS. Needed to use them in the Twig syntax

    public function getNumRows()
    {
        return $this->numRows;
    }

    public function getSyntax()
    {
        return $this->syntax;
    }

    private function sanitizeAttach(string $text, array $entry): string
    {
        $wiki = $this->getWiki();
        $temp_tag_for_entry_creation = $wiki->config['temp_tag_for_entry_creation'];

        if (preg_match_all("/({{attach[^}]*file=\")(({$temp_tag_for_entry_creation}_[A-Fa-f0-9]+)\/([^\"]*))(\"[^}]*}})/m", $text, $matches)) {
            if (!class_exists('attach')) {
                include 'tools/attach/libs/attach.lib.php';
            }
            $entryCreationTime = $this->getEntryCreationTime($entry);
            foreach ($matches[0] as $key => $value) {
                $attach = new \Attach($wiki);
                $attach->file = $matches[2][$key];
                $previousTag = $wiki->tag;
                $previousPage = $wiki->page;
                $wiki->tag = $matches[3][$key];
                $wiki->page = [
                    'tag' => $wiki->tag,
                    'body' => '{##}',
                    'time' => date('YmdHis'),
                    'owner' => '',
                    'user' => '',
                ];
                $previousFileName = $attach->GetFullFilename();
                $attach->file = $matches[4][$key];
                $wiki->tag = $entry['id_fiche'];
                $wiki->page = [
                    'tag' => $entry['id_fiche'],
                    'body' => json_encode($entry),
                    'time' => $entryCreationTime,
                    'owner' => '',
                    'user' => '',
                ];
                $newFileName = $attach->GetFullFilename(true);
                $dirRealPath = realpath(dirname($previousFileName));
                if (rename(
                    $dirRealPath . DIRECTORY_SEPARATOR . basename($previousFileName),
                    $dirRealPath . DIRECTORY_SEPARATOR . basename($newFileName)
                )) {
                    $text = str_replace($matches[0][$key], $matches[1][$key] . $matches[4][$key] . $matches[5][$key], $text);
                }
                unset($attach);
                $wiki->tag = $previousTag;
                $wiki->page = $previousPage;
            }
        }

        return $text;
    }

    private function sanitizeBase64Img(string $text, array $entry): string
    {
        $wiki = $this->getWiki();
        $regExpSearch = '(<img(?>\s*style="[^"]*")?\s*)src="data:image\/(gif|jpeg|png|jpg|svg|webp);base64,([^"]*)"\s*[^>]*(?>(?<=data-filename=")[^"]*")?[^>]*>';
        if (preg_match_all("/$regExpSearch/", $text, $matches)) {
            if (!class_exists('attach')) {
                include 'tools/attach/libs/attach.lib.php';
            }
            $entryCreationTime = $this->getEntryCreationTime($entry);
            $previousTag = $wiki->tag;
            $previousPage = $wiki->page;
            foreach ($matches[0] as $index => $textToReplace) {
                $imageType = $matches[2][$index];
                $imageContent = base64_decode($matches[3][$index]);
                $fileName = $matches[4][$index];
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

                $attach = new \Attach($wiki);
                $attach->file = $fileName;

                // fake page
                $wiki->tag = $entry['id_fiche'];
                $wiki->page = [
                    'tag' => $entry['id_fiche'],
                    'page' => json_encode($entry),
                    'time' => $entryCreationTime,
                    'owner' => '',
                    'user' => '',
                ];
                $newFilePath = $attach->GetFullFilename(true);

                if (!empty($newFilePath)) {
                    // save file
                    file_put_contents($newFilePath, $imageContent);

                    $newText = $matches[1][$index];
                    $newText .= "src=\"$newFilePath\">";

                    $text = str_replace($textToReplace, $newText, $text);
                }
                unset($attach);
            }
            $wiki->tag = $previousTag;
            $wiki->page = $previousPage;
        }

        return $text;
    }

    private function getEntryCreationTime(?array $entry): string
    {
        $dbTz = $this->getService(DbService::class)->getDbTimeZone();
        $sqlTimeFormat = 'Y-m-d H:i:s';
        $entryCreationTime = !empty($entry['date_maj_fiche'])
            ? $entry['date_creation_fiche']
            : (
                !empty($dbTz)
                ? (new DateTime())->setTimezone(new DateTimeZone($dbTz))->format($sqlTimeFormat)
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
        return removeAccents(preg_replace('/--+/u', '-', preg_replace('/[[:punct:]]/', '-', $inputString)));
    }

    /**
     * sanitize html to prevent xss.
     */
    private function sanitizeHTMLInWikiCode(string $value)
    {
        $preformattedDirtyHTML = str_replace(['@@', '""'], ['\\@\\@\\', '@@'], $value);
        $preformattedCleanHTML = $this->getService(HtmlPurifierService::class)->cleanHTML($preformattedDirtyHTML);

        return str_replace(['""', '@@', '\\@\\@\\'], ['\'\'', '""', '@@'], $preformattedCleanHTML);
    }

    /**
     * sanitize html to prevent xss.
     */
    private function sanitizeHTML(string $value)
    {
        return $this->getService(HtmlPurifierService::class)->cleanHTML($value);
    }
}

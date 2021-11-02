<?php

namespace YesWiki\Bazar\Field;

use Psr\Container\ContainerInterface;

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
        $wiki = $this->getWiki();
        // If HTML syntax, load editor's JS and CSS
        if ($this->syntax === self::SYNTAX_HTML) {
            $wiki->AddJavascriptFile('tools/bazar/libs/vendor/summernote/summernote.min.js');
            $wiki->AddCSSFile('tools/bazar/libs/vendor/summernote/summernote.css');

            $langKey = strtolower($GLOBALS['prefered_language']).'-'.strtoupper($GLOBALS['prefered_language']);
            $langFile = 'tools/bazar/libs/vendor/summernote/lang/summernote-'.$langKey.'.js';
            if (file_exists($langFile)) {
                $wiki->AddJavascriptFile($langFile);
                $langOptions = 'lang: "'.$langKey.'",';
            } else {
                $langOptions = '';
            }

            $script = '$(document).ready(function() {
              $(".summernote").summernote({
                '.$langOptions.'
                height: '. $this->numRows*30 .',    // set editor height
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
        } elseif ($this->syntax === self::SYNTAX_WIKI &&
            !empty($wiki->config['actionbuilder_textarea_name'])
            && $this->getName() == $wiki->config['actionbuilder_textarea_name']) {
            // load action builder
            include_once 'tools/aceditor/actions/actions_builder.php';
        }

        return $this->render("@bazar/inputs/textarea.twig", [
            'value' => $this->getValue($entry),
            'entryId' => $entry['id_fiche'] ?? null
        ]);
    }

    public function formatValuesBeforeSave($entry)
    {
        $value = $this->getValue($entry);

        if ($this->syntax == self::SYNTAX_HTML) {
            $value = strip_tags($value, self::ACCEPTED_TAGS);
        }

        return [$this->propertyName => $value];
    }

    protected function renderStatic($entry)
    {
        $value = $this->getValue($entry);
        if (!$value) {
            return null;
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
                $value = htmlentities($value, ENT_QUOTES, YW_CHARSET);
                break;

            case self::SYNTAX_HTML:
                // if the user type "", it's replaced by '' otherwise it crashes the output because it's interpretated
                // by wakka as a beginning of HTML code
                $value = str_replace('""', '\'\'', $value);
                break;
        }

        return $this->render("@bazar/fields/textarea.twig", [
            'value' => $value
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
}

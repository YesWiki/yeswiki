<?php

namespace YesWiki\Render\Action;

use YesWiki\Content\Service\ActionsBuilderService;
use YesWiki\Content\Service\PageManager;
use YesWiki\Core\YesWikiAction;
use YesWiki\Identity\Service\InputFilter;
use YesWiki\Kernel\Performable\RegisteredAction;
use YesWiki\Kernel\Service\CurrentRequest;
use YesWiki\Kernel\Service\RuntimeConfig;

class AceditorAction extends YesWikiAction implements RegisteredAction
{
    /** `{{aceditor}}` in page content -- stated, not inferred from the filename. */
    public static function performableName(): string
    {
        return 'aceditor';
    }

    public function formatArguments($args): array
    {
        return [
            'name' => $args['name'] ?? 'aceditor',
            'value' => $args['value'] ?? '',
            'required' => $args['required'] ?? null,
            'placeholder' => $args['placeholder'] ?? '',
            'rows' => $args['rows'] ?? 3,
            'maxChars' => $args['maxChars'] ?? null,
            'tempTag' => $args['tempTag'] ?? null, // used in new entry form
            'saveButton' => $this->formatBoolean($args['saveButton'] ?? null, false),
        ];
    }

    /** The languages Vditor is vendored with -- same list as TextareaField's. */
    private const VDITOR_LANGS = [
        'en' => 'en_US',
        'es' => 'es_ES',
        'fr' => 'fr_FR',
        'pt' => 'pt_BR',
    ];

    /** Named by javascripts/editor-switch.js, which is the only thing that writes it. */
    private const EDITOR_COOKIE = 'yw_editor';

    public function run(): string
    {
        $data = $this->getService(ActionsBuilderService::class)->getData();
        $pageTags = $this->getService(PageManager::class)->getReadablePageTags();

        return $this->render($this->chosenEditorTemplate(), [
            'actionsBuilderData' => $data,
            'pageTags' => $pageTags,
            'saveValue' => InputFilter::EDIT_PAGE_SUBMIT_VALUE,
            'vditorLang' => self::VDITOR_LANGS[strtolower((string)($GLOBALS['prefered_language'] ?? 'en'))] ?? 'en_US',
        ]);
    }

    /**
     * Which editor writes this field.
     *
     * PROTOTYPE (javascripts/vditor-components.js): the same field, edited as what it will
     * look like rather than as its source. The config says which one a wiki opens with --
     * the ACeditor, until this has been lived with -- and the reader's own choice, made by
     * the switch button in either toolbar, outranks it. That choice is a cookie because it
     * has to be legible here, before a line of JavaScript has run.
     */
    private function chosenEditorTemplate(): string
    {
        $chosen = $this->getService(CurrentRequest::class)->get()->cookies->get(self::EDITOR_COOKIE);
        if ($chosen === 'vditor' || $chosen === 'aceditor') {
            return $chosen === 'vditor' ? '@core/vditor-wiki.twig' : '@core/aceditor.twig';
        }

        return ($this->getService(RuntimeConfig::class)['vditor_wiki_editor'] ?? false)
            ? '@core/vditor-wiki.twig'
            : '@core/aceditor.twig';
    }
}

<?php

namespace YesWiki\Content\Field;

use Psr\Container\ContainerInterface;
use YesWiki\Kernel\Service\AssetRegistry;
use YesWiki\Render\Service\TabsRenderer;

#[\Field(['tabs'])]
class TabsField extends LabelField
{
    /** @var array<int, string> */
    private $formTitles;
    /** @var array<int, string> */
    private $viewTitles;
    /** @var bool */
    private $moveSubmitButtonToLastTab;
    /** @var string */
    private $btnClass;
    /** @var TabsRenderer */
    protected $tabsRenderer;

    protected const FIELD_FORM_TITLES = 1;
    protected const FIELD_VIEW_TITLES = 3;
    protected const FIELD_MOVE_SUBMIT_BUTTON_TO_LAST_TAB = 5;
    protected const FIELD_BTN_COLOR = 7;
    protected const FIELD_BTN_SIZE = 9;

    /**
     * @param array<int|string, mixed> $values
     */
    public function __construct(array $values, ContainerInterface $services)
    {
        parent::__construct($values, $services);
        $this->default = null;
        $this->searchable = null;
        $this->formTitles = $this->sanitizeTitles($values[self::FIELD_FORM_TITLES]);
        $this->viewTitles = $this->sanitizeTitles($values[self::FIELD_VIEW_TITLES]);
        $this->moveSubmitButtonToLastTab = ($values[self::FIELD_MOVE_SUBMIT_BUTTON_TO_LAST_TAB] === 'moveSubmit');
        $this->btnClass = (in_array($values[self::FIELD_BTN_COLOR], ['btn-primary', 'btn-secondary-1', 'btn-secondary-2'], true) ? $values[self::FIELD_BTN_COLOR] : 'btn-primary') .
          ($values[self::FIELD_BTN_SIZE] === 'btn-xs' ? ' btn-xs' : '');
        $this->tabsRenderer = $this->getService(TabsRenderer::class);

        $this->formText = '';
        $this->viewText = '';
    }

    /**
     * @return array<int, string>
     */
    protected function sanitizeTitles(?string $input): array
    {
        if ($input === null) {
            return [];
        }

        $titles = explode(',', str_replace('|', ',', $input));
        $titles = array_filter(array_map('trim', $titles), function ($title) {
            return !empty($title);
        });

        return $titles;
    }

    protected function prepareText(string $mode): string
    {
        return $this->tabsRenderer->openTabs($mode, $this);
    }

    protected function renderInput($entry)
    {
        if ($this->getMoveSubmitButtonToLastTab()) {
            $this->getService(AssetRegistry::class)->addJsFile('javascripts/inputs/tabs.js');
        }
        $this->formText = $this->prepareText('form');

        return $this->formText;
    }

    protected function renderStatic($entry)
    {
        $this->viewText = $this->prepareText('view');

        return $this->viewText;
    }

    /**
     * @return array<int, string>
     */
    public function getFormTitles()
    {
        return $this->formTitles;
    }

    /**
     * @return array<int, string>
     */
    public function getViewTitles()
    {
        return $this->viewTitles;
    }

    /**
     * @return bool
     */
    public function getMoveSubmitButtonToLastTab()
    {
        return $this->moveSubmitButtonToLastTab;
    }

    /**
     * @return string
     */
    public function getBtnClass()
    {
        return $this->btnClass;
    }

    #[\ReturnTypeWillChange]
    public function jsonSerialize()
    {
        return array_merge(
            parent::jsonSerialize(),
            [
                'formTitles' => $this->getFormTitles(),
                'viewTitles' => $this->getViewTitles(),
                'moveSubmitButtonToLastTab' => $this->getMoveSubmitButtonToLastTab(),
                'btnClass' => $this->getBtnClass(),
            ]
        );
    }
}

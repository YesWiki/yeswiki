<?php

namespace YesWiki\Bazar\Field;

use Psr\Container\ContainerInterface;
use YesWiki\Core\Service\AssetsManager;
use YesWiki\Templates\Controller\TabsController;

/**
 * @Field({"tabs"})
 */
class TabsField extends LabelField
{
    private $formTitles; // Tabs titles for from separated by coma
    private $viewTitles; // Tabs titles for view separated by coma
    private $moveSubmitButtonToLastTab;
    private $tabsClass;
    private $btnClass;
    protected $tabsController;

    protected const FIELD_FORM_TITLES = 1;
    protected const FIELD_VIEW_TITLES = 3;
    protected const FIELD_MOVE_SUBMIT_BUTTON_TO_LAST_TAB = 5;
    protected const FIELD_BTN_COLOR = 7;
    protected const FIELD_BTN_SIZE = 9;
    protected const BTN_COLOR_LIST = ['btn-primary', 'btn-secondary-1', 'btn-secondary-2'];
    protected const BTN_SIZE_SMALL = 'btn-xs';
    private const FIELD_CLASS_TYPE = 'TabsField';

    public function __construct(array $values, ContainerInterface $services)
    {
        parent::__construct($values, $services);
        $this->default = null;
        $this->searchable = null;
        $this->formTitles = $this->sanitizeTitles($values[self::FIELD_FORM_TITLES]);
        $this->viewTitles = $this->sanitizeTitles($values[self::FIELD_VIEW_TITLES]);
        $this->moveSubmitButtonToLastTab = ($values[self::FIELD_MOVE_SUBMIT_BUTTON_TO_LAST_TAB] === 'moveSubmit');
        $this->btnClass = (in_array($values[self::FIELD_BTN_COLOR], self::BTN_COLOR_LIST, true) ? $values[self::FIELD_BTN_COLOR] : 'btn-primary') . ' ' .
          ($values[self::FIELD_BTN_SIZE] === self::BTN_SIZE_SMALL ? self::BTN_SIZE_SMALL : '');
        $this->tabsController = $this->getService(TabsController::class);
        // does not call prepareText in constuct only in render (lazy loading)
        $this->formText = '';
        $this->viewText = '';
    }

    protected function sanitizeTitles(?string $input): ?array
    {
        $titles = explode(',', str_replace('|', ',', $input));
        $titles = array_filter(array_map('trim', $titles), function ($title) {
            return !empty($title);
        });

        return $titles;
    }

    protected function prepareText($mode): ?string
    {
        return $this->tabsController->openTabs($mode, $this);
    }

    protected function renderInput($entry)
    {
        if ($this->getMoveSubmitButtonToLastTab()) {
            $this->getService(AssetsManager::class)->AddJavascriptFile('tools/bazar/presentation/javascripts/inputs/tabs.js');
        }
        $this->formText = $this->prepareText('form');

        return $this->formText;
    }

    protected function renderStatic($entry)
    {
        $this->viewText = $this->prepareText('view');

        return $this->viewText;
    }

    public function getFormTitles()
    {
        return $this->formTitles;
    }

    public function getViewTitles()
    {
        return $this->viewTitles;
    }

    public function getMoveSubmitButtonToLastTab()
    {
        return $this->moveSubmitButtonToLastTab;
    }

    public function getBtnClass()
    {
        return $this->btnClass;
    }

    // LANG_FIXME not sure about this function
    public static function mapToFieldArray($fieldProps): array
    {
        $new = parent::mapToFieldArray($fieldProps);
        $new[self::FIELD_FORM_TITLES] = implode(',', $fieldProps['formTitles']);
        $new[self::FIELD_VIEW_TITLES] = implode(',', $fieldProps['viewTitles']);
        $new[self::FIELD_BTN_COLOR] = array_filter(explode(' ', $fieldProps['btnClass']), function ($el) {
            return in_array(trim($el), self::BTN_COLOR_LIST);
        })[0];
        $new[self::FIELD_BTN_SIZE] = str_contains(self::BTN_SIZE_SMALL, $fieldProps['btnClass']) ? self::BTN_SIZE_SMALL : '';
        $new[self::FIELD_MOVE_SUBMIT_BUTTON_TO_LAST_TAB] = $fieldProps['moveSubmitButtonToLastTab'];
        ksort($new);

        return $new;
    }

    // change return of this method to keep compatible with php 7.3 (mixed is not managed)
    #[\ReturnTypeWillChange]
    public function jsonSerialize()
    {
        return array_merge(
            parent::jsonSerialize(),
            [
                'field_type' => self::FIELD_CLASS_TYPE,
                'formTitles' => $this->getFormTitles(),
                'viewTitles' => $this->getViewTitles(),
                'moveSubmitButtonToLastTab' => $this->getMoveSubmitButtonToLastTab(),
                'btnClass' => $this->getBtnClass(),
            ]
        );
    }
}

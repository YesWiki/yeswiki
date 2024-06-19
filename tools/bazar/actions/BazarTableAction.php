<?php

use YesWiki\Bazar\Service\FormManager;
use YesWiki\Core\YesWikiAction;

class BazarTableAction extends YesWikiAction
{
    public function formatArguments($arg)
    {
        $newArg = [];
        $newArg['pagination'] = -1;
        if (empty($arg['columnfieldsids'])) {
            $this->appendAllFieldsIds($arg, $newArg, 'columnfieldsids');
        } elseif ($this->formatBoolean($arg, false, 'exportallcolumns')) {
            $this->appendAllFieldsIds($arg, $newArg, 'exportallcolumnsids');
        }

        return $newArg;
    }

    public function run()
    {
        return $this->callAction('bazarliste', $this->arguments);
    }

    protected function appendAllFieldsIds(array $arg, array &$newArg, string $key)
    {
        $formId = empty($arg['id']) ? '1' : array_values(array_filter(explode(',', $arg['id']), function ($id) {
            return strval($id) == strval(intval($id));
        }))[0];
        $form = $this->getService(FormManager::class)->getOne($formId);
        if (!empty($form['prepared'])) {
            $newArg[$key] = implode(',', array_map(function ($field) {
                return $field->getPropertyName();
            }, array_filter($form['prepared'], function ($field) {
                return !empty($field->getPropertyName());
            })));
        }
    }
}

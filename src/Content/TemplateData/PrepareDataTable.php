<?php

namespace YesWiki\Content\TemplateData;

use YesWiki\Content\Service\FormManager;

/** The columns the Vue table draws, which only the form knows (was `EntryTableAction::formatArguments()`). */
#[\PreparesTemplate(['map-and-table'])]
class PrepareDataTable extends PrepareData
{
    public function prepare(array $arguments): array
    {
        $bareTemplate = (string)preg_replace('/\.(twig|tpl\.html)$/', '', (string)($arguments['template'] ?? ''));
        if (empty($arguments['dynamic']) && $bareTemplate !== 'map-and-table') {
            return [];
        }

        $prepared = ['pagination' => -1];

        if (empty($arguments['columnfieldsids'])) {
            $this->appendAllFieldIds($arguments, $prepared, 'columnfieldsids');
        } elseif ($this->formatBoolean($arguments, false, 'exportallcolumns')) {
            $this->appendAllFieldIds($arguments, $prepared, 'exportallcolumnsids');
        }

        return $prepared;
    }

    /**
     * @param array<string, mixed> $arguments
     * @param array<string, mixed> $prepared
     */
    private function appendAllFieldIds(array $arguments, array &$prepared, string $key): void
    {
        $numericIds = array_values(array_filter(
            explode(',', empty($arguments['id']) ? '1' : (string)$arguments['id']),
            static fn ($id) => strval($id) === strval(intval($id))
        ));
        if ($numericIds === []) {
            return;
        }

        $form = $this->getService(FormManager::class)->getOne($numericIds[0]);
        if (empty($form['prepared'])) {
            return;
        }

        $prepared[$key] = implode(',', array_map(
            static fn ($field) => $field->getPropertyName(),
            array_filter($form['prepared'], static fn ($field) => !empty($field->getPropertyName()))
        ));
    }
}

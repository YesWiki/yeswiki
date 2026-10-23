<?php

namespace YesWiki\Content\Field;

use YesWiki\Render\Service\ActionRunner;
use YesWiki\Render\Service\TabsService;

/** The linked-entry fields that can show their chosen entries through an entry list template, and say how a click opens one. */
trait ShowsEntriesThroughAList
{
    protected string $entriesTemplate = '';

    protected string $entriesParams = '';

    protected string $entryDisplay = '';

    /** @param array<int|string, mixed> $values */
    protected function readEntriesDisplay(array $values): void
    {
        $this->entriesTemplate = trim((string)($values[self::FIELD_ENTRIES_TEMPLATE] ?? ''));
        $this->entriesParams = trim((string)($values[self::FIELD_ENTRIES_PARAMS] ?? ''));
        $entryDisplay = trim((string)($values[self::FIELD_ENTRY_DISPLAY] ?? ''));
        $this->entryDisplay = in_array($entryDisplay, ['modal', 'newtab', 'direct'], true) ? $entryDisplay : '';
    }

    /** @param list<int|string> $tags the chosen entries, rendered by the field's entry list template */
    protected function renderEntriesList(array $tags): string
    {
        $tags = array_values(array_filter(
            array_map('strval', $tags),
            fn (string $tag) => preg_match('/^[\p{L}\p{N}_-]+$/u', $tag) === 1
        ));
        if ($tags === []) {
            return '';
        }

        $vars = [
            'id' => (string)$this->getLinkedObjectName(),
            'template' => $this->entriesTemplate,
            'query' => 'tag=' . implode(',', $tags),
        ];
        if ($this->entryDisplay !== '') {
            $vars['entrydisplay'] = $this->entryDisplay;
        }
        $command = 'entrylist entrydisplay="modal" ' . $this->entriesParams;
        foreach ($vars as $name => $value) {
            $command .= ' ' . $name . '="' . str_replace('"', '', $value) . '"';
        }

        $tabsService = $this->getService(TabsService::class);
        $index = $tabsService->saveState();
        $output = $this->getService(ActionRunner::class)->action($command);
        $tabsService->resetState($index);

        return $this->render('@core/fields/entries-list.twig', ['output' => $output]);
    }
}

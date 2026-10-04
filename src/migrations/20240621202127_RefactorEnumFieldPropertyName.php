<?php

use YesWiki\Content\Entity\PageType;
use YesWiki\Content\Field\EnumField;
use YesWiki\Content\Service\FieldFactory;
use YesWiki\Content\Service\FormManager;
use YesWiki\Content\Service\PageManager;
use YesWiki\Core\YesWikiMigration;

/** Stores an enum field's full property name (type + linked object + name) in its form template when the form's entries keep their values under it. */
class RefactorEnumFieldPropertyName extends YesWikiMigration
{
    /** @var array<string, array<string, true>> form id => property names its entries use */
    private array $storedKeys;

    public function run()
    {
        $this->storedKeys = $this->storedEntryKeys();
        $this->renameInNatureTable();
        $this->renameInFormPages();
    }

    private function renameInNatureTable(): void
    {
        if (!$this->dbService->schema()->columnExists('nature', 'bn_template')) {
            return;
        }
        $formManager = $this->getService(FormManager::class);
        $nature = $this->dbService->prefixTable('nature');
        foreach ($this->dbService->loadAll("SELECT bn_id_nature, bn_template FROM {$nature}") as $row) {
            $template = $this->withFullNames($formManager->parseTemplate($row['bn_template']), (string)$row['bn_id_nature']);
            if ($template === null) {
                continue;
            }
            $this->dbService->query(
                "UPDATE {$nature} SET bn_template = ? WHERE bn_id_nature = ?",
                [$formManager->encodeTemplate($template), $row['bn_id_nature']]
            );
        }
    }

    private function renameInFormPages(): void
    {
        $formManager = $this->getService(FormManager::class);
        $pages = $this->dbService->prefixTable('pages');
        $rows = $this->dbService->loadAll(
            "SELECT id, tag, body FROM {$pages} WHERE latest = 'Y' AND {$this->dbService->quoteIdentifier('type')} = ?",
            [PageType::FORM]
        );
        foreach ($rows as $row) {
            $body = json_decode((string)$row['body'], true);
            if (!is_array($body)) {
                continue;
            }
            $templateKey = array_key_exists('template', $body) ? 'template' : 'bn_template';
            $stored = $body[$templateKey] ?? '';
            $positional = is_array($stored)
                ? $formManager->templateToPositionalList($stored)
                : $formManager->parseTemplate($stored);
            $template = $this->withFullNames($positional, (string)($body['id'] ?? $body['bn_id_nature'] ?? ''));
            if ($template === null) {
                continue;
            }
            $body[$templateKey] = is_array($stored)
                ? $formManager->positionalListToTemplate($template)
                : $formManager->encodeTemplate($template);
            $this->dbService->query(
                "UPDATE {$pages} SET body = ? WHERE id = ?",
                [json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $row['id']]
            );
            $this->getService(PageManager::class)->forget((string)$row['tag']);
        }
        $formManager->startNewRequest();
    }

    /**
     * The template with each enum field renamed to its full property name where the entries use only that one, or null when nothing changes.
     *
     * @param array<int, array<int, string>> $template positional field arrays
     *
     * @return array<int, array<int, string>>|null
     */
    private function withFullNames(array $template, string $formId): ?array
    {
        $keys = $this->storedKeys[$formId] ?? [];
        $fieldFactory = $this->getService(FieldFactory::class);
        $changed = false;
        foreach ($template as $index => $fieldArray) {
            $field = $fieldFactory->create($fieldArray);
            if (!$field instanceof EnumField) {
                continue;
            }
            $name = (string)($field->getName() ?? '');
            $fullName = $field->getType() . $field->getLinkedObjectName() . $name;
            if ($fullName !== $name && isset($keys[$fullName]) && ($name === '' || !isset($keys[$name]))) {
                $template[$index][EnumField::FIELD_NAME] = $fullName;
                $changed = true;
            }
        }

        return $changed ? $template : null;
    }

    /** @return array<string, array<string, true>> */
    private function storedEntryKeys(): array
    {
        $rows = $this->dbService->loadAll(
            'SELECT body FROM ' . $this->dbService->prefixTable('pages')
            . " WHERE latest = 'Y' AND {$this->dbService->quoteIdentifier('type')} = ?",
            [PageType::ENTRY]
        );
        $keys = [];
        foreach ($rows as $row) {
            $entry = json_decode((string)$row['body'], true);
            $formId = is_array($entry) ? (string)($entry['form_id'] ?? $entry['id_typeannonce'] ?? '') : '';
            if ($formId !== '') {
                $keys[$formId] = ($keys[$formId] ?? []) + array_fill_keys(array_map('strval', array_keys($entry)), true);
            }
        }

        return $keys;
    }
}

<?php

use YesWiki\Content\Entity\ContentTypeSchema;
use YesWiki\Content\Field\TextareaField;
use YesWiki\Content\Service\FormManager;
use YesWiki\Core\YesWikiMigration;

/** Give the Page form's `content` field the wiki editor it always meant to have. */
class PageFormContentUsesTheWikiEditor extends YesWikiMigration
{
    public function run(): void
    {
        $formManager = $this->getService(FormManager::class);

        $form = $formManager->getByContentType(ContentTypeSchema::TYPE_PAGE);
        if ($form === null || !is_array($form['template'] ?? null)) {
            return;
        }

        $changed = false;
        foreach ($form['template'] as $index => $field) {
            if (($field['name'] ?? '') !== 'content' || !empty($field['syntax'])) {
                continue;
            }
            $form['template'][$index]['syntax'] = TextareaField::SYNTAX_WIKI;
            $changed = true;
        }

        if ($changed) {
            $formManager->update($form);
        }
    }
}

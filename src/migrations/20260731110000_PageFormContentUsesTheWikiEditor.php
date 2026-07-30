<?php

use YesWiki\Content\Entity\ContentTypeSchema;
use YesWiki\Content\Field\TextareaField;
use YesWiki\Content\Service\FormManager;
use YesWiki\Core\YesWikiMigration;

/**
 * Give the Page form's `content` field the wiki editor it always meant to have.
 *
 * A `textelong` with no syntax renders a bare textarea, so creating a page from the Pages
 * form offered a plain box while editing the very same page offered the ACeditor -- two
 * editors for one field (ticket 13). The syntax is declared in ContentTypeSchema now, but
 * that only reaches forms created from scratch: the Pages form already exists.
 *
 * Only a `content` field with no syntax of its own is touched -- a webmaster who chose
 * plain text or HTML keeps their choice. Idempotent.
 */
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

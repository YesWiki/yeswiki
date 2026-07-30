<?php

use YesWiki\Content\Entity\ContentTypeSchema;
use YesWiki\Content\Service\FormManager;
use YesWiki\Content\Service\FormPropertiesService;
use YesWiki\Core\YesWikiMigration;

/**
 * Repair the Page, User and File forms created before their title template knew about
 * Content types.
 *
 * CreateContentTypeForms made those three forms with no `entry_title_template`, so they
 * inherited the bazar default `{{bf_titre}}` -- a field name none of them has. A page's
 * title is `title`, a user's is `username`, a file's is `original_filename`.
 *
 * Only a form still carrying the untouched bazar default is repaired: a webmaster who has
 * since chosen their own title template keeps it.
 *
 * Idempotent -- a form already naming a real field is left alone, so this is safe to
 * re-run and harmless on a wiki whose seed created the forms correctly.
 */
class ContentTypeFormTitleTemplates extends YesWikiMigration
{
    public function run(): void
    {
        $formManager = $this->getService(FormManager::class);

        foreach (ContentTypeSchema::types() as $contentType) {
            $wanted = ContentTypeSchema::defaultTitleTemplate($contentType);
            if ($wanted === null) {
                continue;
            }

            $form = $formManager->getByContentType($contentType);
            if ($form === null
                || ($form['entry_title_template'] ?? '') !== FormPropertiesService::DEFAULT_TITLE_TEMPLATE) {
                continue;
            }

            $form['entry_title_template'] = $wanted;
            $formManager->update($form);
        }
    }
}

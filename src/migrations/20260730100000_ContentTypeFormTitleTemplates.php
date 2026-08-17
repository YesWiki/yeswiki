<?php

use YesWiki\Content\Entity\ContentTypeSchema;
use YesWiki\Content\Service\FormManager;
use YesWiki\Content\Service\FormPropertiesService;
use YesWiki\Core\YesWikiMigration;

/**
 * Repair the Page, User and File forms created before their title template knew about Content types.
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

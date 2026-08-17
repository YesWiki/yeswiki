<?php

use YesWiki\Content\Entity\ContentTypeSchema;
use YesWiki\Content\Service\FormManager;
use YesWiki\Core\YesWikiMigration;

/** Ticket 10: create the Page, User and File forms. */
class CreateContentTypeForms extends YesWikiMigration
{
    /** Labels are the webmaster's to change afterwards; these are only the starting point. */
    private const LABELS = [
        ContentTypeSchema::TYPE_PAGE => 'Pages',
        ContentTypeSchema::TYPE_USER => 'Comptes',
        ContentTypeSchema::TYPE_FILE => 'Fichiers',
    ];

    public function run(): void
    {
        $formManager = $this->getService(FormManager::class);

        foreach (self::LABELS as $contentType => $label) {
            if ($formManager->getByContentType($contentType) !== null) {
                continue;
            }

            $formManager->create([
                'label' => $label,
                ContentTypeSchema::CONTENT_TYPE => $contentType,

                'template' => [],
            ]);
        }
    }
}

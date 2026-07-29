<?php

use YesWiki\Content\Entity\ContentTypeSchema;
use YesWiki\Content\Service\FormManager;
use YesWiki\Core\YesWikiMigration;

/**
 * Ticket 10: create the Page, User and File forms.
 *
 * Each built-in Content type gets exactly one form, whose template starts as that type's
 * mandatory core structure and which a webmaster may then extend in the ordinary
 * designer. No per-row migration is needed: which form describes a row is decided by the
 * row's own `TYPE_URI` triple, so pages, users and files do not carry a `form_id` the way
 * a bazar entry does.
 *
 * Idempotent -- a type that already has its form is skipped, so this is safe to re-run
 * after a partial failure and harmless on an install whose seed already created them.
 */
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
                // left empty on purpose: templateToStorage() fills in the type's locked
                // fields, so the core structure has one definition, not two
                'template' => [],
            ]);
        }
    }
}

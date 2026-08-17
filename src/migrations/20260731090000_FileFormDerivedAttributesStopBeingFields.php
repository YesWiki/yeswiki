<?php

use YesWiki\Content\Entity\ContentTypeSchema;
use YesWiki\Content\Service\FormManager;
use YesWiki\Core\YesWikiMigration;

/** Take the derived file attributes out of the File form's template. */
class FileFormDerivedAttributesStopBeingFields extends YesWikiMigration
{
    /** Derived from the upload, and never typed in. */
    private const DERIVED = ['original_filename', 'stored_filename', 'uploaded_from', 'size', 'mime_type'];

    public function run(): void
    {
        $formManager = $this->getService(FormManager::class);

        $form = $formManager->getByContentType(ContentTypeSchema::TYPE_FILE);
        if ($form === null || !is_array($form['template'] ?? null)) {
            return;
        }

        $kept = array_values(array_filter(
            $form['template'],
            fn ($field) => !in_array($field['name'] ?? '', self::DERIVED, true)
        ));
        if (count($kept) === count($form['template'])) {
            return;
        }

        $form['template'] = $kept;
        $formManager->update($form);
    }
}

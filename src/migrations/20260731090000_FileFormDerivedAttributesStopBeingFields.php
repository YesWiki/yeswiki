<?php

use YesWiki\Content\Entity\ContentTypeSchema;
use YesWiki\Content\Service\FormManager;
use YesWiki\Core\YesWikiMigration;

/**
 * Take the derived file attributes out of the File form's template.
 *
 * Ticket 10 gave the File type three locked text fields -- `original_filename`,
 * `stored_filename`, `uploaded_from` -- on the reasoning that a locked field is a real
 * input with a real value in the body. For a file that turned out to be false: all three
 * are computed from an upload, nobody should type them, and typing `stored_filename`
 * breaks the download outright. Worse, they broke editing: a text field the form did not
 * submit yields an empty string, so saving a File from its own edit form wrote `""` over
 * both filenames and 404'd the file. Ticket 13 replaces them with the one thing a File
 * form really asks for, `file_content`, and leaves the attributes as body keys FileManager
 * writes.
 *
 * The attributes themselves are untouched -- this only removes the inputs. Idempotent: a
 * template that no longer declares them is left alone.
 */
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

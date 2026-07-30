<?php

namespace YesWiki\Content\Field;

use Field;
use Psr\Container\ContainerInterface;
use YesWiki\Content\Service\FileManager;
use YesWiki\Kernel\Service\UrlFormatter;

/**
 * The bytes of a file Content (`contenu_fichier`, ticket 13).
 *
 * Every other locked field of the File type -- `original_filename`, `stored_filename`,
 * `size`, `mime_type` -- is *derived* from an upload, not typed in, so a File form made
 * only of text inputs could describe a file but never produce one. This field is the
 * upload itself: it renders a file input, hands the bytes to FileManager, and returns the
 * attributes the others hold.
 *
 * It is deliberately NOT the existing `fichier` FileField, which stores under
 * `$type . $name` (so `fichiercontenu`, not the names FileManager reads) and writes
 * through bazar's attachment path into the web-servable `files/` directory -- the very
 * directory ticket 17 moved file bytes out of, and one FileManager cannot read.
 */
#[\Field(['contenu_fichier'])]
class FileContentField extends BazarField
{
    /** @param array<int|string, mixed> $values */
    public function __construct(array $values, ContainerInterface $services)
    {
        parent::__construct($values, $services);

        $this->type = 'file';
        // the upload is not stored under this field's own name: it becomes the file
        // attributes, which is what formatValuesBeforeSave() returns
        $this->propertyName = $this->name;
    }

    /** @param array<string, mixed> $entry */
    protected function renderInput($entry): string
    {
        $storedFilename = $entry['stored_filename'] ?? '';
        $tag = $entry['tag'] ?? '';

        return $this->render('@core/inputs/file-content.twig', [
            'name' => $this->name,
            'required' => $this->required && empty($storedFilename),
            'currentFilename' => $entry['original_filename'] ?? '',
            'currentSize' => $entry['size'] ?? null,
            'downloadUrl' => empty($storedFilename) || empty($tag)
                ? ''
                : $this->getService(UrlFormatter::class)->href('', 'api/files/' . $tag . '/download'),
        ]);
    }

    /**
     * The upload is read straight from the request rather than from $entry: a file has no
     * text value to carry around, and PHP puts uploads in $_FILES, not in the posted body.
     *
     * Nothing is returned when no file was sent, so editing a File without re-uploading
     * leaves the existing bytes exactly where they are.
     *
     * @param array<string, mixed> $entry
     *
     * @return array<string, mixed>
     */
    public function formatValuesBeforeSave($entry): array
    {
        $uploadedFile = $this->getRequest()->files->get($this->name);

        if (empty($uploadedFile)) {
            return [];
        }

        return $this->getService(FileManager::class)->storeUpload($uploadedFile);
    }

    /** @param array<string, mixed> $entry */
    protected function renderStatic($entry): string
    {
        $tag = $entry['tag'] ?? '';
        $filename = $entry['original_filename'] ?? '';
        if (empty($tag) || empty($filename)) {
            return '';
        }

        return $this->render('@core/inputs/file-content-static.twig', [
            'label' => $this->label,
            'filename' => $filename,
            'downloadUrl' => $this->getService(UrlFormatter::class)->href('', 'api/files/' . $tag . '/download'),
        ]);
    }
}

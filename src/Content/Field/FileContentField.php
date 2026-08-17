<?php

namespace YesWiki\Content\Field;

use Psr\Container\ContainerInterface;
use YesWiki\Content\Service\FileManager;
use YesWiki\Kernel\Service\UrlFormatter;

/** The bytes of a file Content (`contenu_fichier`, ticket 13). */
#[\Field(['contenu_fichier'])]
class FileContentField extends BazarField
{
    use ContributesNoSearchableText;

    /**
     * @param array<int|string, mixed> $values
     */
    public function __construct(array $values, ContainerInterface $services)
    {
        parent::__construct($values, $services);

        $this->type = 'file';

        $this->propertyName = $this->name;
    }

    /**
     * @param array<string, mixed> $entry
     */
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
     * The upload is read straight from the request rather than from $entry: a file has no text value to carry around, and PHP puts uploads in $_FILES, not in the posted body.
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

    /**
     * @param array<string, mixed> $entry
     */
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

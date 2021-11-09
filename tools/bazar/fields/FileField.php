<?php

namespace YesWiki\Bazar\Field;

use Psr\Container\ContainerInterface;
use YesWiki\Bazar\Service\Guard;
use YesWiki\Security\Controller\SecurityController;

/**
 * @Field({"fichier"})
 */
class FileField extends BazarField
{
    public function __construct(array $values, ContainerInterface $services)
    {
        parent::__construct($values, $services);

        $this->propertyName = $this->type . $this->name;
    }

    protected function renderInput($entry)
    {
        $value = $this->getValue($entry);

        if (isset($value) && $value != '') {
            if (isset($_GET['delete_file']) && $_GET['delete_file'] == $value) {
                if ($this->isAllowedToDeleteFile($entry)) {
                    if (file_exists(BAZ_CHEMIN_UPLOAD . $value)) {
                        unlink(BAZ_CHEMIN_UPLOAD . $value);
                    }
                } else {
                    $alertMessage = '<div class="alert alert-info">' . _t('BAZ_DROIT_INSUFFISANT') . '</div>' . "\n";
                }
            }

            if (file_exists(BAZ_CHEMIN_UPLOAD . $value)) {
                return ($alertMessage ?? '') .$this->render('@bazar/inputs/file.twig', [
                    'value' => $value,
                    'shortFileName' => $this->getShortFileName($value, $entry),
                    'fileUrl' => BAZ_CHEMIN_UPLOAD . $value,
                    'deleteUrl' => $GLOBALS['wiki']->href('edit', $GLOBALS['wiki']->GetPageTag(), 'delete_file=' . $value, false),
                    'isAllowedToDeleteFile' => $this->isAllowedToDeleteFile($entry)
                ]);
            }
        }

        return ($alertMessage ?? '') . $this->render('@bazar/inputs/file.twig');
    }

    public function formatValuesBeforeSave($entry)
    {
        $value = $this->getValue($entry);

        if (isset($_FILES[$this->propertyName]['name']) && $_FILES[$this->propertyName]['name'] != '') {
            // Remove accents and spaces
            $fileName = $entry['id_fiche'] . '_' . $this->name . '_' . sanitizeFilename($_FILES[$this->propertyName]['name']);
            $filePath = BAZ_CHEMIN_UPLOAD . $fileName;

            $extension = obtenir_extension($fileName);
            if ($extension != '' && extension_autorisee($extension) == true) {
                if (!file_exists($filePath)) {
                    move_uploaded_file($_FILES[$this->propertyName]['tmp_name'], $filePath);
                    chmod($filePath, 0755);
                } else {
                    echo 'fichier déja existant<br />';
                }
            } else {
                echo 'fichier non autorise<br />';

                return [$this->propertyName => ''];
            }

            return [$this->propertyName => $fileName];
        } elseif (isset($value) && file_exists(BAZ_CHEMIN_UPLOAD . $value)) {
            return [$this->propertyName => $value];
        } else {
            return [$this->propertyName => ''];
        }
    }

    protected function renderStatic($entry)
    {
        $value = $this->getValue($entry);

        if (isset($value) && $value != '') {
            return $this->render('@bazar/fields/file.twig', [
                'value' => $value,
                'fileUrl' => BAZ_CHEMIN_UPLOAD . $value,
                'shortFileName' => $this->getShortFileName($value, $entry),
            ]);
        }

        return null;
    }

    /**
     * check if user is allowed to delete file
     * @param array $entry
     * @return bool
     */
    protected function isAllowedToDeleteFile(array $entry):bool
    {
        return !$this->getService(SecurityController::class)->isWikiHibernated() && $this->getService(Guard::class)->isAllowed('supp_fiche', $entry['owner'] ?? '');
    }

    /**
     * method to get the filename from the value
     * @param string $longFileName
     * @param null|array $entry
     * @return string $shortFileName
     */
    private function getShortFileName(string $longFileName, ?array $entry): string
    {
        $shortFileName =  (!empty($entry['id_fiche'])
            && preg_match("/^{$entry['id_fiche']}_{$this->name}_(.*)$/m", $longFileName, $match)
            && !empty($match[1]))
            ? $match[1]
            : $longFileName ;
        return $shortFileName;
    }
}

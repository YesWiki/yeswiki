<?php

namespace YesWiki\Bazar\Field;

use attach;
use Psr\Container\ContainerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use YesWiki\Bazar\Service\EntryManager;
use YesWiki\Bazar\Service\Guard;
use YesWiki\Security\Controller\SecurityController;

/**
 * @Field({"fichier"})
 */
class FileField extends BazarField
{
    protected $readLabel;
    protected const FIELD_READ_LABEL = 6;
    protected $attach;

    public function __construct(array $values, ContainerInterface $services)
    {
        parent::__construct($values, $services);

        $this->propertyName = $this->type . $this->name;
        $this->readLabel = empty(trim($values[self::FIELD_READ_LABEL])) ? _t('BAZ_FILEFIELD_FILE') : $values[self::FIELD_READ_LABEL];
        $this->attach = null;
    }

    protected function renderInput($entry)
    {
        $value = $this->getValue($entry);

        $deletedFile = false;

        if (!empty($value)) {
            if (!empty($entry) && isset($_GET['delete_file']) && $_GET['delete_file'] === $value) {
                if ($this->isAllowedToDeleteFile($entry, $value)) {
                    if (substr($value, 0, strlen($this->defineFilePrefix($entry))) == $this->defineFilePrefix($entry)) {
                        $attach = $this->getAttach();
                        $rawFileName = filter_input(INPUT_GET, 'delete_file', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
                        $attach->fmDelete($rawFileName);
                    } else {
                        // do not delete file if not same entry name (only remove from this entry)
                        $deletedFile = true;
                        $this->updateEntryAfterFileDelete($entry);
                    }
                } else {
                    $alertMessage = '<div class="alert alert-info">' . _t('BAZ_DROIT_INSUFFISANT') . '</div>' . "\n";
                }
            }
        }

        return ($alertMessage ?? '') . $this->render('@bazar/inputs/file.twig', (
            empty($value) ||  !file_exists($this->getBasePath().  $value) || $deletedFile
            ? []
            : [
                'value' => $value,
                'shortFileName' => $this->getShortFileName($value),
                'fileUrl' => $this->getAttach()->getFileUrl($value),
                'deleteUrl' => empty($entry) ? '' : $this->getWiki()->href('edit', $entry['id_fiche'], ['delete_file' => $value], false),
                'isAllowedToDeleteFile' => empty($entry) ? false : $this->isAllowedToDeleteFile($entry, $value)
            ]
        ));
    }

    public function formatValuesBeforeSave($entry)
    {
        $value = $this->getValue($entry);

        $params = $this->getService(ParameterBagInterface::class);
        if (!empty($_FILES[$this->propertyName]['name']) && !empty($entry['id_fiche'])) {
            $rawFileName = filter_var($_FILES[$this->propertyName]['name'], FILTER_UNSAFE_RAW);
            $rawFileName = in_array($rawFileName, [false,null], true) ? "" : htmlspecialchars(strip_tags($rawFileName));
            $sanitizedFilename = $this->sanitizeFilename($rawFileName);
            $fileName = "{$this->getPropertyName()}_$sanitizedFilename";
            $filePath = $this->getFullFileName($fileName, $entry['id_fiche'], true);

            $pathinfo = pathinfo($filePath);
            $extension = strtolower($pathinfo['extension']);
            $extension = preg_replace("/_$/", "", $extension);
            if ($extension != '' && in_array($extension, array_keys($params->get('authorized-extensions')))) {
                if (!file_exists($filePath)) {
                    move_uploaded_file($_FILES[$this->propertyName]['tmp_name'], $filePath);
                    chmod($filePath, 0755);
                } else {
                    echo _t('BAZ_FILE_ALREADY_EXISTING').'<br />';
                }
            } else {
                echo _t('BAZ_NOT_AUTHORIZED_FILE').'<br />';

                return [$this->propertyName => ''];
            }

            return [$this->propertyName => basename($filePath)];
        } elseif (!empty($value)) {
            return [$this->propertyName => file_exists($this->getBasePath(). $value) ? $value : ''];
        } else {
            return [$this->propertyName => ''];
        }
    }

    protected function renderStatic($entry)
    {
        $value = $this->getValue($entry);

        $basePath = $this->getBasePath() ;
        if (!empty($value) && file_exists($basePath.$value)) {
            $shortFileName = $this->getShortFileName($value);
            return $this->render('@bazar/fields/file.twig', [
                'value' => $value,
                'fileUrl' => ($shortFileName == $value)
                    ? $this->getAttah()->getFileUrl($value)
                    : $this->getWiki()->Href('download', $entry['id_fiche']."_".$this->getPropertyName(), ['file'=>$value], false),
                'shortFileName' => $shortFileName,
            ]);
        }

        return "";
    }

    /**
     * check if user is allowed to delete file
     * @param array $entry
     * @param string $fileName
     * @return bool
     */
    protected function isAllowedToDeleteFile(array $entry, string $fileName): bool
    {
        return !$this->getService(SecurityController::class)->isWikiHibernated()
            && $this->getService(Guard::class)->isAllowed('supp_fiche', $entry['owner'] ?? '');
    }

    /**
     * define file prefix
     * @param array $entry
     * @return string $prefixFileName
     */
    protected function defineFilePrefix(array $entry)
    {
        return $entry['id_fiche'].'_'.$this->getPropertyName().'_';
    }

    /**
     * method to get the filename from the value
     * @param string $longFileName
     * @return string $shortFileName
     */
    protected function getShortFileName(string $longFileName): string
    {
        $attach = $this->getAttach();

        $fullFileName = "{$this->getBasePath()}$longFileName";
        $fileNameInfos = file_exists($fullFileName) ? $attach->decodeLongFilename($fullFileName) : [];

        unset($attach);

        $shortFileName =  (empty($fileNameInfos['name']))
            ? $longFileName
            : (
                (preg_match("/^{$this->getPropertyName()}_(.*)$/m", "{$fileNameInfos['name']}.{$fileNameInfos['ext']}", $match)
                && !empty($match[1]))
                ? $match[1]
                : "{$fileNameInfos['name']}.{$fileNameInfos['ext']}"
            );
        return $shortFileName;
    }

    public function getReadLabel(): string
    {
        return $this->readLabel;
    }

    // change return of this method to keep compatible with php 7.3 (mixed is not managed)
    #[\ReturnTypeWillChange]
    public function jsonSerialize()
    {
        return array_merge(
            parent::jsonSerialize(),
            [
              'readLabel' => $this->getReadLabel(),
            ]
        );
    }

    protected function getFullFileName(string $fileName, string $tag, bool $newName = false): string
    {
        $wiki = $this->getWiki();
        $attach = $this->getAttach();
        // adjust $params
        $attach->file = $fileName;

        // current page
        $previousTag = $wiki->tag;
        $previousPage = $wiki->page;
        // fake page
        $wiki->tag = $tag;
        $wiki->page = [
            'tag' => $wiki->tag,
            'body' => '{##}',
            'time' => date('YmdHis'),
            'owner' => '',
            'user' => '',
        ];
        $fullFileName = $attach->GetFullFilename($newName);

        // reset params
        unset($attach);
        $wiki->tag = $previousTag;
        $wiki->page = $previousPage;

        return $fullFileName;
    }

    /**
     * sanitize filename
     * @param string $filename
     * @return string $sanitizedFilename
     */
    protected function sanitizeFilename(string $filename): string
    {
        $attach = $this->getAttach();
        // Remove accents and spaces
        $sanitizedFilename = $attach->sanitizeFilename($filename);
        return $sanitizedFilename;
    }

    protected function getBasePath(): string
    {
        $attach = $this->getAttach();
        $basePath = $attach->GetUploadPath();
        return $basePath . (substr($basePath, -1) != "/" ? "/" : "");
    }

    protected function getAttach(): attach
    {
        if (is_null($this->attach)) {
            if (!class_exists('attach')) {
                include('tools/attach/libs/attach.lib.php');
            }

            $wiki = $this->getWiki();

            $this->attach = new attach($wiki);
        }
        return $this->attach;
    }

    protected function updateEntryAfterFileDelete($entry)
    {
        $entryManager = $this->services->get(EntryManager::class);

        // unset value in entry from db without modifier from GET
        $entryFromDb = $entryManager->getOne($entry['id_fiche']);
        if (!empty($entryFromDb)) {
            $previousGet = $_GET;
            $_GET = ['wiki' => $previousGet['wiki']];
            $previousPost = $_POST;
            $_POST= [];
            $previousRequest = $_REQUEST;
            $_REQUEST = [];
            unset($entryFromDb[$this->propertyName]);
            $entryFromDb['antispam'] = 1;
            $entryFromDb['date_maj_fiche'] = date('Y-m-d H:i:s', time());
            $entryManager->update($entryFromDb['id_fiche'], $entryFromDb, false, true);

            $_GET = $previousGet;
            $_POST = $previousPost;
            $_REQUEST = $previousRequest;
        }
    }
}

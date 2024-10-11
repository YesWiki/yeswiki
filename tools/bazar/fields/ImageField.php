<?php

namespace YesWiki\Bazar\Field;

use Psr\Container\ContainerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use YesWiki\Core\Service\AssetsManager;
use YesWiki\Security\Controller\SecurityController;

/**
 * @Field({"image"})
 */
class ImageField extends FileField
{
    protected $thumbnailHeight;
    protected $thumbnailWidth;
    protected $imageHeight;
    protected $imageWidth;
    protected $imageClass;
    protected $imageDefault;

    protected const FIELD_THUMBNAIL_HEIGHT = 3;
    protected const FIELD_THUMBNAIL_WIDTH = 4;
    protected const FIELD_IMAGE_HEIGHT = 5;
    protected const FIELD_IMAGE_WIDTH = 6;
    protected const FIELD_IMAGE_CLASS = 7;
    public const FIELD_IMAGE_DEFAULT = 13;

    public function __construct(array $values, ContainerInterface $services)
    {
        parent::__construct($values, $services);
        $this->readLabel = '';

        $this->thumbnailHeight = $values[self::FIELD_THUMBNAIL_HEIGHT];
        $this->thumbnailWidth = $values[self::FIELD_THUMBNAIL_WIDTH];
        $this->imageHeight = $values[self::FIELD_IMAGE_HEIGHT];
        $this->imageWidth = $values[self::FIELD_IMAGE_WIDTH];
        $this->imageClass = $values[self::FIELD_IMAGE_CLASS];
        $this->imageDefault = $values[self::FIELD_IMAGE_DEFAULT];

        // We can have no default for images
        $this->default = null;
    }

    protected function getDefaultImageName($entry)
    {
        if (!empty($entry)) {
            $id = $entry['id_typeannonce'];
        } else {
            $id = empty($GLOBALS['wiki']->GetParameter('id')) ? $_REQUEST['id'] : $GLOBALS['wiki']->GetParameter('id');
        }
        $default_image_filename = "defaultimage{$id}_{$this->name}.jpg";
        if (file_exists($this->getBasePath() . $default_image_filename)) {
            return $default_image_filename;
        }
        return false;
    }

    protected function renderInput($entry)
    {
        $output = '';
        $wiki = $this->getWiki();
        $value = $this->getValue($entry);
        // javascript pour gerer la previsualisation
        // si une taille maximale est indiquée, on teste
        $wiki->services->get(AssetsManager::class)->AddJavascriptFile('tools/bazar/presentation/javascripts/inputs/image-field.js');
        $imgDefault = $this->getDefaultImageName($entry);
        if (
            !empty($value)
            || (!empty($imgDefault) && file_exists($this->getBasePath() . $imgDefault))
        ) {
            if (isset($_GET['suppr_image']) && $_GET['suppr_image'] === $value) {
                if ($this->securedDeleteImageAndCache($entry, $value)) {
                    $this->updateEntryAfterFileDelete($entry);

                    $output = $this->render('@templates/alert-message.twig', [
                        'type' => 'info',
                        'message' => str_replace('{file}', $value, _t('BAZ_LE_FICHIER_A_ETE_EFFACE')),
                    ]);
                    $value = '';
                } else {
                    $alertMessage = $this->render('@templates/alert-message.twig', [
                        'type' => 'info',
                        'message' => _t('BAZ_DROIT_INSUFFISANT'),
                    ]) . "\n";
                }
            }

            if (
                file_exists($this->getBasePath() . $value)
                || (!empty($imgDefault) && file_exists($this->getBasePath() . $imgDefault))
            ) {
                $img = $value ? $value : $imgDefault;
                return $output . ($alertMessage ?? '') . $this->render('@bazar/inputs/image.twig', [
                    'value' => $img,
                    'downloadUrl' => $this->getBasePath() . $img,
                    'deleteUrl' => empty($entry) ? '' : $wiki->href('edit', $wiki->GetPageTag(), 'suppr_image=' . $img, false),
                    'image' => $this->getWiki()->render('@attach/display-image.twig', [
                        'baseUrl' => $this->getWiki()->GetBaseUrl() . '/',
                        'imageFullPath' => $this->getBasePath() . $img,
                        'fieldName' => $this->name,
                        'thumbnailHeight' => $this->thumbnailHeight,
                        'thumbnailWidth' => $this->thumbnailWidth,
                        'imageHeight' => $this->imageHeight,
                        'imageWidth' => $this->imageWidth,
                        'class' => 'img-responsive',
                        'shortImageName' => $this->getShortFileName($img),
                    ]),
                    'isDefaultImage' => empty($value) && !empty($imgDefault),
                    'isAllowedToDeleteFile' => empty($entry) || empty($value) ? false : $this->isAllowedToDeleteFile($entry, $value),
                ]);
            } else {
                $this->updateEntryAfterFileDelete($entry);

                $alertMessage = $this->render('@templates/alert-message.twig', [
                    'type' => 'danger',
                    'message' => str_replace('{file}', $value, _t('BAZ_FICHIER_IMAGE_INEXISTANT')),
                ]);
            }
        }

        return ($alertMessage ?? '') . $this->render('@bazar/inputs/image.twig', ['maxSize' => $this->maxSize]);
    }

    public function formatValuesBeforeSave($entry)
    {
        $params = $this->getService(ParameterBagInterface::class);
        $value = $this->getValue($entry);
        if (!empty($_FILES[$this->propertyName]['name']) && !empty($entry['id_fiche'])) {
            $rawFileName = filter_var($_FILES[$this->propertyName]['name'], FILTER_UNSAFE_RAW);
            $rawFileName = in_array($rawFileName, [false, null], true) ? '' : htmlspecialchars(strip_tags($rawFileName));
            $sanitizedFilename = $this->sanitizeFilename($rawFileName);
            $fileName = "{$this->getPropertyName()}_$sanitizedFilename";
            $filePath = $this->getFullFileName($fileName, $entry['id_fiche'], true);

            if ($this->isImage($rawFileName) && !$this->getService(SecurityController::class)->isWikiHibernated()) {
                if (!file_exists($filePath)) {
                    if ($_FILES[$this->propertyName]['size'] > $this->maxSize) {
                        throw new \Exception(_t('BAZ_FILEFIELD_TOO_LARGE_FILE', ['fileMaxSize' => $this->maxSize]));
                    }

                    move_uploaded_file($_FILES[$this->propertyName]['tmp_name'], $filePath);
                    chmod($filePath, 0755);

                    if (isset($entry['oldimage_' . $this->propertyName]) && $entry['oldimage_' . $this->propertyName] != '') {
                        // delete previous files only if authorized (owner)
                        $previousFileName = $entry['oldimage_' . $this->propertyName];
                        $this->securedDeleteImageAndCache($entry, $previousFileName);
                    }

                    // Generate thumbnails to speedup loading of bazar templates
                    if (!empty($this->thumbnailWidth) && !empty($this->thumbnailHeight)) {
                        $attach = $this->getAttach();
                        $filePathResized = $attach->getResizedFilename($filePath, $this->thumbnailWidth, $this->thumbnailHeight);
                        if (!file_exists($filePathResized)) {
                            $attach->redimensionner_image($filePath, $filePathResized, $this->thumbnailWidth, $this->thumbnailHeight);
                        }
                    }
                    // Adapt image dimensions
                    if (!empty($this->imageWidth) && !empty($this->imageHeight)) {
                        $attach = $this->getAttach();
                        $filePathResized = $attach->getResizedFilename($filePath, $this->imageWidth, $this->imageHeight);
                        if (!file_exists($filePathResized)) {
                            $attach->redimensionner_image($filePath, $filePathResized, $this->imageWidth, $this->imageHeight);
                        }
                    }
                } else {
                    flash(str_replace('{fileName}', $fileName, _t('BAZ_IMAGE_ALREADY_EXISTING')), 'info');
                }
            } else {
                flash(_t('BAZ_NOT_AUTHORIZED_EXTENSION'), 'error');

                return [$this->propertyName => ''];
            }
            $img = basename($filePath);
            $entry[$this->propertyName] = $img && $img != $this->getDefaultImageName($entry) ? $img : '';
        } elseif (isset($entry['oldimage_' . $this->propertyName]) && $entry['oldimage_' . $this->propertyName] != '' && $entry['oldimage_' . $this->propertyName] != $this->getDefaultImageName($entry)) {
            $entry[$this->propertyName] = $entry['oldimage_' . $this->propertyName];
        } elseif (!empty($value)) {
            $img = $this->getValue($entry);
            $entry[$this->propertyName] = file_exists($this->getBasePath() . $img) && $img != $this->getDefaultImageName($entry) ? $img : '';
        } else {
            $entry[$this->propertyName] = '';
        }
        return [
            $this->propertyName => $this->getValue($entry),
            'fields-to-remove' => ['oldimage_' . $this->propertyName],
        ];
    }

    protected function renderStatic($entry)
    {
        $value = $this->getValue($entry);
        if (!isset($value) || $value == '') {
            $value = $this->getDefaultImageName($entry);
        }
        if (isset($value) && $value != '' && file_exists($this->getBasePath() . $value)) {
            return $this->getWiki()->render('@attach/display-image.twig', [
                'baseUrl' => $this->getWiki()->GetBaseUrl() . '/',
                'imageFullPath' => $this->getBasePath() . $value,
                'fieldName' => $this->name,
                'thumbnailHeight' => $this->thumbnailHeight,
                'thumbnailWidth' => $this->thumbnailWidth,
                'imageHeight' => $this->imageHeight,
                'imageWidth' => $this->imageWidth,
                'class' => $this->imageClass,
                'shortImageName' => $this->getShortFileName($value),
            ]);
        }

        return '';
    }

    protected function isImage($fileName)
    {
        $imageExtPreg = $this->getService(ParameterBagInterface::class)->get('attach_config')['ext_images'];

        return preg_match("/($imageExtPreg)\$/i", $fileName);
    }

    private function securedDeleteImageAndCache($entry, string $filename)
    {
        if ($this->isAllowedToDeleteFile($entry, $filename)) {
            if (substr($filename, 0, strlen($this->defineFilePrefix($entry))) == $this->defineFilePrefix($entry)) {
                $attach = $this->getAttach();
                $attach->fmDelete($filename);
            } else {
                // do not delete file if not same entry name (only remove from this entry)
            }

            return true;
        }

        return false;
    }

    // change return of this method to keep compatible with php 7.3 (mixed is not managed)
    #[\ReturnTypeWillChange]
    public function jsonSerialize()
    {
        $fileFieldData = parent::jsonSerialize();
        unset($fileFieldData['readLabel']);
        $baseUrl = $this->getWiki()->getBaseUrl();

        return array_merge(
            $fileFieldData,
            [
                'thumbnailHeight' => $this->thumbnailHeight,
                'thumbnailWidth' => $this->thumbnailWidth,
                'imageHeight' => $this->imageHeight,
                'imageWidth' => $this->imageWidth,
                'imageClass' => $this->imageClass,
            ]
        );
    }
}

<?php

namespace YesWiki\Bazar\Field;

use Psr\Container\ContainerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use YesWiki\Core\Service\AclService;
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

    protected const FIELD_THUMBNAIL_HEIGHT = 3;
    protected const FIELD_THUMBNAIL_WIDTH = 4;
    protected const FIELD_IMAGE_HEIGHT = 5;
    protected const FIELD_IMAGE_WIDTH = 6;
    protected const FIELD_IMAGE_CLASS = 7;

    public function __construct(array $values, ContainerInterface $services)
    {
        parent::__construct($values, $services);
        $this->readLabel = "";

        $this->thumbnailHeight = $values[self::FIELD_THUMBNAIL_HEIGHT];
        $this->thumbnailWidth = $values[self::FIELD_THUMBNAIL_WIDTH];
        $this->imageHeight = $values[self::FIELD_IMAGE_HEIGHT];
        $this->imageWidth = $values[self::FIELD_IMAGE_WIDTH];
        $this->imageClass = $values[self::FIELD_IMAGE_CLASS];

        // We can have no default for images
        $this->default = null;
    }

    protected function renderInput($entry)
    {
        $wiki = $this->getWiki();
        $value = $this->getValue($entry);
        $maxSize = $wiki->config['BAZ_TAILLE_MAX_FICHIER'] ;

        // javascript pour gerer la previsualisation

        // si une taille maximale est indiquée, on teste
        if (!empty($maxSize)) {
            $wiki->addJavascript("var imageMaxSize = {$maxSize};");
        }
        $wiki->AddJavascriptFile('tools/bazar/presentation/javascripts/image-field.js');

        if (isset($value) && $value != '') {
            if (isset($_GET['suppr_image']) && $_GET['suppr_image'] === $value) {
                if ($this->securedDeleteImageAndCache($entry, $value)) {
                    $this->updateEntryAfterFileDelete($entry);

                    return $this->render('@templates/alert-message.twig', [
                        'type' => 'info',
                        'message' => str_replace('{file}', $value, _t('BAZ_LE_FICHIER_A_ETE_EFFACE'))
                    ])."\n".$this->render('@bazar/inputs/image.twig');
                } else {
                    $alertMessage = $this->render('@templates/alert-message.twig', [
                        'type' => 'info',
                        'message' => _t('BAZ_DROIT_INSUFFISANT')
                    ]). "\n";
                }
            }

            if (file_exists($this->getBasePath(). $value)) {
                return ($alertMessage ?? '') .$this->render('@bazar/inputs/image.twig', [
                    'value' => $value,
                    'downloadUrl' => $this->getBasePath(). $value,
                    'deleteUrl' => empty($entry) ? '' :$wiki->href('edit', $wiki->GetPageTag(), 'suppr_image=' . $value, false),
                    'image' => $this->getWiki()->render('@attach/display-image.twig', [
                        'baseUrl' => $this->getWiki()->GetBaseUrl().'/',
                        'imageFullPath' => $this->getBasePath(). $value,
                        'fieldName' => $this->name,
                        'thumbnailHeight' => $this->thumbnailHeight,
                        'thumbnailWidth' => $this->thumbnailWidth,
                        'imageHeight' => $this->imageHeight,
                        'imageWidth' => $this->imageWidth,
                        'class' => 'img-responsive',
                        'shortImageName' => $this->getShortFileName($value)
                    ]),
                    'isAllowedToDeleteFile' => empty($entry) ? false :$this->isAllowedToDeleteFile($entry, $value),
                ]);
            } else {
                $this->updateEntryAfterFileDelete($entry);

                $alertMessage = $this->render('@templates/alert-message.twig', [
                    'type' => 'danger',
                    'message' => str_replace('{file}', $value, _t('BAZ_FICHIER_IMAGE_INEXISTANT'))
                ]);
            }
        }
        return ($alertMessage ?? '') .$this->render('@bazar/inputs/image.twig');
    }

    public function formatValuesBeforeSave($entry)
    {
        if (!empty($_POST['data-'.$this->propertyName]) && !empty($_POST['filename-'.$this->propertyName]) && !empty($entry['id_fiche'])) {
            $rawFileName = filter_var($_POST['filename-'.$this->propertyName], FILTER_SANITIZE_STRING);
            $fileName = "{$this->getPropertyName()}_$rawFileName";
            $filePath = $this->getFullFileName($fileName, $entry['id_fiche'], true);
            $fileName = basename($filePath);

            if ($this->isImage($rawFileName)) {
                if (!file_exists($filePath) && !$this->getService(SecurityController::class)->isWikiHibernated()) {
                    file_put_contents($filePath, file_get_contents($_POST['data-'.$this->propertyName]));
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
            }
            $entry[$this->propertyName] = $fileName;
        } elseif (isset($entry['oldimage_' . $this->propertyName]) && $entry['oldimage_' . $this->propertyName] != '') {
            $entry[$this->propertyName] = $entry['oldimage_' . $this->propertyName];
        }
        return [
            $this->propertyName => $this->getValue($entry),
            'fields-to-remove' => ['filename-'.$this->propertyName, 'data-'.$this->propertyName, 'oldimage_' . $this->propertyName]
        ];
    }

    protected function renderStatic($entry)
    {
        $value = $this->getValue($entry);

        if (isset($value) && $value != '' && file_exists($this->getBasePath(). $value)) {
            return $this->getWiki()->render('@attach/display-image.twig', [
                'baseUrl' => $this->getWiki()->GetBaseUrl().'/',
                'imageFullPath' => $this->getBasePath(). $value,
                'fieldName' => $this->name,
                'thumbnailHeight' => $this->thumbnailHeight,
                'thumbnailWidth' => $this->thumbnailWidth,
                'imageHeight' => $this->imageHeight,
                'imageWidth' => $this->imageWidth,
                'class' => $this->imageClass,
                'shortImageName' => $this->getShortFileName($value)
               ]);
        }

        return null;
    }

    protected function isImage($fileName)
    {
        $imageExtPreg = $this->getService(ParameterBagInterface::class)->get("attach_config")["ext_images"];
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

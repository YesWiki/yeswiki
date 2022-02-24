<?php

namespace YesWiki\Bazar\Field;

use Psr\Container\ContainerInterface;
use YesWiki\Bazar\Service\EntryManager;
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
            if (isset($_GET['suppr_image']) && $_GET['suppr_image'] == $value) {
                if ($this->isAllowedToDeleteFile($entry)) {
                    if (file_exists(BAZ_CHEMIN_UPLOAD . $value)) {
                        unlink(BAZ_CHEMIN_UPLOAD . $value);
                    }
                    if (file_exists('cache/vignette_' . $value)) {
                        unlink('cache/vignette_' . $value);
                    }
                    if (file_exists('cache/image_' . $value)) {
                        unlink('cache/image_' . $value);
                    }

                    $this->updateEntryAfterImageDelete($entry);

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

            if (file_exists(BAZ_CHEMIN_UPLOAD . $value)) {
                return ($alertMessage ?? '') .$this->render('@bazar/inputs/image.twig', [
                    'value' => $value,
                    'downloadUrl' => BAZ_CHEMIN_UPLOAD . $value,
                    'deleteUrl' => $wiki->href('edit', $wiki->GetPageTag(), 'suppr_image=' . $value, false),
                    'image' => afficher_image(
                        $this->name,
                        $value,
                        $this->label,
                        'img-responsive',
                        $this->thumbnailWidth,
                        $this->thumbnailHeight,
                        $this->imageWidth,
                        $this->imageHeight
                    ),
                    'isAllowedToDeleteFile' => $this->isAllowedToDeleteFile($entry),
                ]);
            } else {
                $this->updateEntryAfterImageDelete($entry);

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
        if (!empty($_POST['data-'.$this->propertyName]) and !empty($_POST['filename-'.$this->propertyName])) {
            $fileName = $entry['id_fiche'] . '_' . sanitizeFilename($_POST['filename-'.$this->propertyName]);
            $filePath = BAZ_CHEMIN_UPLOAD . $fileName;

            if (preg_match("/(gif|jpeg|png|jpg)$/i", $fileName)) {
                if (!file_exists($filePath) && !$this->getService(SecurityController::class)->isWikiHibernated()) {
                    file_put_contents($filePath, file_get_contents($_POST['data-'.$this->propertyName]));
                    chmod($filePath, 0755);

                    if (isset($entry['oldimage_' . $this->propertyName]) && $entry['oldimage_' . $this->propertyName] != '') {
                        // delete previous files only if authorized (owner)
                        if ($this->getService(AclService::class)->check('%', null, true, $entry['id_fiche'])) {
                            $previousFileName = $entry['oldimage_' . $this->propertyName];
                            if (file_exists(BAZ_CHEMIN_UPLOAD . $previousFileName)) {
                                unlink(BAZ_CHEMIN_UPLOAD . $previousFileName);
                            }
                            if (file_exists('cache/vignette_' . $previousFileName)) {
                                unlink('cache/vignette_' . $previousFileName);
                            }
                            if (file_exists('cache/image_' . $previousFileName)) {
                                unlink('cache/image_' . $previousFileName);
                            }
                        }
                    }

                    // Generate thumbnails
                    if ($this->thumbnailWidth != '' && $this->thumbnailHeight != '' && !file_exists('cache/vignette_' . $fileName)) {
                        redimensionner_image($filePath, 'cache/vignette_' . $fileName, $this->thumbnailWidth, $this->thumbnailHeight);
                    }

                    // Adapt image dimensions
                    if ($this->imageWidth != '' && $this->imageWidth != '' && !file_exists('cache/image_' . '_' . $fileName)) {
                        redimensionner_image($filePath, 'cache/image_' . $fileName, $this->imageWidth, $this->imageHeight);
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

        if (isset($value) && $value != '' && file_exists(BAZ_CHEMIN_UPLOAD . $value)) {
            return afficher_image(
                $this->name,
                $value,
                $this->label,
                $this->imageClass,
                $this->thumbnailWidth,
                $this->thumbnailHeight,
                $this->imageWidth,
                $this->imageHeight
            );
        }

        return null;
    }

    private function updateEntryAfterImageDelete($entry)
    {
        $entryManager = $this->services->get(EntryManager::class);

        unset($entry[$this->propertyName]);
        $entry['antispam'] = 1;
        $entry['date_maj_fiche'] = date('Y-m-d H:i:s', time());

        $entryManager->update($entry['id_fiche'], $entry, false, true);
    }
}

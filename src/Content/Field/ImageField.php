<?php

namespace YesWiki\Content\Field;

use Psr\Container\ContainerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Tamtamchik\SimpleFlash\Flash;
use YesWiki\Kernel\Service\AssetsManager;
use YesWiki\Kernel\Service\HibernationService;
use YesWiki\Kernel\Service\UrlFormatter;
use YesWiki\Render\Service\TemplateEngine;

#[\Field(['image'])]
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
            $id = $entry['form_id'];
        } else {
            $id = $_SESSION['current_form_id'] ?? 'no_id';
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
        $isUrl = $this->isUrl($value);
        // javascript pour gerer la previsualisation
        // si une taille maximale est indiquée, on teste
        $wiki->services->get(AssetsManager::class)->AddJavascriptFile('javascripts/inputs/image-field.js');
        $imgDefault = $this->getDefaultImageName($entry);

        // Handle URL value
        if ($isUrl) {
            // Handle URL deletion
            if ($this->getRequest()->query->has('suppr_image') && urldecode($this->getRequest()->query->get('suppr_image')) === $value) {
                if ($this->isAllowedToDeleteFile($entry, $value)) {
                    $this->updateEntryAfterFileDelete($entry);
                    $output = $this->render('@core/alert-message.twig', [
                        'type' => 'info',
                        'message' => str_replace('{file}', $value, _t('BAZ_LE_FICHIER_A_ETE_EFFACE')),
                    ]);

                    // Return empty input after deletion
                    return $output . $this->render('@core/inputs/image.twig', ['maxSize' => $this->maxSize, 'isUrl' => false]);
                }
                $output = $this->render('@core/alert-message.twig', [
                    'type' => 'info',
                    'message' => _t('BAZ_DROIT_INSUFFISANT'),
                ]) . "\n";
            }

            return $output . $this->render('@core/inputs/image.twig', [
                'value' => $value,
                'isUrl' => true,
                'downloadUrl' => $value,
                'deleteUrl' => empty($entry) ? '' : $this->getService(UrlFormatter::class)->href('edit', $wiki->services->get(\YesWiki\Kernel\Service\PageContext::class)->getTag(), 'suppr_image=' . urlencode($value), false),
                'image' => '<img src="' . htmlspecialchars($value) . '" class="img-responsive" alt="" />',
                'isDefaultImage' => false,
                'isAllowedToDeleteFile' => empty($entry) ? false : $this->isAllowedToDeleteFile($entry, $value),
                'maxSize' => $this->maxSize,
            ]);
        }

        if (
            !empty($value)
            || (!empty($imgDefault) && file_exists($this->getBasePath() . $imgDefault))
        ) {
            if ($this->getRequest()->query->has('suppr_image') && $this->getRequest()->query->get('suppr_image') === $value) {
                if ($this->securedDeleteImageAndCache($entry, $value)) {
                    $this->updateEntryAfterFileDelete($entry);

                    $output = $this->render('@core/alert-message.twig', [
                        'type' => 'info',
                        'message' => str_replace('{file}', $value, _t('BAZ_LE_FICHIER_A_ETE_EFFACE')),
                    ]);
                    $value = '';
                } else {
                    $alertMessage = $this->render('@core/alert-message.twig', [
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

                return $output . ($alertMessage ?? '') . $this->render('@core/inputs/image.twig', [
                    'value' => $img,
                    'isUrl' => false,
                    'downloadUrl' => $this->getBasePath() . $img,
                    'deleteUrl' => empty($entry) ? '' : $this->getService(UrlFormatter::class)->href('edit', $wiki->services->get(\YesWiki\Kernel\Service\PageContext::class)->getTag(), 'suppr_image=' . $img, false),
                    'image' => $this->getService(TemplateEngine::class)->renderSafely('@core/display-image.twig', [
                        'baseUrl' => $this->getService(UrlFormatter::class)->getBaseUrl() . '/',
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
                    'maxSize' => $this->maxSize,
                ]);
            }
            $this->updateEntryAfterFileDelete($entry);

            $alertMessage = $this->render('@core/alert-message.twig', [
                'type' => 'danger',
                'message' => str_replace('{file}', $value, _t('BAZ_FICHIER_IMAGE_INEXISTANT')),
            ]);
        }

        return ($alertMessage ?? '') . $this->render('@core/inputs/image.twig', ['maxSize' => $this->maxSize, 'isUrl' => false]);
    }

    /*
    *	indicates if tag must be set before to format the value
    */

    public function requireIDFiche()
    {
        return true;
    }

    public function formatValuesBeforeSave($entry)
    {
        $params = $this->getService(ParameterBagInterface::class);
        $value = $this->getValue($entry);

        // Check if a URL was submitted
        $urlPropertyName = $this->propertyName . '_url';
        $urlValue = $entry[$urlPropertyName] ?? null;
        if (!empty($urlValue) && $this->isUrl($urlValue)) {
            return [
                $this->propertyName => $urlValue,
                'fields-to-remove' => [$urlPropertyName, 'oldimage_' . $this->propertyName],
            ];
        }

        // Check if the current value is a URL (keep it if no new file uploaded)
        if ($this->isUrl($value) && empty($_FILES[$this->propertyName]['name'])) {
            return [
                $this->propertyName => $value,
                'fields-to-remove' => ['oldimage_' . $this->propertyName],
            ];
        }

        if (!empty($_FILES[$this->propertyName]['name']) && !empty($entry['tag'])) {
            $rawFileName = filter_var($_FILES[$this->propertyName]['name'], FILTER_UNSAFE_RAW);
            $rawFileName = in_array($rawFileName, [false, null], true) ? '' : htmlspecialchars(strip_tags($rawFileName));
            $sanitizedFilename = $this->sanitizeFilename($rawFileName);
            $fileName = "{$this->getPropertyName()}_$sanitizedFilename";
            $filePath = $this->getFullFileName($fileName, $entry['tag'], true);

            if ($this->isImage($rawFileName) && !$this->getService(HibernationService::class)->isWikiHibernated()) {
                if (!file_exists($filePath)) {
                    if ($_FILES[$this->propertyName]['size'] > $this->maxSize) {
                        throw new \Exception(_t('BAZ_FILEFIELD_TOO_LARGE_FILE', ['fileMaxSize' => $this->maxSize]));
                    }

                    move_uploaded_file($_FILES[$this->propertyName]['tmp_name'], $filePath);
                    chmod($filePath, 0755);

                    if (isset($entry['oldimage_' . $this->propertyName]) && $entry['oldimage_' . $this->propertyName] != '' && !$this->isUrl($entry['oldimage_' . $this->propertyName])) {
                        // delete previous files only if authorized (owner) and not a URL
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
                    Flash::info(str_replace('{fileName}', $fileName, _t('BAZ_IMAGE_ALREADY_EXISTING')));
                }
            } else {
                Flash::error(_t('BAZ_NOT_AUTHORIZED_EXTENSION'));

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

        // Handle URL value - display image directly without resize
        if ($this->isUrl($value)) {
            return '<img src="' . htmlspecialchars($value) . '" class="' . htmlspecialchars($this->imageClass ?? '') . '" alt="" loading="lazy" />';
        }

        if (isset($value) && $value != '' && file_exists($this->getBasePath() . $value)) {
            return $this->getService(TemplateEngine::class)->renderSafely('@core/display-image.twig', [
                'baseUrl' => $this->getService(UrlFormatter::class)->getBaseUrl() . '/',
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
            }
            // do not delete file if not same entry name (only remove from this entry)

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
        $baseUrl = $this->getService(UrlFormatter::class)->getBaseUrl();

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

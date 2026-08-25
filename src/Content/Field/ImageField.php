<?php

namespace YesWiki\Content\Field;

use Psr\Container\ContainerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Tamtamchik\SimpleFlash\Flash;
use YesWiki\Content\Attribute\Field;
use YesWiki\Files\Service\FileBrowser;
use YesWiki\Files\Service\ImageResizer;
use YesWiki\Kernel\Service\AssetRegistry;
use YesWiki\Kernel\Service\HibernationService;
use YesWiki\Kernel\Service\UrlFormatter;
use YesWiki\Render\Service\TemplateEngine;

#[Field(['image'])]
class ImageField extends FileField
{
    /** @var int|string|null */
    protected $thumbnailHeight;
    /** @var int|string|null */
    protected $thumbnailWidth;
    /** @var int|string|null */
    protected $imageHeight;
    /** @var int|string|null */
    protected $imageWidth;
    /** @var string|null */
    protected $imageClass;
    /** @var string|null */
    protected $imageDefault;

    protected const FIELD_THUMBNAIL_HEIGHT = 3;
    protected const FIELD_THUMBNAIL_WIDTH = 4;
    protected const FIELD_IMAGE_HEIGHT = 5;
    protected const FIELD_IMAGE_WIDTH = 6;
    protected const FIELD_IMAGE_CLASS = 7;
    public const FIELD_IMAGE_DEFAULT = 13;

    /**
     * @param array<int|string, mixed> $values
     */
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

        $this->default = null;
    }

    /**
     * @param array<string, mixed>|null $entry
     *
     * @return string|false the shared default image for this field, or false when the wiki has none
     */
    protected function getDefaultImageName($entry)
    {
        $id = $entry['form_id'] ?? $_SESSION['current_form_id'] ?? 'no_id';
        $default_image_filename = "defaultimage{$id}_{$this->name}.jpg";
        if ($this->storage()->exists($this->getBasePath() . $default_image_filename)) {
            return $default_image_filename;
        }

        return false;
    }

    protected function renderInput($entry)
    {
        $output = '';
        $value = $this->getValue($entry);
        $isUrl = $this->isUrl($value);

        $this->getService(AssetRegistry::class)->addJsFile('javascripts/inputs/image-field.js');
        $imgDefault = $this->getDefaultImageName($entry);

        $supprImage = $this->getRequest()->query->get('suppr_image');

        if ($isUrl) {
            if ($supprImage !== null && urldecode($supprImage) === $value) {
                if ($this->isAllowedToDeleteFile($entry ?? [], $value)) {
                    $this->updateEntryAfterFileDelete($entry);
                    $output = $this->render('@core/alert-message.twig', [
                        'type' => 'info',
                        'message' => str_replace('{file}', $value, _t('BAZ_LE_FICHIER_A_ETE_EFFACE')),
                    ]);

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
                'deleteUrl' => empty($entry) ? '' : $this->getService(UrlFormatter::class)->href('edit', $this->getService(\YesWiki\Kernel\Service\PageContext::class)->getTag(), 'suppr_image=' . urlencode($value), false),
                'image' => '<img src="' . htmlspecialchars($value) . '" class="img-responsive" alt="" />',
                'isDefaultImage' => false,
                'isAllowedToDeleteFile' => empty($entry) ? false : $this->isAllowedToDeleteFile($entry, $value),
                'maxSize' => $this->maxSize,
            ]);
        }

        if (
            !empty($value)
            || (!empty($imgDefault) && $this->storage()->exists($this->getBasePath() . $imgDefault))
        ) {
            if ($supprImage !== null && $supprImage === $value) {
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
                $this->storage()->exists($this->getBasePath() . $value)
                || (!empty($imgDefault) && $this->storage()->exists($this->getBasePath() . $imgDefault))
            ) {
                $img = $value ? $value : $imgDefault;

                return $output . ($alertMessage ?? '') . $this->render('@core/inputs/image.twig', [
                    'value' => $img,
                    'isUrl' => false,
                    'downloadUrl' => $this->getBasePath() . $img,
                    'deleteUrl' => empty($entry) ? '' : $this->getService(UrlFormatter::class)->href('edit', $this->getService(\YesWiki\Kernel\Service\PageContext::class)->getTag(), 'suppr_image=' . $img, false),
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

    public function requiresTagBeforeFormatting()
    {
        return true;
    }

    public function formatValuesBeforeSave($entry)
    {
        $params = $this->getService(ParameterBagInterface::class);
        $value = $this->getValue($entry);

        $urlPropertyName = $this->propertyName . '_url';
        $urlValue = $entry[$urlPropertyName] ?? null;
        if (!empty($urlValue) && $this->isUrl($urlValue)) {
            return [
                $this->propertyName => $urlValue,
                'fields-to-remove' => [$urlPropertyName, 'oldimage_' . $this->propertyName],
            ];
        }

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
                if (!$this->storage()->exists($filePath)) {
                    if ($_FILES[$this->propertyName]['size'] > $this->maxSize) {
                        throw new \Exception(_t('BAZ_FILEFIELD_TOO_LARGE_FILE', ['fileMaxSize' => $this->maxSize]));
                    }

                    if (!is_uploaded_file($_FILES[$this->propertyName]['tmp_name'])) {
                        throw new \Exception(_t('ERROR_NO_FILE_UPLOADED'));
                    }
                    $this->storage()->writeFrom($filePath, $_FILES[$this->propertyName]['tmp_name']);

                    if (isset($entry['oldimage_' . $this->propertyName]) && $entry['oldimage_' . $this->propertyName] != '' && !$this->isUrl($entry['oldimage_' . $this->propertyName])) {
                        $previousFileName = $entry['oldimage_' . $this->propertyName];
                        $this->securedDeleteImageAndCache($entry, $previousFileName);
                    }

                    if (!empty($this->thumbnailWidth) && !empty($this->thumbnailHeight)) {
                        $resizer = $this->getService(ImageResizer::class);
                        $filePathResized = $resizer->resizedFilename($filePath, (string)$this->thumbnailWidth, (string)$this->thumbnailHeight);
                        if (!$this->storage()->exists($filePathResized)) {
                            $resizer->resize($filePath, $filePathResized, $this->thumbnailWidth, $this->thumbnailHeight);
                        }
                    }

                    if (!empty($this->imageWidth) && !empty($this->imageHeight)) {
                        $resizer = $this->getService(ImageResizer::class);
                        $filePathResized = $resizer->resizedFilename($filePath, (string)$this->imageWidth, (string)$this->imageHeight);
                        if (!$this->storage()->exists($filePathResized)) {
                            $resizer->resize($filePath, $filePathResized, $this->imageWidth, $this->imageHeight);
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
            $entry[$this->propertyName] = $this->storage()->exists($this->getBasePath() . $img) && $img != $this->getDefaultImageName($entry) ? $img : '';
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

        if ($this->isUrl($value)) {
            $sized = $this->ownFileAtFieldSize($value);

            return '<img src="' . htmlspecialchars($sized) . '" class="' . htmlspecialchars($this->imageClass ?? '') . '" alt="" loading="lazy" />';
        }

        if ($value != '' && $this->storage()->exists($this->getBasePath() . $value)) {
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

    /** A file of this wiki's own, asked for at this field's configured size. */
    private function ownFileAtFieldSize(string $url): string
    {
        $width = $this->imageWidth ?: $this->thumbnailWidth;
        $height = $this->imageHeight ?: $this->thumbnailHeight;
        if (empty($width) || empty($height)) {
            return $url;
        }

        $base = $this->getService(UrlFormatter::class)->getBaseUrl();
        if (!str_starts_with($url, $base) || !preg_match('#api/files/[^/?&]+/download#', $url)) {
            return $url;
        }

        return $url . (str_contains($url, '?') ? '&' : '?') . http_build_query([
            'width' => $width,
            'height' => $height,
        ]);
    }

    /**
     * @param string $fileName
     *
     * @return bool
     */
    protected function isImage($fileName)
    {
        $imageExtPreg = $this->getService(ParameterBagInterface::class)->get('attach_config')['ext_images'];

        return (bool)preg_match("/($imageExtPreg)\$/i", $fileName);
    }

    /**
     * @param array<string, mixed>|null $entry
     *
     * @return bool
     */
    private function securedDeleteImageAndCache($entry, string $filename)
    {
        $entry ??= [];

        if ($this->isAllowedToDeleteFile($entry, $filename)) {
            if (substr($filename, 0, strlen($this->defineFilePrefix($entry))) == $this->defineFilePrefix($entry)) {
                $this->getService(FileBrowser::class)->moveToTrash($filename);
            }

            return true;
        }

        return false;
    }

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

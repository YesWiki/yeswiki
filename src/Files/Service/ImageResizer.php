<?php

namespace YesWiki\Files\Service;

use stefangabos\Zebra_Image\Zebra_Image;
use YesWiki\Identity\Service\AclService;
use YesWiki\Kernel\Service\HibernationService;

/**
 * Resized copies of an image, cached under the cache directory (ticket 24, extracted from `Attach`).
 */
class ImageResizer
{
    /** The format every resized copy is written in, whatever the original was. */
    public const FORMAT = 'webp';

    private AttachedFilePaths $paths;
    private Storage $storage;
    private HibernationService $hibernation;
    private AclService $acl;

    public function __construct(AttachedFilePaths $paths, Storage $storage, HibernationService $hibernation, AclService $acl)
    {
        $this->paths = $paths;
        $this->storage = $storage;
        $this->hibernation = $hibernation;
        $this->acl = $acl;
    }

    /** The resized copy of $source, made once and reused after that. */
    public function cached(string $source, string $width, string $height, string $method = 'fit'): string
    {
        if (!$this->storage->exists($source)) {
            return '';
        }

        $target = $this->resizedFilename($source, $width, $height, $method);

        if (
            !$this->hibernation->isWikiHibernated()
            && $this->storage->exists($target)
            && isset($_GET['refresh'])
            && $_GET['refresh'] == 1
            && $this->acl->isAdmin()
        ) {
            $this->storage->delete($target);
        }

        if (!$this->storage->exists($target)) {
            return $this->resize($source, $target, $width, $height, $method) === $target ? $target : $source;
        }

        return $target;
    }

    /** Where the resized copy of $fullFilename at these dimensions lives, whether or not it exists yet. */
    public function resizedFilename(string $fullFilename, string $width, string $height, string $mode = 'fit'): string
    {
        $uploadPath = $this->paths->uploadPath();
        $cachePath = $this->paths->cachePath();
        $newFileName = (string)preg_replace("/^$uploadPath/", "$cachePath", $fullFilename);
        $newFileName = $this->thumbnailFilename($newFileName, $width, $height);

        return $mode === 'crop'
            ? (string)preg_replace('/_vignette_/', '_cropped_', $newFileName)
            : $newFileName;
    }

    /**
     * @return string|int|false the written path, Zebra_Image's error code, or false
     */
    public function resize(string $source, string $destination, mixed $width, mixed $height, string $mode = 'fit')
    {
        if (empty($source) || empty($destination)) {
            return false;
        }
        $sourceSize = $mode === 'crop' ? $this->storage->imageSize($source) : false;

        return $this->storage->withLocalCopy(
            $source,
            fn (string $local) => $this->storage->withLocalTarget(
                $destination,
                fn (string $target) => $this->resizeLeased($local, $target, $destination, $width, $height, $mode, $sourceSize)
            )
        );
    }

    /**
     * Zebra_Image cannot be handed anything but a real path, so this is what a lease exists for.
     *
     * @return string|int|false
     */
    private function resizeLeased(string $source, string $target, string $destination, mixed $width, mixed $height, string $mode, mixed $sourceSize)
    {
        $imgTrans = new Zebra_Image();
        $imgTrans->auto_handle_exif_orientation = true;
        $imgTrans->preserve_aspect_ratio = true;
        $imgTrans->enlarge_smaller_images = true;
        $imgTrans->preserve_time = true;
        $imgTrans->source_path = $source;
        $imgTrans->target_path = $target;

        $previousErrorReporting = error_reporting();
        error_reporting($previousErrorReporting & ~E_DEPRECATED);

        try {
            if ($mode !== 'crop') {
                $result = $imgTrans->resize(intval($width), intval($height), ZEBRA_IMAGE_NOT_BOXED, -1);

                return $result ? $destination : $imgTrans->error;
            }

            return $this->storage->withTemporaryFile(
                (string)(pathinfo($source)['extension'] ?? ''),
                function (string $cropped) use ($imgTrans, $target, $destination, $width, $height, $sourceSize) {
                    if (!$this->cropToRatio($imgTrans, $sourceSize, $cropped, $target, $width, $height)) {
                        return false;
                    }
                    $result = $imgTrans->resize(intval($width), intval($height), ZEBRA_IMAGE_NOT_BOXED, -1);

                    return $result ? $destination : $imgTrans->error;
                }
            );
        } finally {
            error_reporting($previousErrorReporting);
        }
    }

    /** Crop to the wanted aspect ratio before the resize, via a temporary file, so the final resize never distorts. */
    private function cropToRatio(Zebra_Image $imgTrans, mixed $sourceSize, string $cropped, string $target, mixed $width, mixed $height): bool
    {
        if (!is_array($sourceSize)) {
            return false;
        }
        list($sourceWidth, $sourceHeight) = $sourceSize;
        if ($sourceHeight == 0) {
            return false;
        }

        $wantedRatio = $width / $height;
        $imageRatio = $sourceWidth / $sourceHeight;
        if ($imageRatio == $wantedRatio) {
            return true;
        }

        if ($imageRatio > $wantedRatio) {
            $newWidth = round($sourceHeight * $wantedRatio);
            $newHeight = $sourceHeight;
        } else {
            $newHeight = round($sourceWidth / $wantedRatio);
            $newWidth = $sourceWidth;
        }

        $imgTrans->target_path = $cropped;
        if ($imgTrans->resize(intval($newWidth), intval($newHeight), ZEBRA_IMAGE_CROP_CENTER, -1)) {
            $imgTrans->source_path = $cropped;
        }
        $imgTrans->target_path = $target;

        return true;
    }

    /**
     * `name_vignette_<w>_<h>_<page revision>_<upload date>.ext`, keeping the encoded dates so a new revision of the original does not collide with the old thumbnail.
     */
    private function thumbnailFilename(string $fullFilename, string $width, string $height): string
    {
        $file = $this->paths->decodeLongFilename($fullFilename);
        if (empty($file['name'])) {
            $pathInfo = pathinfo($fullFilename);

            return "{$file['path']}/{$pathInfo['filename']}_vignette_{$width}_{$height}." . self::FORMAT;
        }

        $prefix = '';
        if ($this->paths->isSafeMode()) {
            $currentTag = (string)$this->paths->currentPageTag();
            $prefix = substr($file['realname'], 0, strlen($currentTag)) === $currentTag ? $currentTag . '_' : '';
        }

        return $file['path'] . '/' . $prefix . $file['name']
            . '_vignette_' . $width . '_' . $height . '_' . $file['datepage'] . '_' . $file['dateupload']
            . '.' . self::FORMAT;
    }
}

<?php

namespace YesWiki\Files\Service;

use stefangabos\Zebra_Image\Zebra_Image;

/**
 * Resized copies of an image, cached under the cache directory (ticket 24, extracted
 * from `Attach`).
 *
 * Two things, deliberately kept together: the *name* a resized copy gets, and the
 * resizing itself. Callers rely on the pair -- "does the resized file already exist, and
 * if not, make it" is the only way this is ever used:
 *
 *     if (!file_exists($dest = $resizer->resizedFilename($src, $w, $h))) {
 *         $resizer->resize($src, $dest, $w, $h);
 *     }
 *
 * The name is derived, not stored, so a cache file orphaned by a re-upload is found again
 * by `FileBrowser` when the original is deleted.
 */
class ImageResizer
{
    private AttachedFilePaths $paths;

    public function __construct(AttachedFilePaths $paths)
    {
        $this->paths = $paths;
    }

    /**
     * Where the resized copy of $fullFilename at these dimensions lives, whether or not
     * it exists yet. The upload path is swapped for the cache path so the copy never
     * lands beside the original.
     *
     * $width/$height are interpolated into the name, so callers may pass a glob pattern
     * (FileBrowser does, to sweep up every size at once).
     */
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
        $imgTrans = new Zebra_Image();
        $imgTrans->auto_handle_exif_orientation = true;
        $imgTrans->preserve_aspect_ratio = true;
        $imgTrans->enlarge_smaller_images = true;
        $imgTrans->preserve_time = true;
        $imgTrans->source_path = $source;
        $imgTrans->target_path = $destination;

        // Zebra_Image still calls imagedestroy(), a no-op deprecated since PHP 8.5.
        // Silence it here (rather than patching vendor/) so debug-mode deprecation
        // output isn't echoed into an in-progress image response.
        $previousErrorReporting = error_reporting();
        error_reporting($previousErrorReporting & ~E_DEPRECATED);
        $tempFileName = null;
        try {
            if ($mode === 'crop') {
                $tempFileName = $this->cropToRatio($imgTrans, $source, $destination, $width, $height);
                if ($tempFileName === false) {
                    return false;
                }
            }
            $result = $imgTrans->resize(intval($width), intval($height), ZEBRA_IMAGE_NOT_BOXED, -1);
        } finally {
            error_reporting($previousErrorReporting);
        }

        if ($tempFileName !== null && file_exists($tempFileName)) {
            unlink($tempFileName);
        }

        return $result ? $imgTrans->target_path : $imgTrans->error;
    }

    /**
     * Crop to the wanted aspect ratio before the resize, via a temp file, so the final
     * resize never distorts. Returns the temp path to clean up (or null when the image
     * already has the wanted ratio, or false when its size could not be read).
     *
     * The version_compare() guard this carried -- getimagesize() could not read webp on
     * PHP 7.0.x -- is gone: composer.json requires PHP ^8.3, so it was unreachable.
     *
     * @return string|false|null
     */
    private function cropToRatio(Zebra_Image $imgTrans, string $source, string $destination, mixed $width, mixed $height)
    {
        $size = @getimagesize($imgTrans->source_path);
        if ($size === false) {
            return false;
        }
        list($sourceWidth, $sourceHeight) = $size;
        if ($sourceHeight == 0) {
            return false;
        }

        $wantedRatio = $width / $height;
        $imageRatio = $sourceWidth / $sourceHeight;
        if ($imageRatio == $wantedRatio) {
            return null;
        }

        if ($imageRatio > $wantedRatio) {
            // too wide, keep the height
            $newWidth = round($sourceHeight * $wantedRatio);
            $newHeight = $sourceHeight;
        } else {
            // too tall, keep the width
            $newHeight = round($sourceWidth / $wantedRatio);
            $newWidth = $sourceWidth;
        }

        $ext = pathinfo($source)['extension'] ?? '';
        do {
            $tempFile = tmpfile();
            $uri = (string)(stream_get_meta_data($tempFile)['uri'] ?? '');
            $tempFileName = $uri . ".$ext";
            unlink($uri);
        } while (file_exists($tempFileName));

        $imgTrans->target_path = $tempFileName;
        if ($imgTrans->resize(intval($newWidth), intval($newHeight), ZEBRA_IMAGE_CROP_CENTER, -1)) {
            $imgTrans->source_path = $tempFileName;
        }
        $imgTrans->target_path = $destination;

        return $tempFileName;
    }

    /**
     * `name_vignette_<w>_<h>_<page revision>_<upload date>.ext`, keeping the encoded
     * dates so a new revision of the original does not collide with the old thumbnail.
     * Falls back to plain `name_vignette_<w>_<h>.ext` for a file that is not in the
     * encoded form at all.
     */
    private function thumbnailFilename(string $fullFilename, string $width, string $height): string
    {
        $file = $this->paths->decodeLongFilename($fullFilename);
        if (empty($file['name'])) {
            $pathInfo = pathinfo($fullFilename);

            return "{$file['path']}/{$pathInfo['filename']}_vignette_{$width}_{$height}"
                . (isset($pathInfo['extension']) ? ".{$pathInfo['extension']}" : '');
        }

        $prefix = '';
        if ($this->paths->isSafeMode()) {
            $currentTag = (string)$this->paths->currentPageTag();
            $prefix = substr($file['realname'], 0, strlen($currentTag)) === $currentTag ? $currentTag . '_' : '';
        }

        return $file['path'] . '/' . $prefix . $file['name']
            . '_vignette_' . $width . '_' . $height . '_' . $file['datepage'] . '_' . $file['dateupload']
            . '.' . $file['ext'];
    }
}

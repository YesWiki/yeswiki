<?php

namespace YesWiki\Files\Service;

use stefangabos\Zebra_Image\Zebra_Image;

/** A stored picture rewritten as WebP and brought down to the upload bounds, as an upload would have been. */
class ImageShrinker
{
    public const CONVERTIBLE = [IMAGETYPE_JPEG, IMAGETYPE_PNG];

    public const CACHE_ENV = 'YESWIKI_WEBP_CACHE';

    public function __construct(
        private readonly Storage $storage,
        private readonly LocalFiles $localFiles,
    ) {
    }

    /** Whether the picture at $path is a JPEG or PNG, the formats rewritten as WebP; GIF keeps its animation, SVG its vectors. */
    public function isConvertible(string $path): bool
    {
        $size = $this->storage->imageSize($path);

        return is_array($size) && in_array($size[2], self::CONVERTIBLE, true);
    }

    /** Writes $source as WebP at $destination, fitted inside the bounds and never enlarged; true when the file is there, false when GD cannot read it. */
    public function shrink(string $source, string $destination, int $maxWidth, int $maxHeight, int $quality): bool
    {
        $written = $this->storage->withLocalCopy(
            $source,
            fn (string $local) => $this->storage->withLocalTarget(
                $destination,
                function (string $target) use ($local, $maxWidth, $maxHeight, $quality): bool {
                    $cached = $this->cachedPath($local, $maxWidth, $maxHeight, $quality);
                    if ($cached !== null && $this->localFiles->isFile($cached) && $this->localFiles->write($target, $this->localFiles->read($cached))) {
                        $this->localFiles->touch($cached);

                        return true;
                    }
                    if (!self::convert($local, $target, $maxWidth, $maxHeight, $quality)) {
                        return false;
                    }
                    if ($cached !== null) {
                        $this->keep($target, $cached);
                    }

                    return true;
                }
            )
        );

        return $written === true && $this->storage->exists($destination);
    }

    /** Where the conversion of $local under these bounds is kept between runs, or null when YESWIKI_WEBP_CACHE names no directory. */
    public function cachedPath(string $local, int $maxWidth, int $maxHeight, int $quality): ?string
    {
        $directory = rtrim((string)getenv(self::CACHE_ENV), '/');
        if ($directory === '' || !$this->localFiles->isFile($local)) {
            return null;
        }
        $hash = hash('sha256', $this->localFiles->read($local));

        return "{$directory}/" . substr($hash, 0, 2) . "/{$hash}-{$maxWidth}x{$maxHeight}-q{$quality}.webp";
    }

    private static function convert(string $local, string $target, int $maxWidth, int $maxHeight, int $quality): bool
    {
        $image = new Zebra_Image();
        $image->auto_handle_exif_orientation = true;
        $image->preserve_aspect_ratio = true;
        $image->enlarge_smaller_images = false;
        $image->webp_quality = $quality;
        $image->source_path = $local;
        $image->target_path = $target;
        $previous = error_reporting();
        error_reporting($previous & ~E_DEPRECATED);
        try {
            return (bool)@$image->resize($maxWidth, $maxHeight, ZEBRA_IMAGE_NOT_BOXED, -1);
        } catch (\Throwable) {
            return false;
        } finally {
            error_reporting($previous);
        }
    }

    private function keep(string $target, string $cached): void
    {
        if (!$this->localFiles->makeDirectory(\dirname($cached))) {
            return;
        }
        $partial = $cached . '.' . getmypid() . '.partial';
        if ($this->localFiles->write($partial, $this->localFiles->read($target))) {
            $this->localFiles->rename($partial, $cached);
        }
    }
}

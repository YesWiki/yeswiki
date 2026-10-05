<?php

namespace YesWiki\Files\Service;

use stefangabos\Zebra_Image\Zebra_Image;

/** A stored picture rewritten as WebP and brought down to the upload bounds, as an upload would have been. */
class ImageShrinker
{
    public const CONVERTIBLE = [IMAGETYPE_JPEG, IMAGETYPE_PNG];

    public function __construct(private readonly Storage $storage)
    {
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
                static function (string $target) use ($local, $maxWidth, $maxHeight, $quality): bool {
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
            )
        );

        return $written === true && $this->storage->exists($destination);
    }
}

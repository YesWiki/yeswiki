<?php

namespace YesWiki\Attach\Service;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use YesWiki\Bazar\Service\SsrfUrlValidator;
use YesWiki\Wiki;

/**
 * Copies images from other sites into the cache folder and resizes them like uploaded ones.
 */
class RemoteImageCache
{
    private const MAX_BYTES = 10 * 1024 * 1024;
    private const MAX_REDIRECTS = 3;
    private const RETRY_FAILED_AFTER = 86400;
    private const MAX_DOWNLOADS_PER_REQUEST = 5;
    private const EXTENSIONS = [
        IMAGETYPE_JPEG => 'jpg',
        IMAGETYPE_PNG => 'png',
        IMAGETYPE_GIF => 'gif',
        IMAGETYPE_WEBP => 'webp',
    ];

    protected $wiki;
    protected $params;
    protected $ssrfUrlValidator;
    private $downloads = 0;

    public function __construct(Wiki $wiki, ParameterBagInterface $params, SsrfUrlValidator $ssrfUrlValidator)
    {
        $this->wiki = $wiki;
        $this->params = $params;
        $this->ssrfUrlValidator = $ssrfUrlValidator;
    }

    /**
     * Whether a field value is a link to another site rather than a file name.
     */
    public static function isRemote($value): bool
    {
        return is_string($value) && preg_match('#^(https?:)?//#i', $value) === 1;
    }

    /**
     * Path of the resized copy in the cache folder, or null when the URL does not lead to an image.
     */
    public function resized(string $url, int $width, int $height, string $mode = 'fit'): ?string
    {
        if ($width <= 0 || $height <= 0 || !in_array($mode, ['fit', 'crop'], true)) {
            return null;
        }
        $original = $this->localCopy($url);
        if ($original === null) {
            return null;
        }
        $info = pathinfo($original);
        $target = "{$info['dirname']}/{$info['filename']}_" . ($mode === 'crop' ? 'cropped' : 'vignette') . "_{$width}_{$height}.{$info['extension']}";
        if (file_exists($target)) {
            return $target;
        }
        if (!class_exists('attach')) {
            include 'tools/attach/libs/attach.lib.php';
        }
        $attach = new \attach($this->wiki);

        return $attach->redimensionner_image($original, $target, $width, $height, $mode) === $target && file_exists($target)
            ? $target
            : null;
    }

    /**
     * Path of the downloaded image in the cache folder, fetched on first use.
     */
    public function localCopy(string $url): ?string
    {
        if (str_starts_with($url, '//')) {
            $url = "https:$url";
        }
        $base = $this->cachePath() . '/remote_' . sha1($url);
        foreach (self::EXTENSIONS as $extension) {
            if (file_exists("$base.$extension")) {
                return "$base.$extension";
            }
        }
        $failedMarker = "$base.failed";
        if (file_exists($failedMarker) && filemtime($failedMarker) > time() - self::RETRY_FAILED_AFTER) {
            return null;
        }
        if ($this->downloads >= self::MAX_DOWNLOADS_PER_REQUEST) {
            return null;
        }
        $this->downloads++;

        $content = $this->download($url);
        $size = $content === null ? false : @getimagesizefromstring($content);
        if ($size === false || !isset(self::EXTENSIONS[$size[2]]) || empty($size[0]) || empty($size[1])) {
            @touch($failedMarker);

            return null;
        }
        $path = "$base." . self::EXTENSIONS[$size[2]];
        $temporary = "$base." . bin2hex(random_bytes(6)) . '.part';
        if (@file_put_contents($temporary, $content) === false || !@rename($temporary, $path)) {
            @unlink($temporary);

            return null;
        }

        return $path;
    }

    /**
     * Body of the URL, following a few redirects and checking each hop against private addresses.
     */
    private function download(string $url): ?string
    {
        for ($hop = 0; $hop <= self::MAX_REDIRECTS; $hop++) {
            try {
                $pin = $this->ssrfUrlValidator->curlPin($url, ['http', 'https']);
            } catch (\Throwable $error) {
                return null;
            }
            $body = '';
            $curl = curl_init($url);
            curl_setopt_array($curl, $pin + [
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_CONNECTTIMEOUT => 5,
                CURLOPT_TIMEOUT => 15,
                CURLOPT_USERAGENT => 'YesWiki',
                CURLOPT_HTTPHEADER => ['Accept: image/webp,image/png,image/jpeg,image/gif'],
                CURLOPT_WRITEFUNCTION => function ($curl, $chunk) use (&$body) {
                    $body .= $chunk;

                    return strlen($body) > self::MAX_BYTES ? 0 : strlen($chunk);
                },
            ]);
            $completed = curl_exec($curl);
            $status = curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
            $location = curl_getinfo($curl, CURLINFO_REDIRECT_URL);
            if ($completed === false) {
                return null;
            }
            if ($status >= 300 && $status < 400 && !empty($location)) {
                $url = $location;
                continue;
            }

            return ($status >= 200 && $status < 300) ? $body : null;
        }

        return null;
    }

    private function cachePath(): string
    {
        $path = rtrim($this->params->get('attach_config')['cache_path'] ?? 'cache', '/');
        if (!is_dir($path)) {
            @mkdir($path, 0755, true);
        }

        return $path;
    }
}

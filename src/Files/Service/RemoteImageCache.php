<?php

namespace YesWiki\Files\Service;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use YesWiki\Kernel\Service\RuntimeConfig;
use YesWiki\Kernel\Service\UrlFormatter;

/** A picture from somewhere else, fetched once and served from here. */
class RemoteImageCache
{
    /** The largest download this will accept, before resizing. */
    private const MAX_BYTES = 10 * 1024 * 1024;

    /** How long a failed fetch is remembered, in seconds. */
    private const MISS_TTL = 3600;

    /** Who is asking, said plainly. */
    private const USER_AGENT = 'YesWiki/1.0 (+https://yeswiki.net)';

    /** Types worth caching. */
    private const TYPES = [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP];

    /** The extension every cached copy is written under, whatever the publisher sent. */
    private const EXTENSION = 'webp';

    private ParameterBagInterface $params;
    private RuntimeConfig $config;
    private UrlFormatter $urlFormatter;
    private ImageResizer $resizer;

    public function __construct(
        ParameterBagInterface $params,
        RuntimeConfig $config,
        UrlFormatter $urlFormatter,
        ImageResizer $resizer
    ) {
        $this->params = $params;
        $this->config = $config;
        $this->urlFormatter = $urlFormatter;
        $this->resizer = $resizer;
    }

    /** This picture, served from this wiki -- or the address it came from, if it cannot be. */
    public function localUrl(string $url, ?int $width = null, ?int $height = null): string
    {
        $url = trim($url);
        if (!$this->isFetchable($url)) {
            return $url;
        }

        $width = $width ?: (int)($this->config['image-render-max-width'] ?? 0);
        $height = $height ?: (int)($this->config['image-render-max-height'] ?? 0);
        if ($width < 1 || $height < 1) {
            return $url;
        }

        $directory = $this->directory();
        if ($directory === null) {
            return $url;
        }

        $key = sha1($url . '|' . $width . 'x' . $height);
        $cached = $directory . '/' . $key . '.' . self::EXTENSION;
        if (is_file($cached)) {
            return $this->urlFormatter->getBaseUrl() . '/' . $cached;
        }

        $miss = $directory . '/' . $key . '.miss';
        if (is_file($miss) && (time() - (int)filemtime($miss)) < self::MISS_TTL) {
            return $url;
        }

        $stored = $this->store($url, $directory, $key, $width, $height);
        if ($stored === null) {
            @touch($miss);

            return $url;
        }

        return $this->urlFormatter->getBaseUrl() . '/' . $stored;
    }

    /** Is this an address worth this server making a request to? */
    private function isFetchable(string $url): bool
    {
        $parts = parse_url($url);
        if (!is_array($parts)) {
            return false;
        }
        $scheme = strtolower((string)($parts['scheme'] ?? ''));
        $host = (string)($parts['host'] ?? '');
        if (!in_array($scheme, ['http', 'https'], true) || $host === '') {
            return false;
        }
        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return false;
        }

        return !str_starts_with($url, $this->urlFormatter->getBaseUrl());
    }

    /** The shared cache directory, created on demand -- or null if it cannot be. */
    private function directory(): ?string
    {
        $attachConfig = $this->params->get('attach_config');
        $base = is_array($attachConfig) ? (string)($attachConfig['cache_path'] ?? 'cache') : 'cache';

        $directory = $base . '/remote';

        if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
            return null;
        }

        return $directory;
    }

    /** Fetch, check, resize, keep. */
    private function store(string $url, string $directory, string $key, int $width, int $height): ?string
    {
        $bytes = $this->fetch($url);
        if ($bytes === null) {
            return null;
        }

        $size = @getimagesizefromstring($bytes);
        if ($size === false || !in_array($size[2], self::TYPES, true)) {
            return null;
        }

        $destination = $directory . '/' . $key . '.' . self::EXTENSION;

        if ($size[2] === IMAGETYPE_WEBP && $size[0] <= $width && $size[1] <= $height) {
            return file_put_contents($destination, $bytes) === false ? null : $destination;
        }

        $temporary = $destination . '.tmp';
        if (file_put_contents($temporary, $bytes) === false) {
            return null;
        }

        $resized = $this->resizer->resize(
            $temporary,
            $destination,
            min($width, $size[0]),
            min($height, $size[1])
        );
        @unlink($temporary);

        return $resized === $destination ? $destination : null;
    }

    /** GET, with a ceiling on both time and size. */
    protected function fetch(string $url): ?string
    {
        $handle = curl_init($url);
        if ($handle === false) {
            return null;
        }
        curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($handle, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($handle, CURLOPT_MAXREDIRS, 3);
        curl_setopt($handle, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($handle, CURLOPT_TIMEOUT, 10);
        curl_setopt($handle, CURLOPT_USERAGENT, self::USER_AGENT);

        curl_setopt($handle, CURLOPT_MAXFILESIZE, self::MAX_BYTES);

        curl_setopt($handle, CURLOPT_NOPROGRESS, false);
        curl_setopt($handle, CURLOPT_PROGRESSFUNCTION, static function ($resource, $expected, $received): int {
            return $received > self::MAX_BYTES ? 1 : 0;
        });

        $body = curl_exec($handle);
        $status = (int)curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        $failed = curl_errno($handle);
        curl_close($handle);

        return (!$failed && $status < 400 && is_string($body) && $body !== '') ? $body : null;
    }
}

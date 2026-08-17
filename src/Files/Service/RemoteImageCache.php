<?php

namespace YesWiki\Files\Service;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use YesWiki\Kernel\Service\RuntimeConfig;
use YesWiki\Kernel\Service\UrlFormatter;

/**
 * A picture from somewhere else, fetched once and served from here.
 *
 * A syndicated feed hands out an item's image as a URL on the publisher's own server, and
 * every presentation that draws one -- a card wall, a list, a timeline -- put that URL
 * straight into an `<img src>`. Three things followed from that, none of them wanted:
 *
 *   - **Every reader's browser was sent to that server.** A page showing twelve items from
 *     three feeds made requests to three sites nobody chose to visit, carrying a referer and
 *     whatever cookies those sites had set. A wiki that serves its own fonts rather than
 *     Google's (ADR-0021) should not be handing its readers to a news site by the dozen.
 *   - **The picture arrived at whatever size the publisher happened to store.** A card image
 *     is a few hundred pixels wide; feeds routinely carry the two-thousand-pixel version.
 *   - **The page broke when they did.** A feed image that 404s, or a server that is slow, is
 *     a hole in a card that has nothing to do with this wiki.
 *
 * So the image is downloaded once, resized to the render cap, converted to WebP and written
 * into the public cache directory. After that it is a static file this wiki serves, and the
 * feed's own caching decides how often anything is refetched.
 *
 * **Every failure falls back to the remote URL**, which is exactly what happened before this
 * class existed. A feed image is decoration; nothing here is worth an empty card over.
 */
class RemoteImageCache
{
    /**
     * The largest download this will accept, before resizing.
     *
     * Not a configuration key, because it is not a policy anybody wants to tune: it is the
     * point past which a "feed image" is not one, and this runs while a page is rendering.
     */
    private const MAX_BYTES = 10 * 1024 * 1024;

    /**
     * How long a failed fetch is remembered, in seconds.
     *
     * Without this, a feed carrying one dead image URL retries it on every render of every
     * page that shows that feed -- ten seconds of timeout each, in front of the page, forever.
     */
    private const MISS_TTL = 3600;

    /**
     * Who is asking, said plainly.
     *
     * Not a browser string: this is a server fetching a picture on a wiki's behalf, and some
     * publishers refuse a generic `Mozilla/5.0` outright -- Wikimedia answers one with a 400,
     * which is a picture that never arrives for a reason nothing in the wiki could explain.
     * Naming the software and where to complain about it is what their policy asks for.
     */
    private const USER_AGENT = 'YesWiki/1.0 (+https://yeswiki.net)';

    /**
     * Types worth caching. What comes out is always WebP, whichever of these came in.
     *
     * GIF is not here: resizing one through GD keeps the first frame and drops the animation,
     * which is a worse outcome than linking the original. Neither is SVG -- `getimagesize()`
     * does not read one, so it never reaches this list, and rasterising a drawing that has no
     * resolution would make it bigger rather than smaller.
     */
    private const TYPES = [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP];

    /**
     * The extension every cached copy is written under, whatever the publisher sent.
     *
     * Same rule the browser applies to an upload (javascripts/image-upload.js): a picture this
     * wiki serves is WebP. A feed's PNG is typically two or three times the size of the WebP
     * that looks identical, and this one is re-encoded anyway -- it is being resized.
     */
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

    /**
     * This picture, served from this wiki -- or the address it came from, if it cannot be.
     *
     * Left alone when it is already one of ours: an entry's image is an `api/files/…/download`
     * address on this host, and copying a local file into a cache of remote files would be a
     * second copy of it with no owner and no ACL.
     */
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

    /**
     * Is this an address worth this server making a request to?
     *
     * `http(s)` to a **named host**, and nothing else. A feed is configured by an admin, but
     * its contents are written by whoever publishes it -- so an item's image URL is a string
     * from a stranger that this server is about to fetch. `file://` would read the disk and a
     * bare IP is the shape of somebody mapping the network this server sits in rather than
     * naming a picture. Same rule the font importer applies to another wiki's address.
     *
     * A URL on this wiki is refused too, for a different reason: it is already served from
     * here, and a copy of it in the cache would be one nothing owns.
     */
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
        // deliberately NOT AttachedFilePaths::cachePath(), which is per-page outside safe
        // mode: the same feed image shown on four pages would then be fetched four times and
        // stored four times, and it is the same picture
        $directory = $base . '/remote';

        if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
            return null;
        }

        return $directory;
    }

    /**
     * Fetch, check, resize, keep. Returns the path written, or null if any of that failed.
     *
     * The bytes are sniffed rather than trusted: `Content-Type` is the publisher's word for
     * it, and what this writes into a public directory has to be an image because it IS one,
     * not because a header said so.
     *
     * The re-encode is what makes the copy WebP: `ImageResizer` reads the format to write from
     * the destination's extension, so a JPEG in becomes a WebP out. It runs even for a picture
     * already inside the cap -- there the size asked for is the picture's own, so the pixels
     * are untouched and only the container changes. The one thing not re-encoded is a WebP
     * that is already small enough, which is nothing but a lossy generation lost.
     *
     * An animated WebP fails the resize (GD reads one frame, and Zebra_Image refuses rather
     * than silently flattening it) and so falls back to the remote URL, exactly as a GIF does.
     */
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
        // capped to the picture's own size, because the resizer enlarges smaller images when
        // asked for a box bigger than they are -- a 300px feed thumbnail blown up to 1920 is
        // a bigger file that shows less
        $resized = $this->resizer->resize(
            $temporary,
            $destination,
            min($width, $size[0]),
            min($height, $size[1])
        );
        @unlink($temporary);

        return $resized === $destination ? $destination : null;
    }

    /**
     * GET, with a ceiling on both time and size. Null if it did not answer with a body.
     *
     * `protected` so a test can hand this class bytes without a network, which is the only
     * way the caching, the resizing and the negative cache can be asserted at all.
     */
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
        // the announced length, when there is one...
        curl_setopt($handle, CURLOPT_MAXFILESIZE, self::MAX_BYTES);
        // ...and the real one, when there is not: a server that sends no Content-Length can
        // otherwise stream as much as it likes into this process's memory
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

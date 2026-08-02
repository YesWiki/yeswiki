<?php

namespace YesWiki\Content\Action;

use YesWiki\Content\Service\AttachedFilePaths;
use YesWiki\Content\Service\FileManager;
use YesWiki\Content\Service\ImageResizer;
use YesWiki\Content\Service\LinkTracker;
use YesWiki\Core\YesWikiAction;
use YesWiki\Kernel\Performable\RegisteredAction;
use YesWiki\Kernel\Service\PerformableArguments;
use YesWiki\Kernel\Service\RuntimeConfig;
use YesWiki\Kernel\Service\UrlFormatter;
use YesWiki\Render\Service\ActionRunner;
use YesWiki\Render\Service\MarkdownFormatterService;
use YesWiki\Render\Service\TemplateHelperService;

/**
 * `{{attach}}` -- renders one attached file inline, as an image, player, PDF or link
 * depending on its type.
 *
 * Ticket 24 dissolved the `Attach` class into this action plus three services
 * (AttachedFilePaths, ImageResizer, FileBrowser). What lives here is what was only ever
 * about *this action*: reading its arguments and turning a resolved file into HTML.
 *
 * **The parsed arguments live in a value object, never on `$this`.** Actions are shared
 * services, so one instance renders every `{{attach}}` on the page; per-instance state
 * would leak from one tag to the next. That is exactly what `Attach` avoided by being
 * constructed fresh per tag -- and it is not a hypothetical: an early draft of this class
 * kept the parsed `class=` on `$this` and the second `{{attach}}` on a page inherited the
 * first one's CSS classes and its 20px whiteborder adjustment.
 *
 * Two file models meet here. `file=` is normally a **FileManager tag** (ticket 10): the
 * bytes live under private/ and are served through the ACL-checked download route
 * (ADR-0006). Anything that is not a known tag falls back to the **legacy** search for an
 * encoded filename under files/, which is web-served directly, as it always was.
 */
class AttachAction extends YesWikiAction implements RegisteredAction
{
    public static function performableName(): string
    {
        return 'attach';
    }

    public function run(): string
    {
        $request = $this->readArguments();
        if ($request->error !== '') {
            return $request->error;
        }

        // A tag-resolved file's bytes are under private/ and not web-servable; the legacy
        // filename search is only reached for the untagged fallback.
        $fullFilename = $request->fileTag !== ''
            ? $this->getService(FileManager::class)->getPhysicalPath($request->fileTag)
            : $this->paths()->fullFilename($request->file);

        if (empty($fullFilename) || !file_exists($fullFilename)) {
            return '<div class="yw-alert yw-alert--danger">' . _t('ATTACH_PARAM_FILE_NOT_FOUND') . ' (' . htmlspecialchars($request->file) . ')</div>';
        }

        $paths = $this->paths();
        if ($paths->isPicture($request->file)) {
            return $this->asImage($request, $fullFilename);
        }
        if ($paths->isVideo($request->file) || $paths->isFlashVideo($request->file)) {
            return $this->asVideo($request, $fullFilename);
        }
        if ($paths->isAudio($request->file) || $paths->isWma($request->file)) {
            return $this->asAudio($request, $fullFilename);
        }
        if ($paths->isPdf($request->file) && $request->displayPdf) {
            return $this->asPdf($request, $fullFilename);
        }

        return $this->asLink($request, $fullFilename);
    }

    private function paths(): AttachedFilePaths
    {
        return $this->getService(AttachedFilePaths::class);
    }

    private function arg(string $name): mixed
    {
        return $this->getService(PerformableArguments::class)->get($name);
    }

    /**
     * Read this tag's arguments into a fresh value object, validating as we go. See
     * docs/actions/attach.yaml for the full list.
     */
    private function readArguments(): AttachRequest
    {
        $request = new AttachRequest();

        $request->file = htmlspecialchars((string)($this->arg('attachfile') ?: $this->arg('file')));

        // `file=` is a FileManager tag on the current path -- resolve it here so the
        // renderers below serve it through the ACL-checked download route rather than a
        // direct static path. Anything else falls through to the legacy filename search.
        $fileManager = $this->getService(FileManager::class);
        if ($request->file !== '' && $fileManager->isFileTag($request->file)) {
            $entry = $fileManager->getOne($request->file);
            $request->fileTag = $request->file;
            $request->file = (string)($entry['original_filename'] ?? '');
        }

        $desc = (string)($this->arg('attachdesc') ?: $this->arg('desc'));
        $request->desc = htmlentities(strip_tags($desc)); // avoid XSS

        $request->link = (string)($this->arg('attachlink') ?: $this->arg('link')); // only meaningful on an image
        $request->caption = (string)$this->arg('caption'); // shown on hover
        $request->legend = (string)$this->arg('legend'); // shown underneath
        $request->nofullimagelink = (string)$this->arg('nofullimagelink');
        $request->height = $this->arg('height');
        $request->width = $this->arg('width');
        $request->displayPdf = $this->arg('displaypdf');
        $request->data = $this->getService(TemplateHelperService::class)->getDataParameter();

        if (empty($request->file)) {
            $request->error = $this->errorMessage(_t('ATTACH_PARAM_FILE_NOT_FOUND'));
        }
        if (!empty($request->width) && !ctype_digit(strval($request->width))) {
            $request->error = $this->errorMessage(_t('ATTACH_PARAM_WIDTH_NOT_NUMERIC'));
        }
        if (!empty($request->height) && !ctype_digit(strval($request->height))) {
            $request->error = $this->errorMessage(_t('ATTACH_PARAM_HEIGHT_NOT_NUMERIC'));
        }

        if ($this->arg('class')) {
            foreach (explode(' ', (string)$this->arg('class')) as $c) {
                $request->classes .= ' ' . trim($c);
            }
        }

        $config = $this->getService(RuntimeConfig::class);
        switch ($this->arg('size')) {
            case 'small':
                $request->width = $config['image-small-width'];
                $request->height = $config['image-small-height'];
                break;
            case 'medium':
                $request->width = $config['image-medium-width'];
                $request->height = $config['image-medium-height'];
                break;
            case 'big':
                $request->width = $config['image-big-width'];
                $request->height = $config['image-big-height'];
                break;
        }

        // one dimension given means a square bound
        if (empty($request->height) && !empty($request->width)) {
            $request->height = $request->width;
        } elseif (!empty($request->height) && empty($request->width)) {
            $request->width = $request->height;
        }

        return $request;
    }

    private function errorMessage(string $message): string
    {
        return '<div class="alert alert-danger"><strong>' . _t('ATTACH_ACTION_ATTACH') . '</strong> : ' . $message . '.</div>' . "\n";
    }

    /**
     * URL serving the file's bytes. A FileManager-tagged entry goes through the API
     * download route, which is what actually enforces its read ACL (ADR-0006) -- its
     * bytes under private/ are not web-servable. A legacy file stays under files/ and is
     * linked directly, as before.
     */
    private function fileUrl(AttachRequest $request, string $fullFilename, bool $forceDownload = false): string
    {
        if ($request->fileTag !== '') {
            $url = $this->getService(UrlFormatter::class)->href('', 'api/files/' . $request->fileTag . '/download', [], false);

            return $forceDownload ? $url . '?download=1' : $url;
        }

        return $this->paths()->scriptPath() . $fullFilename;
    }

    private function asImage(AttachRequest $request, string $fullFilename): string
    {
        // A tagged file is served full-size with width/height as plain HTML attributes:
        // its bytes are under private/, and writing a resized COPY into the public cache/
        // would defeat the point of moving them there. SVG needs no resize either way.
        // The legacy path keeps the original resize-to-cache behaviour.
        if ($request->fileTag !== '' || preg_match('/.(svg)$/i', $request->file) === 1) {
            $width = $request->width;
            $height = $request->height;
            $imgName = $fullFilename;
        } else {
            $imgName = $fullFilename;
            if (!empty($request->height) && !empty($request->width)) {
                $resizer = $this->getService(ImageResizer::class);
                $destination = $resizer->resizedFilename($fullFilename, (string)$request->width, (string)$request->height);
                if (!file_exists($destination)) {
                    $resizer->resize($fullFilename, $destination, $request->width, $request->height);
                }
                $imgName = $destination;
            }
            $size = getimagesize($imgName);
            $width = $size === false ? null : $size[0];
            $height = $size === false ? null : $size[1];
        }

        // the border is drawn inside the box, so take it off the declared size
        if (strstr($request->classes, 'whiteborder')) {
            $width -= 20;
            $height -= 20;
        }

        $imgSrc = $request->fileTag !== '' ? $this->fileUrl($request, $fullFilename) : ($this->paths()->scriptPath() . $imgName);
        $img = '<img loading="lazy" class="img-responsive" src="' . $imgSrc . '" '
            . 'alt="' . $request->desc . ($request->link ? "\nLien vers: $request->link" : '') . '"'
            . (!empty($width) ? ' width="' . $width . '"' : '')
            . (!empty($height) ? ' height="' . $height . '"' : '') . ' />';

        $classDataForLinks = strstr($request->classes, 'new-window')
            ? ' class="new-window"'
            : (strstr($request->classes, 'modalbox') ? ' class="modalbox" data-size="modal-lg"' : '');

        $link = null;
        if (!empty($request->link)) {
            $linkParts = $this->getService(UrlFormatter::class)->extractLinkParts($request->link);
            if (!empty($linkParts['tag'])) {
                $this->getService(LinkTracker::class)->forceAddIfNotIncluded((string)$linkParts['tag']);
            }
            $link = '<a href="' . $this->getService(UrlFormatter::class)->generateLink($request->link) . '"' . $classDataForLinks . '>';
        } elseif (empty($request->nofullimagelink)) {
            $link = '<a href="' . $this->fileUrl($request, $fullFilename, true) . '"' . $classDataForLinks . '>';
        }

        $caption = !empty($request->caption) ? '<figcaption>' . $request->caption . '</figcaption>' : '';
        $legend = !empty($request->legend) ? '<div class="legend">' . $request->legend . '</div>' : '';
        $data = '';
        if (is_array($request->data)) {
            foreach ($request->data as $key => $value) {
                $data .= ' data-' . $key . '="' . $value . '"';
            }
        }

        $notAligned = strpos($request->classes, 'left') === false
            && strpos($request->classes, 'right') == false
            && strpos($request->classes, 'center') == false;

        return ($notAligned ? '<div>' : '')
            . ($link ?? '')
            . "<figure class=\"$request->classes\" $data>$img$caption$legend</figure>"
            . ($link !== null ? '</a>' : '')
            . ($notAligned ? '</div>' : '');
    }

    private function asLink(AttachRequest $request, string $fullFilename): string
    {
        return '<a href="' . $this->fileUrl($request, $fullFilename, true) . '">' . ($request->desc ?: $request->file) . '</a>';
    }

    private function asVideo(AttachRequest $request, string $fullFilename): string
    {
        return $this->getService(MarkdownFormatterService::class)->format(
            '{{player url="' . $this->fileUrl($request, $fullFilename) . '" type="video" '
            . 'height="' . (!empty($request->height) ? $request->height : '300px') . '" '
            . 'width="' . (!empty($request->width) ? $request->width : '400px') . '"}}'
        );
    }

    private function asAudio(AttachRequest $request, string $fullFilename): string
    {
        return $this->getService(MarkdownFormatterService::class)->format(
            '{{player url="' . $this->fileUrl($request, $fullFilename) . '" type="audio"}}'
        );
    }

    private function asPdf(AttachRequest $request, string $fullFilename): string
    {
        $arguments = $this->getService(PerformableArguments::class);
        $arguments->set('url', $this->fileUrl($request, $fullFilename));
        if (empty($arguments->get('maxheight')) && empty($arguments->get('maxwidth'))) {
            $arguments->set('maxheight', $arguments->get('height'));
            $arguments->set('maxwidth', $arguments->get('width'));
        }

        // {{pdf}} expects Bootstrap's pull-* for alignment, {{attach}} takes plain left/right
        $newClass = '';
        foreach (['right', 'left'] as $side) {
            if (strstr($request->classes, $side)) {
                $newClass = strstr($request->classes, 'pull-' . $side)
                    ? str_replace($side, '', $request->classes)
                    : str_replace($side, 'pull-' . $side, $request->classes);
            }
        }
        if ($newClass !== '') {
            $arguments->set('class', $newClass);
        }

        return $this->getService(ActionRunner::class)->action('pdf', false, $arguments->all());
    }
}

/**
 * One `{{attach}}` tag's parsed arguments. Exists so the action -- a shared service --
 * holds no per-tag state; see the class comment above for the bug that proved it needed to.
 *
 * @internal
 */
final class AttachRequest
{
    /** Set when `file=` resolved to a FileManager tag; empty on the legacy path. */
    public string $fileTag = '';
    public string $file = '';
    public string $desc = '';
    public string $link = '';
    public string $caption = '';
    public string $legend = '';
    public string $nofullimagelink = '';
    public string $classes = 'attached_file';
    public mixed $height = null;
    public mixed $width = null;
    public mixed $displayPdf = null;
    public mixed $data = '';
    public string $error = '';
}

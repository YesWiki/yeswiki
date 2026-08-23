<?php

namespace YesWiki\Files\Service;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use YesWiki\Kernel\Service\PageContext;
use YesWiki\Kernel\Service\RuntimeConfig;

/**
 * Where an attached file lives and what its name encodes -- the half of the former `Attach` class that was pure fact about a filename (ticket 24).
 */
class AttachedFilePaths
{
    /** Where uploads live, relative to the instance: `files/`. */
    public const UPLOAD_DIR = 'files/';

    /**
     * @var array<string, mixed>
     */
    private array $attachConfig;
    private bool $safeMode;

    private ParameterBagInterface $params;
    private RuntimeConfig $runtimeConfig;
    private PageContext $pageContext;

    public function __construct(
        ParameterBagInterface $params,
        RuntimeConfig $runtimeConfig,
        PageContext $pageContext,
    ) {
        $this->params = $params;
        $this->runtimeConfig = $runtimeConfig;
        $this->pageContext = $pageContext;

        $attachConfig = $this->params->get('attach_config');
        if (!is_array($attachConfig)) {
            throw new \Exception('attach_config should be an array in yeswiki.config.php');
        }
        if (empty($attachConfig['max_file_size'])) {
            $attachConfig['max_file_size'] = $this->params->get('max-upload-size');
        }
        $this->attachConfig = $attachConfig;

        $this->safeMode = empty($this->runtimeConfig->getValue('no_safe_mode'));
    }

    /**
     * @return array<string, mixed>
     */
    public function config(): array
    {
        return $this->attachConfig;
    }

    public function isSafeMode(): bool
    {
        return $this->safeMode;
    }

    /** The page whose upload directory (or filename prefix, under safe mode) applies. */
    public function currentPageTag(): string
    {
        return (string)$this->pageContext->getTag();
    }

    /** Upload directory: flat under safe mode, one sub-directory per page otherwise. */
    public function uploadPath(): string
    {
        return $this->pathFor((string)$this->attachConfig['upload_path']);
    }

    /** Cache (resized images) directory, following the same safe-mode rule. */
    public function cachePath(): string
    {
        return $this->pathFor((string)$this->attachConfig['cache_path']);
    }

    private function pathFor(string $base): string
    {
        if ($this->safeMode) {
            return $base;
        }
        $path = $base . '/' . $this->pageContext->getTag();
        if (!is_dir($path)) {
            $this->mkdirRecursive($path);
        }

        return $path;
    }

    public function mkdirRecursive(string $dir): bool
    {
        if (strlen($dir) === 0) {
            return false;
        }
        if (is_dir($dir) || dirname($dir) === $dir) {
            return true;
        }

        return $this->mkdirRecursive(dirname($dir)) && mkdir($dir, 0755);
    }

    /**
     * The full path of an attached file, either freshly named (`$newName`) or found by searching the upload directory for the most recently uploaded match.
     */
    public function fullFilename(string $file, bool $newName = false): string
    {
        $page = $this->pageContext->getPage() ?? [];
        $pageDate = $this->compactDate(
            isset($page['time'])
                ? $page['time']
                : ($this->pageContext->getTag() === 'root' ? date('Y-m-d H:i:s') : null)
        );

        $parts = [];
        if (!preg_match('`^((.+)/)?(.*)\.(.*)$`', str_replace(' ', '_', $file), $match)) {
            return '';
        }
        list(, , $parts['page'], $parts['name'], $parts['ext']) = $match;
        if (!$this->isDisplayableMedia($file)) {
            $parts['ext'] .= '_';
        }

        $path = $this->uploadPath();
        $pageTag = $parts['page'] ? $parts['page'] : $this->pageContext->getTag();

        if ($newName) {
            $fullFileName = $parts['name'] . '_' . $pageDate . '_' . $this->currentStamp() . '.' . $parts['ext'];

            return $this->safeMode
                ? $path . '/' . $pageTag . '_' . $fullFileName
                : $path . '/' . $fullFileName;
        }

        $name = preg_quote($parts['name'], '`');
        $extension = preg_quote($parts['ext'], '`');

        $isActionBuilderPreview = $this->pageContext->getTag() === 'root';
        if ($isActionBuilderPreview) {
            $searchPattern = '`' . $name . '_\d{14}_\d{14}\.' . $extension . '$`';
        } elseif ($this->safeMode) {
            $searchPattern = '`^' . preg_quote($pageTag, '`') . '_' . $name . '_\d{14}_\d{14}\.' . $extension . '$`';
        } else {
            $searchPattern = '`^' . $name . '_\d{14}_\d{14}\.' . $extension . '$`';
        }

        $files = $this->searchFiles($searchPattern, $path);

        $theFile = null;
        $latest = 0;
        foreach ($files as $candidate) {
            if (($candidate['dateupload'] ?? 0) > $latest) {
                $theFile = $candidate;
                $latest = $candidate['dateupload'];
            }
        }
        if ($isActionBuilderPreview && count($files) > 0) {
            $theFile = $files[0];
        }

        return is_array($theFile) ? $path . '/' . $theFile['realname'] : '';
    }

    /**
     * Split an encoded filename back into its parts.
     *
     * @return array<string, mixed>
     */
    public function decodeLongFilename(string $filename): array
    {
        $decoded = [];
        $decoded['realname'] = basename($filename);
        $decoded['size'] = file_exists($filename) ? filesize($filename) : null;
        $decoded['path'] = dirname($filename);
        if (preg_match('`^(.*)_(\d{14})_(\d{14})\.(.*)(trash\d{14})?$`', $decoded['realname'], $m)) {
            $decoded['name'] = $m[1];
            if ($this->safeMode) {
                $decoded['name'] = (string)preg_replace('`^(' . $this->pageContext->getTag() . ')_(.*)$`i', '$2', $decoded['name']);
            }
            $decoded['datepage'] = $m[2];
            $decoded['dateupload'] = $m[3];
            $decoded['trashdate'] = (string)preg_replace('`(.*)trash(\d{14})`', '$2', $m[4]);
            $decoded['ext'] = rtrim((string)preg_replace('`^(.*)(trash\d{14})$`', '$1', $m[4]), '_');
        }

        return $decoded;
    }

    /**
     * Every file in $startDir whose name matches $filePattern, decoded.
     *
     * @return list<array<string, mixed>>
     */
    public function searchFiles(string $filePattern, string $startDir): array
    {
        $matched = [];
        $startDir = rtrim($startDir, '\/');
        $fh = opendir($startDir);
        if ($fh === false) {
            return $matched;
        }
        while (($file = readdir($fh)) !== false) {
            if ($file === '.' || $file === '..' || is_dir($file)) {
                continue;
            }
            if (preg_match($filePattern, $file)) {
                $matched[] = $this->decodeLongFilename($startDir . '/' . $file);
            }
        }
        closedir($fh);

        return $matched;
    }

    public function isPicture(string $file): bool
    {
        return $this->matchesExtensions($file, 'ext_images');
    }

    public function isAudio(string $file): bool
    {
        return $this->matchesExtensions($file, 'ext_audio');
    }

    public function isVideo(string $file): bool
    {
        return $this->matchesExtensions($file, 'ext_video');
    }

    public function isFlashVideo(string $file): bool
    {
        return $this->matchesExtensions($file, 'ext_flashvideo');
    }

    public function isWma(string $file): bool
    {
        return $this->matchesExtensions($file, 'ext_wma');
    }

    public function isPdf(string $file): bool
    {
        return $this->matchesExtensions($file, 'ext_pdf');
    }

    /** The types the encoder leaves without a trailing `_`, i.e. */
    private function isDisplayableMedia(string $file): bool
    {
        return $this->isPicture($file)
            || $this->isAudio($file)
            || $this->isVideo($file)
            || $this->isWma($file)
            || $this->isFlashVideo($file);
    }

    private function matchesExtensions(string $file, string $configKey): bool
    {
        return preg_match('/.(' . $this->attachConfig[$configKey] . ')$/i', $file) === 1;
    }

    /** Now, in the `YmdHis` form filenames use. */
    public function currentStamp(): string
    {
        return date('YmdHis');
    }

    /** `yyyy-mm-dd hh:mm:ss` to the `yyyymmddhhmmss` filenames use. */
    public function compactDate(mixed $date): string
    {
        if (!is_string($date)) {
            return '';
        }

        return str_replace(['-', ' ', ':'], '', $date);
    }

    /**
     * `yyyymmddhhmmss` back to `yyyy-mm-dd hh:mm:ss`.
     *
     * @return string|false
     */
    public function parseDate(string $stamp)
    {
        if (preg_match('`^(\d{4})(\d{2})(\d{2})(\d{2})(\d{2})(\d{2})$`', $stamp, $m)) {
            return $m[1] . '-' . $m[2] . '-' . $m[3] . ' ' . $m[4] . ':' . $m[5] . ':' . $m[6];
        }

        return false;
    }
}

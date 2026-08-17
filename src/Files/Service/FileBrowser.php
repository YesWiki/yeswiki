<?php

namespace YesWiki\Files\Service;

use YesWiki\Identity\Service\InputFilter;
use YesWiki\Kernel\Service\PageContext;
use YesWiki\Render\Service\TemplateEngine;

/**
 * The file-manager screen: list, trash, restore and erase the files attached to the current page (ticket 24, extracted from `Attach`'s `fm*` methods).
 */
class FileBrowser
{
    private AttachedFilePaths $paths;
    private ImageResizer $resizer;
    private PageContext $pageContext;
    private TemplateEngine $templateEngine;
    private InputFilter $inputFilter;

    public function __construct(
        AttachedFilePaths $paths,
        ImageResizer $resizer,
        PageContext $pageContext,
        TemplateEngine $templateEngine,
        InputFilter $inputFilter
    ) {
        $this->paths = $paths;
        $this->resizer = $resizer;
        $this->pageContext = $pageContext;
        $this->templateEngine = $templateEngine;
        $this->inputFilter = $inputFilter;
    }

    /** Apply the `?do=` operation, then render the resulting listing. */
    public function render(): string
    {
        $do = (string)($_GET['do'] ?? '');
        switch ($do) {
            case 'restore':
                $this->restore();

                return $this->renderListing(true);
            case 'erase':
                $this->erase();

                return $this->renderListing(true);
            case 'del':
                $this->moveToTrash();

                return $this->renderListing(false);
            case 'trash':
                return $this->renderListing(true);
            case 'emptytrash':
                $this->emptyTrash();

                // no break
            default:
                return $this->renderListing(false);
        }
    }

    private function renderListing(bool $trash): string
    {
        $files = $this->files($trash);
        $files = $this->sortByNameThenRevision($files);
        $files = array_map(function ($file) {
            return array_merge($file, [
                'parsedTrashDate' => isset($file['trashdate']) ? $this->paths->parseDate((string)$file['trashdate']) : '',
                'parsedDateUpload' => isset($file['dateupload']) ? $this->paths->parseDate((string)$file['dateupload']) : '',
                'readableSize' => isset($file['size']) ? self::readableSize((int)$file['size']) : '',
            ]);
        }, $files);

        return $this->templateEngine->renderSafely(
            '@core/attach-filemanager.twig',
            [
                'tag' => $this->pageContext->getTag(),
                'method' => $this->pageContext->getMethod() !== 'show' ? $this->pageContext->getMethod() : '',
                'trash' => $trash,
                'files' => $files,
            ]
        );
    }

    /**
     * The page's attached files, either live or trashed.
     *
     * @return list<array<string, mixed>>
     */
    public function files(bool $trash = false): array
    {
        $filePattern = $this->paths->isSafeMode()
            ? '^' . $this->paths->currentPageTag() . '_.*_\d{14}_\d{14}\..*'
            : '^.*_\d{14}_\d{14}\..*';
        $filePattern .= $trash ? 'trash\d{14}' : '[^(trash\d{14})]';

        return $this->paths->searchFiles('`' . $filePattern . '$`', $this->paths->uploadPath());
    }

    /** Move a file to the trash, and delete every cached resize of it. */
    public function moveToTrash(string $rawFileName = ''): void
    {
        $path = $this->paths->uploadPath();
        $rawFileName = $rawFileName !== ''
            ? $rawFileName
            : (string)$this->inputFilter->filterInput(INPUT_GET, 'file', FILTER_SANITIZE_FULL_SPECIAL_CHARS, false, 'string');
        if ($rawFileName === '') {
            return;
        }

        $filename = $path . '/' . basename($rawFileName);
        if (!file_exists($filename)) {
            return;
        }

        rename($filename, $filename . 'trash' . $this->paths->currentStamp());

        foreach ($this->cachedResizePatterns($filename) as $pattern) {
            array_map('unlink', glob($pattern) ?: []);
        }
    }

    /**
     * @return list<string>
     */
    private function cachedResizePatterns(string $filename): array
    {
        $cachePath = $this->paths->cachePath();
        $base = basename($filename);
        $threeOrFour = ['[0-9][0-9][0-9]', '[0-9][0-9][0-9][0-9]'];

        $patterns = [];
        foreach (['fit', 'crop'] as $mode) {
            foreach ($threeOrFour as $width) {
                foreach ($threeOrFour as $height) {
                    $patterns[] = $this->resizer->resizedFilename($filename, $width, $height, $mode);
                }
            }
        }

        $patterns[] = $cachePath . '/vignette_' . $base;
        $patterns[] = $cachePath . '/image_' . $base;

        foreach ($threeOrFour as $width) {
            foreach ($threeOrFour as $height) {
                $patterns[] = $cachePath . '/image_' . $width . '[x_]' . $height . '_' . $base;
                $patterns[] = $cachePath . '/' . $width . 'x' . $height . '-' . $base;
            }
        }

        return $patterns;
    }

    /** Delete one trashed file for good. */
    public function erase(): void
    {
        $filename = $this->paths->uploadPath() . '/' . basename(realpath($_GET['file'] ?? '') ?: '');

        if (file_exists($filename) && preg_match('/trash\d{14}$/', $filename)) {
            unlink($filename);
        }
    }

    /** Delete every trashed file for good. */
    public function emptyTrash(): void
    {
        foreach ($this->files(true) as $file) {
            $filename = $file['path'] . '/' . $file['realname'];
            if (file_exists($filename)) {
                unlink($filename);
            }
        }
    }

    /** Take a file back out of the trash. */
    public function restore(): void
    {
        $filename = $this->paths->uploadPath() . '/' . ($_GET['file'] ?? '');
        if (file_exists($filename)) {
            rename($filename, (string)preg_replace('`^(.*\..*)trash\d{14}$`', '$1', $filename));
        }
    }

    /**
     * By name, then by upload date so successive revisions of one file stay together.
     *
     * @param list<array<string, mixed>> $files
     *
     * @return list<array<string, mixed>>
     */
    private function sortByNameThenRevision(array $files): array
    {
        usort($files, function ($a, $b) {
            $result = strcasecmp(
                ($a['name'] ?? '') . '.' . ($a['ext'] ?? ''),
                ($b['name'] ?? '') . '.' . ($b['ext'] ?? '')
            );

            return $result === 0
                ? strcasecmp((string)($a['dateupload'] ?? ''), (string)($b['dateupload'] ?? ''))
                : $result;
        });

        return $files;
    }

    /**
     * Bytes as a human-readable size.
     *
     * @author Aidan Lister <aidan@php.net>
     *
     * @see http://aidanlister.com/2004/04/human-readable-file-sizes/
     */
    public static function readableSize(int $size, ?string $max = null, string $system = 'si', string $retstring = '%01.2f %s'): string
    {
        $systems = [
            'si' => ['prefix' => ['', 'Ko', 'Mo', 'Go', 'To', 'Po'], 'size' => 1000],
            'bi' => ['prefix' => ['B', 'KiB', 'MiB', 'GiB', 'TiB', 'PiB'], 'size' => 1024],
        ];
        $sys = $systems[$system] ?? $systems['si'];

        $depth = count($sys['prefix']) - 1;
        if ($max !== null) {
            $found = array_search($max, $sys['prefix'], true);
            if ($found !== false) {
                $depth = $found;
            }
        }

        $value = (float)$size;
        $i = 0;
        while ($value >= $sys['size'] && $i < $depth) {
            $value /= $sys['size'];
            $i++;
        }

        if ($sys['prefix'][$i] === '') {
            $retstring = '%01u %s';
        }

        return sprintf($retstring, $value, $sys['prefix'][$i]);
    }
}

<?php

namespace YesWiki\Import\Service;

use Psr\Container\ContainerInterface;
use YesWiki\Content\Entity\PageBody;
use YesWiki\Content\Field\TextareaField;
use YesWiki\Content\Service\FormManager;
use YesWiki\Content\Service\PageManager;
use YesWiki\Files\Service\AttachedFilePaths;
use YesWiki\Files\Service\LocalFiles;
use YesWiki\Files\Service\Storage;
use YesWiki\Kernel\Service\UrlFormatter;

class ImportFilesManager
{
    protected ?string $uploadPath = null;
    protected ContainerInterface $container;

    protected UrlFormatter $urlFormatter;

    /**
     * ImportManager constructor.
     *
     * @param ContainerInterface $container service container
     */
    public function __construct(ContainerInterface $container, UrlFormatter $urlFormatter, private readonly Storage $storage, private readonly LocalFiles $localFiles)
    {
        $this->urlFormatter = $urlFormatter;
        $this->container = $container;
        $this->uploadPath = null;
    }

    /**
     * Get the local path to files uploads (usually "files").
     */
    private function getLocalFileUploadPath(): string
    {
        if ($this->uploadPath !== null) {
            return $this->uploadPath;
        }

        $attachConfig = $this->container->get(\YesWiki\Kernel\Service\RuntimeConfig::class)['attach_config'];

        if (!is_array($attachConfig)) {
            $attachConfig = [];
        }

        $this->uploadPath = empty($attachConfig['upload_path'])
            ? 'files'
            : (string)$attachConfig['upload_path'];

        return $this->uploadPath;
    }

    /**
     * Download file url to local wiki using cURL.
     *
     * @param string $from      file url
     * @param string $to        local path
     * @param bool   $overwrite overwrite existing file ? (default:false)
     *
     * @return string the curl error, or '' when the download succeeded
     */
    private function cURLDownload(string $from, string $to, bool $overwrite = false): string
    {
        $output = '';
        if ($this->storage->exists($to)) {
            if ($overwrite) {
                $output .= _t('FILE') . ' ' . $to . ' ' . _t('FILE_OVERWRITE') . '.';
            } else {
                $output .= _t('FILE') . ' ' . $to . ' ' . _t('FILE_NO_OVERWRITE') . '.';

                return $output;
            }
        }

        // A scratch file, then Storage: curl needs a stream and the destination may be a bucket.
        // It also means a download that fails leaves nothing behind rather than a corrupted
        // attachment somebody has to notice and delete.
        return $this->storage->withTemporaryFile(pathinfo($to, PATHINFO_EXTENSION), function (string $tmpPath) use ($from, $to, $output) {
            $fp = $this->localFiles->openForWriting($tmpPath);
            if ($fp === null) {
                throw new \Exception($output . _t('ERROR_DOWNLOADING') . ' ' . $from . ': ' . $to);
            }
            $ch = curl_init($from);
            curl_setopt($ch, CURLOPT_FILE, $fp);
            curl_setopt($ch, CURLOPT_HEADER, false);
            curl_setopt($ch, CURLOPT_FAILONERROR, true);
            curl_exec($ch);
            $err = curl_error($ch);
            curl_close($ch);
            fclose($fp);

            if ($err) {
                throw new \Exception($output . _t('ERROR_DOWNLOADING') . ' ' . $from . ': ' . $err . "\n" . _t('REMOVING_CORRUPTED_FILE') . ' ' . $to);
            }

            $this->storage->writeFrom($to, $tmpPath);

            return $output;
        });
    }

    /**
     * Return fields that may contain attachments to import (body for wikipage, or textelong fields for bazar entries).
     *
     * @param array<string, mixed> $wikiPage page or entry content as an array
     *
     * @return list<string> keys of $wikiPage that may contain attachments to import
     */
    public function getTextFieldsFromWikiPage(array $wikiPage): array
    {
        $fields = [];
        if (!empty($wikiPage['form_id'])) {
            $formManager = $this->container->get(FormManager::class);
            $form = $formManager->getOne($wikiPage['form_id']);

            foreach ($form['prepared'] ?? [] as $field) {
                if ($field instanceof TextareaField && $field->getName() !== null) {
                    $fields[] = $field->getName();
                }
            }
        } elseif (!empty($wikiPage['tag'])) {
            $fields[] = 'body';
        }

        return $fields;
    }

    /**
     * Get attachements from raw page content.
     *
     * @param string $tag page id
     *
     * @return list<array{path: string, size: int, humanSize: string}> the stored files each
     *                                                                 `{{attach}}` in the page resolves to
     */
    public function findDirectLinkAttachements(string $tag = ''): array
    {
        if (empty(trim($tag))) {
            $tag = $this->container->get(\YesWiki\Kernel\Service\PageContext::class)->getTag();
        }
        $rawContent = PageBody::content($this->container->get(PageManager::class)->getOne($tag)['body'] ?? []);
        $regex = '#\{\{attach.*file="(.*)".*\}\}#Ui';
        preg_match_all(
            $regex,
            $rawContent,
            $attachments
        );
        $filesMatched = [];
        foreach ($attachments[1] as $a) {
            $ext = pathinfo($a, PATHINFO_EXTENSION);
            $filename = pathinfo($a, PATHINFO_FILENAME);
            $searchPattern = '`^' . $tag . '_' . $filename . '_\d{14}_\d{14}\.' . $ext . '_?$`';
            $path = $this->getLocalFileUploadPath();
            foreach ($this->storage->files($path) as $filePath) {
                if (!preg_match($searchPattern, basename($filePath))) {
                    continue;
                }
                $size = $this->storage->fileSize($filePath);
                $filesMatched[] = ['path' => $filePath, 'size' => $size, 'humanSize' => $this->humanFilesize($size)];
            }
        }

        return $filesMatched;
    }

    public function humanFilesize(int $bytes, int $decimals = 2): string
    {
        $units = ['', 'K', 'M', 'G', 'T'];
        $factor = (int)min(floor((strlen((string)$bytes) - 1) / 3), count($units) - 1);

        return sprintf("%.{$decimals}f", $bytes / pow(1024, $factor)) . $units[$factor] . 'B';
    }

    /**
     * Generate distant file url and download to local file path.
     *
     * @param string $remoteUrl distant file url
     * @param string $filename  file name
     * @param bool   $overwrite overwrite existing file ? (default:false)
     *
     * @return string the curl error, or '' when the download succeeded
     */
    public function downloadDirectLinkAttachment(string $remoteUrl, string $filename, bool $overwrite = false): string
    {
        $remoteFileUrl = $remoteUrl . '/files/' . $filename;
        $saveFileLoc = $this->getLocalFileUploadPath() . '/' . $filename;

        return $this->cURLDownload($remoteFileUrl, $saveFileLoc, $overwrite);
    }

    /**
     * Find file attachments in page or bazar entry It finds attachments linked with /download links.
     *
     * @param string               $remoteUrl distant url
     * @param array<string, mixed> $wikiPage  page or entry content as an array
     * @param bool                 $transform transform attachments urls for their new location (default:false)
     *
     * @return list<string> all file attachments
     */
    public function findHiddenAttachments(string $remoteUrl, array &$wikiPage, bool $transform = false): array
    {
        preg_match_all(
            '#(?:href|src)="' . preg_quote($remoteUrl, '#') . '\?.+/download&(?:amp;)?file=(?P<filename>.*)"#Ui',
            (string)($wikiPage['html_output'] ?? ''),
            $htmlMatches
        );
        $attachments = $htmlMatches['filename'];

        $wikiRegex = '#="' . preg_quote($remoteUrl, '#')
            . '(?P<trail>\?.+/download&(?:amp;)?file=(?P<filename>.*))"#Ui';

        $contentKeys = $this->getTextFieldsFromWikiPage($wikiPage);
        foreach ($contentKeys as $key) {
            preg_match_all($wikiRegex, (string)($wikiPage[$key] ?? ''), $wikiMatches);
            $attachments = array_merge($attachments, $wikiMatches['filename']);
        }

        $attachments = array_values(array_unique($attachments));

        if ($transform) {
            foreach ($contentKeys as $key) {
                $wikiPage[$key] = preg_replace(
                    $wikiRegex,
                    '="' . $this->urlFormatter->getBaseUrl() . '${trail}"',
                    (string)($wikiPage[$key] ?? '')
                );
            }
        }

        return $attachments;
    }

    /**
     * Generate local path and download hidden attachments It downloads attachments linked with /download links.
     *
     * @param string $remoteUrl      distant url
     * @param string $pageTag        page tag
     * @param string $lastPageUpdate last update time
     * @param string $filename       file name
     * @param bool   $overwrite      overwrite existing file ? (default:false)
     *
     * @return void it downloads the file; the `@return array all file attachments` this
     *              carried described a different method entirely
     */
    public function downloadHiddenAttachment(string $remoteUrl, string $pageTag, string $lastPageUpdate, string $filename, bool $overwrite = false): void
    {
        $this->container->get(\YesWiki\Kernel\Service\PageContext::class)->setTag($pageTag);
        $this->container->get(\YesWiki\Kernel\Service\PageContext::class)->setPage(['tag' => $pageTag, 'time' => $lastPageUpdate]);

        $remoteFileUrl = $remoteUrl . '?' . $pageTag . '/download&file=' . $filename;
        $newFilename = $this->container->get(AttachedFilePaths::class)->fullFilename($filename, true);

        $this->cURLDownload($remoteFileUrl, $newFilename, $overwrite);
    }

    /**
     * All type of attachment related to a page or a bazar entry.
     *
     * @param string               $remoteUrl distant url
     * @param array<string, mixed> $wikiPage  page or entry content as an array
     * @param bool                 $overwrite overwrite existing file ? (default:false)
     */
    public function downloadAttachments(string $remoteUrl, array &$wikiPage, bool $overwrite = false): void
    {
        $directLinks = $this->findDirectLinkAttachements((string)($wikiPage['tag'] ?? ''));

        foreach ($directLinks as $directLink) {
            $this->downloadDirectLinkAttachment($remoteUrl, basename($directLink['path']), $overwrite);
        }

        $attachments = $this->findHiddenAttachments($remoteUrl, $wikiPage, true);

        foreach ($attachments as $attachment) {
            $this->downloadHiddenAttachment($remoteUrl, (string)($wikiPage['tag'] ?? ''), date('Y-m-d H:i:s'), $attachment, $overwrite);
        }
    }
}

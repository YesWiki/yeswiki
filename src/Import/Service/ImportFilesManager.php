<?php

namespace YesWiki\Import\Service;

use Psr\Container\ContainerInterface;
use YesWiki\Content\Entity\PageBody;
use YesWiki\Content\Field\TextareaField;
use YesWiki\Content\Service\FormManager;
use YesWiki\Content\Service\PageManager;
use YesWiki\Files\Service\AttachedFilePaths;
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
    public function __construct(ContainerInterface $container, UrlFormatter $urlFormatter)
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
        if (file_exists($to)) {
            if ($overwrite) {
                $output .= _t('FILE') . ' ' . $to . ' ' . _t('FILE_OVERWRITE') . '.';
            } else {
                $output .= _t('FILE') . ' ' . $to . ' ' . _t('FILE_NO_OVERWRITE') . '.';

                return $output;
            }
        }

        $fp = fopen($to, 'wb');
        if ($fp === false) {
            // without this the handle went to curl_setopt() as `false`, which curl reads as
            // "write to stdout": the download landed in the page instead of in the file
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
            unlink($to);
            throw new \Exception($output . _t('ERROR_DOWNLOADING') . ' ' . $from . ': ' . $err . "\n" . _t('REMOVING_CORRUPTED_FILE') . ' ' . $to);
        }

        return $output;
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
        // an entry carries a `tag` of its own, so the entry test has to come first and has to
        // be the one that tells the two apart: both arms used to read `!empty($wikiPage['tag'])`,
        // which made the entry arm unreachable and left every long-text field of every imported
        // entry unscanned for attachments
        if (!empty($wikiPage['form_id'])) {
            $formManager = $this->container->get(FormManager::class);
            $form = $formManager->getOne($wikiPage['form_id']);

            foreach ($form['prepared'] ?? [] as $field) {
                // an unnamed field addresses no key of the entry, so there is nothing to scan
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
        // used to be built inside an `if (is_array($attachments[1]))` that is always true, and
        // returned below whether or not that branch ran -- a page whose content had no
        // `{{attach}}` at all reached the return with the variable never assigned
        $filesMatched = [];
        foreach ($attachments[1] as $a) {
            $ext = pathinfo($a, PATHINFO_EXTENSION);
            $filename = pathinfo($a, PATHINFO_FILENAME);
            $searchPattern = '`^' . $tag . '_' . $filename . '_\d{14}_\d{14}\.' . $ext . '_?$`';
            $path = $this->getLocalFileUploadPath();
            $fh = opendir($path);
            if ($fh === false) {
                continue;
            }
            while (($file = readdir($fh)) !== false) {
                if (strcmp($file, '.') == 0 || strcmp($file, '..') == 0 || is_dir($file)) {
                    continue;
                }
                if (preg_match($searchPattern, $file)) {
                    $filePath = $path . '/' . $file;
                    $size = filesize($filePath) ?: 0;
                    $humanSize = $this->humanFilesize($size);
                    $filesMatched[] = ['path' => $filePath, 'size' => $size, 'humanSize' => $humanSize];
                }
            }
            closedir($fh);
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
        // findDirectLinkAttachements() takes a page tag and nothing else; it was called with
        // the remote url and two arguments it does not have, so it looked up a page named
        // after the url, found none, and every `{{attach}}` went undownloaded
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

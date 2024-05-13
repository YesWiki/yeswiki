<?php

namespace YesWiki\Core\Service;

use YesWiki\Bazar\Field\TextareaField;
use YesWiki\Bazar\Service\FormManager;
use YesWiki\Bazar\Service\EntryManager;
use YesWiki\Core\Service\PageManager;
use YesWiki\Wiki;

class ImportFilesManager
{
    protected $uploadPath;
    protected $wiki;


    /**
     * ImportManager constructor
     * @param Wiki $wiki the injected Wiki instance
     */
    public function __construct(Wiki $wiki)
    {
        $this->wiki = $wiki;
        $this->uploadPath = null;
    }

    /**
     * Get the local path to files uploads (usually "files")
     *
     * @return string local path to files uploads
     */
    private function getLocalFileUploadPath()
    {
        if ($this->uploadPath !== null) {
            return $this->uploadPath;
        }

        $attachConfig = $this->wiki->config['attach_config'];

        if (!is_array($attachConfig)) {
            $attachConfig = array();
        }

        if (empty($attachConfig['upload_path'])) {
            $this->uploadPath = 'files';
        } else {
            $this->uploadPath = $attachConfig['upload_path'];
        }

        return $this->uploadPath;
    }

    /**
     * Download file url to local wiki using cURL
     *
     * @param string $from file url
     * @param string $to local path
     * @param boolean $overwrite overwrite existing file ? (default:false)
     * @return void
     */
    private function cURLDownload($from, $to, $overwrite = false)
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

        // Do cURL transfer
        $fp = fopen($to, 'wb');
        $ch = curl_init($from);
        curl_setopt($ch, CURLOPT_FILE, $fp);
        curl_setopt($ch, CURLOPT_HEADER, 0);
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
     * Return fields that may contain attachments to import (body for wikipage, or textelong fields for bazar entries)
     *
     * @param array $wikiPage page or entry content as an array
     * @return array keys of $wikiPage that may contain attachments to import
     */
    public function getTextFieldsFromWikiPage($wikiPage)
    {
        $fields = [];
        if (!empty($wikiPage['tag'])) { // classic wiki page
            $fields[] = 'body';
        } elseif (!empty($wikiPage['id_fiche'])) { // bazar entry
            $formManager = $this->wiki->services->get(FormManager::class);
            $form = $formManager->getOne($wikiPage['id_typeannonce']);
            // find fields that are textareas
            foreach ($form['prepared'] as $field) {
                if ($field instanceof TextareaField) {
                    $fields[] = $field->getName();
                }
            }
        }
        return $fields;
    }

    /**
     * Get attachements from raw page content
     *
     * @param string $tag page id
     * @return array attachments filenames
     */
    public function findDirectLinkAttachements($tag = '')
    {
        if (empty(trim($tag))) {
            $tag = $this->wiki->GetPageTag();
        }
        $rawContent = $this->wiki->services->get(PageManager::class)->getOne($tag)['body'];
        $regex = '#\{\{attach.*file="(.*)".*\}\}#Ui';
        preg_match_all(
            $regex,
            $rawContent,
            $attachments
        );
        if (is_array($attachments[1])) {
            $filesMatched = [];
            foreach ($attachments[1] as $a) {
                $ext = pathinfo($a, PATHINFO_EXTENSION);
                $filename = pathinfo($a, PATHINFO_FILENAME);
                $searchPattern = '`^' . $tag . '_' . $filename . '_\d{14}_\d{14}\.' . $ext . '_?$`';
                $path = $this->getLocalFileUploadPath();
                $fh = opendir($path);
                while (($file = readdir($fh)) !== false) {
                    if (strcmp($file, '.') == 0 || strcmp($file, '..') == 0 || is_dir($file)) {
                        continue;
                    }
                    if (preg_match($searchPattern, $file)) {
                        $filePath = $path . '/' . $file;
                        $size = filesize($filePath);
                        $humanSize = $this->humanFilesize($size);
                        $filesMatched[] = ['path' => $filePath, 'size' => $size, 'humanSize' => $humanSize];
                    }
                }
            }
        }
        return $filesMatched;
    }

    public function humanFilesize($bytes, $decimals = 2)
    {
        $factor = floor((strlen($bytes) - 1) / 3);
        if ($factor > 0) {
            $sz = 'KMGT';
        }
        return sprintf("%.{$decimals}f", $bytes / pow(1024, $factor)) . @$sz[$factor - 1] . 'B';
    }

    /**
     * Generate distant file url and download to local file path
     *
     * @param string $remoteUrl distant file url
     * @param string $filename file name
     * @param boolean $overwrite overwrite existing file ? (default:false)
     * @return void
     */
    public function downloadDirectLinkAttachment($remoteUrl, $filename, $overwrite = false)
    {
        $remoteFileUrl = $remoteUrl . '/files/' . $filename;
        $saveFileLoc = $this->getLocalFileUploadPath() . '/' . $filename;

        return $this->cURLDownload($remoteFileUrl, $saveFileLoc, $overwrite);
    }

    /**
     * Find file attachments in page or bazar entry
     * It finds attachments linked with /download links
     *
     * @param string $remoteUrl distant url
     * @param array $wikiPage page or entry content as an array
     * @param boolean $transform transform attachments urls for their new location (default:false)
     * @return array all file attachments
     */
    public function findHiddenAttachments($remoteUrl, &$wikiPage, $transform = false)
    {
        preg_match_all(
            '#(?:href|src)="' . preg_quote($remoteUrl, '#') . '\?.+/download&(?:amp;)?file=(?P<filename>.*)"#Ui',
            $wikiPage['html_output'],
            $htmlMatches
        );
        $attachments = $htmlMatches['filename'];

        $wikiRegex = '#="' . preg_quote($remoteUrl, '#')
            . '(?P<trail>\?.+/download&(?:amp;)?file=(?P<filename>.*))"#Ui';

        $contentKeys = $this->getTextFieldsFromWikiPage($wikiPage);
        foreach ($contentKeys as $key) {
            preg_match_all($wikiRegex, $wikiPage[$key], $wikiMatches);
            $attachments = array_merge($attachments, $wikiMatches['filename']);
        }

        $attachments = array_unique($attachments);

        if ($transform) {
            foreach ($contentKeys as $key) {
                $wikiPage[$key] = preg_replace($wikiRegex, '="' . $this->wiki->getBaseUrl() . '${trail}"', $wikiPage[$key]);
            }
        }

        return $attachments;
    }

    /**
     * Generate local path and download hidden attachments
     * It downloads attachments linked with /download links
     *
     * @param string $remoteUrl distant url
     * @param string $pageTag page tag
     * @param string $lastPageUpdate last update time
     * @param string $filename file name
     * @param boolean $overwrite overwrite existing file ? (default:false)
     * @return array all file attachments
     */
    public function downloadHiddenAttachment($remoteUrl, $pageTag, $lastPageUpdate, $filename, $overwrite = false)
    {
        if (!class_exists('attach')) {
            require_once("tools/attach/libs/attach.lib.php");
        }

        $this->wiki->tag = $pageTag;
        $this->wiki->page = array('tag' => $pageTag, 'time' => $lastPageUpdate);

        $remoteFileUrl = $remoteUrl . '?' . $pageTag . '/download&file=' . $filename;
        $att = new \attach($this->wiki);
        $att->file = $filename;
        $newFilename = $att->GetFullFilename(true);

        $this->cURLDownload($remoteFileUrl, $newFilename, $overwrite);
    }


    /**
     * All type of attachment related to a page or a bazar entry
     *
     * @param string $remoteUrl distant url
     * @param array $wikiPage page or entry content as an array
     * @param boolean $overwrite overwrite existing file ? (default:false)
     * @return void
     */
    public function downloadAttachments($remoteUrl, &$wikiPage, $overwrite = false)
    {
        // Handle Pictures and file attachments
        $attachments = $this->findDirectLinkAttachements($remoteUrl, $wikiPage, true);

        if (count($attachments)) {
            foreach ($attachments as $image) {
                $this->downloadDirectLinkAttachment($remoteUrl, $image, $overwrite);
            }
        }

        // Downloading hidden attachments
        $attachments = $this->findHiddenAttachments($remoteUrl, $wikiPage, true);

        if (!empty($attachments)) {
            foreach ($attachments as $attachment) {
                $this->downloadHiddenAttachment($remoteUrl, $wikiPage['id_fiche'], date("Y-m-d H:i:s"), $attachment, $overwrite);
            }
        }
    }
}

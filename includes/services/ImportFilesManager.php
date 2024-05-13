<?php

namespace YesWiki\Core\Service;

use YesWiki\Bazar\Field\FileField;
use YesWiki\Bazar\Field\ImageField;
use YesWiki\Bazar\Field\TextareaField;
use YesWiki\Bazar\Service\FormManager;
use YesWiki\Bazar\Service\EntryManager;
use YesWiki\Bazar\Service\ListManager;
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
        $this->uploadPath = $this->getLocalFileUploadPath();
    }

    /**
     * Get the local path to files uploads (usually "files")
     *
     * @return string local path to files uploads
     */
    private function getLocalFileUploadPath()
    {
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
     * Return fields that may contain attachments to import (fichier, image, or textelong fields for bazar entries)
     *
     * @param array $id
     * @return array keys of fields that may contain attachments to import
     */
    public function getUploadFieldsFromEntry($id)
    {
        $fields = [];
        $entry = $this->wiki->services->get(EntryManager::class)->getOne($id);
        if (!empty($entry['id_fiche'])) { // bazar entry
            $formManager = $this->wiki->services->get(FormManager::class);
            $form = $formManager->getOne($entry['id_typeannonce']);
            // find fields that are textareas
            foreach ($form['prepared'] as $field) {
                if ($field instanceof TextareaField or $field instanceof ImageField or $field instanceof FileField) {
                    $fields[] = [
                        'id' => $field->getPropertyName(),
                        'type' => $field->getType()
                    ];
                }
            }
        }
        return $fields;
    }

    public function findFilesInUploadField($fieldValue)
    {
        $f = $this->uploadPath . '/' . $fieldValue;
        if (file_exists($f)) {
            $size = filesize($f);
            $humanSize = $this->humanFilesize($size);
            return ['path' => $f, 'size' => $size, 'humanSize' => $humanSize];
        } else {
            return [];
        }
    }

    /**
     * find files in wiki text 
     *
     * @param string $wikiTag
     * @param string $wikiText
     * @return array files
     */
    public function findFilesInWikiText($tag, $wikiText)
    {
        $filesMatched = [];
        $regex = '#\{\{attach.*file="(.*)".*\}\}#Ui';
        preg_match_all(
            $regex,
            $wikiText,
            $attachments
        );
        if (is_array($attachments[1])) {
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
        $fileUrlRegex = '#' . preg_quote(str_replace('?', '', $this->wiki->config['base_url']), '#') .
            '(files/.*\.[a-zA-Z0-9]{1,16}\b([-a-zA-Z0-9!@:%_\+.~\#?&\/\/=]*))#Ui';
        preg_match_all(
            $fileUrlRegex,
            $wikiText,
            $fileUrls
        );
        foreach ($fileUrls[1] as $f) {
            if (file_exists($f)) {
                $size = filesize($f);
                $humanSize = $this->humanFilesize($size);
                $filesMatched[] = ['path' => $f, 'size' => $size, 'humanSize' => $humanSize];
            }
        }
        return $filesMatched;
    }

    /**
     * Get file attachements from pageTag
     * 
     * @param string $tag page id
     * @return array attachments filenames
     */
    public function findFiles($tag = '')
    {
        $files = [];
        if (empty(trim($tag))) {
            $tag = $this->wiki->GetPageTag();
        }
        if ($this->wiki->services->get(EntryManager::class)->isEntry($tag)) {
            // bazar 
            $fields = $this->getUploadFieldsFromEntry($tag);
            $entry = $this->wiki->services->get(EntryManager::class)->getOne($tag);
            foreach ($fields as $f) {
                if ($f['type'] == 'image' || $f['type'] == 'fichier') {
                    if (!empty($fi = $this->findFilesInUploadField($entry[$f['id']]))) {
                        $files[] = $fi;
                    }
                } elseif ($f['type'] == 'textelong') {
                    if (!empty($fi = $this->findFilesInWikiText($tag, $entry[$f['id']]))) {
                        $files = array_merge($files, $fi);
                    }
                }
            }
        } elseif (!$this->wiki->services->get(ListManager::class)->isList($tag)) { // page
            $wikiText = $this->wiki->services->get(PageManager::class)->getOne($tag)['body'];
            if ($fi = $this->findFilesInWikiText($tag, $wikiText)) {
                $files[] = $fi;
            }
        }
        return $files;
    }

    public function duplicateFiles($fromTag, $toTag)
    {
        $files = $this->findFiles($fromTag);
        foreach ($files as $f) {
            $newPath = preg_replace(
                '~files/' . preg_quote($fromTag, '~') . '_~Ui',
                'files/' . $toTag . '_',
                $f['path']
            );
            // if the file name has not changed, we add newPageTag_ as filename prefix
            if ($f['path'] == $newPath) {
                $newPath = str_replace('files/', 'files/' . $toTag . '_', $newPath);
            }
            copy($f['path'], $newPath);
        }
    }

    public function checkPostData($data)
    {
        if (empty($data['type']) || !in_array($data['type'], ['page', 'list', 'entry'])) {
            throw new \Exception(_t('NO_VALID_DATA_TYPE'));
        }
        if (empty($data['pageTag'])) {
            throw new \Exception(_t('EMPTY_PAGE_TAG'));
        }
        if ($data['type'] != 'page' && empty($data['pageTitle'])) {
            throw new \Exception(_t('EMPTY_PAGE_TITLE'));
        }
        $page = $this->wiki->services->get(PageManager::class)->getOne($data['pageTag']);
        if ($page) {
            throw new \Exception($data['pageTag'] . ' ' . _t('ALREADY_EXISTING'));
        }
        if (empty($data['duplicate-action']) || !in_array($data['duplicate-action'], ['open', 'edit'])) {
            throw new \Exception(_t('NO_DUPLICATE_ACTION') . '.');
        }
        return $data;
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

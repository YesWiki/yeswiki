<?php

namespace YesWiki\Core\Service;

use Exception;
use YesWiki\Bazar\Field\FileField;
use YesWiki\Bazar\Field\ImageField;
use YesWiki\Bazar\Field\TextareaField;
use YesWiki\Bazar\Service\FormManager;
use YesWiki\Bazar\Service\EntryManager;
use YesWiki\Bazar\Service\ListManager;
use YesWiki\Core\Service\PageManager;
use YesWiki\Wiki;

class DuplicationManager
{
    protected $uploadPath;
    protected $wiki;


    /**
     * DuplicationManager constructor
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
        if ($f !== $this->uploadPath . '/' && file_exists($f)) {
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
                $searchPattern = '`^' . $tag . '_' . $filename . '_\d{14}_(\d{14})\.' . $ext . '_?$`';
                $path = $this->getLocalFileUploadPath();
                $fh = opendir($path);
                while (($file = readdir($fh)) !== false) {
                    if (strcmp($file, '.') == 0 || strcmp($file, '..') == 0 || is_dir($file)) {
                        continue;
                    }
                    if (preg_match($searchPattern, $file, $matches)) {
                        $filePath = $path . '/' . $file;
                        $size = filesize($filePath);
                        $humanSize = $this->humanFilesize($size);
                        if (in_array($filename, array_keys($filesMatched)) && $matches[1] < $filesMatched[$filename]['modified']) {
                            continue; // we only take the latest modified version of file
                        }
                        $filesMatched[$filename] = ['path' => $filePath, 'size' => $size, 'humanSize' => $humanSize, 'modified' => $matches[1]];
                    }
                }
            }
        }
        $fileUrlRegex = '#' . preg_quote(str_replace('?', '', $this->wiki->config['base_url']), '#') .
            '(' . $this->uploadPath . '/.*\.[a-zA-Z0-9]{1,16}\b([-a-zA-Z0-9!@:%_\+.~\#?&\/\/=]*))#Ui';
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
                $files = array_merge($files, $fi);
            }
        }
        return $files;
    }

    public function duplicateFiles($fromTag, $toTag)
    {
        $files = $this->findFiles($fromTag);
        $doneFiles = [];
        foreach ($files as $f) {
            $newPath = preg_replace(
                '~' . $this->uploadPath . '/' . preg_quote($fromTag, '~') . '_~Ui',
                $this->uploadPath . '/' . $toTag . '_',
                $f['path']
            );
            // if the file name has not changed, we add newPageTag_ as filename prefix
            if ($f['path'] == $newPath) {
                $newPath = str_replace($this->uploadPath . '/', $this->uploadPath . '/' . $toTag . '_', $newPath);
            }
            copy($f['path'], $newPath);
            $doneFiles[] = [
                'originalFile' => str_replace($this->uploadPath . '/', '', $f['path']),
                'duplicatedFile' => str_replace($this->uploadPath . '/', '', $newPath),
            ];
        }
        return $doneFiles;
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
        if (!$this->wiki->UserIsAdmin()) {
            throw new \Exception(_t('ONLY_ADMINS_CAN_DUPLICATE') . '.');
        }
        $page = $this->wiki->services->get(PageManager::class)->getOne($data['pageTag']);
        if ($page) {
            throw new \Exception($data['pageTag'] . ' ' . _t('ALREADY_EXISTING'));
        }
        if (empty($data['duplicate-action']) || !in_array($data['duplicate-action'], ['open', 'edit', 'return'])) {
            throw new \Exception(_t('NO_DUPLICATE_ACTION') . '.');
        }
        return $data;
    }

    public function duplicateLocally($data)
    {
        if (!$this->wiki->UserIsAdmin()) {
            throw new \Exception(_t('ONLY_ADMINS_CAN_DUPLICATE') . '.');
        }
        switch ($data['type']) {
            case 'list':
                $list = $this->wiki->services->get(ListManager::class)->getOne($this->wiki->getPageTag());
                $this->wiki->services->get(ListManager::class)->create($data['pageTitle'], $list['label'], $data['pageTag']);
                break;

            case 'entry':
                $files = $this->duplicateFiles($this->wiki->getPageTag(), $data['pageTag']);
                $entry = $this->wiki->services->get(EntryManager::class)->getOne($this->wiki->getPageTag());
                $fields = $this->getUploadFieldsFromEntry($this->wiki->GetPageTag());
                foreach ($fields as $f) {
                    foreach ($files as $fi) {
                        $entry[$f['id']] = str_replace($fi['originalFile'], $fi['duplicatedFile'], $entry[$f['id']]);
                    }
                }
                $entry['id_fiche'] = $data['pageTag'];
                $entry['bf_titre'] = $data['pageTitle'];
                $entry['antispam'] = 1;
                $this->wiki->services->get(EntryManager::class)->create($entry['id_typeannonce'], $entry);
                break;

            default:
            case 'page':
                $newBody = $this->wiki->page['body'];
                $files = $this->duplicateFiles($this->wiki->getPageTag(), $data['pageTag']);
                foreach ($files as $f) {
                    $newBody = str_replace($f['originalFile'], $f['duplicatedFile'], $newBody);
                }
                $this->wiki->services->get(PageManager::class)->save($data['pageTag'], $newBody);
                break;
        }

        // duplicate acls
        foreach (['read', 'write', 'comment'] as $privilege) {
            $values = $this->wiki->services->get(AclService::class)->load(
                $this->wiki->getPageTag(),
                $privilege
            );

            $this->wiki->services->get(AclService::class)->save(
                $data['pageTag'],
                $privilege,
                $values['list']
            );
        }

        // duplicate metadatas and tags (TODO: is there more duplicable triples?)
        $properties = [
            'http://outils-reseaux.org/_vocabulary/metadata',
            'http://outils-reseaux.org/_vocabulary/tag'
        ];
        foreach ($properties as $prop) {
            $values = $this->wiki->services->get(TripleStore::class)->getAll($this->wiki->GetPageTag(), $prop, '', '');
            foreach ($values as $val) {
                $this->wiki->services->get(TripleStore::class)->create($data['pageTag'], $prop, $val['value'], '', '');
            }
        }
    }

    public function importDistantContent($tag, $request)
    {
        if ($this->wiki->services->get(PageManager::class)->getOne($tag)) {
            throw new Exception(_t('ACEDITOR_LINK_PAGE_ALREADY_EXISTS'));
            return;
        }
        $req = $request->request->all();
        foreach (['pageContent', 'sourceUrl', 'originalTag', 'type'] as $key) {
            if (empty($req[$key])) {
                throw new Exception(_t('NOT_FOUND_IN_REQUEST', $key));
                return;
            }
        }
        foreach ($req['files'] as $fileUrl) {
            $this->downloadFile($fileUrl, $req['originalTag'], $tag);
        }

        $newUrl =  explode('/?', $this->wiki->config['base_url'])[0];
        $newBody = str_replace($req['sourceUrl'], $newUrl, $req['pageContent']);
        if ($req['type'] === 'page') {
            $this->wiki->services->get(PageManager::class)->save($tag, $newBody);
        } elseif ($req['type'] === 'entry') {
            $entry = json_decode($newBody, true);
            $entry['id_fiche'] = $tag;
            $entry['antispam'] = 1;
            $this->wiki->services->get(EntryManager::class)->create($entry['id_typeannonce'], $entry);
        }
    }

    public function downloadFile($sourceUrl, $fromTag, $toTag, $timeoutInSec = 10)
    {
        $t = explode('/', $sourceUrl);
        $fileName = array_pop($t);
        $destPath = 'files/' . str_replace($fromTag, $toTag, $fileName);
        $fp = fopen($destPath, 'wb');
        $ch = curl_init($sourceUrl);
        curl_setopt($ch, CURLOPT_FILE, $fp);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        // TODO: make options to allow ssl verify
        curl_setopt($ch, CURLOPT_SSL_VERIFYSTATUS, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeoutInSec);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeoutInSec);
        curl_exec($ch);
        curl_close($ch);
        fclose($fp);

        return $destPath;
    }

    public function humanFilesize($bytes, $decimals = 2)
    {
        $factor = floor((strlen($bytes) - 1) / 3);
        if ($factor > 0) {
            $sz = 'KMGT';
        }
        return sprintf("%.{$decimals}f", $bytes / pow(1024, $factor)) . @$sz[$factor - 1] . 'B';
    }
}
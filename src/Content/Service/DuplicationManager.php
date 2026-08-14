<?php

namespace YesWiki\Content\Service;

use Psr\Container\ContainerInterface;
use YesWiki\Content\Entity\PageBody;
use YesWiki\Content\Field\FileField;
use YesWiki\Content\Field\ImageField;
use YesWiki\Content\Field\TextareaField;
use YesWiki\Identity\Service\AclService;
use YesWiki\Kernel\Routing\ReservedTags;
use YesWiki\Kernel\Service\TripleStore;

class DuplicationManager
{
    protected $uploadPath;
    protected ContainerInterface $container;

    /**
     * DuplicationManager constructor.
     *
     * @param ContainerInterface $container service container
     */
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
        $this->uploadPath = $this->getLocalFileUploadPath();
    }

    /**
     * Get the local path to files uploads (usually "files").
     *
     * @return string local path to files uploads
     */
    private function getLocalFileUploadPath()
    {
        $attachConfig = $this->container->get(\YesWiki\Kernel\Service\RuntimeConfig::class)['attach_config'];

        if (!is_array($attachConfig)) {
            $attachConfig = [];
        }

        if (empty($attachConfig['upload_path'])) {
            $this->uploadPath = 'files';
        } else {
            $this->uploadPath = $attachConfig['upload_path'];
        }

        return $this->uploadPath;
    }

    /**
     * Return fields that may contain attachments to import (fichier, image, or textelong fields for bazar entries).
     *
     * @param array $id
     *
     * @return array keys of fields that may contain attachments to import
     */
    private function getUploadFieldsFromEntry($id)
    {
        $fields = [];
        $entry = $this->container->get(EntryManager::class)->getOne($id);
        if (!empty($entry['tag'])) { // bazar entry
            $formManager = $this->container->get(FormManager::class);
            $form = $formManager->getOne($entry['form_id']);
            // find fields that are textareas
            foreach ($form['prepared'] as $field) {
                if ($field instanceof TextareaField || $field instanceof ImageField || $field instanceof FileField) {
                    $fields[] = $field;
                }
            }
        }

        return $fields;
    }

    private function findFilesInUploadField($fieldValue)
    {
        $f = $this->uploadPath . '/' . $fieldValue;
        if ($f !== $this->uploadPath . '/' && file_exists($f)) {
            $size = filesize($f);
            $humanSize = $this->humanFilesize($size);

            return ['path' => $f, 'size' => $size, 'humanSize' => $humanSize];
        }

        return [];
    }

    /**
     * find files in wiki text.
     *
     * @param string $wikiText
     *
     * @return array files
     */
    private function findFilesInWikiText($tag, $wikiText)
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
        $fileUrlRegex = '#' . preg_quote(str_replace('?', '', $this->container->get(\YesWiki\Kernel\Service\RuntimeConfig::class)['base_url']), '#') .
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
     * Get file attachements from newTag.
     *
     * @param string $tag page id
     *
     * @return array attachments filenames
     */
    public function findFiles($tag = '')
    {
        $files = [];
        if (empty(trim($tag))) {
            $tag = $this->container->get(\YesWiki\Kernel\Service\PageContext::class)->getTag();
        }
        if ($this->container->get(EntryManager::class)->isEntry($tag)) {
            // bazar
            $fields = $this->getUploadFieldsFromEntry($tag);
            $entry = $this->container->get(EntryManager::class)->getOne($tag);
            foreach ($fields as $f) {
                if ($f instanceof ImageField || $f instanceof FileField) {
                    if (!empty($fi = $this->findFilesInUploadField($entry[$f->getPropertyName()]))) {
                        $files[] = $fi;
                    }
                } elseif ($f instanceof TextareaField) {
                    if (!empty($fi = $this->findFilesInWikiText($tag, $entry[$f->getPropertyName()]))) {
                        $files = array_merge($files, $fi);
                    }
                }
            }
        } elseif (!$this->container->get(ListManager::class)->isList($tag)) { // page
            $wikiText = PageBody::content($this->container->get(PageManager::class)->getOne($tag)['body'] ?? []);
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
        if (empty($data['type']) || !in_array($data['type'], ['form', 'page', 'list', 'entry'])) {
            throw new \Exception(_t('NO_VALID_DATA_TYPE'));
        }
        if (empty($data['newTag'])) {
            throw new \Exception(_t('EMPTY_PAGE_TAG'));
        }
        if ($data['type'] != 'page' && empty($data['newTitle'])) {
            throw new \Exception(_t('EMPTY_PAGE_TITLE'));
        }
        if (!$this->container->get(AclService::class)->isAdmin()) {
            throw new \Exception(_t('ONLY_ADMINS_CAN_DUPLICATE') . '.');
        }
        // reserved before taken, and with its own wording: "someone already has this, here
        // is a free one" and "nobody can ever have this" are different problems, and a
        // webmaster who is told the wrong one goes looking for a page that does not exist
        // (ticket 20)
        if (ReservedTags::isReserved($data['newTag'])) {
            throw new \Exception(_t('RESERVED_TAG_CANNOT_BE_USED', ['tag' => $data['newTag']]) . ' ' . _t('RESERVED_TAG_TRY_INSTEAD', ['suggestion' => $this->container->get(PageManager::class)->suggestFreeTag($data['newTag'])]));
        }
        $page = $this->container->get(PageManager::class)->getOne($data['newTag']);
        if ($page) {
            throw new \Exception($data['newTag'] . ' ' . _t('ALREADY_EXISTING'));
        }
        if (empty($data['duplicate-action']) || !in_array($data['duplicate-action'], ['open', 'edit', 'return'])) {
            throw new \Exception(_t('NO_DUPLICATE_ACTION') . '.');
        }

        return $data;
    }

    public function duplicateLocally($data)
    {
        if (!$this->container->get(AclService::class)->isAdmin()) {
            throw new \Exception(_t('ONLY_ADMINS_CAN_DUPLICATE') . '.');
        }
        switch ($data['type']) {
            case 'list':
                $list = $this->container->get(ListManager::class)->getOne($data['originalTag']);
                $this->container->get(ListManager::class)->create($data['newTitle'], $list['label'], $data['newTag']);
                break;

            case 'entry':
                $files = $this->duplicateFiles($data['originalTag'], $data['newTag']);
                $entry = $this->container->get(EntryManager::class)->getOne($this->container->get(\YesWiki\Kernel\Service\PageContext::class)->getTag());
                $fields = $this->getUploadFieldsFromEntry($this->container->get(\YesWiki\Kernel\Service\PageContext::class)->getTag());
                foreach ($fields as $f) {
                    foreach ($files as $fi) {
                        $entry[$f->getPropertyName()] = str_replace($fi['originalFile'], $fi['duplicatedFile'], $entry[$f->getPropertyName()]);
                    }
                }
                $entry['tag'] = $data['newTag'];
                // the new name goes in the field this form names its entries with -- the
                // computed `title` is derived on save, so writing that would be discarded,
                // and bf_titre is only right for a form that happens to have one (ticket 11)
                $titleField = $this->container->get(FormPropertiesService::class)
                    ->titleFieldName($this->container->get(FormManager::class)->getOne($entry['form_id']));
                if ($titleField !== null) {
                    $entry[$titleField] = $data['newTitle'];
                }
                $entry['antispam'] = 1;
                $this->container->get(EntryManager::class)->create($entry['form_id'], $entry);
                break;

            default:
            case 'page':
                $newBody = PageBody::content(($this->container->get(\YesWiki\Kernel\Service\PageContext::class)->getPage() ?? [])['body'] ?? []);
                $files = $this->duplicateFiles($data['originalTag'], $data['newTag']);
                foreach ($files as $f) {
                    $newBody = str_replace($f['originalFile'], $f['duplicatedFile'], $newBody);
                }
                $this->container->get(PageManager::class)->save($data['newTag'], [PageBody::CONTENT => $newBody]);
                break;
        }

        // duplicate acls
        foreach (['read', 'write', 'comment'] as $privilege) {
            $values = $this->container->get(AclService::class)->load(
                $this->container->get(\YesWiki\Kernel\Service\PageContext::class)->getTag(),
                $privilege
            );

            $this->container->get(AclService::class)->save(
                $data['newTag'],
                $privilege,
                $values['list']
            );
        }

        // duplicate metadata (versioned pages.metadata column, not a triple -- see
        // PageManager::getMetadata()/setMetadata())
        $originalMetadata = $this->container->get(PageManager::class)->getMetadata($data['originalTag']);
        if (!empty($originalMetadata)) {
            $this->container->get(PageManager::class)->setMetadata($data['newTag'], $originalMetadata);
        }

        // duplicate tags (TODO: is there more duplicable triples?)
        $values = $this->container->get(TripleStore::class)->getAll($data['originalTag'], 'http://outils-reseaux.org/_vocabulary/tag', '', '');
        foreach ($values as $val) {
            $this->container->get(TripleStore::class)->create($data['newTag'], 'http://outils-reseaux.org/_vocabulary/tag', $val['value'], '', '');
        }
    }

    public function importDistantContent($tag, $request)
    {
        if ($this->container->get(PageManager::class)->getOne($tag)) {
            throw new \Exception(_t('ACEDITOR_LINK_PAGE_ALREADY_EXISTS'));
        }
        $req = $request->request->all();
        foreach (['originalContent', 'sourceUrl', 'originalTag', 'type'] as $key) {
            if (empty($req[$key])) {
                throw new \Exception(_t('NOT_FOUND_IN_REQUEST', $key));
            }
        }
        foreach ($req['files'] as $fileUrl) {
            $this->downloadFile($fileUrl, $req['originalTag'], $tag);
        }

        $newUrl = explode('/?', $this->container->get(\YesWiki\Kernel\Service\RuntimeConfig::class)['base_url'])[0];
        // $req comes from the HTTP request : originalContent is still the remote wiki's
        // raw content (markup for a page, a JSON field map for an entry)
        $newBody = str_replace($req['sourceUrl'], $newUrl, $req['originalContent']);
        if ($req['type'] === 'page') {
            $this->container->get(PageManager::class)->save($tag, [PageBody::CONTENT => $newBody]);
        } elseif ($req['type'] === 'entry') {
            $entry = json_decode($newBody, true);
            $entry['tag'] = $tag;
            $entry['antispam'] = 1;
            $this->container->get(EntryManager::class)->create($entry['form_id'], $entry, false, $req['sourceUrl']);
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
        // `@$sz[$factor - 1]` silenced an undefined variable for anything under a kilobyte,
        // where $sz was never assigned. Indexing the unit list directly says the same thing
        // without the suppression -- and without relying on PHP's negative string offsets,
        // where `'KMGT'[-1]` is 'T' rather than nothing (ticket 40).
        $units = ['', 'K', 'M', 'G', 'T'];
        $factor = (int)min(floor((strlen((string)$bytes) - 1) / 3), count($units) - 1);

        return sprintf("%.{$decimals}f", $bytes / pow(1024, $factor)) . $units[$factor] . 'B';
    }
}

<?php

namespace YesWiki\Templates\Service;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use YesWiki\Bazar\Controller\EntryController;
use YesWiki\Bazar\Service\EntryManager;
use YesWiki\Core\Service\AclService;
use YesWiki\Wiki;

class Utils
{
    protected $params;
    protected $wiki;

    public function __construct(
        ParameterBagInterface $params,
        Wiki $wiki
    ) {
        $this->params = $params;
        $this->wiki = $wiki;
    }

    /**
     * Get the first image in the page.
     *
     * @param array  $page   Page info
     * @param string $width  Width of the image
     * @param string $height Height of the image
     *
     * @return string link to the image
     */
    public function getImageFromBody(array $page, string $width, string $height): string
    {
        if (!$this->canRead($page)) {
            return $this->getDefaultOpenGraphImage();
        }

        $image = '';
        if (isset($page['body'])) {
            $images = [];
            preg_match("/\{\{attach.*file=\"(.*\.(?i)(jpe?g|png|webp))\".*\}\}/U", $page['body'], $images);
            if (!empty($images[1])) {
                $image = $this->getResizedFilename($images[1], $page, $page['tag'], $width, $height, true);
            } else {
                $images = [];
                if (preg_match('/"imagebf_image":"(.*)"/U', $page['body'], $images)
                        && !empty($images[1])) {
                    $imageFileName = json_decode('"' . $images[1] . '"', true);
                    if (!empty($imageFileName)) {
                        if (file_exists("files/$imageFileName")) {
                            $image = $this->getResizedFilename("files/$imageFileName", $page, $page['tag'], $width, $height, false);
                        }
                    }
                } else {
                    $images = [];
                    if (preg_match("/<img.*src=\"(.*\.(jpe?g|png))\"/U", $page['body'], $images)
                        && !empty($images[1])) {
                        if (file_exists('files/' . basename($images[1][0]))) {
                            $image = $this->getResizedFilename('files/' . basename($images[1]), $page, $page['tag'], $width, $height, false);
                        }
                    }
                }
            }
        }
        if (empty($image)) {
            return $this->getDefaultOpenGraphImage();
        }

        return $image;
    }

    /**
     * Whether the current reader may read the page the metadata comes from.
     */
    protected function canRead($page): bool
    {
        return !empty($page['tag']) && $this->wiki->services->get(AclService::class)->hasAccess('read', $page['tag']);
    }

    protected function getDefaultOpenGraphImage(): string
    {
        $opengraphImage = $this->params->has('opengraph_image') ? $this->params->get('opengraph_image') : '';
        if (empty($opengraphImage) || !is_string($opengraphImage)) {
            return '';
        }
        if (preg_match('/^https?:\/\//i', $opengraphImage)) {
            return $opengraphImage;
        }
        $opengraphImage = ltrim($opengraphImage, '/');

        return file_exists($opengraphImage) ? "{$this->wiki->getBaseUrl()}/$opengraphImage" : '';
    }

    protected function getResizedFilename(string $fileName, array $page, string $tag, string $width, string $height, bool $extractFullFileName = false): string
    {
        $attach = $this->getAttach();

        $previousTag = $this->wiki->tag;
        $previousPage = $this->wiki->page;
        $this->wiki->tag = $tag;
        $this->wiki->page = $page;
        if ($extractFullFileName) {
            if (!empty($fileName)) {
                $attach->file = $fileName;
                $fileName = $attach->GetFullFilename(false);
            }
        }
        if (!empty($fileName) && file_exists($fileName)) {
            $imageDest = $attach->getResizedFilename($fileName, $width, $height, 'crop');

            if (!empty($imageDest)) {
                if (!file_exists($imageDest)) {
                    $resizedImage = $attach->redimensionner_image(
                        $fileName,
                        $imageDest,
                        $width,
                        $height,
                        'crop'
                    );

                    if (!empty($resizedImage)) {
                        $image = "{$this->wiki->getBaseUrl()}/$resizedImage";
                    }
                } else {
                    $image = "{$this->wiki->getBaseUrl()}/$imageDest";
                }
            }
        }

        unset($attach);
        $this->wiki->tag = $previousTag;
        $this->wiki->page = $previousPage;

        return empty($image) ? '' : $image;
    }

    protected function getAttach()
    {
        if (!class_exists('attach')) {
            include_once 'tools/attach/libs/attach.lib.php';
        }

        return new \Attach($this->wiki);
    }

    /**
     * Verifie si le nombre d'elements graphiques d'un type trouvés et de leur fermeture correspondent.
     *
     * @param $element : name of element
     *
     * return bool vrai si chaque élément est bien fermé
     */
    public function checkGraphicalElements($element, $pagetag, $pagecontent)
    {
        if ($pagecontent == null) {
            $pagecontent = '';
        }
        preg_match_all('/{{\b' . $element . '\b.*}}/Ui', $pagecontent, $matchesaction);
        preg_match_all('/{{end.*elem="' . $element . '".*}}/Ui', $pagecontent, $matchesendaction);

        return count($matchesaction[0]) == count($matchesendaction[0]);
    }

    /**
     * Parcours des dossiers a la recherche de templates.
     *
     * @param      $directory : chemin relatif vers le dossier contenant les templates
     * @param bool $isCustom
     *
     * return array : tableau des themes trouves, ranges par ordre alphabetique
     */
    public function searchTemplateFiles($directory, bool $isCustom = false)
    {
        $tab_themes = [];
        $dir = opendir($directory);
        while ($dir && ($file = readdir($dir)) !== false) {
            if ($file != '.' && $file != '..' && $file != 'CVS' && is_dir($directory . DIRECTORY_SEPARATOR . $file)) {
                $pathToStyles = $directory . DIRECTORY_SEPARATOR . $file . DIRECTORY_SEPARATOR . 'styles';
                if (is_dir($pathToStyles) && $dir2 = opendir($pathToStyles)) {
                    while (false !== ($file2 = readdir($dir2))) {
                        if (substr($file2, -4, 4) == '.css') {
                            $tab_themes[$file]['isCustom'] = $isCustom;
                            $tab_themes[$file]['style'][$file2] = $this->removeExtension($file2);
                        }
                    }
                    closedir($dir2);
                }

                $pathToSquelettes = $directory . DIRECTORY_SEPARATOR . $file . DIRECTORY_SEPARATOR . 'squelettes';
                if (is_dir($pathToSquelettes) && $dir3 = opendir($pathToSquelettes)) {
                    while (false !== ($file3 = readdir($dir3))) {
                        if (substr($file3, -9, 9) == '.tpl.html') {
                            $tab_themes[$file]['isCustom'] = $isCustom;
                            $tab_themes[$file]['squelette'][$file3] = $this->removeExtension($file3, true);
                        }
                    }
                    closedir($dir3);
                }

                $pathToPresets = $directory . DIRECTORY_SEPARATOR . $file . DIRECTORY_SEPARATOR . 'presets';
                if (is_dir($pathToPresets) && $dir4 = opendir($pathToPresets)) {
                    while (false !== ($file4 = readdir($dir4))) {
                        if (substr($file4, -4, 4) == '.css' && file_exists($pathToPresets . '/' . $file4)) {
                            $css = file_get_contents($pathToPresets . '/' . $file4);
                            if (!empty($css)) {
                                $tab_themes[$file]['isCustom'] = $isCustom;
                                $tab_themes[$file]['presets'][$file4] = $css;
                            }
                        }
                    }
                    closedir($dir4);
                    if (isset($tab_themes[$file]['presets']) && is_array($tab_themes[$file]['presets'])) {
                        ksort($tab_themes[$file]['presets']);
                    }
                }
            }
        }
        closedir($dir);

        if (is_array($tab_themes)) {
            ksort($tab_themes);
        }

        return $tab_themes;
    }

    public function removeExtension($filename, bool $onlyTemplate = false)
    {
        if ($onlyTemplate) {
            return preg_replace("/(\.twig|\.tpl.html)$/", '', $filename);
        }

        return preg_replace("/\..*/i", '', $filename);
    }

    public function strIreplacement($search, $replace, $subject)
    {
        $token = chr(1);
        $haystack = strtolower($subject);
        $needle = strtolower($search);
        while (($pos = strpos($haystack, $needle)) !== false) {
            $subject = substr_replace($subject, $token, $pos, strlen($search));
            $haystack = substr_replace($haystack, $token, $pos, strlen($search));
        }
        $subject = str_replace($token, $replace, $subject);

        return $subject;
    }

    /**
     * recupere le parametre data sous forme d'un tableau.
     *
     * @return array or null if not result
     */
    public function getDataParameter()
    {
        $data = $this->wiki->GetParameter('data');
        if (!empty($data)) {
            $datas = [];
            $tab = explode(',', $data);
            foreach ($tab as $req) {
                $tabdecoup = explode('=', $req, 2);
                $key = htmlspecialchars($tabdecoup[0]);
                $datas[$key] = htmlspecialchars(trim($tabdecoup[1]));
            }
            if (is_array($datas)) {
                return $datas;
            }

            return null;
        }

        return null;
    }

    public function postFormat($output)
    {
        $pattern = [
            '/(\<!-- start of buttondropdown -->.*)\<ul\>(.*\<!-- end of buttondropdown --\>)/Uis',
            '/<li>\s*<hr \/>\s*<\/li>/Uis',
        ];
        $replacement = [
            '$1<ul class="dropdown-menu dropdown-menu-right" role="menu">$2',
            '<li class="divider"></li>',
        ];

        return preg_replace($pattern, $replacement, $output);
    }

    /**
     * Récupère les droits de la page désignée en argument et renvoie un tableau.
     *
     * @param string $page
     *
     * @return array()
     */
    public function recupDroits($page)
    {
        $acls = [
            'page' => $page['tag'],
            'lire' => $this->wiki->GetConfigValue('default_read_acl'),
            'lire_default' => true,
            'ecrire' => $this->wiki->GetConfigValue('default_write_acl'),
            'ecrire_default' => true,
            'comment' => $this->wiki->GetConfigValue('default_comment_acl'),
            'comment_default' => true,
        ];
        if (!empty($page['acl_read'])) {
            $acls['lire'] = $page['acl_read'];
            $acls['lire_default'] = false;
        }
        if (!empty($page['acl_write'])) {
            $acls['ecrire'] = $page['acl_write'];
            $acls['ecrire_default'] = false;
        }
        if (!empty($page['acl_comment'])) {
            $acls['comment'] = $page['acl_comment'];
            $acls['comment_default'] = false;
        }

        return $acls;
    }

    /**
     * Get the first title in page.
     *
     * @param array $page Informations de la page
     *
     * @return string The title string
     */
    public function getTitleFromBody($page): string
    {
        if (!$this->canRead($page)) {
            return '';
        }

        $entryManager = $this->wiki->services->get(EntryManager::class);

        if (!isset($page['body']) || !isset($page['tag'])) {
            return '';
        }
        $title = '';

        if ($entryManager->isEntry($page['tag'])) {
            $entry = $entryManager->getOne($page['tag']);
            if (isset($entry['bf_titre'])) {
                $title = $entry['bf_titre'];
            }
        } else {
            if (preg_match('/<h[12].*>\s*(.*)\s*<\/h[12]>/iUs', $page['body'], $titles)) {
                $title = $titles[1];
            } else {
                preg_match_all("/\={6}(.*)\={6}/U", $page['body'], $titles);
                if (is_array($titles[1]) && isset($titles[1][0]) && $titles[1][0] != '') {
                    $title = $this->wiki->Format(trim($titles[1][0]));
                } else {
                    preg_match_all('/={5}(.*)={5}/U', $page['body'], $titles);
                    if (is_array($titles[1]) && isset($titles[1][0]) && $titles[1][0] != '') {
                        $title = $this->wiki->Format(trim($titles[1][0]));
                    }
                }
            }
        }

        return empty($title) ? '' : strip_tags($title);
    }

    /**
     * Get the first title in page.
     *
     * @param array  $page   Page informations
     * @param string $title  The page title
     * @param int    $length Max number of chars (default 300)
     *
     * @return string The title string
     */
    public function getDescriptionFromBody($page, $title, $length = 300)
    {
        if (!$this->canRead($page)) {
            return '';
        }

        $entryManager = $this->wiki->services->get(EntryManager::class);

        if (!isset($page['body'])) {
            return '';
        }
        $desc = '';

        if ($entryManager->isEntry($page['tag'])) {
            $entry = $entryManager->getOne($page['tag']);
            foreach (['description', 'bf_description', 'content', 'bf_content', 'soustitre'] as $prop) {
                if (isset($entry[$prop])) {
                    $desc = _convert($entry[$prop], 'UTF-8');
                }
            }
            if ($desc == '') {
                $desc = $this->wiki->services->get(EntryController::class)->view($entry, '', 0);
            }
        }

        $desc = preg_replace('~<\s*\bscript\b[^>]*>(.*?)<\s*\/\s*script\s*>~Uis', '', $desc);

        $desc = trim(
            preg_replace(
                '!\s+!',
                ' ',
                str_replace(
                    ["\r", "\n"],
                    ' ',
                    html_entity_decode(str_replace($title, '', strip_tags($desc)), ENT_COMPAT | ENT_HTML5)
                )
            )
        );
        $desc = strtok(wordwrap($desc, $length, "…\n"), "\n");

        return $desc;
    }
}

<?php

namespace YesWiki\Templates\Service;

use Attach;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use YesWiki\Bazar\Controller\EntryController;
use YesWiki\Bazar\Service\EntryManager;
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
        $image = '';
        if (isset($page['body'])) {
            // on cherche les actions attach avec image, puis les images bazar
            $images = [];
            preg_match("/\{\{attach.*file=\"(.*\.(?i)(jpe?g|png))\".*\}\}/U", $page['body'], $images);
            if (!empty($images[1])) {
                $image = $this->getResizedFilename($images[1], $page, $page['tag'], $width, $height, true);
            } else {
                $images = [];
                if (preg_match('/"imagebf_image":"(.*)"/U', $page['body'], $images) &&
                        !empty($images[1])) {
                    $imageFileName = json_decode('"' . $images[1] . '"', true);
                    if (!empty($imageFileName)) {
                        if (file_exists("files/$imageFileName")) {
                            $image = $this->getResizedFilename("files/$imageFileName", $page, $page['tag'], $width, $height, false);
                        }
                    }
                } else {
                    $images = [];
                    if (preg_match("/<img.*src=\"(.*\.(jpe?g|png))\"/U", $page['body'], $images) &&
                        !empty($images[1])) {
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

    protected function getDefaultOpenGraphImage(): string
    {
        $image = '';
        if ($this->params->has('opengraph_image')) {
            $opengraphImage = $this->params->get('opengraph_image');
            if (!empty($opengraphImage) &&
                is_string($opengraphImage) &&
                file_exists($opengraphImage)
            ) {
                $image = "{$this->wiki->getBaseUrl()}/$opengraphImage";
            }
        }

        return $image;
    }

    protected function getResizedFilename(string $fileName, array $page, string $tag, string $width, string $height, bool $extractFullFileName = false): string
    {
        $attach = $this->getAttach();

        // current page
        $previousTag = $this->wiki->tag;
        $previousPage = $this->wiki->page;
        // fake page
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

        // reset params
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

        return new Attach($this->wiki);
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
     * @param $directory : chemin relatif vers le dossier contenant les templates
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
     * cree un diaporama a partir d'une PageWiki.
     *
     * @param $pagetag : nom de la PageWiki
     * @param $template : fichier template pour le diaporama
     * @param $class : classe CSS a ajouter au diaporama
     */
    public function printDiaporama($pagetag, $template = 'diaporama_slides.tpl.html', $class = '')
    {
        // On teste si l'utilisateur peut lire la page
        if (!$this->wiki->HasAccess('read', $pagetag)) {
            return '<div class="alert alert-danger">'
                . _t('TEMPLATE_NO_ACCESS_TO_PAGE') . '</div>'
                . $this->wiki->Format('{{login template="minimal.tpl.html"}}');
        } else {
            // On teste si la page existe
            if (!$page = $this->wiki->LoadPage($pagetag)) {
                return '<div class="alert alert-danger">' . _t('TEMPLATE_PAGE_DOESNT_EXIST') . ' (' . $pagetag . ').</div>';
            } else {
                // $body_f = $this->wiki->Format($page["body"], 'wakka', $pagetag);
                // on regarde si on gere la 2d pour reveal
                //preg_match_all('/<h1>.*<\/h1>/m', $body_f, $titles);
                preg_match_all('/======.*======/Um', $page['body'], $titles);
                $istwodimensions = count($titles[0]) > 1;
                $first = true;
                // on decoupe pour chaque titre de niveau 1 ou 2, ou chaque fois que background-image est utilisée
                // $body = preg_split(
                //     '/(.*<h[12]>.*<\/h[12]>)'
                //     .'|(.*<div class="background-image.*">.*<\!-- \/\.background-image -->)/m',
                //     $body_f,
                //     -1,
                //     PREG_SPLIT_DELIM_CAPTURE
                // );
                $body = preg_split(
                    '/(\======.*======)'
                    . '|(=====.*=====)'
                    . '|(\{\{backgroundimage.*\}\}\s*.*\s*\{\{endbackgroundimage\}\})/Um',
                    $page['body'],
                    -1,
                    PREG_SPLIT_DELIM_CAPTURE
                );
                //var_dump($body);break;
                if (!$body) {
                    return '<div class="=alert alert-danger">'
                        . _t('TEMPLATE_PAGE_CANNOT_BE_SLIDESHOW') . ' (' . $pagetag . ').</div>';
                } else {
                    // preparation des tableaux pour le squelette -------------------------
                    $i = 0;
                    $slides = [];
                    $titles = [];
                    $previousistitle = false;
                    foreach ($body as $slide) {
                        $slide = $this->wiki->Format($slide);
                        //var_dump($slide);
                        // s'il a des titres de niveau 1 ou 2 il s'agit des separateurs de diapo
                        if (preg_match('/<h[12]>.*<\/h[12]>/', $slide)) {
                            // s'il y a un titre de niveau 1 qui commence la diapositive, on la deplace en titre
                            // et on gere l'aspect multidimentionnel
                            if (preg_match('/<h1>.*<\/h1>/', $slide)) {
                                if ($istwodimensions) {
                                    if ($first) {
                                        $first = false;
                                    } else {
                                        $slides[$i]['closesection'] = true;
                                    }
                                    $slides[$i]['opensection'] = true;
                                }
                            }
                            //pour les titres de niveau 2, on les transforme en titre 1
                            $titles[$i] = str_replace('<h2', '<h1', $slide);
                            if ($previousistitle) {
                                $slides[$i]['html'] = '';
                                $i++;
                            }
                            $previousistitle = true;
                        } elseif (!empty($slide) || $previousistitle) {
                            $previousistitle = false;
                            $slides[$i]['html'] = $slide;
                            $slides[$i]['title'] = ((isset($titles[$i])) ? strip_tags($titles[$i]) : '');
                            $i++;
                        }
                    }
                }
            }

            $buttons = '';
            //si la fonction est appelee par le handler diaporama, on ajoute les liens d'edition et de retour
            if ($this->wiki->GetMethod() == 'diaporama') {
                $buttons .= '<a class="btn" href="' . $this->wiki->href('', $pagetag) . '">&times;</a>' . "\n";
            }

            // on affiche le template
            $output = $this->wiki->render("@templates/$template", [
                'pagetag' => $pagetag,
                'slides' => $slides,
                'titles' => $titles,
                'buttons' => $buttons,
                'class' => $class,
            ]);

            return $output;
        }
    }

    /**
     * recupere le parametre data sous forme d'un tableau.
     *
     * @return array or null if not result
     */
    public function getDataParameter()
    {
        // container data attributes
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
            } else {
                return null;
            }
        } else {
            return null;
        }
    }

    public function postFormat($output)
    {
        // pour les buttondropdown, on ajoute les classes css aux listes
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
        $readACL = $this->wiki->LoadAcl($page, 'read', false);
        $writeACL = $this->wiki->LoadAcl($page, 'write', false);
        $commentACL = $this->wiki->LoadAcl($page, 'comment', false);

        $acls = [
            'page' => $page,
            'lire' => $this->wiki->GetConfigValue('default_read_acl'),
            'lire_default' => true,
            'ecrire' => $this->wiki->GetConfigValue('default_write_acl'),
            'ecrire_default' => true,
            'comment' => $this->wiki->GetConfigValue('default_comment_acl'),
            'comment_default' => true,
        ];
        if (isset($readACL['list'])) {
            $acls['lire'] = $readACL['list'];
            $acls['lire_default'] = false;
        }
        if (isset($writeACL['list'])) {
            $acls['ecrire'] = $writeACL['list'];
            $acls['ecrire_default'] = false;
        }
        if (isset($commentACL['list'])) {
            $acls['comment'] = $commentACL['list'];
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
    public function getTitleFromBody($page)
    {
        $entryManager = $this->wiki->services->get(EntryManager::class);

        if (!isset($page['body']) || !isset($page['tag'])) {
            return '';
        }
        $title = '';

        if ($entryManager->isEntry($page['tag'])) {
            $entry = $entryManager->getOne($page['tag']);
            if (isset($entry['bf_titre'])) {
                $title = _convert($entry['bf_titre'], 'UTF-8');
            }
        } else {
            // on recupere les bf_titre ou les titres de niveau 1 et de niveau 2
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
        } else {
            // $desc = $this->wiki->Format($page['body'], 'wakka', $page["tag"]);
        }
        // no javascript
        $desc = preg_replace('~<\s*\bscript\b[^>]*>(.*?)<\s*\/\s*script\s*>~Uis', '', $desc);

        // no double space or new lines
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

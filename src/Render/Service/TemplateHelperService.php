<?php

namespace YesWiki\Render\Service;

use Psr\Container\ContainerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use YesWiki\Content\Controller\EntryController;
use YesWiki\Content\Entity\PageBody;
use YesWiki\Content\Service\AttachedFilePaths;
use YesWiki\Content\Service\EntryManager;
use YesWiki\Content\Service\ImageResizer;
use YesWiki\Kernel\Service\PerformableArguments;
use YesWiki\Kernel\Service\UrlFormatter;

class TemplateHelperService
{
    protected $params;
    protected ContainerInterface $container;

    protected UrlFormatter $urlFormatter;
    protected PerformableArguments $performableArguments;

    public function __construct(
        ParameterBagInterface $params,
        ContainerInterface $container,
        UrlFormatter $urlFormatter,
        PerformableArguments $performableArguments
    ) {
        $this->performableArguments = $performableArguments;
        $this->urlFormatter = $urlFormatter;
        $this->params = $params;
        $this->container = $container;
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
            // the body is a decoded array (ticket 09): the wiki markup lives under
            // `content`, an entry's image field is a key of its own
            $body = $page['body'];
            $content = PageBody::content($body);
            // on cherche les actions attach avec image, puis les images bazar
            $images = [];
            preg_match("/\{\{attach.*file=\"(.*\.(?i)(jpe?g|png))\".*\}\}/U", $content, $images);
            if (!empty($images[1])) {
                $image = $this->getResizedFilename($images[1], $page, $page['tag'], $width, $height, true);
            } else {
                $imageFileName = $body['imagebf_image'] ?? '';
                if (!empty($imageFileName)) {
                    if (file_exists("files/$imageFileName")) {
                        $image = $this->getResizedFilename("files/$imageFileName", $page, $page['tag'], $width, $height, false);
                    }
                } else {
                    $images = [];
                    if (preg_match("/<img.*src=\"(.*\.(jpe?g|png))\"/U", $content, $images)
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

    protected function getDefaultOpenGraphImage(): string
    {
        $image = '';
        if ($this->params->has('opengraph_image')) {
            $opengraphImage = $this->params->get('opengraph_image');
            if (!empty($opengraphImage)
                && is_string($opengraphImage)
                && file_exists($opengraphImage)
            ) {
                $image = "{$this->urlFormatter->getBaseUrl()}/$opengraphImage";
            }
        }

        return $image;
    }

    protected function getResizedFilename(string $fileName, array $page, string $tag, string $width, string $height, bool $extractFullFileName = false): string
    {
        $resizer = $this->container->get(ImageResizer::class);

        // current page
        $previousTag = $this->container->get(\YesWiki\Kernel\Service\PageContext::class)->getTag();
        $previousPage = $this->container->get(\YesWiki\Kernel\Service\PageContext::class)->getPage();
        // fake page
        $this->container->get(\YesWiki\Kernel\Service\PageContext::class)->setTag($tag);
        $this->container->get(\YesWiki\Kernel\Service\PageContext::class)->setPage($page);
        if ($extractFullFileName) {
            if (!empty($fileName)) {
                $fileName = $this->container->get(AttachedFilePaths::class)->fullFilename($fileName, false);
            }
        }
        if (!empty($fileName) && file_exists($fileName)) {
            $imageDest = $resizer->resizedFilename($fileName, $width, $height, 'crop');

            if (!empty($imageDest)) {
                if (!file_exists($imageDest)) {
                    $resizedImage = $resizer->resize(
                        $fileName,
                        $imageDest,
                        $width,
                        $height,
                        'crop'
                    );

                    if (!empty($resizedImage)) {
                        $image = "{$this->urlFormatter->getBaseUrl()}/$resizedImage";
                    }
                } else {
                    $image = "{$this->urlFormatter->getBaseUrl()}/$imageDest";
                }
            }
        }

        // reset params
        $this->container->get(\YesWiki\Kernel\Service\PageContext::class)->setTag($previousTag);
        $this->container->get(\YesWiki\Kernel\Service\PageContext::class)->setPage($previousPage);

        return empty($image) ? '' : $image;
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
                        if (str_ends_with($file3, '.twig')) {
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

    public function strIreplacement(string $search, string $replace, string $subject): string
    {
        $token = chr(1);
        $haystack = strtolower($subject);
        $needle = strtolower($search);
        while (($pos = strpos($haystack, $needle)) !== false) {
            $subject = substr_replace($subject, $token, $pos, strlen($search));
            $haystack = substr_replace($haystack, $token, $pos, strlen($search));
        }

        return str_replace($token, $replace, $subject);
    }

    /**
     * recupere le parametre data sous forme d'un tableau.
     *
     * @return array or null if not result
     */
    public function getDataParameter()
    {
        // container data attributes
        $data = $this->performableArguments->get('data');
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

    /**
     * wrap a trimmed icon parameter (from `button`, `buttondropdown`, `nav`) into its <i> markup ;
     * a space in the value means it is a raw class list, not a bare bootstrap/fontawesome icon name.
     */
    public function formatIconHtml(string $icon): string
    {
        $icon = trim($icon);
        if (empty($icon)) {
            return '';
        }
        // Tabler sprite name, or a historic FontAwesome class string that maps onto it
        $sprite = $this->container->get(TemplateEngine::class)->legacyIconToSprite($icon);
        if ($sprite !== null) {
            return $sprite;
        }

        // unmapped stored value: emit it as-is (renders only if the site still ships a
        // matching icon css)
        return '<i class="' . $icon . '"></i>';
    }

    public function postFormat($output)
    {
        // pour les buttondropdown, on ajoute les classes css aux listes
        $pattern = [
            '/(\<!-- start of buttondropdown -->.*)\<ul\>(.*\<!-- end of buttondropdown --\>)/Uis',
            '/<li>\s*<hr \/>\s*<\/li>/Uis',
        ];
        $replacement = [
            '$1<ul class="yw-dropdown__menu">$2',
            '<li class="yw-dropdown__divider"></li>',
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
            'lire' => $this->container->get(\YesWiki\Kernel\Service\RuntimeConfig::class)->getValue('default_read_acl'),
            'lire_default' => true,
            'ecrire' => $this->container->get(\YesWiki\Kernel\Service\RuntimeConfig::class)->getValue('default_write_acl'),
            'ecrire_default' => true,
            'comment' => $this->container->get(\YesWiki\Kernel\Service\RuntimeConfig::class)->getValue('default_comment_acl'),
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
        $entryManager = $this->container->get(EntryManager::class);

        if (!isset($page['body']) || !isset($page['tag'])) {
            return '';
        }
        $title = '';

        if ($entryManager->isEntry($page['tag'])) {
            $entry = $entryManager->getOne($page['tag']);
            // the computed title (ADR-0010), not the field some forms happen to call
            // bf_titre -- which is what CONTEXT.md's Entry title entry already says
            $title = (string)($entry['title'] ?? $entry['bf_titre'] ?? '');
        } else {
            // the markup lives under `content` in the decoded body (ticket 09)
            $content = PageBody::content($page['body']);
            // the first level-1 or level-2 heading names a page that has no stored title
            if (preg_match('/<h[12].*>\s*(.*)\s*<\/h[12]>/iUs', $content, $titles)) {
                $title = $titles[1];
            } else {
                preg_match_all("/\={6}(.*)\={6}/U", $content, $titles);
                if (is_array($titles[1]) && isset($titles[1][0]) && $titles[1][0] != '') {
                    $title = $this->container->get(MarkdownFormatterService::class)->format(trim($titles[1][0]));
                } else {
                    preg_match_all('/={5}(.*)={5}/U', $content, $titles);
                    if (is_array($titles[1]) && isset($titles[1][0]) && $titles[1][0] != '') {
                        $title = $this->container->get(MarkdownFormatterService::class)->format(trim($titles[1][0]));
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
        $entryManager = $this->container->get(EntryManager::class);

        if (!isset($page['body'])) {
            return '';
        }
        $desc = '';

        if ($entryManager->isEntry($page['tag'])) {
            $entry = $entryManager->getOne($page['tag']);
            foreach (['description', 'bf_description', 'content', 'bf_content', 'soustitre'] as $prop) {
                if (isset($entry[$prop])) {
                    $desc = $entry[$prop];
                }
            }
            if ($desc == '') {
                $desc = $this->container->get(EntryController::class)->view($entry, '', 0);
            }
        }
        // $desc = $this->container->get(MarkdownFormatterService::class)->format($page['body'], 'wakka', $page["tag"]);

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

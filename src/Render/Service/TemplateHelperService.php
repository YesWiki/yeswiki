<?php

namespace YesWiki\Render\Service;

use Psr\Container\ContainerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use YesWiki\Content\Entity\PageBody;
use YesWiki\Content\Service\EntryDisplay;
use YesWiki\Content\Service\EntryManager;
use YesWiki\Files\Service\AttachedFilePaths;
use YesWiki\Files\Service\ImageResizer;
use YesWiki\Files\Service\ProgramFiles;
use YesWiki\Files\Service\Storage;
use YesWiki\Kernel\Service\PerformableArguments;
use YesWiki\Kernel\Service\UrlFormatter;

class TemplateHelperService
{
    protected ParameterBagInterface $params;
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

    private function storage(): Storage
    {
        return $this->container->get(Storage::class);
    }

    /**
     * Get the first image in the page.
     *
     * @param array<string, mixed> $page   Page info
     * @param string               $width  Width of the image
     * @param string               $height Height of the image
     *
     * @return string link to the image
     */
    public function getImageFromBody(array $page, string $width, string $height): string
    {
        $image = '';
        if (isset($page['body'])) {
            $body = $page['body'];
            $content = PageBody::content($body);

            $images = [];
            preg_match("/\{\{attach.*file=\"(.*\.(?i)(jpe?g|png))\".*\}\}/U", $content, $images);
            if (!empty($images[1])) {
                $image = $this->getResizedFilename($images[1], $page, $page['tag'], $width, $height, true);
            } else {
                $imageFileName = $body['imagebf_image'] ?? '';
                if (!empty($imageFileName)) {
                    if ($this->storage()->exists("files/$imageFileName")) {
                        $image = $this->getResizedFilename("files/$imageFileName", $page, $page['tag'], $width, $height, false);
                    }
                } else {
                    $images = [];
                    if (preg_match("/<img.*src=\"(.*\.(jpe?g|png))\"/U", $content, $images)) {
                        if ($this->storage()->exists('files/' . basename($images[1]))) {
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
                && $this->storage()->exists($opengraphImage)
            ) {
                $image = "{$this->urlFormatter->getBaseUrl()}/$opengraphImage";
            }
        }

        return $image;
    }

    /**
     * @param array<string, mixed> $page
     */
    protected function getResizedFilename(string $fileName, array $page, string $tag, string $width, string $height, bool $extractFullFileName = false): string
    {
        $resizer = $this->container->get(ImageResizer::class);

        $previousTag = $this->container->get(\YesWiki\Kernel\Service\PageContext::class)->getTag();
        $previousPage = $this->container->get(\YesWiki\Kernel\Service\PageContext::class)->getPage();

        $this->container->get(\YesWiki\Kernel\Service\PageContext::class)->setTag($tag);
        $this->container->get(\YesWiki\Kernel\Service\PageContext::class)->setPage($page);
        if ($extractFullFileName) {
            if (!empty($fileName)) {
                $fileName = $this->container->get(AttachedFilePaths::class)->fullFilename($fileName, false);
            }
        }
        if (!empty($fileName) && $this->storage()->exists($fileName)) {
            $imageDest = $resizer->resizedFilename($fileName, $width, $height, 'crop');

            if (!empty($imageDest)) {
                if (!$this->storage()->exists($imageDest)) {
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

        $this->container->get(\YesWiki\Kernel\Service\PageContext::class)->setTag($previousTag);
        $this->container->get(\YesWiki\Kernel\Service\PageContext::class)->setPage($previousPage);

        return empty($image) ? '' : $image;
    }

    /**
     * Verifie si le nombre d'elements graphiques d'un type trouvés et de leur fermeture correspondent.
     *
     * @param string      $element     name of element
     * @param string      $pagetag     the page being rendered
     * @param string|null $pagecontent its wiki markup
     *
     * @return bool true when every element of this type is closed
     */
    public function checkGraphicalElements($element, $pagetag, $pagecontent): bool
    {
        if ($pagecontent == null) {
            $pagecontent = '';
        }
        preg_match_all('/{{\b' . $element . '\b.*}}/Ui', $pagecontent, $matchesaction);
        preg_match_all('/{{end.*elem="' . $element . '".*}}/Ui', $pagecontent, $matchesendaction);

        return count($matchesaction[0]) == count($matchesendaction[0]);
    }

    /**
     * Every theme in one tree, with the stylesheets, squelettes and presets it offers.
     *
     * `$isCustom` says which tree, and now decides which service is asked rather than only how the
     * answer is labelled: a wiki's own themes are Instance data and live in `custom/themes`, the
     * shipped ones are code and live in the Program. That was the same `opendir` for both, which
     * is why a wiki on object storage listed its own themes off a disk that has none.
     *
     * @return array<string, array<string, mixed>> themes by name, alphabetically
     */
    public function searchTemplateFiles(string $directory, bool $isCustom = false): array
    {
        $themes = [];

        foreach ($this->directoriesIn($directory, $isCustom) as $themePath) {
            $name = basename($themePath);
            if ($name === 'CVS') {
                continue;
            }

            foreach ($this->filesIn($themePath . '/styles', $isCustom) as $style) {
                if (str_ends_with($style, '.css')) {
                    $themes[$name]['isCustom'] = $isCustom;
                    $themes[$name]['style'][basename($style)] = $this->removeExtension(basename($style));
                }
            }

            foreach ($this->filesIn($themePath . '/squelettes', $isCustom) as $squelette) {
                if (str_ends_with($squelette, '.twig')) {
                    $themes[$name]['isCustom'] = $isCustom;
                    $themes[$name]['squelette'][basename($squelette)] = $this->removeExtension(basename($squelette), true);
                }
            }

            foreach ($this->filesIn($themePath . '/presets', $isCustom) as $preset) {
                if (!str_ends_with($preset, '.css')) {
                    continue;
                }
                $css = $this->readIn($preset, $isCustom);
                if ($css !== '') {
                    $themes[$name]['isCustom'] = $isCustom;
                    $themes[$name]['presets'][basename($preset)] = $css;
                }
            }
            if (isset($themes[$name]['presets'])) {
                ksort($themes[$name]['presets']);
            }
        }

        ksort($themes);

        return $themes;
    }

    /**
     * @return list<string>
     */
    private function directoriesIn(string $directory, bool $isCustom): array
    {
        return $isCustom
            ? $this->container->get(Storage::class)->directories($directory)
            : $this->container->get(ProgramFiles::class)->directories($directory);
    }

    /**
     * @return list<string>
     */
    private function filesIn(string $directory, bool $isCustom): array
    {
        return $isCustom
            ? $this->container->get(Storage::class)->files($directory)
            : $this->container->get(ProgramFiles::class)->files($directory);
    }

    private function readIn(string $path, bool $isCustom): string
    {
        if (!$isCustom) {
            return $this->container->get(ProgramFiles::class)->read($path);
        }

        return $this->container->get(Storage::class)->exists($path)
            ? $this->container->get(Storage::class)->read($path)
            : '';
    }

    /**
     * @param string $filename
     *
     * @return string
     */
    public function removeExtension($filename, bool $onlyTemplate = false)
    {
        if ($onlyTemplate) {
            return (string)preg_replace("/(\.twig|\.tpl.html)$/", '', $filename);
        }

        return (string)preg_replace("/\..*/i", '', $filename);
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
     * @return array<string, string> the `key=value` pairs, empty when the parameter is not set
     */
    public function getDataParameter()
    {
        $data = $this->performableArguments->get('data');
        if (!empty($data)) {
            $datas = [];
            $tab = explode(',', $data);
            foreach ($tab as $req) {
                $tabdecoup = explode('=', $req, 2);
                $key = htmlspecialchars($tabdecoup[0]);
                $datas[$key] = htmlspecialchars(trim($tabdecoup[1]));
            }

            return $datas;
        }

        return [];
    }

    /**
     * wrap a trimmed icon parameter (from `button`, `buttondropdown`, `nav`) into its <i> markup ; a space in the value means it is a raw class list, not a bare bootstrap/fontawesome icon name.
     */
    public function formatIconHtml(string $icon): string
    {
        $icon = trim($icon);
        if (empty($icon)) {
            return '';
        }

        $sprite = $this->container->get(TemplateEngine::class)->legacyIconToSprite($icon);
        if ($sprite !== null) {
            return $sprite;
        }

        return '<i class="' . $icon . '"></i>';
    }

    /**
     * @param string $output
     *
     * @return string
     */
    public function postFormat($output)
    {
        $pattern = [
            '/(\<!-- start of buttondropdown -->.*)\<ul\>(.*\<!-- end of buttondropdown --\>)/Uis',
            '/<li>\s*<hr \/>\s*<\/li>/Uis',
        ];
        $replacement = [
            '$1<ul class="yw-dropdown__menu">$2',
            '<li class="yw-dropdown__divider"></li>',
        ];

        return (string)preg_replace($pattern, $replacement, $output);
    }

    /**
     * Récupère les droits de la page désignée en argument et renvoie un tableau.
     *
     * @param array<string, mixed> $page a page row, with its acl columns
     *
     * @return array<int|string, mixed>
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
     * @param array<string, mixed> $page Informations de la page
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

            $title = (string)($entry['title'] ?? $entry['bf_titre'] ?? '');
        } else {
            $content = PageBody::content($page['body']);

            if (preg_match('/<h[12].*>\s*(.*)\s*<\/h[12]>/iUs', $content, $titles)) {
                $title = $titles[1];
            } else {
                preg_match_all("/\={6}(.*)\={6}/U", $content, $titles);
                if (isset($titles[1][0]) && $titles[1][0] != '') {
                    $title = $this->container->get(MarkdownFormatterService::class)->format(trim($titles[1][0]));
                } else {
                    preg_match_all('/={5}(.*)={5}/U', $content, $titles);
                    if (isset($titles[1][0]) && $titles[1][0] != '') {
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
     * @param array<string, mixed> $page   Page informations
     * @param string               $title  The page title
     * @param int                  $length Max number of chars (default 300)
     *
     * @return string The title string
     */
    public function getDescriptionFromBody($page, $title, $length = 300): string
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
                $desc = $this->container->get(EntryDisplay::class)->renderEntryOrNothing($entry);
            }
        }

        $desc = (string)preg_replace('~<\s*\bscript\b[^>]*>(.*?)<\s*\/\s*script\s*>~Uis', '', $desc);

        $desc = trim(
            (string)preg_replace(
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

        return $desc === false ? '' : $desc;
    }
}

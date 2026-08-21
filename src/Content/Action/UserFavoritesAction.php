<?php

namespace YesWiki\Content\Action;

use YesWiki\Content\Entity\PageBody;
use YesWiki\Content\Field\ImageField;
use YesWiki\Content\Service\EntryManager;
use YesWiki\Content\Service\FavoritesManager;
use YesWiki\Content\Service\FormManager;
use YesWiki\Content\Service\PageManager;
use YesWiki\Content\Service\PageSummary;
use YesWiki\Core\YesWikiAction;
use YesWiki\Files\Service\AttachedFilePaths;
use YesWiki\Identity\Service\AuthenticationService;
use YesWiki\Kernel\Performable\RegisteredAction;
use YesWiki\Kernel\Service\PageContext;
use YesWiki\Render\Service\TemplateEngine;

class UserFavoritesAction extends YesWikiAction implements RegisteredAction
{
    /** `{{userfavorites}}` in page content -- stated, not inferred from the filename. */
    public static function performableName(): string
    {
        return 'userfavorites';
    }

    protected AuthenticationService $authenticationService;
    protected EntryManager $entryManager;
    protected FavoritesManager $favoritesManager;
    protected FormManager $formManager;
    protected PageManager $pageManager;
    protected TemplateEngine $templateEngine;

    public function formatArguments($arg)
    {
        return [
            'template' => !empty($arg['template']) ? basename($arg['template']) : '',
        ];
    }

    public function run(): string
    {
        $this->authenticationService = $this->getService(AuthenticationService::class);
        $this->entryManager = $this->getService(EntryManager::class);
        $this->favoritesManager = $this->getService(FavoritesManager::class);
        $this->formManager = $this->getService(FormManager::class);
        $this->pageManager = $this->getService(PageManager::class);
        $this->templateEngine = $this->getService(TemplateEngine::class);

        $user = $this->authenticationService->getLoggedUser();
        $currentUser = empty($user) ? null : $user['name'];

        $favorites = empty($currentUser) ? [] : $this->favoritesManager->getUserFavorites($currentUser);

        $template = (empty($this->arguments['template']) || !$this->templateEngine->hasTemplate("@core/actions/{$this->arguments['template']}"))
            ? '@core/actions/my-favorites.twig'
            : "@core/actions/{$this->arguments['template']}";

        $this->updateFavoritesWithTitleImagesAndEntries($favorites);

        return $this->render($template, [
            'areFavoritesActivated' => $this->favoritesManager->areFavoritesActivated(),
            'currentUser' => $currentUser,
            'favorites' => $favorites,
        ]);
    }

    /** @param array<array-key, array<string, mixed>> $favorites */
    private function updateFavoritesWithTitleImagesAndEntries(array &$favorites): void
    {
        foreach ($favorites as $key => $favorite) {
            if ($this->entryManager->isEntry($favorite['resource'])) {
                $entry = $this->entryManager->getOne($favorite['resource']);
                if (!empty($entry)) {
                    $favorites[$key]['entry'] = $entry;
                    $favorites[$key]['title'] = $entry['title'] ?? $entry['bf_titre'] ?? $favorite['resource'];
                    $form = $this->formManager->getOne($entry['form_id']);
                    if (!empty($form)) {
                        $favorites[$key]['form'] = $form;
                        $imageFields = array_filter($form['prepared'], function ($field) {
                            return $field instanceof ImageField;
                        });
                        if (!empty($imageFields)) {
                            $imageField = $imageFields[array_key_first($imageFields)];
                            if (!empty($entry[$imageField->getPropertyName()])) {
                                $favorites[$key]['image'] = $entry[$imageField->getPropertyName()];
                            }
                        }
                    }
                }
            } else {
                $page = $this->pageManager->getOne($favorite['resource']);
                if (!empty($page)) {
                    $title = $this->getService(PageSummary::class)->title($page);
                    if (!empty($title)) {
                        $favorites[$key]['title'] = $title;
                    }
                    $image = $this->imageFromBody($page);
                    if (!empty($image)) {
                        $favorites[$key]['image'] = $image;
                    }
                }
            }
        }
    }

    /** @param array<string, mixed> $page */
    private function imageFromBody(array $page): string
    {
        $body = PageBody::content($page['body']);

        preg_match_all("/\{\{attach.*file=\".*\.(?i)(jpg|png|gif|bmp).*\}\}/U", $body, $images);
        if (isset($images[0][0]) && $images[0][0] != '') {
            preg_match_all("/.*file=\"(.*\.(?i)(jpg|png|gif|bmp))\".*desc=\"(.*)\".*\}\}/U", $images[0][0], $attachimg);

            return $this->getFileName($page, $attachimg[1][0]);
        }
        preg_match_all('/"imagebf_image":"(.*)"/U', $body, $image);
        if (isset($image[1][0]) && $image[1][0] != '') {
            $imagefile = mb_convert_encoding(
                preg_replace_callback(
                    '/\\\\u([a-f0-9]{4})/',
                    // was the global `encodingFromUTF8()`, reached by name through a string
                    // callback -- which is why no grep for its name found a caller (ticket 50)
                    static fn (array $matches): string => (string)iconv('UCS-4LE', 'UTF-8', pack('V', hexdec($matches[1]))),
                    $image[1][0]
                ),
                'ISO-8859-1',
                'UTF-8'
            );

            return $imagefile;
        }
        preg_match_all("/\[\[(http.*\.(?i)(jpg|png|gif|bmp)) .*\]\]/U", $body, $image);
        if (isset($image[1][0]) && $image[1][0] != '') {
            return $image[1][0];
        }
        preg_match_all("/\<img.*src=\"(.*)\"/U", $body, $image);
        if (isset($image[1][0]) && $image[1][0] != '') {
            return $image[1][0];
        }

        return '';
    }

    /**
     * @param array<string, mixed> $page
     * @param string               $file
     */
    private function getFileName($page, $file): string
    {
        $oldpagetag = $this->getService(PageContext::class)->getTag();
        $oldpage = $this->getService(PageContext::class)->getPage();
        $this->getService(PageContext::class)->setTag($page['tag']);
        $this->getService(PageContext::class)->setPage($page);

        $fullFileName = $this->getService(AttachedFilePaths::class)->fullFilename($file);

        if (substr($fullFileName, 0, strlen('files/')) == 'files/') {
            $fullFileName = substr($fullFileName, strlen('files/'));
        }

        $this->getService(PageContext::class)->setTag($oldpagetag);
        $this->getService(PageContext::class)->setPage($oldpage);

        return $fullFileName;
    }
}

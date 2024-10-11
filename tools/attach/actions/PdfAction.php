<?php
/**
 * Action to display a pdf in an embedded reader.
 *
 * @param url  required The url of the pdf file. The url has to be from the same origin than the wiki (same schema, same host & same port)
 * @param ratio shape for the container : possible values empty (default), 'portrait' - 'paysage' - 'carre'
 * @param largeurmax  the maximum wanted width ; number without "px"
 * @param hauteurmax  the maximum wanted heigth ; number without "px"
 * @param class class add class to the container : use "pull-right" and "pull-left" for position
 *
 * @category YesWiki
 *
 * @author   Adrien Cheype <adrien.cheype@gmail.com>
 * @author   Jérémy Dufraisse <jeremy.dufraisse@orange.fr>
 * @license  https://www.gnu.org/licenses/agpl-3.0.en.html AGPL 3.0
 *
 * @see     https://yeswiki.net
 */

namespace YesWiki\Attach;

use YesWiki\Core\YesWikiAction;

class PdfAction extends YesWikiAction
{
    public function formatArguments($arg)
    {
        return [
            'url' => $arg['url'] ?? '',
            'ratio' => $arg['ratio'] ?? '',
            'largeurmax' => $arg['largeurmax'] ?? '',
            'hauteurmax' => $arg['hauteurmax'] ?? '',
            'class' => str_replace('attached_file', '', ($arg['class'] ?? '')), // to prevent errors
        ];
    }

    public function run()
    {
        if (
            empty($this->arguments['url']) ||
            (!in_array(parse_url($this->arguments['url'], PHP_URL_HOST), [$_SERVER['SERVER_NAME'], 'www.' . $_SERVER['SERVER_NAME']])) ||
                (
                    parse_url($this->arguments['url'], PHP_URL_PORT) == '' &&
                    $_SERVER['SERVER_PORT'] != ''
                    && $_SERVER['SERVER_PORT'] != '80'
                    && $_SERVER['SERVER_PORT'] != '443'
                ) ||
                (
                    parse_url($this->arguments['url'], PHP_URL_PORT) != '' &&
                    parse_url($this->arguments['url'], PHP_URL_PORT) != $_SERVER['SERVER_PORT']
                ) ||
                    (
                        !empty($_SERVER['HTTP_REFERER']) &&
                        parse_url($this->arguments['url'], PHP_URL_SCHEME) != parse_url($_SERVER['HTTP_REFERER'], PHP_URL_SCHEME)
                    )
        ) {
            return $this->render('@templates/alert-message.twig', [
                'type' => 'danger',
                'message' => _t('ATTACH_ACTION_PDF_PARAM_URL_ERROR'),
            ]);
        } else {
            switch ($this->arguments['ratio']) {
                case 'portrait':
                    $shape = 'pdf';
                    $ratio = 1.38;
                    break;
                case 'paysage':
                    $shape = 'pdf-landscape';
                    $ratio = 0.75;
                    break;
                case 'carre':
                    $shape = 'pdf-square';
                    $ratio = 1;
                    break;
                default:
                    $shape = 'pdf';
                    $ratio = 1.38;
            }

            //size
            $maxWidth = $this->arguments['largeurmax'];
            $maxHeight = $this->arguments['hauteurmax'];
            $manageSize = false;
            if (!empty($maxWidth) && is_numeric($maxWidth)) {
                $manageSize = true;
                if (empty($maxHeight) || !(is_numeric($maxHeight))) {
                    $maxHeight = $maxWidth * $ratio;
                } else {
                    // calculte the minimum between width and height
                    $newMaxHeight = min($maxWidth * $ratio, $maxHeight);
                    $newMaxWidth = min($maxHeight / $ratio, $maxWidth);
                    $maxHeight = $newMaxHeight;
                    $maxWidth = $newMaxWidth;
                }
            } elseif (!empty($maxHeight) && is_numeric($maxHeight)) {
                $manageSize = true;
                if (empty($maxWidth) || !(is_numeric($maxWidth))) {
                    $maxWidth = $maxHeight / $ratio;
                }
            }

            return $this->render('@attach/actions/pdf.twig', [
                'url' => $this->arguments['url'],
                'class' => $this->arguments['class'],
                'manageSize' => $manageSize,
                'shape' => $shape,
                'maxWidth' => $maxWidth,
                'maxHeight' => $maxHeight,
            ]);
        }
    }
}

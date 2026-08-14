<?php

namespace YesWiki\Content\Action;

/*
 * Action to display a pdf in an embedded reader.
 *
 * @param url  required The url of the pdf file. The url has to be from the same origin than the wiki (same schema, same host & same port)
 * @param ratio shape for the container : possible values empty (default), 'portrait' - 'paysage' - 'carre'
 * @param maxwidth  the maximum wanted width ; number without "px"
 * @param maxheight  the maximum wanted heigth ; number without "px"
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

use YesWiki\Core\YesWikiAction;
use YesWiki\Kernel\Component\Category;
use YesWiki\Kernel\Component\Component;
use YesWiki\Kernel\Component\ProvidesComponents;
use YesWiki\Kernel\Component\Setting;
use YesWiki\Kernel\Performable\RegisteredAction;

class PdfAction extends YesWikiAction implements RegisteredAction, ProvidesComponents
{
    /** `{{pdf}}` in page content -- stated, not inferred from the filename. */
    public static function performableName(): string
    {
        return 'pdf';
    }

    public function components(): array
    {
        return [
            Component::for('pdf')
                ->category(Category::Media)
                ->label(_t('AB_attach_pdf_label'))
                ->icon('file')
                ->description(_t('AB_attach_pdf_description'))
                ->hint(_t('AB_attach_pdf_hint'))
                ->previewHeight('300px')
                ->notOffered()
                ->settings(
                    Setting::text('url')
                        ->label(_t('AB_attach_pdf_url_label'))
                        ->required(),
                    Setting::choice('ratio', [
                        'portrait' => _t('AB_attach_pdf_ratio_option_portrait'),
                        'paysage' => _t('AB_attach_pdf_ratio_option_paysage'),
                        'carre' => _t('AB_attach_pdf_ratio_option_carre'),
                    ])
                        ->label(_t('AB_attach_pdf_ratio_label'))
                        ->default(''),
                    Setting::number('maxwidth')
                        ->label(_t('AB_attach_pdf_largeur_max_label')),
                    Setting::number('maxheight')
                        ->label(_t('AB_attach_pdf_hauteur_max_label')),
                    Setting::cssClass('class')
                        ->label('Classe')
                        ->subSettings(
                            Setting::choice('position', [
                                '' => 'standard',
                                'pull-left' => 'float left',
                                'pull-right' => 'float right',
                            ])
                            ->label(_t('AB_attach_pdf_position_label'))
                            ->default(''),
                        ),
                ),
        ];
    }

    public function formatArguments($arg)
    {
        return [
            'url' => $arg['url'] ?? '',
            'ratio' => $arg['ratio'] ?? '',
            'maxwidth' => $arg['maxwidth'] ?? '',
            'maxheight' => $arg['maxheight'] ?? '',
            'class' => str_replace('attached_file', '', $arg['class'] ?? ''), // to prevent errors
        ];
    }

    public function run()
    {
        if (
            empty($this->arguments['url'])
            || (!in_array(parse_url($this->arguments['url'], PHP_URL_HOST), [$this->getRequest()->getHost(), 'www.' . $this->getRequest()->getHost()]))
                || (
                    parse_url($this->arguments['url'], PHP_URL_PORT) == ''
                    && $this->getRequest()->getPort() != ''
                    && $this->getRequest()->getPort() != 80
                    && $this->getRequest()->getPort() != 443
                )
                || (
                    parse_url($this->arguments['url'], PHP_URL_PORT) != ''
                    && parse_url($this->arguments['url'], PHP_URL_PORT) != $this->getRequest()->getPort()
                )
                    || (
                        !empty($this->getRequest()->headers->get('referer'))
                        && parse_url($this->arguments['url'], PHP_URL_SCHEME) != parse_url($this->getRequest()->headers->get('referer'), PHP_URL_SCHEME)
                    )
        ) {
            return $this->render('@core/alert-message.twig', [
                'type' => 'danger',
                'message' => _t('ATTACH_ACTION_PDF_PARAM_URL_ERROR'),
            ]);
        }
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

        // size
        $maxWidth = $this->arguments['maxwidth'];
        $maxHeight = $this->arguments['maxheight'];
        $manageSize = false;
        if (!empty($maxWidth) && is_numeric($maxWidth)) {
            $manageSize = true;
            if (empty($maxHeight) || !is_numeric($maxHeight)) {
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
            if (empty($maxWidth) || !is_numeric($maxWidth)) {
                $maxWidth = $maxHeight / $ratio;
            }
        }

        return $this->render('@core/actions/pdf.twig', [
            'url' => $this->arguments['url'],
            'class' => $this->arguments['class'],
            'manageSize' => $manageSize,
            'shape' => $shape,
            'maxWidth' => $maxWidth,
            'maxHeight' => $maxHeight,
        ]);
    }
}

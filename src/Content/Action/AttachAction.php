<?php

namespace YesWiki\Content\Action;

use YesWiki\Content\Service\FileManager;
use YesWiki\Core\YesWikiAction;
use YesWiki\Files\Service\AttachedFilePaths;
use YesWiki\Files\Service\ImageResizer;
use YesWiki\Kernel\Component\Category;
use YesWiki\Kernel\Component\Component;
use YesWiki\Kernel\Component\ProvidesComponents;
use YesWiki\Kernel\Component\Setting;
use YesWiki\Kernel\Component\SettingGroup;
use YesWiki\Kernel\Performable\RegisteredAction;
use YesWiki\Kernel\Service\PerformableArguments;
use YesWiki\Kernel\Service\RuntimeConfig;
use YesWiki\Kernel\Service\UrlFormatter;
use YesWiki\Render\Service\ActionRunner;
use YesWiki\Render\Service\MarkdownFormatterService;
use YesWiki\Render\Service\TemplateHelperService;

/**
 * `{{attach}}` -- renders one attached file inline, as an image, player, PDF or link depending on its type.
 */
class AttachAction extends YesWikiAction implements RegisteredAction, ProvidesComponents
{
    public static function performableName(): string
    {
        return 'attach';
    }

    public function components(): array
    {
        return [
            Component::for('attach')
                ->category(Category::Media)
                ->label(_t('AB_attach_attach_title'))
                ->icon('paperclip')
                ->description(_t('AB_attach_attach_description'))
                ->previewHeight('400px')
                ->notOffered()
                ->group(self::pictureSettings())
                ->settings(
                    Setting::text('file')
                        ->label(_t('AB_attach_file_label'))
                        ->hint(_t('AB_attach_file_hint')),
                    Setting::text('desc')
                        ->label(_t('ALTERNATIVE_TEXT')),
                    Setting::choice('displaypdf', [
                        _t('AB_attach_yes'),
                        _t('AB_attach_no'),
                    ])
                        ->label(_t('AB_attach_displaypdf_label'))
                        ->default('')
                        ->showIf([
                            'file' => '\\.pdf$',
                        ]),
                    Setting::choice('ratio', [
                        'portrait' => _t('AB_attach_pdf_ratio_option_portrait'),
                        'paysage' => _t('AB_attach_pdf_ratio_option_paysage'),
                        'carre' => _t('AB_attach_pdf_ratio_option_carre'),
                    ])
                        ->label(_t('AB_attach_pdf_ratio_label'))
                        ->default('')
                        ->showIf([
                            'displaypdf' => 1,
                            'file' => '\\.pdf$',
                        ]),
                    Setting::number('maxwidth')
                        ->label(_t('AB_attach_pdf_largeur_max_label'))
                        ->showIf([
                            'displaypdf' => 1,
                            'file' => '\\.pdf$',
                        ]),
                    Setting::number('maxheight')
                        ->label(_t('AB_attach_pdf_hauteur_max_label'))
                        ->showIf([
                            'displaypdf' => 1,
                            'file' => '\\.pdf$',
                        ]),
                ),
        ];
    }

    /** The settings that only mean something once the file is a picture (or a PDF). */
    private static function pictureSettings(): SettingGroup
    {
        return SettingGroup::named(
            _t('AB_attach_commons_title'),
            Setting::url('link')
                ->label(_t('AB_attach_link_label'))
                ->default('https://')
                ->showIf([
                    'file' => '\\.(png|jpg|jpeg|gif|svg|webp)$',
                ]),
            Setting::text('caption')
                ->label(_t('AB_attach_caption_label'))
                ->showIf([
                    'file' => '\\.(png|jpg|jpeg|gif|svg|webp)$',
                ]),
            Setting::text('legend')
                ->label(_t('AB_attach_legend_label'))
                ->showIf([
                    'file' => '\\.(png|jpg|jpeg|gif|svg|webp)$',
                ]),
            Setting::checkbox('nofullimagelink')
                ->label(_t('AB_attach_nofullimagelink_label'))
                ->showIf([
                    'file' => '\\.(png|jpg|jpeg|gif|svg|webp)$',
                ])
                ->checkedValues('0', '1'),
            Setting::choice('size', [
                'small' => _t('AB_attach_size_small'),
                'medium' => _t('AB_attach_size_medium'),
                'big' => _t('AB_attach_size_big'),
                'original' => _t('AB_attach_size_original'),
            ])
                ->label(_t('AB_attach_size_label'))
                ->showIf([
                    'file' => '\\.(png|jpg|jpeg|gif|svg|webp)$',
                ]),
            Setting::number('width')
                ->label(_t('AB_attach_width_label'))
                ->showIf([
                    'file' => '\\.(png|jpg|jpeg|gif|svg|webp)$',
                ]),
            Setting::number('height')
                ->label(_t('AB_attach_height_label'))
                ->showIf([
                    'file' => '\\.(png|jpg|jpeg|gif|svg|webp)$',
                ]),
            Setting::cssClass('class')
                ->label(_t('AB_attach_class_label'))
                ->subSettings(
                    Setting::choice('position', [
                        '' => _t('AB_attach_class_position_none'),
                        'left' => _t('AB_attach_class_position_left'),
                        'center' => _t('AB_attach_class_position_center'),
                        'right' => _t('AB_attach_class_position_right'),
                    ])
                    ->label(_t('AB_attach_class_position_label'))
                    ->showIf([
                        'file' => '\\.(png|jpg|jpeg|gif|pdf|svg|webp)$',
                    ]),
                    Setting::choice('displaylink', [
                        '' => _t('AB_attach_class_displaylink_default'),
                        'new-window' => '_t(AB_attach_class_displaylink_new-window)',
                        'modalbox' => _t('AB_attach_class_displaylink_modalbox'),
                    ])
                    ->label(_t('AB_attach_class_displaylink_label'))
                    ->default(''),
                    Setting::checkbox('lightshadow')
                    ->label(_t('AB_attach_class_effect_lightshadow'))
                    ->default('')
                    ->showIf([
                        'file' => '\\.(png|jpg|jpeg|gif|svg|webp)$',
                    ])
                    ->checkedValues('lightshadow', ''),
                    Setting::checkbox('whiteborder')
                    ->label(_t('AB_attach_class_effect_whiteborder'))
                    ->default('')
                    ->showIf([
                        'file' => '\\.(png|jpg|jpeg|gif|svg|webp)$',
                    ])
                    ->checkedValues('whiteborder', ''),
                    Setting::checkbox('zoom')
                    ->label(_t('AB_attach_class_effect_zoom'))
                    ->default('')
                    ->showIf([
                        'file' => '\\.(png|jpg|jpeg|gif|svg|webp)$',
                    ])
                    ->checkedValues('zoom', ''),
                    Setting::choice('izmir', [
                        'c4-izmir' => _t('AB_attach_class_izmir_izmir'),
                        'c4-izmir c4-border-cc-3' => _t('AB_attach_class_izmir_border'),
                        'c4-izmir c4-image-zoom-in' => _t('AB_attach_class_izmir_zoom'),
                        'c4-izmir c4-reveal-up' => _t('AB_attach_class_izmir_revealup'),
                        'c4-izmir c4-gradient-top' => _t('AB_attach_class_izmir_gradiant'),
                        'C4-izmir c4-layout-top-center' => _t('AB_attach_class_izmir_topcentertext'),
                    ])
                    ->label(_t('AB_attach_class_izmir_label'))
                    ->hint(_t('AB_attach_class_izmir_hint'))
                    ->showIf([
                        'file' => '\\.(png|jpg|jpeg|gif)$',
                    ]),
                ),
        )->width('40%');
    }

    public function run(): string
    {
        $request = $this->readArguments();
        if ($request->error !== '') {
            return $request->error;
        }

        $fullFilename = $request->fileTag !== ''
            ? $this->getService(FileManager::class)->getPhysicalPath($request->fileTag)
            : $this->paths()->fullFilename($request->file);

        if (empty($fullFilename) || !file_exists($fullFilename)) {
            return '<div class="yw-alert yw-alert--danger">' . _t('ATTACH_PARAM_FILE_NOT_FOUND') . ' (' . htmlspecialchars($request->file) . ')</div>';
        }

        $paths = $this->paths();
        if ($paths->isPicture($request->file)) {
            return $this->asImage($request, $fullFilename);
        }
        if ($paths->isVideo($request->file) || $paths->isFlashVideo($request->file)) {
            return $this->asVideo($request, $fullFilename);
        }
        if ($paths->isAudio($request->file) || $paths->isWma($request->file)) {
            return $this->asAudio($request, $fullFilename);
        }
        if ($paths->isPdf($request->file) && $request->displayPdf) {
            return $this->asPdf($request, $fullFilename);
        }

        return $this->asLink($request, $fullFilename);
    }

    private function paths(): AttachedFilePaths
    {
        return $this->getService(AttachedFilePaths::class);
    }

    private function arg(string $name): mixed
    {
        return $this->getService(PerformableArguments::class)->get($name);
    }

    /** Read this tag's arguments into a fresh value object, validating as we go. */
    private function readArguments(): AttachRequest
    {
        $request = new AttachRequest();

        $request->file = htmlspecialchars((string)($this->arg('attachfile') ?: $this->arg('file')));

        $fileManager = $this->getService(FileManager::class);
        $entry = $request->file === '' ? null : $fileManager->getOne($request->file);
        if ($entry !== null) {
            $request->fileTag = $request->file;
            $request->file = (string)($entry['original_filename'] ?? '');
        }

        $desc = (string)($this->arg('attachdesc') ?: $this->arg('desc'));
        $request->desc = htmlentities(strip_tags($desc));

        $request->link = (string)($this->arg('attachlink') ?: $this->arg('link'));
        $request->caption = (string)$this->arg('caption');
        $request->legend = (string)$this->arg('legend');
        $request->nofullimagelink = (string)$this->arg('nofullimagelink');
        $request->height = $this->arg('height');
        $request->width = $this->arg('width');
        $request->displayPdf = $this->arg('displaypdf');
        $request->data = $this->getService(TemplateHelperService::class)->getDataParameter();

        if (empty($request->file)) {
            $request->error = $this->errorMessage(_t('ATTACH_PARAM_FILE_NOT_FOUND'));
        }
        if (!empty($request->width) && !ctype_digit(strval($request->width))) {
            $request->error = $this->errorMessage(_t('ATTACH_PARAM_WIDTH_NOT_NUMERIC'));
        }
        if (!empty($request->height) && !ctype_digit(strval($request->height))) {
            $request->error = $this->errorMessage(_t('ATTACH_PARAM_HEIGHT_NOT_NUMERIC'));
        }

        if ($this->arg('class')) {
            foreach (explode(' ', (string)$this->arg('class')) as $c) {
                $request->classes .= ' ' . trim($c);
            }
        }

        $config = $this->getService(RuntimeConfig::class);
        switch ($this->arg('size')) {
            case 'small':
                $request->width = $config['image-small-width'];
                $request->height = $config['image-small-height'];
                break;
            case 'medium':
                $request->width = $config['image-medium-width'];
                $request->height = $config['image-medium-height'];
                break;
            case 'big':
                $request->width = $config['image-big-width'];
                $request->height = $config['image-big-height'];
                break;
        }

        if (empty($request->height) && !empty($request->width)) {
            $request->height = $request->width;
        } elseif (!empty($request->height) && empty($request->width)) {
            $request->width = $request->height;
        }

        return $request;
    }

    private function errorMessage(string $message): string
    {
        return '<div class="alert alert-danger"><strong>' . _t('ATTACH_ACTION_ATTACH') . '</strong> : ' . $message . '.</div>' . "\n";
    }

    /** URL serving the file's bytes. */
    private function fileUrl(AttachRequest $request, string $fullFilename, bool $forceDownload = false): string
    {
        if ($request->fileTag !== '') {
            $url = $this->getService(UrlFormatter::class)->href('', 'api/files/' . $request->fileTag . '/download', [], false);

            return $forceDownload ? $url . '?download=1' : $url;
        }

        return $this->paths()->scriptPath() . $fullFilename;
    }

    /** The same address, asking for a copy no larger than the page can use. */
    private function sizedFileUrl(AttachRequest $request, string $url): string
    {
        $config = $this->getService(RuntimeConfig::class);
        $width = (int)($request->width ?: ($config['image-render-max-width'] ?? 0));
        $height = (int)($request->height ?: ($config['image-render-max-height'] ?? 0));
        if ($width < 1 || $height < 1) {
            return $url;
        }

        return $url . (str_contains($url, '?') ? '&' : '?') . http_build_query([
            'width' => $width,
            'height' => $height,
        ]);
    }

    private function asImage(AttachRequest $request, string $fullFilename): string
    {
        if ($request->fileTag !== '' || preg_match('/.(svg)$/i', $request->file) === 1) {
            $width = $request->width;
            $height = $request->height;
            $imgName = $fullFilename;
        } else {
            $imgName = $fullFilename;
            if (!empty($request->height) && !empty($request->width)) {
                $resizer = $this->getService(ImageResizer::class);
                $destination = $resizer->resizedFilename($fullFilename, (string)$request->width, (string)$request->height);
                if (!file_exists($destination)) {
                    $resizer->resize($fullFilename, $destination, $request->width, $request->height);
                }
                $imgName = $destination;
            }
            $size = getimagesize($imgName);
            $width = $size === false ? null : $size[0];
            $height = $size === false ? null : $size[1];
        }

        if (strstr($request->classes, 'whiteborder')) {
            $width -= 20;
            $height -= 20;
        }

        $imgSrc = $request->fileTag !== ''
            ? $this->sizedFileUrl($request, $this->fileUrl($request, $fullFilename))
            : ($this->paths()->scriptPath() . $imgName);
        $img = '<img loading="lazy" class="img-responsive" src="' . $imgSrc . '" '
            . 'alt="' . $request->desc . ($request->link ? "\nLien vers: $request->link" : '') . '"'
            . (!empty($width) ? ' width="' . $width . '"' : '')
            . (!empty($height) ? ' height="' . $height . '"' : '') . ' />';

        $classDataForLinks = strstr($request->classes, 'new-window')
            ? ' class="new-window"'
            : (strstr($request->classes, 'modalbox') ? ' class="modalbox" data-size="modal-lg"' : '');

        $link = null;
        if (!empty($request->link)) {
            $linkParts = $this->getService(UrlFormatter::class)->extractLinkParts($request->link);
            if (!empty($linkParts['tag'])) {
            }
            $link = '<a href="' . $this->getService(UrlFormatter::class)->generateLink($request->link) . '"' . $classDataForLinks . '>';
        } elseif (empty($request->nofullimagelink)) {
            $link = '<a href="' . $this->fileUrl($request, $fullFilename, true) . '"' . $classDataForLinks . '>';
        }

        $caption = !empty($request->caption) ? '<figcaption>' . $request->caption . '</figcaption>' : '';
        $legend = !empty($request->legend) ? '<div class="legend">' . $request->legend . '</div>' : '';
        $data = '';
        if (is_array($request->data)) {
            foreach ($request->data as $key => $value) {
                $data .= ' data-' . $key . '="' . $value . '"';
            }
        }

        $notAligned = strpos($request->classes, 'left') === false
            && strpos($request->classes, 'right') == false
            && strpos($request->classes, 'center') == false;

        return ($notAligned ? '<div>' : '')
            . ($link ?? '')
            . "<figure class=\"$request->classes\" $data>$img$caption$legend</figure>"
            . ($link !== null ? '</a>' : '')
            . ($notAligned ? '</div>' : '');
    }

    private function asLink(AttachRequest $request, string $fullFilename): string
    {
        return '<a href="' . $this->fileUrl($request, $fullFilename, true) . '">' . ($request->desc ?: $request->file) . '</a>';
    }

    private function asVideo(AttachRequest $request, string $fullFilename): string
    {
        return $this->getService(MarkdownFormatterService::class)->format(
            '{{player url="' . $this->fileUrl($request, $fullFilename) . '" type="video" '
            . 'height="' . (!empty($request->height) ? $request->height : '300px') . '" '
            . 'width="' . (!empty($request->width) ? $request->width : '400px') . '"}}'
        );
    }

    private function asAudio(AttachRequest $request, string $fullFilename): string
    {
        return $this->getService(MarkdownFormatterService::class)->format(
            '{{player url="' . $this->fileUrl($request, $fullFilename) . '" type="audio"}}'
        );
    }

    private function asPdf(AttachRequest $request, string $fullFilename): string
    {
        $arguments = $this->getService(PerformableArguments::class);
        $arguments->set('url', $this->fileUrl($request, $fullFilename));
        if (empty($arguments->get('maxheight')) && empty($arguments->get('maxwidth'))) {
            $arguments->set('maxheight', $arguments->get('height'));
            $arguments->set('maxwidth', $arguments->get('width'));
        }

        $newClass = '';
        foreach (['right', 'left'] as $side) {
            if (strstr($request->classes, $side)) {
                $newClass = strstr($request->classes, 'pull-' . $side)
                    ? str_replace($side, '', $request->classes)
                    : str_replace($side, 'pull-' . $side, $request->classes);
            }
        }
        if ($newClass !== '') {
            $arguments->set('class', $newClass);
        }

        return $this->getService(ActionRunner::class)->action('pdf', $arguments->all());
    }
}

/**
 * One `{{attach}}` tag's parsed arguments.
 *
 * @internal
 */
final class AttachRequest
{
    /** Set when `file=` resolved to a FileManager tag; empty on the legacy path. */
    public string $fileTag = '';
    public string $file = '';
    public string $desc = '';
    public string $link = '';
    public string $caption = '';
    public string $legend = '';
    public string $nofullimagelink = '';
    public string $classes = 'attached_file';
    public mixed $height = null;
    public mixed $width = null;
    public mixed $displayPdf = null;
    public mixed $data = '';
    public string $error = '';
}

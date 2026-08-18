<?php

namespace YesWiki\Render\Action;

use YesWiki\Content\Service\FileManager;
use YesWiki\Core\YesWikiAction;
use YesWiki\Files\Service\AttachedFilePaths;
use YesWiki\Files\Service\Storage;
use YesWiki\Identity\Service\AclService;
use YesWiki\Kernel\Component\Category;
use YesWiki\Kernel\Component\Component;
use YesWiki\Kernel\Component\ProvidesComponents;
use YesWiki\Kernel\Component\Setting;
use YesWiki\Kernel\Performable\RegisteredAction;
use YesWiki\Kernel\Service\AssetRegistry;
use YesWiki\Kernel\Service\PageContext;
use YesWiki\Kernel\Service\RuntimeConfig;
use YesWiki\Kernel\Service\UrlFormatter;
use YesWiki\Render\Service\PresetService;
use YesWiki\Render\Service\TemplateHelperService;

class SectionAction extends YesWikiAction implements RegisteredAction, ProvidesComponents
{
    /** `{{section}}` in page content -- stated, not inferred from the filename. */
    public static function performableName(): string
    {
        return 'section';
    }

    /** The patterns `run()`'s switch draws, offered as the choice they are. */
    private const PATTERN_LABELS = [
        '' => 'AB_templates_section_pattern_solid',

        'gradient-down' => 'AB_templates_section_pattern_gradient_down',
        'gradient-up' => 'AB_templates_section_pattern_gradient_up',
        'gradient-diagonal' => 'AB_templates_section_pattern_gradient_diagonal',
        'glow' => 'AB_templates_section_pattern_glow',
        'mesh' => 'AB_templates_section_pattern_mesh',

        'point' => 'AB_templates_section_pattern_points',
        'point-reverse' => 'AB_templates_section_pattern_points_reverse',
        'point2' => 'AB_templates_section_pattern_points_not_aligned',
        'point2-reverse' => 'AB_templates_section_pattern_points_not_aligned_reverse',
        'cross' => 'AB_templates_section_pattern_cross',
        'cross-reverse' => 'AB_templates_section_pattern_cross_reverse',
        'cross2' => 'AB_templates_section_pattern_cross_not_aligned',
        'cross2-reverse' => 'AB_templates_section_pattern_cross_not_aligned_reverse',
        'zigzag' => 'AB_templates_section_pattern_zigzag',
        'zigzag-reverse' => 'AB_templates_section_pattern_zigzag_reverse',
        'diagonal' => 'AB_templates_section_pattern_diag',
        'diagonal-reverse' => 'AB_templates_section_pattern_diag_reverse',
        'stripes' => 'AB_templates_section_pattern_stripes',
        'stripes-reverse' => 'AB_templates_section_pattern_stripes_reverse',
        'grid' => 'AB_templates_section_pattern_grid',
        'grid-reverse' => 'AB_templates_section_pattern_grid_reverse',

        'border-solid' => 'AB_templates_section_pattern_border_solid',
        'border-dashed' => 'AB_templates_section_pattern_border_dashed',
        'border-dotted' => 'AB_templates_section_pattern_border_dotted',
    ];

    /**
     * What `repeat=`, `size=` and `position=` accept: a hand-typed value reaches an inline style, so only these do.
     */
    private const REPEATS = ['no-repeat', 'repeat', 'repeat-x', 'repeat-y'];

    private const SIZES = ['auto', 'cover', 'contain'];

    /** The nine places a picture can sit, as CSS writes them. */
    private const POSITIONS = [
        'left top', 'center top', 'right top',
        'left center', 'center center', 'right center',
        'left bottom', 'center bottom', 'right bottom',
    ];

    public function components(): array
    {
        return [
            Component::for('section')
                ->category(Category::Writing)
                ->label(_t('AB_templates_section_label'))
                ->icon('layout-rows')
                ->previewHeight('300px')
                ->wraps(_t('AB_templates_section_wrappedcontentexample'))
                ->settings(
                    Setting::color('bgcolor')
                        ->label(_t('AB_templates_section_bgcolor_full_label'))
                        ->withIcon('palette')
                        ->third(),
                    Setting::choice('textcolor', [
                        '' => _t('AB_templates_section_textcolor_auto'),
                        'white' => _t('AB_templates_section_textcolor_white'),
                        'black' => _t('AB_templates_section_textcolor_black'),
                    ])
                        ->label(_t('AB_templates_section_textcolor_label'))
                        ->withIcon('contrast')
                        ->writesTo('class')
                        ->default('')
                        ->third(),
                    Setting::choice('textalign', [
                        'text-left' => _t('AB_templates_section_textalign_left'),
                        'text-right' => _t('AB_templates_section_textalign_right'),
                        'text-center' => _t('AB_templates_section_textalign_center'),
                        'text-justify' => _t('AB_templates_section_textalign_justify'),
                    ])
                        ->label(_t('AB_templates_section_align_label'))
                        ->withIcon('align-center')
                        ->writesTo('class')
                        ->default('text-left')
                        ->third(),
                    Setting::choice('pattern', self::patternOptions())
                        ->label(_t('AB_templates_section_pattern_label'))
                        ->withIcon('texture')
                        ->half(),
                    Setting::choice('shape', [
                        '' => _t('AB_templates_section_shape_rect'),
                        'shape-rounded' => _t('AB_templates_section_shape_rounded'),
                        'shape-circle' => _t('AB_templates_section_shape_circ'),
                        'shape-blob1' => _t('AB_templates_section_shape_blob1'),
                        'shape-blob2' => _t('AB_templates_section_shape_blob2'),
                        'shape-blob3' => _t('AB_templates_section_shape_blob3'),
                        'shape-blob4' => _t('AB_templates_section_shape_blob4'),
                        'shape-blob5' => _t('AB_templates_section_shape_blob5'),
                    ])
                        ->label(_t('AB_templates_section_shape_label'))
                        ->withIcon('shape')
                        ->writesTo('class')
                        ->default('')
                        ->half(),
                    Setting::image('file')
                        ->label(_t('AB_templates_section_file_label'))
                        ->withIcon('photo')
                        ->half(),
                    Setting::slider('opacity', 0, 1, 0.05)
                        ->label(_t('AB_templates_section_opacity_label'))
                        ->hint(_t('AB_templates_section_opacity_hint'))
                        ->withIcon('droplet-half-2')
                        ->showIf('file')
                        ->default(1)
                        ->half(),
                    Setting::choice('repeat', [
                        'no-repeat' => _t('AB_templates_section_repeat_none'),
                        'repeat' => _t('AB_templates_section_repeat_both'),
                        'repeat-x' => _t('AB_templates_section_repeat_x'),
                        'repeat-y' => _t('AB_templates_section_repeat_y'),
                    ])
                        ->label(_t('AB_templates_section_repeat_label'))
                        ->withIcon('repeat')
                        ->showIf('file')
                        ->default('no-repeat')
                        ->half(),
                    Setting::choice('size', [
                        'auto' => _t('AB_templates_section_size_auto'),
                        'cover' => _t('AB_templates_section_size_cover'),
                        'contain' => _t('AB_templates_section_size_contain'),
                    ])
                        ->label(_t('AB_templates_section_size_label'))
                        ->withIcon('crop')
                        ->showIf('file')
                        ->default('auto')
                        ->half(),
                    Setting::choice('position', [
                        'left top' => _t('AB_templates_section_position_left_top'),
                        'center top' => _t('AB_templates_section_position_center_top'),
                        'right top' => _t('AB_templates_section_position_right_top'),
                        'left center' => _t('AB_templates_section_position_left_center'),
                        'center center' => _t('AB_templates_section_position_center'),
                        'right center' => _t('AB_templates_section_position_right_center'),
                        'left bottom' => _t('AB_templates_section_position_left_bottom'),
                        'center bottom' => _t('AB_templates_section_position_center_bottom'),
                        'right bottom' => _t('AB_templates_section_position_right_bottom'),
                    ])
                        ->label(_t('AB_templates_section_position_label'))
                        ->withIcon('focus-centered')
                        ->showIf('file')
                        ->default('center center')
                        ->half(),
                    Setting::checkbox('fixed')
                        ->title(_t('AB_templates_section_fixed_title'))
                        ->label(_t('AB_templates_section_fixed_label'))
                        ->withIcon('pin')
                        ->showIf('file')
                        ->checkedValues('1', '')
                        ->default('')
                        ->half(),
                    Setting::slider('minheight', 1, 100)
                        ->label(_t('AB_templates_section_minheight_label'))
                        ->hint(_t('AB_templates_section_minheight_hint'))
                        ->withIcon('arrows-vertical')
                        ->half(),
                    Setting::checkbox('fullwidth')
                        ->title(_t('AB_templates_section_width_label'))
                        ->label(_t('AB_templates_section_fullwidth_short'))
                        ->withIcon('arrows-horizontal')
                        ->writesTo('class')
                        ->checkedValues('full-width', '')
                        ->default('')
                        ->half(),
                    Setting::choice('visibility', [
                        '' => _t('AB_templates_section_visible_everyone'),
                        '+' => _t('AB_templates_section_visible_connected_user'),
                        '%' => _t('AB_templates_section_visible_owner'),
                        '@admins' => _t('AB_templates_section_visible_admins'),
                    ])
                        ->label(_t('AB_templates_section_visible_label'))
                        ->withIcon('eye')
                        ->default('')
                        ->half(),
                    Setting::choice('nocontainer', [
                        '' => _t('YES'),
                        '1' => _t('NO'),
                    ])
                        ->label(_t('AB_templates_section_container_label'))
                        ->hint(_t('AB_templates_section_container_hint'))
                        ->withIcon('box-margin')
                        ->default('')
                        ->half(),
                    Setting::choice('animation', self::animationOptions())
                        ->label(_t('AB_templates_section_animation_label'))
                        ->hint(_t('AB_templates_section_animation_hint'))
                        ->withIcon('wand')
                        ->documentedAt('https://yeswiki.net/?AniMation')
                        ->writesTo('class')
                        ->default('')
                        ->full(),
                ),
        ];
    }

    /**
     * The animations `{{section}}` can carry, as `wow <name>` class pairs.
     *
     * @var array<string, string>
     */
    private const ANIMATION_LABELS = [
        '' => 'NO_ANIMATION',
        'wow bounce' => 'AB_templates_section_animation_bounce',
        'wow flash' => 'AB_templates_section_animation_flash',
        'wow pulse' => 'AB_templates_section_animation_pulse',
        'wow rubberBand' => 'AB_templates_section_animation_rubberband',
        'wow shakeX' => 'AB_templates_section_animation_shakex',
        'wow shakeY' => 'AB_templates_section_animation_shakey',
        'wow headShake' => 'AB_templates_section_animation_headshaked',
        'wow swing' => 'AB_templates_section_animation_swing',
        'wow tada' => 'AB_templates_section_animation_tada',
        'wow wobble' => 'AB_templates_section_animation_wobble',
        'wow jello' => 'AB_templates_section_animation_jello',
        'wow heartBeat' => 'AB_templates_section_animation_heartbat',
    ];

    /**
     * The animation this section carries, as the class pair stored content already uses -- and the stylesheet that makes it move, declared only when there is one to run.
     */
    private function animationClass(): string
    {
        $animation = trim((string)($this->arguments['animation'] ?? ''));
        if ($animation === '' || !array_key_exists($animation, self::ANIMATION_LABELS)) {
            return '';
        }

        $this->getService(AssetRegistry::class)->addCssFile('styles/animate.css');

        return ' ' . $animation;
    }

    /**
     * @return array<string, string>
     */
    private static function patternOptions(): array
    {
        return array_map(static fn (string $key) => _t($key), self::PATTERN_LABELS);
    }

    /**
     * @return array<string, string>
     */
    private static function animationOptions(): array
    {
        return array_map(static fn (string $key) => _t($key), self::ANIMATION_LABELS);
    }

    public function run()
    {
        $bgcolor = $this->arguments['bgcolor'] ?? '';
        $patternId = (string)($this->arguments['pattern'] ?? '');

        $reversed = ($this->arguments['patternreverse'] ?? false) == 'true'
            || str_ends_with($patternId, '-reverse');
        $patternId = (string)preg_replace('/-reverse$/', '', $patternId);

        $patternbg = $reversed ? 'var(--yw-surface)' : $bgcolor;
        $patterncolor = $reversed ? $bgcolor : 'var(--yw-surface)';
        $patternborder = false;

        $base = $bgcolor !== '' ? $bgcolor : 'var(--yw-surface)';
        $shade = "color-mix(in srgb, $base 62%, #000)";
        $tint = "color-mix(in srgb, $base 74%, #fff)";

        switch ($patternId) {
            case 'border-solid':
            case 'border-dashed':
            case 'border-dotted':
                $patternborder = true;
                $pattern = <<<css
                    border-color: $bgcolor;
                    background-color: var(--yw-surface);
                css;
                break;
            case 'point':
                $pattern = <<<css
                    background-image: radial-gradient($patterncolor 2.5px, transparent 2.5px);
                    background-size: 31px 31px;
                css;
                break;
            case 'point2':
                $pattern = <<<css
                    background-image: radial-gradient($patterncolor 2px, transparent 2px), radial-gradient($patterncolor 2px, transparent 2px);
                    background-size: 25px 25px;
                    background-position: 0 0, 12.5px 12.5px;
                css;
                break;
            case 'cross':
                $pattern = <<<css
                    background: radial-gradient(circle, transparent 20%, $patternbg 30%, $patternbg 70%, transparent 70%, transparent) 0% 0% / 30px 30px, radial-gradient(circle, transparent 20%, $patternbg 40%, $patternbg 75%, transparent 70%, transparent) 30px 30px / 30px 30px, linear-gradient($patterncolor 2px, transparent 2px) 0px -1px / 30px 30px, linear-gradient(90deg, $patterncolor 2px, $patternbg 2px) -1px 0px / 30px 30px $patternbg;
                    background-position-y: 7px;
                css;
                break;
            case 'cross2':
                $pattern = <<<css
                    background: radial-gradient(circle, transparent 20%, $patternbg 20%, $patternbg 80%, transparent 80%, transparent) 0% 0% / 46px 46px, radial-gradient(circle, transparent 20%, $patternbg 20%, $patternbg 80%, transparent 80%, transparent) 23px 23px / 46px 46px, linear-gradient($patterncolor 2px, transparent 2px) 0px -1px / 23px 23px, linear-gradient(90deg, $patterncolor 2px, $patternbg 2px) -1px 0px / 23px 23px $patternbg;
                css;
                break;
            case 'zigzag':
                $pattern = <<<css
                    background: linear-gradient(135deg, $patterncolor 25%, transparent 25%) -10px 0, linear-gradient(225deg, $patterncolor 25%, transparent 25%) -10px 0, linear-gradient(315deg, $patterncolor 25%, transparent 25%), linear-gradient(45deg, $patterncolor 25%, transparent 25%);
                    background-size: 20px 20px;
                css;
                break;
            case 'diagonal':
                $pattern = <<<css
                    background-image: repeating-linear-gradient(45deg, $patterncolor 0, $patterncolor 3.5px, transparent 0, transparent 50%);
                    background-size: 18px 18px;
                css;
                break;
            case 'gradient-down':
                $pattern = <<<css
                    background-image: linear-gradient(180deg, $base 0%, $shade 100%);
                css;
                break;
            case 'gradient-up':
                $pattern = <<<css
                    background-image: linear-gradient(0deg, $base 0%, $shade 100%);
                css;
                break;
            case 'gradient-diagonal':
                $pattern = <<<css
                    background-image: linear-gradient(135deg, $tint 0%, $base 45%, $shade 100%);
                css;
                break;
            case 'glow':
                $pattern = <<<css
                    background-image: radial-gradient(ellipse at 50% -10%, $tint 0%, $base 62%);
                css;
                break;
            case 'mesh':
                $pattern = <<<css
                    background-image:
                        radial-gradient(at 18% 22%, $tint 0px, transparent 55%),
                        radial-gradient(at 82% 14%, $shade 0px, transparent 50%),
                        radial-gradient(at 62% 88%, $tint 0px, transparent 55%);
                css;
                break;
            case 'stripes':
                $pattern = <<<css
                    background-image: repeating-linear-gradient(135deg, $patterncolor 0 10px, transparent 10px 34px);
                css;
                break;
            case 'grid':
                $pattern = <<<css
                    background-image: linear-gradient($patterncolor 1px, transparent 1px), linear-gradient(90deg, $patterncolor 1px, transparent 1px);
                    background-size: 32px 32px;
                css;
                break;
            default:
                $pattern = '';
                break;
        }
        if ($pattern && !$patternborder) {
            $pattern .= <<<css
            background-color: $patternbg !important;
            background-repeat: repeat;
        css;
        }

        ob_start();

        $file = $this->arguments['file'] ?? '';
        $backgroundimg = true;
        if (empty($file) && empty($bgcolor)) {
            $bgcolor = false;
            $backgroundimg = false;
        }

        $imageUrl = null;
        if (!empty($file)) {
            $entry = $this->getService(FileManager::class)->getOne($file);

            if ($entry !== null) {
                $family = FileManager::familyOf(
                    (string)($entry['mime_type'] ?? ''),
                    (string)($entry['original_filename'] ?? '')
                );
                if ($family !== 'image') {
                    echo '<div class="yw-alert yw-alert--danger"><strong>' . _t('ATTACH_ACTION_BACKGROUNDIMAGE') . '</strong> : '
                        . _t('ATTACH_PARAM_FILE_MUST_BE_IMAGE') . '.</div>' . "\n";

                    return;
                }

                $config = $this->getService(RuntimeConfig::class);
                $imageUrl = $this->getService(UrlFormatter::class)
                    ->href('', 'api/files/' . rawurlencode($file) . '/download', [
                        'width' => (int)($config['image-render-max-width'] ?? 0),
                        'height' => (int)($config['image-render-max-height'] ?? 0),
                    ], false);
            } else {
                $paths = $this->getService(AttachedFilePaths::class);

                if (!$paths->isPicture($file)) {
                    echo '<div class="yw-alert yw-alert--danger"><strong>' . _t('ATTACH_ACTION_BACKGROUNDIMAGE') . '</strong> : '
                        . _t('ATTACH_PARAM_FILE_MUST_BE_IMAGE') . '.</div>' . "\n";

                    return;
                }
                $fullFilename = $paths->fullFilename($file);
                $imageUrl = $fullFilename === '' ? null : $this->getService(Storage::class)->url($fullFilename);
            }

            $height = $this->arguments['height'] ?? '';
        }

        $class = $this->arguments['class'] ?? '';

        $id = $this->arguments['id'] ?? '';

        $data = $this->getService(TemplateHelperService::class)->getDataParameter();

        $pagetag = $this->getService(PageContext::class)->getTag();

        if ($this->check_end_elem('section')) {
            $role = strval($this->arguments['visibility'] ?? '');
            $role = empty($role) ? $role : str_replace('\\n', "\n", $role);
            $visible = !$role || $this->getService(AclService::class)->check($role, null, false);
            $class = ($backgroundimg ? 'background-image' : '')
                . ($patternId && !$patternborder ? ' with-bg-pattern' : '')
                . ($patternborder ? ' pattern-border' : '')
                . ($visible ? '' : ' remove-this-div-on-page-load ')
                . " pattern-$patternId"
                . (!empty($class) ? ' ' . $class : '')
                . $this->animationClass();

            $minHeight = $this->arguments['minheight'] ?? '';

            $imageStyle = '';
            if ($imageUrl !== null) {
                $opacity = $this->arguments['opacity'] ?? '';
                $repeat = (string)($this->arguments['repeat'] ?? '');
                $size = (string)($this->arguments['size'] ?? '');
                $position = (string)($this->arguments['position'] ?? '');

                $tokens = preg_split('/\s+/', (string)($this->arguments['class'] ?? '')) ?: [];
                if ($size === '' && in_array('cover', $tokens, true)) {
                    $size = 'cover';
                }
                if ($position === '' && in_array('center', $tokens, true)) {
                    $position = 'center top';
                }
                $attachment = ($this->arguments['fixed'] ?? '') !== '' || in_array('fixed', $tokens, true)
                    ? 'fixed'
                    : '';

                $imageStyle = '--yw-section-image:url(' . $imageUrl . '); '
                    . (in_array($repeat, self::REPEATS, true) ? '--yw-section-image-repeat:' . $repeat . '; ' : '')
                    . (in_array($size, self::SIZES, true) ? '--yw-section-image-size:' . $size . '; ' : '')
                    . (in_array($position, self::POSITIONS, true) ? '--yw-section-image-position:' . $position . '; ' : '')
                    . ($attachment !== '' ? '--yw-section-image-attachment:' . $attachment . '; ' : '')
                    . ($opacity !== '' ? '--yw-section-image-opacity:' . (float)$opacity . '; ' : '');
            }

            $sectionInk = (str_contains($class, 'white') || str_contains($class, 'black'))
                ? ''
                : $this->getService(PresetService::class)->inkForBackground((string)$bgcolor);

            echo '<!-- start of section -->
    <section' . (!empty($id) ? ' id="' . $id . '"' : '') . ' class="' . $class
                . ($imageUrl !== null ? ' with-bg-image' : '') . '" data-file="' . $file . '" style="'
                . (!empty($bgcolor) ? 'background-color:' . $bgcolor . '; ' : '')

                . ($sectionInk !== '' ? 'color:' . $sectionInk . '; ' : '')
                . (!empty($height) ? 'height:' . $height . 'px; ' : '')
                . ($minHeight !== '' ? 'min-height:' . (float)$minHeight . 'vh; ' : '')
                . (!empty($pattern) ? $pattern : '')
                . $imageStyle . '"'
            ;
            if (is_array($data)) {
                foreach ($data as $key => $value) {
                    echo ' data-' . $key . '="' . $value . '"';
                }
            }
            echo '>' . "\n";

            $nocontainer = $this->arguments['nocontainer'] ?? '';
            if (empty($nocontainer)) {
                echo '<div class="yw-container">' . "\n";
            } else {
                echo '<div>';
            }

            if (isset($fullFilename) && ($fullFilename === '' || !$this->getService(Storage::class)->exists($fullFilename))) {
                echo '<div class="yw-alert yw-alert--danger">' . _t('ATTACH_PARAM_FILE_NOT_FOUND') . ' (' . htmlspecialchars($file) . ')</div>';
            }
        } else {
            echo $this->generate_error_msg('section');
        }
        $section = ob_get_contents();
        ob_end_clean();

        return $section;
    }

    public function end(): string
    {
        return '</div></section><!-- end of section -->';
    }
}

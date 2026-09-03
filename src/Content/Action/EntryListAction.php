<?php

namespace YesWiki\Content\Action;

use YesWiki\Content\Entity\Item;
use YesWiki\Content\Entity\PageBody;
use YesWiki\Content\Entity\SuppliesItems;
use YesWiki\Content\Exception\ParsingMultipleException;
use YesWiki\Content\Field\BazarField;
use YesWiki\Content\Field\CheckboxField;
use YesWiki\Content\Field\EmailField;
use YesWiki\Content\Field\EnumField;
use YesWiki\Content\Field\ImageField;
use YesWiki\Content\Field\MapField;
use YesWiki\Content\Service\BazarListService;
use YesWiki\Content\Service\EntryDisplay;
use YesWiki\Content\Service\EntryManager;
use YesWiki\Content\Service\FieldRoleResolver;
use YesWiki\Content\Service\FormManager;
use YesWiki\Content\Service\FormPropertiesService;
use YesWiki\Content\Service\ListIndex;
use YesWiki\Content\Service\TemplateDataFactory;
use YesWiki\Core\YesWikiAction;
use YesWiki\Files\Service\AttachedFilePaths;
use YesWiki\Files\Service\Storage;
use YesWiki\Identity\Service\AclService;
use YesWiki\Identity\Service\AuthenticationService;
use YesWiki\Kernel\Component\Category;
use YesWiki\Kernel\Component\Component;
use YesWiki\Kernel\Component\ProvidesComponents;
use YesWiki\Kernel\Component\Setting;
use YesWiki\Kernel\Component\SettingGroup;
use YesWiki\Kernel\Exception\TemplateNotFound;
use YesWiki\Kernel\Performable\AliasesPerformable;
use YesWiki\Kernel\Performable\RegisteredAction;
use YesWiki\Kernel\Service\AssetRegistry;
use YesWiki\Kernel\Service\PageContext;
use YesWiki\Kernel\Service\Paginator;
use YesWiki\Kernel\Service\RuntimeConfig;
use YesWiki\Kernel\Service\UrlFormatter;
use YesWiki\Kernel\Service\WikiUrls;
use YesWiki\Render\Service\PresentationCatalog;
use YesWiki\Render\Service\PresentationRenderer;
use YesWiki\Search\Service\SearchManager;

class EntryListAction extends YesWikiAction implements AliasesPerformable, RegisteredAction, ProvidesComponents, SuppliesItems
{
    /** `{{entrylist}}` in page content -- stated, not inferred from the filename. */
    public static function performableName(): string
    {
        return 'entrylist';
    }

    /**
     * The deprecated spellings of `{{entrylist}}` that stored pages still contain (ticket 49).
     *
     * @return array<string, array<string, string>>
     */
    public static function performableAliases(): array
    {
        return [
            'entrymap' => ['template' => 'map'],
            'calendar' => ['template' => 'calendar'],
            'entrytable' => ['template' => 'tableau'],
            'entryuserpage' => ['filteruserasowner' => 'true'],
        ];
    }

    public static function sourceLabel(): string
    {
        return _t('SOURCE_ENTRYLIST');
    }

    /** The form whose entries are listed. */
    public static function formSetting(): Setting
    {
        return Setting::form('id')
            ->label(_t('ACTION_BUILDER_CHOOSE_FORM'))
            ->withIcon('database')
            ->required();
    }

    public static function sourceSettings(): array
    {
        return [
            self::formSetting(),
            Setting::fieldMapping('displayfields')
                ->subSettings(
                    Setting::formField('title')
                        ->label(_t('AB_bazarliste_displayfields_title_label'))
                        ->extraFields(['title'])
                        ->ofTypes(['text'])
                        ->default('title'),
                    Setting::formField('subtitle')
                        ->label(_t('AB_bazarliste_displayfields_subtitle_label'))
                        ->default('')
                        ->extraFields(['owner', 'created_at', 'updated_at']),
                    Setting::formField('visual')
                        ->label(_t('AB_bazarliste_displayfields_visual_label'))
                        ->default('')
                        ->ofTypes(['image'])
                        ->suggests('imagebf_image'),
                    Setting::formField('description')
                        ->label(_t('AB_bazarliste_displayfields_text_label'))
                        ->default(''),
                    Setting::formField('floating')
                        ->label(_t('AB_bazarliste_displayfields_floating_label'))
                        ->default('')
                        ->extraFields(['owner']),
                    Setting::choice('cta', [
                        '' => _t('AB_bazarliste_cta_none'),
                        'entry' => _t('AB_bazarliste_cta_entry'),
                        'edit' => _t('AB_bazarliste_cta_edit'),
                    ])
                        ->label(_t('AB_bazarliste_cta_label'))
                        ->default(''),
                    Setting::formField('date')
                        ->label(_t('AB_bazarliste_displayfields_date_label'))
                        ->default('')
                        ->ofTypes(['jour', 'listedatedeb', 'listedatefin'])
                        ->extraFields(['created_at', 'updated_at']),
                ),
        ];
    }

    /** @return list<Setting> */
    public static function sourceSelectionSettings(): array
    {
        return self::selectionSettings();
    }

    /** `query`, as conditions rather than as a string. */
    private static function querySetting(): Setting
    {
        return Setting::query('query')
            ->raw('intro', '<h4>' . _t('AB_bazar_query_label') . '</h4>')
            ->hint(_t('AB_bazar_query_hint'))
            ->raw('btn-label-add', _t('AB_bazar_query_add'))
            ->subSettings(
                Setting::formField('name')
                    ->label(_t('AB_bazar_query_field_label'))
                    ->extraFields(['owner', 'created_at', 'updated_at']),
                Setting::choice('operator', [
                    '=' => _t('AB_bazar_query_operator_is'),
                    '!=' => _t('AB_bazar_query_operator_is_not'),
                    '>' => _t('AB_bazar_query_operator_greater'),
                    '>=' => _t('AB_bazar_query_operator_greater_or_equal'),
                    '<' => _t('AB_bazar_query_operator_lower'),
                    '<=' => _t('AB_bazar_query_operator_lower_or_equal'),
                ])
                    ->label(_t('AB_bazar_query_operator_label'))
                    ->default('='),
                Setting::text('values')
                    ->label(_t('AB_bazar_query_values_label'))
                    ->hint(_t('AB_bazar_query_values_hint')),
            );
    }

    /**
     * Which entries, how many, in what order.
     *
     * @return list<Setting>
     */
    private static function selectionSettings(): array
    {
        return [
            Setting::number('pagination')
                ->label(_t('AB_bazar_commons_pagination_label'))
                ->hint(_t('AB_bazar_commons_pagination_hint'))
                ->withIcon('separator-horizontal')
                ->min(0),
            Setting::number('nb')
                ->label(_t('AB_bazar_commons2_nb_label'))
                ->hint(_t('AB_bazar_commons2_nb_hint'))
                ->withIcon('list-numbers')
                ->min(0),
            Setting::formField('field')
                ->label(_t('AB_bazar_sort_field_label'))
                ->default('')
                ->extraFields(['form_id', 'created_at', 'updated_at'])
                ->showIf(['random' => '']),
            Setting::choice('order', [
                'asc' => _t('AB_bazar_commons2_ordre_option_asc'),
                'desc' => _t('AB_bazar_commons2_ordre_option_desc'),
            ])
                ->label(_t('AB_bazar_commons2_ordre_label'))
                ->default('asc')
                ->showIf(['random' => '']),
            Setting::checkbox('random')
                ->title(_t('AB_bazar_order_title'))
                ->label(_t('AB_bazar_random_label'))
                ->withIcon('arrows-shuffle')
                ->checkedValues('1', ''),
            Setting::choice('search', [
                '' => _t('AB_attach_no'),
                'true' => _t('AB_attach_yes'),
            ])
                ->label(_t('AB_bazar_commons_search_label'))
                ->withIcon('search')
                ->default(''),
            self::querySetting(),
            ...self::facetSettings(),
        ];
    }

    /**
     * The boxes a reader narrows the list with, and how they are laid out.
     *
     * @return list<Setting>
     */
    private static function facetSettings(): array
    {
        return [
            Setting::facets('facets')
                ->raw('intro', '<h4>' . _t('AB_bazar_facettes_intro') . '</h4>')
                ->hint(_t('AB_bazar_facettes_hint'))
                ->raw('btn-label-add', _t('AB_bazar_facettes_btn-label-add'))
                ->subSettings(
                    Setting::formField('field')
                        ->label(_t('AB_bazar_facettes_field_label'))
                        ->extraFields([
                            'form_id',
                        ])
                        ->raw('only', 'lists'),
                    Setting::text('title')
                        ->label(_t('AB_bazar_facettes_title_label')),
                    Setting::icon('icon')
                        ->label('Icone'),
                ),
            Setting::choice('filterposition', [
                'left' => _t('AB_LEFT'),
                'right' => _t('AB_RIGHT'),
                'top' => _t('AB_bazar_commons2_filterposition_top'),
            ])
                ->label(_t('AB_bazar_commons2_filterposition_label'))
                ->showIf('groups'),
            Setting::choice('groupsexpanded', [
                'false' => _t('AB_bazar_commons2_groupsexpanded_false'),
                'true' => _t('AB_bazar_commons2_groupsexpanded_true'),
            ])
                ->label(_t('AB_bazar_commons2_groupsexpanded_label'))
                ->showIf('groups'),
            Setting::range('filtercolsize')
                ->label(_t('AB_bazar_commons2_filtercolsize_label'))
                ->min(1)
                ->max(12)
                ->showIf(['groups' => 'notNull', 'filterposition' => '^(?!top)']),
            Setting::checkbox('filtersresultnb')
                ->label(_t('AB_bazar_commons2_filtersresultnb_label'))
                ->default(true)
                ->showIf('groups'),
            Setting::checkbox('resetfiltersbutton')
                ->label(_t('AB_bazar_commons2_resetfiltersbutton_label'))
                ->default('0')
                ->showIf('groups')
                ->checkedValues('1', '0'),
        ];
    }

    /**
     * The entries, as Items (ticket 37).
     *
     * @return list<Item>
     */
    public function items(): array
    {
        $bazarListService = $this->getService(BazarListService::class);
        $forms = $bazarListService->getForms($this->arguments);
        $entries = $bazarListService->getEntries($this->arguments, $forms);

        return $this->itemsFrom($entries);
    }

    /**
     * @param array<mixed> $entries
     *
     * @return list<Item>
     */
    private function itemsFrom(array $entries): array
    {
        $slots = $this->arguments['displayfields'] ?? [];
        $field = static function (array $entry, $name) {
            if (empty($name) || !isset($entry[$name])) {
                return null;
            }
            $value = $entry[$name];

            return is_scalar($value) && (string)$value !== '' ? (string)$value : null;
        };

        $items = [];
        foreach ($entries as $entry) {
            $image = $field($entry, $slots['visual'] ?? null);
            $tag = (string)($entry['tag'] ?? '');
            $items[] = new Item(
                id: (string)($entry['id_fiche'] ?? $entry['tag'] ?? ''),
                title: $field($entry, $slots['title'] ?? null) ?? (string)($entry['title'] ?? $entry['tag'] ?? ''),
                subtitle: $field($entry, $slots['subtitle'] ?? null),
                description: $field($entry, $slots['description'] ?? null),
                image: $this->imageUrl($image),
                url: $this->getService(UrlFormatter::class)->href('', $tag),
                date: self::asDate($field($entry, $slots['date'] ?? null)),
                badge: $field($entry, $slots['floating'] ?? null),
                ctaUrl: $this->ctaUrl((string)($slots['cta'] ?? ''), $tag),
                ctaLabel: self::ctaLabel((string)($slots['cta'] ?? '')),
            );
        }

        return $items;
    }

    /** An entry's picture, as something an `<img>` can point at. */
    private function imageUrl(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (preg_match('#^(https?:)?//#i', $value) === 1) {
            return $value;
        }

        $paths = $this->getService(AttachedFilePaths::class);
        $inUploads = rtrim($paths->uploadPath(), '/') . '/' . $value;
        if ($this->getService(Storage::class)->exists($inUploads)) {
            return $inUploads;
        }

        $attached = $paths->fullFilename($value);

        return $attached === '' ? null : $attached;
    }

    /** Where an item's button goes, if the list asked for one. */
    private function ctaUrl(string $mode, string $tag): ?string
    {
        if ($tag === '' || !in_array($mode, ['entry', 'edit'], true)) {
            return null;
        }

        return $this->getService(UrlFormatter::class)->href($mode === 'edit' ? 'edit' : '', $tag);
    }

    private static function ctaLabel(string $mode): ?string
    {
        return match ($mode) {
            'entry' => _t('AB_bazarliste_cta_entry'),
            'edit' => _t('AB_bazarliste_cta_edit'),
            default => null,
        };
    }

    /** An entry's stored date, as the ISO-8601 string an Item carries -- or nothing at all. */
    private static function asDate(mixed $stored): ?string
    {
        if (!is_string($stored) || $stored === '') {
            return null;
        }
        $timestamp = strtotime($stored);

        return $timestamp === false ? null : date('c', $timestamp);
    }

    public function components(): array
    {
        return [
            ...$this->customTemplateComponents(),

            Component::for('entrylist-any')
                ->writes('entrylist')
                ->category(Category::Lists)
                ->label(_t('AB_bazarliste_label'))
                ->icon('layout-list')
                ->previewHeight('450px')
                ->notOffered()
                ->settings(self::formSetting())
                ->group(self::commonSettings(), self::commonDisplaySettings()),
            Component::for('entrylist')
                ->category(Category::Lists)
                ->label(_t('AB_bazarliste_label'))
                ->icon('layout-list')
                ->pin('template', 'liste_accordeon')
                ->description(_t('AB_bazarliste_description'))
                ->previewHeight('450px')
                ->notOffered()
                ->settings(self::formSetting())
                ->group(self::commonSettings(), self::commonDisplaySettings())
                ->settings(
                    Setting::fieldMapping('displayfields')
                        ->showIf('dynamic')
                        ->subSettings(
                            Setting::formField('title')
                            ->label(_t('AB_bazarliste_displayfields_title_label'))
                            ->default('bf_titre'),
                            Setting::formField('subtitle')
                            ->label(_t('AB_bazarliste_displayfields_subtitle_label'))
                            ->default('')
                            ->extraFields([
                                'owner',
                                'created_at',
                                'updated_at',
                            ]),
                            Setting::formField('floating')
                            ->label(_t('AB_bazarliste_displayfields_floating_label'))
                            ->default('')
                            ->extraFields([
                                'owner',
                                'created_at',
                                'updated_at',
                            ]),
                            Setting::formField('visual')
                            ->label(_t('AB_bazarliste_displayfields_visual_label'))
                            ->default('')
                            ->extraFields([
                                'owner',
                                'created_at',
                                'updated_at',
                            ]),
                        ),
                ),
            Component::for('entrymap')
                ->writes('entrylist')
                ->category(Category::Lists)
                ->label(_t('AB_bazarcarto_label'))
                ->icon('map-2')
                ->pin('template', 'map')
                ->description(_t('AB_bazarcarto_description'))
                ->hint(_t('AB_bazarcarto_hint'))
                ->previewHeight('450px')
                ->settings(self::formSetting())
                ->group(self::commonSettings(), self::commonDisplaySettings())
                ->settings(
                    Setting::choice('provider', [
                        'OpenStreetMap.Mapnik',
                        'OpenStreetMap.BlackAndWhite',
                        'OpenStreetMap.DE',
                        'OpenStreetMap.France',
                        'OpenStreetMap.HOT',
                        'OpenTopoMap',
                        'Stadia.AlidadeSmooth',
                        'Stadia.AlidadeSmoothDark',
                        'Stadia.OSMBright',
                        'Stamen.Toner',
                        'Stamen.TonerBackground',
                        'Stamen.TonerLite',
                        'Stamen.Watercolor',
                        'Stamen.Terrain',
                        'Stamen.TerrainBackground',
                        'Esri.WorldStreetMap',
                        'Esri.DeLorme',
                        'Esri.WorldTopoMap',
                        'Esri.WorldImagery',
                        'Esri.WorldTerrain',
                        'Esri.WorldShadedRelief',
                        'Esri.WorldPhysical',
                        'Esri.OceanBasemap',
                        'Esri.NatGeoWorldMap',
                        'Esri.WorldGrayCanvas',
                        'HERE.normalDay',
                        'MtbMap',
                        'CartoDB.Positron',
                        'CartoDB.PositronNoLabels',
                        'CartoDB.PositronOnlyLabels',
                        'CartoDB.DarkMatter',
                        'CartoDB.DarkMatterNoLabels',
                        'CartoDB.DarkMatterOnlyLabels',
                        'HikeBike.HikeBike',
                        'HikeBike.HillShading',
                        'BasemapAT.orthofoto',
                        'NASAGIBS.ViirsEarthAtNight2012',
                    ])
                        ->label(_t('AB_bazarcarto_provider_label'))
                        ->default('OpenStreetMap.Mapnik')
                        ->required(),
                    Setting::geo('coordinates')
                        ->label(_t('AB_bazarcarto_coordinates_label')),
                    Setting::checkbox('cluster')
                        ->label(_t('AB_bazarcarto_cluster_label'))
                        ->default('false'),
                    Setting::text('width')
                        ->label(_t('AB_bazarcarto_width_label'))
                        ->hint('500px, 100%...')
                        ->default('100%'),
                    Setting::text('height')
                        ->label(_t('AB_bazarcarto_height_label'))
                        ->default('700px'),
                    Setting::choice('entrydisplay', [
                        'direct' => _t('AB_bazarcarto_entrydisplay_option_direct'),
                        'sidebar' => _t('AB_bazarcarto_entrydisplay_option_sidebar'),
                        'modal' => _t('AB_bazarcarto_entrydisplay_option_modal'),
                        'newtab' => _t('AB_bazarcarto_entrydisplay_option_newtab'),
                        'popup' => _t('AB_bazarcarto_entrydisplay_option_popup'),
                    ])
                        ->label(_t('AB_bazarcarto_entrydisplay_label'))
                        ->default('sidebar')
                        ->showIf('dynamic'),
                    Setting::choice('popuptemplate', [
                        '_map_popup_html.twig' => _t('AB_bazarcarto_popuptemplate_entry_from_html'),
                        '_map_popup_from_data.twig' => _t('AB_bazarcarto_popuptemplate_entry_from_data'),
                        'custom' => _t('AB_bazarcarto_popuptemplate_custom'),
                    ])
                        ->label(_t('AB_bazarcarto_popuptemplate_label'))
                        ->suggests('_map_popup_html.twig')
                        ->showIf([
                            'dynamic' => true,
                            'entrydisplay' => 'popup',
                        ]),
                    Setting::text('popupcustomtemplate')
                        ->label(_t('AB_bazarcarto_popupcustomtemplate_label'))
                        ->hint(_t('AB_bazarcarto_popupcustomtemplate_hint'))
                        ->suggests('custom_map_popup.twig')
                        ->showIf([
                            'dynamic' => true,
                            'entrydisplay' => 'popup',
                            'popuptemplate' => 'custom',
                        ]),
                    Setting::formField('popupselectedfields')
                        ->label(_t('AB_bazarliste_popupselectedfields_label'))
                        ->default('')
                        ->multiple()
                        ->showIf([
                            'dynamic' => true,
                            'entrydisplay' => 'popup',
                            'popuptemplate' => '_map_popup_html.twig|custom',
                        ]),
                    Setting::formField('necessary_fields')
                        ->label(_t('AB_bazarliste_popupneededfields_label'))
                        ->suggests('bf_titre,imagebf_image')
                        ->multiple()
                        ->showIf([
                            'dynamic' => true,
                            'entrydisplay' => 'popup',
                            'popuptemplate' => '_map_popup_from_data.twig|custom',
                        ]),
                    Setting::fieldMapping('displayfields')
                        ->showIf('dynamic')
                        ->subSettings(
                            Setting::formField('markerhover')
                            ->label(_t('AB_bazarcarto_displayfields_markhover_label'))
                            ->default('bf_titre'),
                        ),
                    Setting::checkbox('smallmarker')
                        ->label(_t('AB_bazarcarto_smallmarker_label'))
                        ->default('0')
                        ->checkedValues('1', '0'),
                    Setting::checkbox('scrollwheelzoom')
                        ->label(_t('AB_bazarcarto_zoommolette_label'))
                        ->default('false'),
                ),
            Component::for('bazarcalendar')
                ->writes('entrylist')
                ->category(Category::Lists)
                ->label(_t('AB_bazarcalendar'))
                ->icon('calendar')
                ->pin('template', 'calendar')
                ->description(_t('AB_bazarcalendar_description'))
                ->previewHeight('450px')
                ->settings(self::formSetting())
                ->group(self::commonSettings(), self::commonDisplaySettings())
                ->settings(
                    Setting::fieldMapping('fieldmapping')
                        ->subSettings(
                            Setting::formField('bf_date_debut_evenement')
                            ->label(_t('AB_bazar_bf_date_debut_evenement_label'))
                            ->default(''),
                            Setting::formField('bf_date_fin_evenement')
                            ->label(_t('AB_bazar_bf_date_fin_evenement_label'))
                            ->default(''),
                        ),
                    Setting::choice('showlist', [
                        'week' => _t('AB_bazarcalendar_showlist_week'),
                        'month' => _t('AB_bazarcalendar_showlist_month'),
                        'year' => _t('AB_bazarcalendar_showlist_year'),
                    ])
                        ->label(_t('AB_bazarcalendar_showlist_label'))
                        ->default('year'),
                    Setting::choice('initialview', [
                        'dayGridMonth' => _t('AB_bazarcalendar_initialview_month'),
                        'timeGridWeek' => _t('AB_bazarcalendar_initialview_week'),
                        'timeGridDay' => _t('AB_bazarcalendar_initialview_day'),
                        'list' => _t('AB_bazarcalendar_initialview_list'),
                    ])
                        ->label(_t('AB_bazarcalendar_initialview_label'))
                        ->default('dayGridMonth'),
                    Setting::choice('entrydisplay', [
                        'direct' => _t('AB_bazarcarto_entrydisplay_option_direct'),
                        'sidebar' => _t('AB_bazarcarto_entrydisplay_option_sidebar'),
                        'modal' => _t('AB_bazarcarto_entrydisplay_option_modal'),
                        'newtab' => _t('AB_bazarcarto_entrydisplay_option_newtab'),
                    ])
                        ->label(_t('AB_bazarcarto_entrydisplay_label'))
                        ->default('modal')
                        ->suggests('modal'),
                    Setting::checkbox('showicalbutton')
                        ->label(_t('AB_bazarcarto_showicalbutton_label'))
                        ->default('false'),
                ),
            Component::for('bazaragenda')
                ->writes('entrylist')
                ->category(Category::Lists)
                ->label(_t('AB_bazaragenda_label'))
                ->icon('calendar-event')
                ->pin('template', 'agenda')
                ->description(_t('AB_bazaragenda_description'))
                ->previewHeight('450px')
                ->settings(self::formSetting())
                ->group(self::commonSettings(), self::commonDisplaySettings())
                ->settings(
                    Setting::fieldMapping('fieldmapping')
                        ->subSettings(
                            Setting::formField('bf_date_debut_evenement')
                            ->label(_t('AB_bazar_bf_date_debut_evenement_label'))
                            ->default('')
                            ->extraFields([
                                'created_at',
                                'updated_at',
                            ]),
                            Setting::formField('bf_date_fin_evenement')
                            ->label(_t('AB_bazar_bf_date_fin_evenement_label'))
                            ->default('')
                            ->extraFields([
                                'created_at',
                                'updated_at',
                            ]),
                        ),
                    Setting::number('columns')
                        ->label(_t('AB_bazaragenda_nbcol_label'))
                        ->default(''),
                    Setting::checkbox('modal')
                        ->label(_t('AB_bazaragenda_modal_label'))
                        ->default('')
                        ->checkedValues('1', ''),
                ),
            Component::for('bazarannuaire')
                ->writes('entrylist')
                ->category(Category::Lists)
                ->label(_t('AB_bazarannuaire_label'))
                ->icon('list-details')
                ->pin('template', 'annuaire_alphabetique')
                ->description(_t('AB_bazarannuaire_description'))
                ->previewHeight('450px')
                ->settings(self::formSetting())
                ->group(self::commonSettings(), self::commonDisplaySettings()),
            Component::for('bazarcarousel')
                ->writes('entrylist')
                ->category(Category::Lists)
                ->label(_t('AB_bazarcarousel_label'))
                ->icon('arrows-horizontal')
                ->pin('template', 'carousel')
                ->description(_t('AB_bazarcarousel_description'))
                ->hint(_t('AB_bazarcarousel_hint'))
                ->previewHeight('450px')
                ->settings(self::formSetting())
                ->group(self::commonSettings(), self::commonDisplaySettings())
                ->settings(
                    Setting::checkbox('notitle')
                        ->label(_t('AB_bazarcarousel_sanstitre_label'))
                        ->default('')
                        ->checkedValues('oui', ''),
                    Setting::checkbox('withpage')
                        ->label(_t('AB_bazarcarousel_avecpage_label'))
                        ->hint(_t('AB_bazarcarousel_avecpage_hint'))
                        ->default('')
                        ->checkedValues('oui', ''),
                    Setting::checkbox('showlinkinsteadofurl')
                        ->label(_t('AB_bazarcarousel_showlinkinsteadofurl_label'))
                        ->default('')
                        ->checkedValues('oui', ''),
                    Setting::fieldMapping('fieldmapping')
                        ->subSettings(
                            Setting::formField('bf_titre')
                            ->label(_t('AB_bazarcarousel_bf_titre_label'))
                            ->default('')
                            ->extraFields([
                                'owner',
                            ]),
                        ),
                ),
            Component::for('bazarlistephotobox')
                ->writes('entrylist')
                ->category(Category::Lists)
                ->label(_t('AB_bazarlistephotobox_label'))
                ->icon('photo')
                ->pin('template', 'photobox')
                ->description(_t('AB_bazarlistephotobox_description'))
                ->hint(_t('AB_bazarlistephotobox_hint'))
                ->previewHeight('450px')
                ->settings(self::formSetting())
                ->group(self::commonSettings(), self::commonDisplaySettings()),
            Component::for('bazarlisteliens')
                ->writes('entrylist')
                ->category(Category::Lists)
                ->label(_t('AB_bazarlisteliens_label'))
                ->icon('link')
                ->pin('template', 'liste_liens')
                ->description(_t('AB_bazarlisteliens_description'))
                ->previewHeight('450px')
                ->settings(self::formSetting())
                ->group(self::commonSettings(), self::commonDisplaySettings()),
            Component::for('bazarblog')
                ->writes('entrylist')
                ->category(Category::Lists)
                ->label(_t('AB_bazarblog_label'))
                ->icon('file')
                ->pin('template', 'blog')
                ->description(_t('AB_bazarblog_description'))
                ->hint(_t('AB_bazarblog_hint'))
                ->previewHeight('450px')
                ->settings(self::formSetting())
                ->group(self::commonSettings(), self::commonDisplaySettings())
                ->settings(
                    Setting::checkbox('header')
                        ->label(_t('AB_bazarblog_header_label'))
                        ->default('true')
                        ->checkedValues('true', 'false'),
                    Setting::checkbox('show_author')
                        ->label(_t('AB_bazarblog_show_author_label'))
                        ->default('0')
                        ->checkedValues('1', '0'),
                    Setting::checkbox('show_date')
                        ->label(_t('AB_bazarblog_show_date_label'))
                        ->default('0')
                        ->checkedValues('1', '0'),
                    Setting::fieldMapping('fieldmapping')
                        ->subSettings(
                            Setting::formField('created_at')
                            ->label(_t('AB_bazarblog_date_creation_fiche_label'))
                            ->default('')
                            ->extraFields([
                                'created_at',
                                'updated_at',
                            ]),
                        ),
                ),
            Component::for('bazartableau')
                ->writes('entrylist')
                ->category(Category::Lists)
                ->label(_t('AB_bazartableau_label'))
                ->icon('table')
                ->pin('template', 'tableau.tpl.html')
                ->description(_t('AB_bazartableau_description'))
                ->previewHeight('450px')
                ->notOffered()
                ->settings(self::formSetting())
                ->group(self::commonSettings(), self::commonDisplaySettings())
                ->settings(
                    Setting::formField('columnfieldsids')
                        ->label(_t('AB_bazartableau_columnfieldsids_label'))
                        ->hint(_t('AB_bazartableau_columnfieldsids_hint'))
                        ->default('')
                        ->multiple()
                        ->extraFields([
                            'form_id',
                            'created_at',
                            'updated_at',
                            'url',
                        ]),
                    Setting::text('columntitles')
                        ->label(_t('AB_bazartableau_columntitles_label'))
                        ->hint(_t('AB_bazartableau_columntitles_hint'))
                        ->default(''),
                    Setting::checkbox('checkboxfieldsincolumns')
                        ->label(_t('AB_bazartableau_checkboxfieldsincolumns_label'))
                        ->default('true')
                        ->checkedValues('true', 'false'),
                    Setting::checkbox('displayimagesasthumbnails')
                        ->label(_t('AB_bazartableau_displayimagesasthumbnails_label'))
                        ->default('false')
                        ->checkedValues('true', 'false'),
                    Setting::checkbox('displayvaluesinsteadofkeys')
                        ->label(_t('AB_bazartableau_displayvaluesinsteadofkeys_label'))
                        ->default('false')
                        ->checkedValues('true', 'false'),
                    Setting::formField('sumfieldsids')
                        ->label(_t('AB_bazartableau_sumfieldsids_label'))
                        ->hint(_t('AB_bazartableau_sumfieldsids_hint'))
                        ->default('')
                        ->multiple(),
                    Setting::choice('displayadmincol', [
                        '' => _t('NO'),
                        'yes' => _t('YES'),
                        'onlyadmins' => _t('AB_bazartableau_displayadmincol_onlyadmins'),
                    ])
                        ->label(_t('AB_bazartableau_displayadmincol_label'))
                        ->hint(_t('AB_bazartableau_displayadmincol_hint'))
                        ->default(''),
                    Setting::choice('displaycreationdate', [
                        '' => _t('NO'),
                        'yes' => _t('YES'),
                        'onlyadmins' => _t('AB_bazartableau_displayadmincol_onlyadmins'),
                    ])
                        ->label(_t('AB_bazartableau_displaycreationdate_label'))
                        ->default(''),
                    Setting::choice('displaylastchangedate', [
                        '' => _t('NO'),
                        'yes' => _t('YES'),
                        'onlyadmins' => _t('AB_bazartableau_displayadmincol_onlyadmins'),
                    ])
                        ->label(_t('AB_bazartableau_displaylastchangedate_label'))
                        ->default(''),
                    Setting::choice('displayowner', [
                        '' => _t('NO'),
                        'yes' => _t('YES'),
                        'onlyadmins' => _t('AB_bazartableau_displayadmincol_onlyadmins'),
                    ])
                        ->label(_t('AB_bazartableau_displayowner_label'))
                        ->default(''),
                    Setting::text('defaultcolumnwidth')
                        ->label(_t('AB_bazartableau_defaultcolumnwidth_label'))
                        ->hint(_t('AB_bazartableau_defaultcolumnwidth_hint'))
                        ->default(''),
                    Setting::columnsWidth('columnswidth')
                        ->label(_t('AB_bazartableau_columnswidth_label'))
                        ->hint(_t('AB_bazartableau_columnswidth_hint'))
                        ->subSettings(
                            Setting::formField('field')
                            ->label(_t('AB_bazartableau_columnswidth_field_label')),
                            Setting::text('width')
                            ->label(_t('AB_bazartableau_columnswidth_width_label'))
                            ->default(''),
                        ),
                    Setting::checkbox('exportallcolumns')
                        ->label(_t('AB_bazartableau_exportallcolumns_label'))
                        ->default('false'),
                ),
            Component::for('bazarmapandtable')
                ->writes('entrylist')
                ->category(Category::Lists)
                ->label(_t('AB_BAZAR_MAP_AND_TABLE_LABEL'))
                ->icon('map-2')
                ->pin('template', 'map-and-table')
                ->description(_t('AB_bazarcarto_description'))
                ->hint(_t('AB_bazarcarto_hint'))
                ->previewHeight('450px')
                ->settings(self::formSetting())
                ->group(self::commonSettings(), self::commonDisplaySettings())
                ->settings(
                    Setting::choice('provider', [
                        'OpenStreetMap.Mapnik',
                        'OpenStreetMap.BlackAndWhite',
                        'OpenStreetMap.DE',
                        'OpenStreetMap.France',
                        'OpenStreetMap.HOT',
                        'OpenTopoMap',
                        'Stadia.AlidadeSmooth',
                        'Stadia.AlidadeSmoothDark',
                        'Stadia.OSMBright',
                        'Stamen.Toner',
                        'Stamen.TonerBackground',
                        'Stamen.TonerLite',
                        'Stamen.Watercolor',
                        'Stamen.Terrain',
                        'Stamen.TerrainBackground',
                        'Esri.WorldStreetMap',
                        'Esri.DeLorme',
                        'Esri.WorldTopoMap',
                        'Esri.WorldImagery',
                        'Esri.WorldTerrain',
                        'Esri.WorldShadedRelief',
                        'Esri.WorldPhysical',
                        'Esri.OceanBasemap',
                        'Esri.NatGeoWorldMap',
                        'Esri.WorldGrayCanvas',
                        'HERE.normalDay',
                        'MtbMap',
                        'CartoDB.Positron',
                        'CartoDB.PositronNoLabels',
                        'CartoDB.PositronOnlyLabels',
                        'CartoDB.DarkMatter',
                        'CartoDB.DarkMatterNoLabels',
                        'CartoDB.DarkMatterOnlyLabels',
                        'HikeBike.HikeBike',
                        'HikeBike.HillShading',
                        'BasemapAT.orthofoto',
                        'NASAGIBS.ViirsEarthAtNight2012',
                    ])
                        ->label(_t('AB_bazarcarto_provider_label'))
                        ->default('OpenStreetMap.Mapnik')
                        ->required(),
                    Setting::geo('coordinates')
                        ->label(_t('AB_bazarcarto_coordinates_label')),
                    Setting::checkbox('cluster')
                        ->label(_t('AB_bazarcarto_cluster_label'))
                        ->default('false'),
                    Setting::text('width')
                        ->label(_t('AB_bazarcarto_width_label'))
                        ->hint('500px, 100%...')
                        ->default('100%'),
                    Setting::text('height')
                        ->label(_t('AB_bazarcarto_height_label'))
                        ->default('300px'),
                    Setting::choice('entrydisplay', [
                        'direct' => _t('AB_bazarcarto_entrydisplay_option_direct'),
                        'sidebar' => _t('AB_bazarcarto_entrydisplay_option_sidebar'),
                        'modal' => _t('AB_bazarcarto_entrydisplay_option_modal'),
                        'newtab' => _t('AB_bazarcarto_entrydisplay_option_newtab'),
                        'popup' => _t('AB_bazarcarto_entrydisplay_option_popup'),
                    ])
                        ->label(_t('AB_bazarcarto_entrydisplay_label'))
                        ->default('sidebar')
                        ->showIf('dynamic'),
                    Setting::choice('popuptemplate', [
                        '_map_popup_html.twig' => _t('AB_bazarcarto_popuptemplate_entry_from_html'),
                        '_map_popup_from_data.twig' => _t('AB_bazarcarto_popuptemplate_entry_from_data'),
                        'custom' => _t('AB_bazarcarto_popuptemplate_custom'),
                    ])
                        ->label(_t('AB_bazarcarto_popuptemplate_label'))
                        ->suggests('_map_popup_html.twig')
                        ->showIf([
                            'dynamic' => true,
                            'entrydisplay' => 'popup',
                        ]),
                    Setting::text('popupcustomtemplate')
                        ->label(_t('AB_bazarcarto_popupcustomtemplate_label'))
                        ->hint(_t('AB_bazarcarto_popupcustomtemplate_hint'))
                        ->suggests('custom_map_popup.twig')
                        ->showIf([
                            'dynamic' => true,
                            'entrydisplay' => 'popup',
                            'popuptemplate' => 'custom',
                        ]),
                    Setting::formField('popupselectedfields')
                        ->label(_t('AB_bazarliste_popupselectedfields_label'))
                        ->default('')
                        ->multiple()
                        ->showIf([
                            'dynamic' => true,
                            'entrydisplay' => 'popup',
                            'popuptemplate' => '_map_popup_html.twig|custom',
                        ]),
                    Setting::formField('necessary_fields')
                        ->label(_t('AB_bazarliste_popupneededfields_label'))
                        ->suggests('bf_titre,imagebf_image')
                        ->multiple()
                        ->showIf([
                            'dynamic' => true,
                            'entrydisplay' => 'popup',
                            'popuptemplate' => '_map_popup_from_data.twig|custom',
                        ]),
                    Setting::fieldMapping('displayfields')
                        ->showIf('dynamic')
                        ->subSettings(
                            Setting::formField('markerhover')
                            ->label(_t('AB_bazarcarto_displayfields_markhover_label'))
                            ->default('bf_titre'),
                        ),
                    Setting::checkbox('smallmarker')
                        ->label(_t('AB_bazarcarto_smallmarker_label'))
                        ->default('0')
                        ->checkedValues('1', '0'),
                    Setting::checkbox('scrollwheelzoom')
                        ->label(_t('AB_bazarcarto_zoommolette_label'))
                        ->default('false'),
                    Setting::formField('columnfieldsids')
                        ->label(_t('AB_bazartableau_columnfieldsids_label'))
                        ->hint(_t('AB_bazartableau_columnfieldsids_hint'))
                        ->default('')
                        ->multiple()
                        ->extraFields([
                            'form_id',
                            'created_at',
                            'updated_at',
                            'url',
                        ]),
                    Setting::text('columntitles')
                        ->label(_t('AB_bazartableau_columntitles_label'))
                        ->hint(_t('AB_bazartableau_columntitles_hint'))
                        ->default(''),
                    Setting::checkbox('checkboxfieldsincolumns')
                        ->label(_t('AB_bazartableau_checkboxfieldsincolumns_label'))
                        ->default('true')
                        ->checkedValues('true', 'false'),
                    Setting::checkbox('displayimagesasthumbnails')
                        ->label(_t('AB_bazartableau_displayimagesasthumbnails_label'))
                        ->default('false')
                        ->checkedValues('true', 'false'),
                    Setting::checkbox('displayvaluesinsteadofkeys')
                        ->label(_t('AB_bazartableau_displayvaluesinsteadofkeys_label'))
                        ->default('false')
                        ->checkedValues('true', 'false'),
                    Setting::formField('sumfieldsids')
                        ->label(_t('AB_bazartableau_sumfieldsids_label'))
                        ->hint(_t('AB_bazartableau_sumfieldsids_hint'))
                        ->default('')
                        ->multiple(),
                    Setting::choice('displayadmincol', [
                        '' => _t('NO'),
                        'yes' => _t('YES'),
                        'onlyadmins' => _t('AB_bazartableau_displayadmincol_onlyadmins'),
                    ])
                        ->label(_t('AB_bazartableau_displayadmincol_label'))
                        ->hint(_t('AB_bazartableau_displayadmincol_hint'))
                        ->default(''),
                    Setting::choice('displaycreationdate', [
                        '' => _t('NO'),
                        'yes' => _t('YES'),
                        'onlyadmins' => _t('AB_bazartableau_displayadmincol_onlyadmins'),
                    ])
                        ->label(_t('AB_bazartableau_displaycreationdate_label'))
                        ->default(''),
                    Setting::choice('displaylastchangedate', [
                        '' => _t('NO'),
                        'yes' => _t('YES'),
                        'onlyadmins' => _t('AB_bazartableau_displayadmincol_onlyadmins'),
                    ])
                        ->label(_t('AB_bazartableau_displaylastchangedate_label'))
                        ->default(''),
                    Setting::choice('displayowner', [
                        '' => _t('NO'),
                        'yes' => _t('YES'),
                        'onlyadmins' => _t('AB_bazartableau_displayadmincol_onlyadmins'),
                    ])
                        ->label(_t('AB_bazartableau_displayowner_label'))
                        ->default(''),
                    Setting::text('defaultcolumnwidth')
                        ->label(_t('AB_bazartableau_defaultcolumnwidth_label'))
                        ->hint(_t('AB_bazartableau_defaultcolumnwidth_hint'))
                        ->default(''),
                    Setting::columnsWidth('columnswidth')
                        ->label(_t('AB_bazartableau_columnswidth_label'))
                        ->hint(_t('AB_bazartableau_columnswidth_hint'))
                        ->subSettings(
                            Setting::formField('field')
                            ->label(_t('AB_bazartableau_columnswidth_field_label')),
                            Setting::text('width')
                            ->label(_t('AB_bazartableau_columnswidth_width_label'))
                            ->default(''),
                        ),
                    Setting::checkbox('exportallcolumns')
                        ->label(_t('AB_bazartableau_exportallcolumns_label'))
                        ->default('false'),
                    Setting::choice('tablewith', [
                        '' => _t('AB_BAZAR_MAP_AND_TABLE_TABLEWITH_ALL'),
                        'only-geolocation' => _t('AB_BAZAR_MAP_AND_TABLE_TABLEWITH_ONLY_GEOLOC'),
                        'no-geolocation' => _t('AB_BAZAR_MAP_AND_TABLE_TABLEWITH_NO_GEOLOC'),
                    ])
                        ->label(_t('AB_BAZAR_MAP_AND_TABLE_TABLEWITH_LABEL'))
                        ->default(''),
                ),
        ];
    }

    /**
     * A presentation for every list template this wiki has added of its own.
     *
     * @return list<Component>
     */
    private function customTemplateComponents(): array
    {
        $components = [];
        foreach ($this->getService(Storage::class)->glob('custom/templates/bazar/*.twig') as $path) {
            $file = str_replace('custom/templates/bazar/', '', $path);
            if (str_starts_with($file, 'fiche')) {
                continue;
            }
            $name = str_replace('.twig', '', $file);
            $translated = _t('AB_' . $name . '_label');
            $label = $translated === 'AB_' . $name . '_label'
                ? _t('ACTION_BUILDER_TEMPLATE_CUSTOM') . ' ' . $name
                : $translated;

            $components[] = Component::for($name)
                ->writes('entrylist')
                ->pin('template', $file)
                ->category(Category::Lists)
                ->label($label)
                ->icon('layout-list')
                ->previewHeight('450px')
                ->settings(self::formSetting())
                ->group(self::commonSettings(), self::commonDisplaySettings());
        }

        return $components;
    }

    /** The settings every presentation of a list shares. */
    private static function commonSettings(): SettingGroup
    {
        return SettingGroup::named(
            _t('AB_bazar_commons_title'),
            self::querySetting(),
            Setting::choice('search', [
                'true' => _t('AB_attach_yes'),
                'false' => _t('AB_attach_no'),
                'dynamic' => [
                    'label' => _t('AB_bazar_commons_search_label_dynamic'),
                    'showif' => 'dynamic',
                ],
            ])
                ->label(_t('AB_bazar_commons_search_label'))
                ->withIcon('search'),
            Setting::formField('searchfields')
                ->label(_t('AB_bazar_commons_search_fields_label'))
                ->default('bf_titre')
                ->multiple()
                ->showIf([
                    'search' => 'dynamic',
                ])
                ->onlyFor([
                    'entrylist',
                    'entrymap',
                    'bazarmapandtable',
                ])
                ->exceptFor([
                    'bazarcarousel',
                ]),
            Setting::checkbox('dynamic')
                ->label(_t('AB_bazar_commons_dynamic_label'))
                ->onlyFor([
                    'entrylist',
                    'entrymap',
                    'bazarmapandtable',
                    'bazarcalendar',
                    'bazartableau',
                ]),
            Setting::number('pagination')
                ->label(_t('AB_bazar_commons_pagination_label'))
                ->hint(_t('AB_bazar_commons_pagination_hint'))
                ->exceptFor([
                    'entrymap',
                    'bazarmapandtable',
                    'bazartimeline',
                    'bazarcarousel',
                    'bazarcalendar',
                    'bazartableau',
                ]),
            Setting::number('nb')
                ->label(_t('AB_bazar_commons2_nb_label'))
                ->hint(_t('AB_bazar_commons2_nb_hint')),
            Setting::formField('colorfield')
                ->label(_t('AB_bazar_commons_colorfield_label'))
                ->onlyFor([
                    'entrymap',
                    'bazarmapandtable',
                    'entrylist',
                    'bazarcalendar',
                    'bazartableau',
                    'bazarcard',
                    'bazartimeline',
                ])
                ->extraFields([
                    'form_id',
                ])
                ->raw('only', 'lists'),
            Setting::formField('iconfield')
                ->label(_t('AB_bazar_commons_iconfield_label'))
                ->onlyFor([
                    'entrymap',
                    'bazarmapandtable',
                    'entrylist',
                    'bazarcalendar',
                    'bazartimeline',
                    'bazartableau',
                    'bazarcard',
                ])
                ->extraFields([
                    'form_id',
                ])
                ->raw('only', 'lists'),
            Setting::checkbox('minicalendar')
                ->label(_t('AB_bazar_commons_minical'))
                ->default('false')
                ->onlyFor([
                    'bazarcalendar',
                ]),
            Setting::checkbox('showexportbuttons')
                ->label(_t('AB_bazar_commons2_showexportbuttons'))
                ->default('0')
                ->exceptFor([
                    'bazarcarousel',
                    'bazarcalendar',
                ])
                ->checkedValues('1', '0'),
            Setting::checkbox('showmapinlistview')
                ->label(_t('AB_bazar_commons2_showmapinlistview_label'))
                ->hint(_t('AB_bazar_commons2_showmapinlistview_hint'))
                ->default('0')
                ->exceptFor([
                    'entrymap',
                    'bazarmapandtable',
                ])
                ->checkedValues('1', '0'),
        )->width('33%');
    }

    /** ...and the second block, which is about how the entries are displayed. */
    private static function commonDisplaySettings(): SettingGroup
    {
        $settings = array_merge(self::facetSettings(), [
            Setting::checkbox('filteruserasowner')
                ->label(_t('AB_bazar_commons2_filter_user_as_owner'))
                ->default('false'),
            Setting::choice('datefilter', [
                'futur' => _t('AB_bazar_commons2_filter_on_date_future'),
                'past' => _t('AB_bazar_commons2_filter_on_date_past'),
                'today' => _t('AB_bazar_commons2_filter_on_date_today'),
                '>-1M' => _t('AB_bazar_commons2_filter_on_date_for_one_month'),
                '>-0D&<+1M' => _t('AB_bazar_commons2_filter_on_date_on_current_month'),
                '>-2Y' => _t('AB_bazar_commons2_filter_on_date_for_two_years'),
                '>-7D&<+7D' => _t('AB_bazar_commons2_filter_on_date_one_week_more_and_less'),
            ])
                ->label(_t('AB_bazar_commons2_filter_on_date'))
                ->hint(_t('AB_bazar_commons2_filter_index')),
            Setting::formField('field')
                ->label(_t('AB_bazar_facettes_field_label'))
                ->default('')
                ->exceptFor([
                    'entrymap',
                    'bazarannuaire',
                    'bazarcalendar',
                    'bazarcarousel',
                ])
                ->extraFields([
                    'form_id',
                    'created_at',
                    'updated_at',
                ])
                ->raw('intro', '<h3>' . _t('AB_bazar_sort') . '</h3>'),
            Setting::choice('order', [
                'asc' => _t('AB_bazar_commons2_ordre_option_asc'),
                'desc' => _t('AB_bazar_commons2_ordre_option_desc'),
            ])
                ->label(_t('AB_bazar_commons2_ordre_label'))
                ->default('asc')
                ->exceptFor([
                    'entrymap',
                    'bazarannuaire',
                    'bazarcalendar',
                ]),
            Setting::sortFields('sortfields')
                ->showIf('dynamic')
                ->exceptFor([
                    'entrymap',
                    'bazarannuaire',
                    'bazarcalendar',
                    'bazarcarousel',
                ])
                ->raw('intro', '<center><h4>' . _t('AB_bazar_sort_dynamique') . '</h4></center>')
                ->raw('btn-label-add', _t('AB_bazar_sort_add_field'))
                ->subSettings(
                    Setting::formField('field')
                    ->label(_t('AB_bazar_facettes_field_label'))
                    ->extraFields([
                        'created_at',
                        'updated_at',
                    ]),
                    Setting::text('title')
                    ->label(_t('AB_bazar_facettes_title_label')),
                ),
        ]);

        return SettingGroup::named(_t('AB_bazar_commons2_title'), ...$settings)->width('33%');
    }

    // `gogomap` and `gogocarto` are retired names kept here on purpose: the GoGoCartoJs
    // library is gone, but page bodies still ask for them, and they have to keep reaching
    // the map action -- which answers them with `map` -- rather than falling through to a
    // plain list and looking for a template that no longer exists.
    protected bool $debug = false;

    public function formatArguments($arg)
    {
        $entryManager = $this->getService(EntryManager::class);

        $get = $this->getRequest()->query;
        $iconField = $get->get('iconfield') ?? $arg['iconfield'] ?? null;

        $icon = $get->get('icon') ?? $arg['icon'] ?? $get->get('icons') ?? $arg['icons'] ?? null;
        $iconAlreadyDefined = ($icon == $this->params->get('baz_marker_icon') || is_array($icon));
        if (!$iconAlreadyDefined) {
            if (!empty($icon)) {
                try {
                    $tabparam = $entryManager->getMultipleParameters($icon, ',', '=');
                    if (count($tabparam) > 0 && !empty($iconField)) {
                        foreach ($tabparam as $key => $data) {
                            $tabparam[$data] = $key;
                        }
                        $icon = $tabparam;
                    } else {
                        $icon = trim(array_values($tabparam)[0]);
                    }
                } catch (ParsingMultipleException $th) {
                    throw new \Exception('action entrylist : le paramètre icon est mal rempli.<br />Il doit être de la forme icon="nomIcone1=valeur1, nomIcone2=valeur2"<br/>(' . $th->getMessage() . ')');
                }
            } else {
                $icon = $this->params->get('baz_marker_icon');
            }
        }

        $colorField = $get->get('colorfield') ?? $arg['colorfield'] ?? null;

        $color = $get->get('color') ?? $get->get('colors') ?? $arg['colors'] ?? $arg['color'] ?? null;
        $colorAlreadyDefined = ($color == $this->params->get('baz_marker_color') || is_array($color));
        if (!$colorAlreadyDefined) {
            if (!empty($color)) {
                try {
                    $tabparam = $entryManager->getMultipleParameters($color, ',', '=');
                    if (count($tabparam) > 0 && !empty($colorField)) {
                        foreach ($tabparam as $key => $data) {
                            $tabparam[$data] = $key;
                        }
                        $color = $tabparam;
                    } else {
                        $color = trim(array_values($tabparam)[0]);
                    }
                } catch (ParsingMultipleException $th) {
                    throw new \Exception('action entrylist : le paramètre color est mal rempli.<br />Il doit être de la forme color="couleur1=valeur1, couleur2=valeur2"<br/>(' . $th->getMessage() . ')');
                }
            } else {
                $color = $this->params->get('baz_marker_color');
            }
        }

        $template = $get->get('template') ?? $arg['template'] ?? null;
        if ($template) {
            $template = htmlspecialchars($template);
        }
        $configuredTemplate = $this->params->get('default_bazar_template');
        $template = $template ?: (is_string($configuredTemplate) ? $configuredTemplate : '');
        $dynamic = $this->formatBoolean($arg, false, 'dynamic');

        if (isset($arg['displayfields']) && is_array($arg['displayfields'])) {
            $displayFields = $arg['displayfields'];
        } else {
            $displayFields = [];
            foreach (explode(',', $arg['displayfields'] ?? '') as $field) {
                $values = explode('=', $field);
                if (count($values) == 2) {
                    $displayFields[$values[0]] = $values[1];
                }
            }
        }

        $bareTemplate = (string)preg_replace('/\.(twig|tpl\.html)$/', '', (string)$template);
        if ($bareTemplate === 'map-and-table') {
            $dynamic = true;
        }
        if ($dynamic && $bareTemplate === 'liste_accordeon') {
            $template = 'list';
        }
        if ($dynamic && $bareTemplate === 'tableau') {
            $template = 'table';
        }
        $searchfields = $this->formatArray($arg['searchfields'] ?? null);
        $searchfields = empty($searchfields) ? [PageBody::TITLE] : $searchfields;

        $agendaMode = (!empty($arg['agenda']) || !empty($arg['datefilter']) || str_starts_with($template, 'agenda'));

        $search = !isset($arg['search'])
            ? null
            : (
                $arg['search'] === 'dynamic'
                ? $arg['search']
                : (
                    in_array($arg['search'], ['true', true, '1', 1], true)
                    ? 'true'
                    : null
                )
            );

        $order = $get->get('order') ?? $arg['order'] ?? ((empty($arg['field']) && $agendaMode) ? 'desc' : 'asc');
        $sortField = $get->get('field') ?? $arg['field'] ?? ($agendaMode ? 'bf_date_debut_evenement' : PageBody::TITLE);

        $vSearchManager = $this->getService(SearchManager::class);

        $vKeywords = $vSearchManager->aggregateKeywords($arg['keywords'] ?? null, $this->getRequest()->get('q'), $this->getRequest()->get('keywords'));

        $formatted = [
            'user' => $arg['user'] ?? ((isset($arg['filteruserasowner']) && $arg['filteruserasowner'] == 'true') ?
                $this->getService(AuthenticationService::class)->getLoggedUserName() : null),

            'id' => $arg['id'] ?? $get->get('id') ?? null,

            'refresh' => $this->formatBoolean($get->all(), false, 'refresh'),

            'queries' => $vSearchManager->parseQuery($vSearchManager->aggregateQueries($arg, $get->all())),
            'keywords' => $vKeywords,
            'dateMin' => $this->formatDateMin($get->get('period') ?? $arg['period'] ?? null),

            'random' => $this->formatBoolean($arg, false, 'random'),
            'order' => $order,
            'field' => $sortField,
            'sortfields' => $this->formatArray($get->get('sortfields') ?? $arg['sortfields'] ?? []),
            'sortfieldstitles' => $this->formatArray($get->get('sortfieldstitles') ?? $arg['sortfieldstitles'] ?? []),

            'nb' => $arg['nb'] ?? null,

            'pagination' => $arg['pagination'] ?? null,

            'fieldmapping' => $arg['fieldmapping'] ?? null,

            'agenda' => $arg['datefilter'] ?? $arg['agenda'] ?? null,
            'datefilter' => $arg['datefilter'] ?? $arg['agenda'] ?? null,

            'dynamic' => $dynamic,
            'displayfields' => $displayFields,

            'necessary_fields' => $this->formatArray($get->get('necessaryfields') ?? $arg['necessaryfields'] ?? $get->get('necessary_fields') ?? $arg['necessary_fields'] ?? []),
            'extrafields' => $this->formatBoolean($arg, false, 'extrafields'),

            'template' => $template,

            'managementbar' => $this->formatBoolean($arg, true, 'managementbar'),

            'resetfiltersbutton' => $this->formatBoolean($arg, false, 'resetfiltersbutton'),

            'showexportbuttons' => $this->formatBoolean($arg, false, 'showexportbuttons'),

            'search' => $search,
            'searchfields' => $searchfields,

            'shownumentries' => $this->formatBoolean($arg, false, 'shownumentries'),

            'filtersresultnb' => $this->formatBoolean($arg, true, 'filtersresultnb'),

            'class' => $arg['class'] ?? '',

            'columns' => $arg['columns'] ?? null,

            'groups' => $this->formatArray($get->get('groups') ?? $arg['groups'] ?? null),
            'titles' => $this->formatArray($get->get('groupstitles') ?? $arg['groupstitles'] ?? $get->get('titles') ?? $arg['titles'] ?? null),

            'groupsexpanded' => $this->formatBoolean($get->get('groupsexpanded') ?? $arg, true, 'groupsexpanded'),

            'groupicons' => $this->formatArray($arg['groupicons'] ?? null),

            'filtertext' => $this->formatBoolean($arg, false, 'filtertext'),

            'filterposition' => $get->get('filterposition') ?? $arg['filterposition'] ?? 'right',
            'filtercolsize' => $get->get('filtercolsize') ?? $arg['filtercolsize'] ?? '3',

            'iconprefix' => $get->has('iconprefix') ? trim($get->getString('iconprefix')) : (isset($arg['iconprefix']) ? trim($arg['iconprefix']) : ($this->params->get('baz_marker_icon_prefix') ?? '')),
            'iconfield' => $iconField,
            'icon' => $icon,

            'colorfield' => $colorField,
            'color' => $color,

            'isInIframe' => WikiUrls::iframeSuffixFor(),

            'selectedID' => $get->get('selectedID'),
        ];

        return $this->getService(TemplateDataFactory::class)->prepare(
            (string)$formatted['template'],
            array_merge($arg, $formatted)
        );
    }

    /** @return string */
    public function run()
    {
        $this->debug = (bool)$this->getService(RuntimeConfig::class)->getValue('debug');

        $bazarListService = $this->getService(BazarListService::class);
        $vForms = $bazarListService->getForms($this->arguments);

        if ($this->arguments['dynamic']) {
            if (isset($this->arguments['zoom'])) {
                $this->arguments['zoom'] = intval($this->arguments['zoom']);
            }
            $currentUser = $this->getService(AuthenticationService::class)->getLoggedUser();

            return $this->render("@core/entries/index-dynamic-templates/{$this->arguments['template']}.twig", [
                'param' => $this->arguments,
                'params' => $this->arguments,
                'keywords' => $this->arguments['keywords'],
                'forms' => $vForms,
                'currentUserName' => empty($currentUser['name']) ? '' : $currentUser['name'],
            ]);
        }
        $entries = $bazarListService->getEntries($this->arguments, $vForms);
        // the counts and the checked boxes are computed over everything the list holds, so
        // that a second value of a box a reader has already used is still offered; what is
        // *drawn* is only what the checked facets leave
        $filters = $bazarListService->getFilters($this->arguments, $entries, $vForms, true);
        $entries = $bazarListService->filterEntriesOnFacets($entries);

        // Two lists on a page need two sets of DOM ids, so each takes the next number.
        $listIndex = $this->getService(ListIndex::class)->next();
        $this->arguments['listindex'] = $listIndex;

        // TODO put in all bazar templates

        $this->getService(AssetRegistry::class)->addJsFile('javascripts/bazar.js', true, true);

        return $this->render('@core/entries/index.twig', [
            'listId' => $listIndex,
            'filters' => $filters,
            'entries' => $entries,
            'renderedEntries' => $this->renderEntries($entries, $filters, $vForms),
            'numEntries' => count($entries),
            'param' => $this->arguments,
            'params' => $this->arguments,
            // Search form parameters
            'keywords' => $this->arguments['keywords'],
            'pageTag' => $this->getService(PageContext::class)->getTag(),
            'forms' => $vForms,
            // 'formId' => $this->arguments['id'][0] ?? null,
            'selectedID' => $this->arguments['selectedID'] ?? null,
            'facet' => $this->getRequest()->query->all()['facet'] ?? null,
            // where checking a box goes: this same page, minus whatever facet was already
            // checked (htmx appends the form's values to it) and back to page one
            'facetForm' => $this->facetForm(),
        ]);
    }

    /**
     * Where checking a facet goes, in the two spellings the form needs.
     *
     * @return array{url: string, action: string, params: array<string, string>}
     */
    private function facetForm(): array
    {
        $uri = $this->getRequest()->getRequestUri();
        [$path, $queryString] = array_pad(explode('?', $uri, 2), 2, '');
        $kept = array_values(array_filter(
            explode('&', (string)$queryString),
            static fn (string $part): bool => $part !== ''
                && preg_match('/^(facet(%5B|\[|=)|pageID=)/i', $part) !== 1
        ));

        $params = [];
        foreach ($kept as $part) {
            [$name, $value] = array_pad(explode('=', $part, 2), 2, '');
            $params[urldecode($name)] = urldecode((string)$value);
        }

        return [
            'url' => $path . ($kept === [] ? '' : '?' . implode('&', $kept)),
            'action' => $path,
            'params' => $params,
        ];
    }

    /**
     * @param array<array<string, mixed>> $entries
     * @param array<string, mixed>        $filters
     * @param array<mixed>                $pForms  the forms the entries belong to, keyed by form id
     */
    private function renderEntries(array $entries, array $filters = [], array $pForms = []): string
    {
        $showNumEntries = count($entries) === 0 || $this->arguments['shownumentries'];
        $templateName = $this->arguments['template'];
        if (strpos($templateName, '.html') === false && strpos($templateName, '.twig') === false) {
            $templateName = $templateName . '.twig';
            $this->arguments['template'] = $templateName;
        }
        $data = [];
        $data['entries'] = $entries;
        $data['resultsInfo'] = $showNumEntries ? '<div class="alert alert-info">' . _t('BAZ_IL_Y_A') . ' ' . count($data['entries']) . ' ' . (count($data['entries']) <= 1 ? _t('BAZ_FICHE') : _t('BAZ_FICHES')) . '</div>' : '';
        $data['params'] = $this->arguments;
        $data['pager_links'] = '';
        $data['filters'] = $filters;
        $data['forms'] = $pForms;

        if (!empty($this->arguments['pagination']) && $this->arguments['pagination'] > 0) {
            $query = $this->getRequest()->query->all();
            unset($query['wiki']);
            $paginationUrl = $this->getService(UrlFormatter::class)->getBaseUrl() . '/' . basename((string)($_SERVER['PHP_SELF'] ?? 'index.php'));

            $paginator = new Paginator(
                $data['entries'],
                (int)$this->arguments['pagination'],
                Paginator::pageFromQuery($query),
                $this->configuredDelta()
            );

            $data['entries'] = $paginator->getPageData();
            $links = $paginator->renderLinks($paginationUrl, $query, [
                'prev' => _t('BAZ_PRECEDENT'),
                'next' => _t('BAZ_SUIVANT'),
            ]);
            $data['pager_links'] = $links === ''
                ? ''
                : '<div class="bazar_numero yw-text-center"><ul class="yw-pagination">' . $links . '</ul></div>';
        }

        try {
            $templateBaseName = preg_replace('/\.(twig|tpl\.html)$/', '', $templateName);
            $warning = $this->missingRoleWarning((string)$templateBaseName, $pForms);
            if (PresentationRenderer::knows($templateName)) {
                return $warning . $this->getService(PresentationRenderer::class)
                    ->render($templateName, $this->itemsFrom($data['entries']), $this->arguments)
                    . $data['pager_links'];
            }
            if ($templateBaseName === 'tableau') {
                return $warning . $this->renderTableau($data);
            }
            if ($templateBaseName === 'map') {
                return $warning . $this->renderMap($data);
            }

            return $warning . $this->render("@core/{$templateName}", $data);
        } catch (TemplateNotFound $e) {
            return '<div class="alert alert-danger">' . $e->getMessage() . '</div>';
        }
    }

    /**
     * An alert naming the roles none of the listed forms can answer, or '' when they can.
     *
     * @param array<int|string, mixed>|string $forms
     */
    private function missingRoleWarning(string $templateBaseName, $forms): string
    {
        $required = $this->getService(PresentationCatalog::class)->requiredRoles($templateBaseName);
        if ($required === [] || !is_array($forms) || empty($forms)) {
            return '';
        }

        $resolver = $this->getService(FieldRoleResolver::class);
        $missing = array_filter(
            $required,
            fn (string $role) => empty(array_filter($forms, fn ($form) => is_array($form) && $resolver->field($form, $role) !== null))
        );
        if (empty($missing)) {
            return '';
        }

        return $this->render('@core/alert-message.twig', [
            'type' => 'warning',
            'message' => _t('BAZ_LIST_MISSING_ROLE', ['roles' => implode(', ', $missing)]),
        ]);
    }

    /**
     * Data preparation for tableau.twig -- historically done in PHP inside
     * templates/tableau.tpl.html; moved here when tpl.html templates died.
     *
     * @param array<string,mixed> $data the uniform bazar template data (entries, params, resultsInfo, ...)
     */
    private function renderTableau(array $data): string
    {
        /** @var FormManager $formManager */
        $formManager = $this->getService(FormManager::class);
        $entries = $data['entries'];
        $params = $data['params'];

        $prefix = '';
        $colors = [];
        $icons = [];
        $columnsInfo = [];
        $sanitizedParams = [];
        /** @var array<mixed> $optionsIfDisplayvaluesinsteadofkeys */
        $optionsIfDisplayvaluesinsteadofkeys = [];
        $sumFieldsIds = [];

        if (count($entries) > 0) {
            $formId = $entries[array_key_first($entries)]['form_id'] ?? null;
            $form = $formManager->getOne($formId);
            if (!empty($form)) {
                $fields = $form['prepared'];

                $listParams = [];
                foreach (['columntitles', 'columnfieldsids', 'sumfieldsids'] as $paramKey) {
                    $tmp = (isset($params[$paramKey]) && is_string($params[$paramKey])) ? $params[$paramKey] : '';
                    $listParams[$paramKey] = array_filter(
                        array_map('trim', explode(',', trim($tmp))),
                        function ($name) {
                            return !empty($name);
                        }
                    );
                }
                $columnTitlesNames = $listParams['columntitles'];
                $columnFieldsIdsRaw = $listParams['columnfieldsids'];
                $sumFieldsIds = $listParams['sumfieldsids'];

                $checkboxFieldsInColumns = $params['checkboxfieldsincolumns'] ?? null;
                $checkboxFieldsInColumns = !in_array($checkboxFieldsInColumns, ['0', 0, false, 'false', 'non'], true);

                foreach (['displayadmincol', 'displaycreationdate', 'displaylastchangedate', 'displayowner'] as $paramName) {
                    $paramValue = (!empty($params[$paramName]) && in_array($params[$paramName], ['yes', 'onlyadmins'], true)) ? $params[$paramName] : false;
                    switch ($paramValue) {
                        case 'onlyadmins':
                            $paramValue = $this->getService(AclService::class)->isAdmin();
                            break;
                        case 'yes':
                            $paramValue = true;
                            break;
                        default:
                            $paramValue = false;
                            break;
                    }
                    $sanitizedParams[$paramName] = $paramValue;
                }
                foreach (['displayvaluesinsteadofkeys', 'exportallcolumns', 'displayimagesasthumbnails'] as $paramName) {
                    $sanitizedParams[$paramName] = isset($params[$paramName]) && filter_var($params[$paramName], FILTER_VALIDATE_BOOLEAN);
                }

                $sanitizedParams['columnswidth'] = [];
                if (isset($params['columnswidth']) && is_string($params['columnswidth'])) {
                    foreach (explode(',', $params['columnswidth']) as $columnInfo) {
                        $columnInfoExtracted = explode('=', $columnInfo);
                        if (!empty($columnInfoExtracted[0]) && !empty($columnInfoExtracted[1])) {
                            $sanitizedParams['columnswidth'][$columnInfoExtracted[0]] = $columnInfoExtracted[1];
                        }
                    }
                }

                if (empty($columnFieldsIdsRaw)) {
                    foreach ($fields as $field) {
                        foreach ($this->tableauFieldCols($field, $checkboxFieldsInColumns, $sanitizedParams['displayimagesasthumbnails']) as $col) {
                            $columnsInfo[] = $col;
                        }
                        $this->tableauCollectOptions($field, $optionsIfDisplayvaluesinsteadofkeys, $sanitizedParams);
                    }
                } else {
                    foreach ($columnFieldsIdsRaw as $fieldId) {
                        $field = $formManager->findFieldFromNameOrPropertyName($fieldId, $formId);
                        if (!empty($field)) {
                            foreach ($this->tableauFieldCols($field, $checkboxFieldsInColumns, $sanitizedParams['displayimagesasthumbnails']) as $col) {
                                $columnsInfo[] = $col;
                            }
                            $this->tableauCollectOptions($field, $optionsIfDisplayvaluesinsteadofkeys, $sanitizedParams);
                        } elseif ($fieldId === PageBody::TITLE) {
                            $columnsInfo[] = [
                                'propertyName' => PageBody::TITLE,
                                'title' => _t('BAZ_TITREANNONCE'),
                                'key' => null,
                                'mapFieldId' => null,
                            ];
                        }
                    }
                    if ($sanitizedParams['exportallcolumns']) {
                        $alreadyDefinedPropertyNames = array_map(function ($col) {
                            return $col['propertyName'];
                        }, $columnsInfo);
                        foreach ($fields as $field) {
                            $fieldPropertyName = $field->getPropertyName();
                            if (!empty($fieldPropertyName) && !in_array($fieldPropertyName, $alreadyDefinedPropertyNames)) {
                                foreach ($this->tableauFieldCols($field, $checkboxFieldsInColumns, $sanitizedParams['displayimagesasthumbnails']) as $col) {
                                    $columnsInfo[] = array_merge($col, ['not-visible' => true]);
                                }
                                $this->tableauCollectOptions($field, $optionsIfDisplayvaluesinsteadofkeys, $sanitizedParams);
                            }
                        }
                    }
                }

                foreach ([
                    'displaycreationdate' => ['fieldName' => 'created_at', 'translationKey' => 'BAZ_DATE_CREATION'],
                    'displaylastchangedate' => ['fieldName' => 'updated_at', 'translationKey' => 'BAZ_DATE_MAJ'],
                    'displayowner' => ['fieldName' => 'owner', 'translationKey' => 'TEMPLATE_OWNER'],
                ] as $paramName => $extraCol) {
                    if (!empty($sanitizedParams[$paramName])) {
                        $columnsInfo[] = [
                            'propertyName' => $extraCol['fieldName'],
                            'title' => _t($extraCol['translationKey']),
                            'key' => null,
                            'mapFieldId' => null,
                        ];
                    }
                }

                foreach ($columnTitlesNames as $key => $value) {
                    if (isset($columnsInfo[$key])) {
                        $columnsInfo[$key]['title'] = $value;
                    }
                }

                if (!$this->getService(AclService::class)->isAdmin()) {
                    $displayedPropertyNames = array_column($columnsInfo, 'propertyName');
                    foreach ($entries as $index => $entry) {
                        $entryFormId = $entry['form_id'];
                        if (strval($entryFormId) != strval(intval($entryFormId))) {
                            unset($entries[$index]);
                        } else {
                            $entryForm = $formManager->getOne($entryFormId);
                            if (empty($entryForm['prepared'])) {
                                unset($entries[$index]);
                            } else {
                                foreach ($entryForm['prepared'] as $field) {
                                    if (empty($field->getPropertyName()) || !in_array($field->getPropertyName(), $displayedPropertyNames, true)) {
                                        continue;
                                    }
                                    if ($field instanceof EmailField && !$field->canRead($entries[$index], null)) {
                                        $entries[$index][$field->getPropertyName()] = '***@***.***';
                                    } elseif (empty(trim($field->renderStaticIfPermitted($entry) ?? ''))) {
                                        $entries[$index][$field->getPropertyName()] = '';
                                    }
                                }
                            }
                        }
                    }
                }

                foreach ($entries as $entry) {
                    $colors[$entry['tag']] = $this->getService(EntryDisplay::class)->customValueFor($params['color'] ?? null, $params['colorfield'] ?? null, $entry, '');
                    $icons[$entry['tag']] = $this->getService(EntryDisplay::class)->customValueFor($params['icon'] ?? null, $params['iconfield'] ?? null, $entry, '');
                }
            } else {
                $prefix = $this->render('@core/alert-message.twig', [
                    'type' => 'danger',
                    'message' => _t('BAZ_NO_FORMS_FOUND'),
                ]);
            }
        }

        return $prefix . $this->render('@core/tableau.twig', [
            'infoRes' => $data['resultsInfo'],
            'params' => $params,
            'columnsInfo' => $columnsInfo,
            'titleFieldName' => $this->getService(FormPropertiesService::class)->titleFieldName($form ?? null),
            'entries' => $entries,
            'sumFieldsIds' => $sumFieldsIds,
            'displayadmincol' => $sanitizedParams['displayadmincol'] ?? null,
            'displayvaluesinsteadofkeys' => $sanitizedParams['displayvaluesinsteadofkeys'] ?? null,
            'optionsIfDisplayvaluesinsteadofkeys' => $optionsIfDisplayvaluesinsteadofkeys,
            'colors' => $colors,
            'icons' => $icons,
            'columnswidth' => $sanitizedParams['columnswidth'] ?? [],
        ]);
    }

    /**
     * JS builder for the static (non-dynamic) leaflet map -- historically done in PHP
     * inside templates/map.tpl.html; moved here when tpl.html templates died.
     *
     * @param array<string,mixed> $data the uniform bazar template data (entries, params, resultsInfo, ...)
     */
    private function renderMap(array $data): string
    {
        $params = $data['params'];
        $entries = $data['entries'];
        $output = $data['resultsInfo'];
        if (count($entries) === 0) {
            return $output;
        }
        $js = '';

        $this->getService(AssetRegistry::class)->addCssFile('styles/vendor/leaflet/leaflet.css');
        $this->getService(AssetRegistry::class)->addCssFile('styles/yw-entries.css');
        $this->getService(AssetRegistry::class)->addCssFile('javascripts/vendor/leaflet-draw/leaflet.draw.css');
        $this->getService(AssetRegistry::class)->addJsFile('javascripts/bazar.js', true, true);
        $this->getService(AssetRegistry::class)->addJsFile('javascripts/vendor/leaflet/leaflet.min.js');
        $this->getService(AssetRegistry::class)->addJsFile('javascripts/vendor/leaflet-providers/leaflet-providers.js');
        $this->getService(AssetRegistry::class)->addJsFile('javascripts/vendor/leaflet-draw/leaflet.draw.js', false, true);

        $output .= '<div id="osmmap' . $params['listindex'] . '" class="no-dblclick" style="width:' . $params['width'] . '; height:' . $params['height'] . '"></div>';

        if ($params['spider'] == 'true' or $params['spider'] == '1') {
            $this->getService(AssetRegistry::class)->addJsFile('javascripts/vendor/leaflet-spiderfier/oms.min.js');
            $markersjs = 'var popups = Array();' . "\n" . 'oms = new OverlappingMarkerSpiderfier(map' . $params['listindex'] . ');' . "\n" .
            'var popup = new L.Popup();
        oms.addListener("click", function(marker) {
            marker.openPopup();
        });
        oms.addListener(\'spiderfy\', function(markers) {
          map' . $params['listindex'] . '.closePopup();
        });' . "\n";
        } elseif ($params['cluster'] == 'true' or $params['cluster'] == '1') {
            $this->getService(AssetRegistry::class)->addCssFile('styles/vendor/leaflet-markercluster/leaflet-markercluster.css');
            $this->getService(AssetRegistry::class)->addJsFile('javascripts/vendor/leaflet-markercluster/leaflet-markercluster.min.js');
            $markersjs = 'var markerscluster = new L.MarkerClusterGroup();' . "\n";
        } else {
            $markersjs = '';
        }

        if ($params['fullscreen'] == 'true' || $params['fullscreen'] == '1') {
            $params['fullscreen'] = 'true';
            $this->getService(AssetRegistry::class)->addCssFile('styles/vendor/leaflet-fullscreen/leaflet-fullscreen.css');
            $this->getService(AssetRegistry::class)->addJsFile('javascripts/vendor/leaflet-fullscreen/leaflet-fullscreen.js');
        } else {
            $params['fullscreen'] = 'false';
        }

        $vAllGeometries = $geometriesWithoutMarker = [];
        $entry = ['html_data' => ''];

        $vGeolocationField = $params['geolocationfield'] ?? 'bf_geolocation';

        foreach ($entries as $entry) {
            $vGeolocation = $entry[$vGeolocationField] ?? [];

            $vLatitude = $vGeolocation['latitude'] ?? '';
            $vLongitude = $vGeolocation['longitude'] ?? '';
            $vGeometries = $vGeolocation['geometries'] ?? '';
            $vGeometries = str_replace(
                '"type":"FeatureCollection"',
                '"id":"' . $entry['tag'] . '","type":"FeatureCollection"',
                $vGeometries
            );

            $color = $this->getService(EntryDisplay::class)->customValueFor($params['color'], $params['colorfield'], $entry, $this->getService(RuntimeConfig::class)['baz_marker_color']);

            $icon = $params['iconprefix']
                    . $this->getService(EntryDisplay::class)->customValueFor($params['icon'], $params['iconfield'], $entry, $this->getService(RuntimeConfig::class)['baz_marker_icon']);

            if (is_numeric($vLatitude) && is_numeric($vLongitude)) {
                $markersjs .= '
				i++;
				var markerLocation = new L.LatLng(' . $vLatitude . ', ' . $vLongitude . ');
				marker[i] = new L.Marker(
						markerLocation,
						{
								icon: L.divIcon({
										iconSize: ' . $params['iconSize'] . ',
										iconAnchor: ' . $params['iconAnchor'] . ',
										popupAnchor: ' . $params['popupAnchor'] . ',
										className: \'bazar-marker' . $params['smallmarker'] . '\',
										html: \'<div class="bazar-entry" '
                                        . str_replace('\'', '', $entry['html_data']) . ' style="color:' . $color . ';">'
                                        . (!empty($icon) ? ($this->getService(\YesWiki\Render\Service\TemplateEngine::class)->legacyIconToSprite($icon) ?? '<i class="' . $icon . '"></i>') : '')
                                        . '</div>\'
								}),
								title: \'' . addslashes($entry['title'] ?? $entry['bf_titre'] ?? '') . '\'
						});
				marker[i].bindPopup(\'' . preg_replace("(\r\n|\n|\r|)", '', addslashes($this->getService(EntryDisplay::class)->renderEntry($params['managementbar'], $entry))) . '\');
				';
                if ($params['spider'] == 'true' or $params['spider'] == '1') {
                    $markersjs .= 'map' . $params['listindex'] . '.addLayer(marker[i]);' . "\n" . 'oms.addMarker(marker[i]);' . "\n";
                } elseif ($params['cluster'] == 'true' or $params['cluster'] == '1') {
                    $markersjs .= 'markerscluster.addLayer(marker[i]);' . "\n";
                } else {
                    $markersjs .= 'map' . $params['listindex'] . '.addLayer(marker[i]);' . "\n";
                }
            } elseif (!empty($vGeometries)) {
                $geometriesWithoutMarker[$entry['tag']] = '<div class="bazar-entry" ' . str_replace('\'', '', $entry['html_data']) . '></div>';
            }
            if (!empty($vGeometries)) {
                $vAllGeometries[$entry['tag']] = $vGeometries;
            }
        }

        if ($params['cluster'] == 'true' or $params['cluster'] == '1') {
            $markersjs .= 'map' . $params['listindex'] . '.addLayer(markerscluster);' . "\n";
        }
        $js .=
                '// Init leaflet map
		var map' . $params['listindex'] . ' = new L.Map(\'osmmap' . $params['listindex'] . '\', {
				scrollWheelZoom:' . $params['zoom_molette'] . ',
				zoomControl:' . $params['navigation'] . ',
		fullscreenControl:' . $params['fullscreen'] . ',
				iconAnchor:   [6, 20]
		});';

        if (empty($params['providers']) && empty($params['layers'])) {
            $js .=
                '
			var provider = L.tileLayer.provider("' . $params['provider'] . '"' . $params['provider_credentials'] . ');
			';
        } else {
            $js .= 'var provider; var baseLayers = {};';
            if (empty($params['providers'])) {
                $params['providers'] = [$params['provider']];
            }
            foreach ($params['providers'] as $provider) {
                $js .= 'baseLayers["' . $provider . '"] = L.tileLayer.provider("' . $provider . '");';
                if (empty($params['provider'])) {
                    $js .= 'if(provider==null) provider=baseLayers["' . $provider . '"];';
                } elseif ($provider == $params['provider']) {
                    $js .= 'provider=baseLayers["' . $provider . '"];';
                }
            }

            $js .= 'var layers = {};';
            if (is_array($params['layers'])) {
                $leafletajaxIncluded = false;
                foreach ($params['layers'] as $layer) {
                    @list($layerLabel, $layerType, $layerOptions, $layerUrl) = explode('|', $layer);
                    if ($layerUrl == null) {
                        $layerUrl = $layerOptions;
                        $layerOptions = null;
                    }
                    $layerType = strtolower($layerType);
                    if (!in_array($layerType, ['tiles', 'geojson'])) {
                        $js .= 'alert("Erreur paramètre \\"layers\\": le type \\"' . $layerType . '\\" est inconnu")';
                    }
                    switch ($layerType) {
                        case 'tiles':
                            $js .= 'layers["' . $layerLabel . '"] = L.tileLayer("' . $layerUrl . '");';
                            break;
                        case 'geojson':
                            if (!$leafletajaxIncluded) {
                                $leafletajaxIncluded = true;
                                $this->getService(AssetRegistry::class)->addJsFile('javascripts/vendor/leaflet-ajax/leaflet.ajax.min.js');
                            }

                            $styleJs = '';
                            $isVisibleByDefault = false;
                            if ($layerOptions != null) {
                                if (preg_match_all('/visiblebydefault\\s*;?/i', $layerOptions, $matches)) {
                                    $isVisibleByDefault = true;
                                    foreach ($matches[0] as $key => $value) {
                                        $layerOptions = str_replace($value, '', $layerOptions);
                                    }
                                }
                                $layerOptions = str_replace(';', ',', $layerOptions);
                                $styleJs .= '
							style: function (feature, latlng) {
								// pour les lignes et polygones
								if( feature.geometry.type=="Point" )
									return ;
								return {' . $layerOptions . '};
							},
							pointToLayer: function (feature, latlng) {
								// pour les points
								// parsing de layerOptions pour distinguer les options de marker et celles pour construire un icon pour le marker.
								var layerOptions = "' . $layerOptions . '".split(",");
								var markerOptions = {} , iconClass=null, color=null ;
								for( opt in layerOptions )
								{
									opt = layerOptions[opt].split(":");
									switch(opt[0].trim()) {
										case "opacity":
										case "clickable":
											// les options pour le marker http://leafletjs.com/reference.html#marker
											markerOptions[opt[0].trim()] = opt[1].trim();
											break;
										case "icon":
											// pour le html du L.divIcon http://leafletjs.com/reference.html#divicon
											// supprimer les éventuels apostrhophes
											iconClass = opt[1].trim().replace(/\'/g, "");
											break;
										case "color":
											// pour le html du L.divIcon
											color = opt[1].trim();
											break;
									}
								}

								if( iconClass!=null || color!=null ) {
									// Construit un L.divIcon pour le marker, sinon ce sera par défaut la goutte bleu.
									markerOptions["icon"] = L.divIcon({
											iconSize: ' . $params['iconSize'] . ',
											iconAnchor: ' . $params['iconAnchor'] . ',
											popupAnchor: ' . $params['popupAnchor'] . ',
											className: "bazar-marker' . $params['smallmarker'] . '",
											html: "<div class=\"bazar-entry "+ (color==null?"":"icon-"+color)+"\" ' . addslashes($entry['html_data']) . '>" + (iconClass==null?"":"<i class=\""+iconClass+"\"></i>") + "</div>"
									});
								}

								return L.marker(latlng, markerOptions);
							},
							';
                            }

                            $js .= 'layers["' . $layerLabel . '"] = L.geoJson.ajax("' . $layerUrl . '",
						{
              interactive: false,
							' . $styleJs . '
							onEachFeature: function (feature, layer) {
								//layer.bindPopup(feature.properties.NOM + feature.properties.NOM_QP);
								var str = "" ;
								for( var prop in feature.properties){
									if( prop.toLowerCase() == "url" ) {
										str+= prop +": <a href=\""+ feature.properties[prop] + "\" target=\"_blank\" >" + feature.properties[prop] +"<br/>";
									} else {
										str+= prop +": "+ feature.properties[prop] +"<br/>";
									}
								}
								//layer.bindPopup( str );
							}
						} );';
                            if ($isVisibleByDefault) {
                                $js .= 'layers["' . $layerLabel . '"].addTo(map' . $params['listindex'] . ');';
                            }
                            break;
                    }
                }
            }

            $js .= 'L.control.layers(baseLayers, layers).addTo(map' . $params['listindex'] . ');';
        }
        $geometriesModuleJs = '';
        if (!empty($vAllGeometries)) {
            $geometriesModuleJs .= '

    var drawnFeatures = new L.FeatureGroup()
    map' . $params['listindex'] . ".addLayer(drawnFeatures)\n";
            foreach ($vAllGeometries as $id => $g) {
                $geometriesModuleJs .= "const geo{$id} = " . $g . "\n";
                $geometriesModuleJs .= 'var popup = \'' . preg_replace("(\r\n|\n|\r|)", '', addslashes($this->getService(EntryDisplay::class)->renderEntry($params['managementbar'], $id))) . '\'' . "\n";
                $geometriesModuleJs .= "drawnFeatures = drawGeometries(drawnFeatures, geo{$id}.features, popup, '{$id}')\n";
            }
            if (!empty($geometriesWithoutMarker)) {
                $js .= 'L.Control.geometriesPanel = L.Control.extend({
    onAdd: function(map) {
        const div = L.DomUtil.create(\'div\', \'info-panel\');
        div.innerHTML = \'' . implode('', $geometriesWithoutMarker) . '\';
        div.style.background = \'transparent\';
        div.style.padding = \'0\';

        // Prevent clicks from reaching the map
        L.DomEvent.disableClickPropagation(div);

        return div;
    }
});

  new L.Control.geometriesPanel({ position: \'topright\' }).addTo(map' . $params['listindex'] . ');';
            }
        }
        $this->getService(AssetRegistry::class)->addJs(
            '
import { drawGeometries } from "./javascripts/leaflet-draw.helper.js"

var map' . $params['listindex'] . ';
// ticket 16: ywInitEach, not DOMContentLoaded. Internal links load through htmx, and
// DOMContentLoaded fires once per document -- so a map on any page reached by a navigation
// was never built. Keyed on the map container, which also replaces the getElementById guard
// this used to need.
ywInitEach(\'#osmmap' . $params['listindex'] . '\', function() {
		' . $js . '
		// N\'ajoute pas un doublon de layer, mais active la sélection du layer.
		map' . $params['listindex'] . '.addLayer(provider);
		map' . $params['listindex'] . '.setView(new L.LatLng(' . $params['latitude'] . ', ' . $params['longitude'] . '), ' . $params['zoom'] . ');
		var i = 0;
		var marker = Array();
    ' . $markersjs . '
    ' . $geometriesModuleJs . '
	});',
            true
        );

        return $output;
    }

    /**
     * One tableau column descriptor per displayable facet of a field (checkbox options may fan out).
     *
     * @return array<int,array<string,mixed>>
     */
    private function tableauFieldCols(BazarField $field, bool $checkboxFieldsInColumns, bool $displayImagesAsThumbnails): array
    {
        $propertyName = $field->getPropertyName();
        if (empty($propertyName)) {
            return [];
        }

        $fieldLabel = $field->getLabel();
        if ($field instanceof MapField) {
            return [[
                'propertyName' => $propertyName,
                'title' => $fieldLabel,
                'key' => null,
                'mapFieldId' => $propertyName,
                'multivalues' => false,
            ]];
        }
        if ($field instanceof CheckboxField && $checkboxFieldsInColumns) {
            $options = $field->getOptions();
            if (empty($options)) {
                return [];
            }
            $cols = [];
            foreach ($options as $key => $optionName) {
                $cols[] = [
                    'propertyName' => $propertyName,
                    'title' => "$fieldLabel - $optionName",
                    'key' => $key,
                    'mapFieldId' => null,
                    'multivalues' => false,
                ];
            }

            return $cols;
        }

        return [[
            'propertyName' => $propertyName,
            'title' => $fieldLabel,
            'key' => null,
            'mapFieldId' => null,
            'multivalues' => $field instanceof CheckboxField,
        ] + ($displayImagesAsThumbnails && $field instanceof ImageField ? ['imageAsThumbnail' => true] : [])];
    }

    /**
     * @param array<mixed> $optionsIfDisplayvaluesinsteadofkeys
     * @param array<mixed> $sanitizedParams
     */
    private function tableauCollectOptions(BazarField $field, array &$optionsIfDisplayvaluesinsteadofkeys, array $sanitizedParams): void
    {
        $propertyName = $field->getPropertyName();
        if ($sanitizedParams['displayvaluesinsteadofkeys']
            && $field instanceof EnumField
            && !isset($optionsIfDisplayvaluesinsteadofkeys[$propertyName])) {
            $optionsIfDisplayvaluesinsteadofkeys[$propertyName] = $field->getOptions();
        }
    }

    /** The oldest date a `period=` argument admits, or null when it names no period at all. */
    /** `BAZ_DELTA` is a configuration value, so it arrives as whatever the config file holds. */
    private function configuredDelta(): int
    {
        $delta = $this->params->get('BAZ_DELTA');

        return is_numeric($delta) ? (int)$delta : 12;
    }

    private function formatDateMin(mixed $period): ?string
    {
        switch ($period) {
            case 'day':
                $d = strtotime('-1 day');

                return date('Y-m-d H:i:s', $d);
            case 'week':
                $d = strtotime('-1 week');

                return date('Y-m-d H:i:s', $d);
            case 'month':
                $d = strtotime('-1 month');

                return date('Y-m-d H:i:s', $d);
        }

        return null;
    }
}

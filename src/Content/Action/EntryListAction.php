<?php

namespace YesWiki\Content\Action;

use YesWiki\Content\Entity\FieldRole;
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
use YesWiki\Content\Service\AttachedFilePaths;
use YesWiki\Content\Service\BazarListService;
use YesWiki\Content\Service\EntryManager;
use YesWiki\Content\Service\FieldRoleResolver;
use YesWiki\Content\Service\FormManager;
use YesWiki\Content\Service\FormPropertiesService;
use YesWiki\Core\YesWikiAction;
use YesWiki\Identity\Service\AclService;
use YesWiki\Identity\Service\AuthenticationService;
use YesWiki\Kernel\Component\Category;
use YesWiki\Kernel\Component\Component;
use YesWiki\Kernel\Component\ProvidesComponents;
use YesWiki\Kernel\Component\Setting;
use YesWiki\Kernel\Component\SettingGroup;
use YesWiki\Kernel\Exception\TemplateNotFound;
use YesWiki\Kernel\Performable\RegisteredAction;
use YesWiki\Kernel\Service\AssetRegistry;
use YesWiki\Kernel\Service\PageContext;
use YesWiki\Kernel\Service\Paginator;
use YesWiki\Kernel\Service\RuntimeConfig;
use YesWiki\Kernel\Service\UrlFormatter;
use YesWiki\Render\Service\PresentationRenderer;
use YesWiki\Search\Service\SearchManager;

class EntryListAction extends YesWikiAction implements RegisteredAction, ProvidesComponents, SuppliesItems
{
    /** `{{entrylist}}` in page content -- stated, not inferred from the filename. */
    public static function performableName(): string
    {
        return 'entrylist';
    }

    public static function sourceLabel(): string
    {
        return _t('SOURCE_ENTRYLIST');
    }

    /**
     * The form whose entries are listed.
     *
     * Since ADR-0011 that includes the Page, User and File forms, so "which Content?" and
     * "which form?" are one question. Declared by every component that lists entries --
     * `needFormField: true` on the whole `entrylist` YAML group is what this replaces, and
     * a group is no longer a thing a component belongs to.
     */
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
            // ...and which of that form's fields fill an Item's slots. This is the mapping
            // `displayfields` always was; it belongs to the Source because only a form has
            // fields to map, and a feed has nothing to say here.
            // Paired, because the panel draws them two to a row and they read as pairs:
            // the two lines of text at the top, then the picture beside the prose, then
            // the two things that sit on top of a card rather than in it.
            Setting::fieldMapping('displayfields')
                ->subSettings(
                    // The Content's own computed title (ADR-0010) is what a list shows
                    // when nothing is mapped -- so that is what the control says, rather
                    // than an empty select the reader has to guess the meaning of. Every
                    // form has one; `bf_titre` is a convention of some of them.
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
                    // ...and the button, which is a slot like the others -- the sixth
                    // thing a card can show -- even though what fills it is a choice
                    // rather than one of the form's fields. It rides in `displayfields`
                    // because that IS the list of what goes where on an Item.
                    Setting::choice('cta', [
                        '' => _t('AB_bazarliste_cta_none'),
                        'entry' => _t('AB_bazarliste_cta_entry'),
                        'edit' => _t('AB_bazarliste_cta_edit'),
                    ])
                        ->label(_t('AB_bazarliste_cta_label'))
                        ->default(''),
                    // ...and the date, which used to be drawn whether or not anybody had
                    // asked for one: an Item always carried its Content's creation date, so
                    // every card in every list wore a date in a corner. A slot like the
                    // others now -- nothing is shown until a field is named for it.
                    Setting::formField('date')
                        ->label(_t('AB_bazarliste_displayfields_date_label'))
                        ->default('')
                        // `jour` is a date on its own; the other two are the pair an
                        // agenda form has (DateField answers to all three)
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

    /**
     * `query`, as conditions rather than as a string.
     *
     * The parameter has always existed and the palette has never offered it: writing one
     * by hand means knowing the field names, the seven operators, and that `|` between
     * conditions is AND while `,` inside one is OR. A composite input like the mappings,
     * captioned with `intro` because a multi-input draws no label of its own.
     *
     * Shared by the Sources and by the entrylist family, which have no settings in common
     * to declare it in -- one call site each, one declaration.
     */
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
     * A Source's own concern, not a Presentation's: what a card looks like is the same
     * question over a feed, but "only the entries whose `bf_type` is 3, the ten most
     * recent" is a question only a form can answer. So a Presentation gets these the way
     * it gets the form picker -- from the Source, shown when that Source is the one chosen
     * (ticket 37) -- and `Cards` over a form can be told everything `{{entrylist}}` could.
     *
     * @return list<Setting>
     */
    private static function selectionSettings(): array
    {
        // Declaration order is layout: the rail lays its settings on a six-column grid and
        // an ordinary one takes half a row, so these read as the pairs they are written in.
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
            // What to sort on, and which way -- offered only while the order is not
            // random. `showIf` rather than a disabled control: a sort field beside "in a
            // random order" is a setting that does nothing, which is worse than one that
            // is not offered.
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
            // the reader's own search box over this list, which the server-rendered
            // presentations draw as readily as the old templates (`entries/index.twig`)
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
     * Declared here as well as in the entrylist family's own panel (`commonDisplaySettings`)
     * because they are a Source's question -- the boxes are that form's fields -- and because
     * the presentations render them: `entries/index.twig` wraps whatever `renderEntries`
     * produced, a card list included. They were offered on the old templates only, so the
     * shared presentations had facets nobody could turn on.
     *
     * @return list<Setting>
     */
    private static function facetSettings(): array
    {
        return [
            Setting::facets('facets')
                // a multi-input draws no label of its own, and unlabelled it reads as an
                // "add" button with nothing to say what it adds
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
            // Everything below is shown once there is a facet, and the value that says so is
            // `groups` -- what the facets input writes. `showIf('facets')` is what they all
            // said, and `facets` is the name of the *input*, not of a value any of them
            // writes: not one of these had ever appeared in the rail.
            //
            // `top` is the horizontal layout: the boxes in a row above the list rather than
            // in a column beside it, which is the only one that fits a narrow page
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
            // ...and how wide that column is, which a row has no use for
            Setting::range('filtercolsize')
                ->label(_t('AB_bazar_commons2_filtercolsize_label'))
                ->min(1)
                ->max(12)
                // "anything but the horizontal one", as a pattern rather than as a list of
                // the others: an unset setting reads as the string `false` in the rail, and
                // a list that spelled out `left|right` would hide this one until the position
                // had been touched
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
     * `displayfields` is what does the work, and it always did: it is the declared mapping
     * from a form's own field names -- which a webmaster chose and core cannot know -- onto
     * the handful of slots a list actually draws. Naming the result an Item is most of what
     * this ticket was: the mapping existed, the shape it produced had no name, so every
     * presentation was written against entries instead.
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
                // every Content carries the computed `title` (ADR-0010), so a form whose
                // mapping names nothing still has a name to show
                title: $field($entry, $slots['title'] ?? null) ?? (string)($entry['title'] ?? $entry['tag'] ?? ''),
                subtitle: $field($entry, $slots['subtitle'] ?? null),
                description: $field($entry, $slots['description'] ?? null),
                // resolved here, because only this Source knows what an entry's picture is
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

    /**
     * An entry's picture, as something an `<img>` can point at.
     *
     * Three shapes reach this, and only the last one used to be handled -- so a card whose
     * `visual` named an ordinary image field showed no picture at all, which is most of
     * them:
     *
     *  - a file in the upload directory, which is what an image field holds and what every
     *    other template reads as `files/{value}`;
     *  - a URL, for an entry pointing at a picture on another site (ImageField accepts one);
     *  - an attachment written by `{{attach}}`, whose name carries two timestamps that only
     *    `fullFilename()` can match back to a file on disk.
     */
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
        if (file_exists($inUploads)) {
            return $inUploads;
        }

        $attached = $paths->fullFilename($value);

        return $attached === '' ? null : $attached;
    }

    /**
     * Where an item's button goes, if the list asked for one.
     *
     * Resolved by the Source, not by the template: "open it" and "edit it" are URLs only
     * whatever supplied the item knows how to build, which is the same reason the image is
     * resolved here.
     */
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

            // The fallback for the tag itself. Every presentation is pinned on a template,
            // so a `{{entrylist}}` written by hand -- or one naming a template nobody
            // declares -- would match none of them and the rail would open on nothing.
            // Not offered: what the palette offers is the presentations.
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
                // the accordion list. Not offered: the palette's list is the shared
                // Presentation now (ticket 37), and a second card called "Liste" was the
                // duplication this ticket set out to remove. Still recognised, so a page
                // that says `template="liste_accordeon"` opens on all of its settings.
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
                ->writes('entrylist', 'entrymap')
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
                // likewise the bazar table, which is not the shared `table` Presentation:
                // it has its own renderer, its own columns and its own export buttons.
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
     * `custom/templates/bazar/` is where a webmaster drops a Twig file to render a list
     * their own way, and it has always shown up in the palette -- but as a special case
     * inside the palette service, which had to know that entrylist was the group to put it
     * in. It is this action's business: they are its templates.
     *
     * The key is built from the filename, which is the one place in this repo a `_t()` key
     * is not a literal -- and legitimately so: the name is user data, so there is no key to
     * write down. A file nobody has translated falls back to being named after itself.
     *
     * @return list<Component>
     */
    private function customTemplateComponents(): array
    {
        $components = [];
        foreach (glob('custom/templates/bazar/*.twig') ?: [] as $path) {
            $file = str_replace('custom/templates/bazar/', '', $path);
            // `fiche*` renders one entry, not a list of them
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

    /**
     * The settings every presentation of a list shares.
     *
     * These were two YAML entries called `commons` and `commons2`, declared as if they were
     * components of their own and recognised by the browser on the strength of their NAMES
     * -- `actionName.startsWith('common')`. They are blocks of settings handed to the
     * components that use them now, which is the same sharing said out loud.
     */
    private static function commonSettings(): SettingGroup
    {
        return SettingGroup::named(
            _t('AB_bazar_commons_title'),
            // which entries at all, before anything about how they are shown. The same
            // builder the Sources offer -- `{{entrylist template="liste_accordeon"}}` reads
            // `query` exactly as a Presentation does, and could not be told it either.
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
            Setting::colorMapping('colormapping')
                ->showIf('colorfield')
                ->onlyFor([
                    'entrymap',
                    'bazarmapandtable',
                    'entrylist',
                    'bazarcalendar',
                    'bazartimeline',
                    'bazartableau',
                    'bazarcard',
                ])
                ->subSettings(
                    Setting::choice('id', [
                    ])
                    ->label(_t('AB_bazar_commons_subproperty_id_label'))
                    ->extraFields([
                        'form_id',
                    ])
                    ->raw('dataFromFormField', 'colorfield'),
                    Setting::color('color')
                    ->label(_t('AB_bazar_commons_colormapping_color_label')),
                ),
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
            Setting::iconMapping('iconmapping')
                ->showIf('iconfield')
                ->onlyFor([
                    'entrymap',
                    'bazarmapandtable',
                    'entrylist',
                    'bazarcalendar',
                    'bazartableau',
                    'bazartimeline',
                    'bazarcard',
                ])
                ->raw('iconprefix', [
                    'advanced' => true,
                    'type' => 'text',
                    'label' => _t('AB_bazar_commons_iconfield_iconprefix_label'),
                    'hint' => _t('AB_bazar_commons_iconfield_iconprefix_hint'),
                ])
                ->subSettings(
                    Setting::choice('id', [
                    ])
                    ->label(_t('AB_bazar_commons_subproperty_id_label'))
                    ->extraFields([
                        'form_id',
                    ])
                    ->raw('dataFromFormField', 'iconfield'),
                    Setting::icon('icon')
                    ->label(_t('AB_bazar_commons_iconfield_icon_label')),
                ),
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
    protected const BAZARCARTO_TEMPLATES = ['map', 'gogomap', 'gogocarto', 'map-and-table']; // liste des templates sans .twig ni .tpl.html
    protected const BAZARTABLE_TEMPLATES = ['table', 'map-and-table']; // liste des templates sans .twig ni .tpl.html
    protected const CALENDAR_TEMPLATES = ['calendar']; // liste des templates sans .twig ni .tpl.html

    protected $debug;

    public function formatArguments($arg)
    {
        $entryManager = $this->getService(EntryManager::class);

        $get = $this->getRequest()->query;
        // ICONS FIELD
        $iconField = $get->get('iconfield') ?? $arg['iconfield'] ?? null;

        // ICONS
        $icon = $get->get('icon') ?? $arg['icon'] ?? $get->get('icons') ?? $arg['icons'] ?? null;
        $iconAlreadyDefined = ($icon == $this->params->get('baz_marker_icon') || is_array($icon));
        if (!$iconAlreadyDefined) {
            if (!empty($icon)) {
                try {
                    $tabparam = $entryManager->getMultipleParameters($icon, ',', '=');
                    if (count($tabparam) > 0 && !empty($iconField)) {
                        // on inverse cle et valeur, pour pouvoir les reprendre facilement dans la carto
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

        // COLORS FIELD
        $colorField = $get->get('colorfield') ?? $arg['colorfield'] ?? null;

        // COLORS
        $color = $get->get('color') ?? $get->get('colors') ?? $arg['colors'] ?? $arg['color'] ?? null;
        $colorAlreadyDefined = ($color == $this->params->get('baz_marker_color') || is_array($color));
        if (!$colorAlreadyDefined) {
            if (!empty($color)) {
                try {
                    $tabparam = $entryManager->getMultipleParameters($color, ',', '=');
                    if (count($tabparam) > 0 && !empty($colorField)) {
                        // on inverse cle et valeur, pour pouvoir les reprendre facilement dans la carto
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
        // The configured default is as much a choice as an explicit one, and it has to be
        // made here rather than at the end: the dynamic mapping below is what turns a
        // template name into one of the JS views, and it was reading a `$template` that was
        // still null whenever nobody passed one. `{{entrylist dynamic="true"}}` therefore
        // kept `default_bazar_template` -- which ships as `liste_accordeon.twig`, extension
        // included -- and the dynamic renderer appends `.twig` of its own, asking for
        // `liste_accordeon.twig.twig` and failing with "template not found".
        $template = $template ?: (string)$this->params->get('default_bazar_template');
        // Dynamic templates
        $dynamic = $this->formatBoolean($arg, false, 'dynamic');

        if (isset($arg['displayfields']) && is_array($arg['displayfields'])) { // with entrymap this method is run twice
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

        // compared without its extension: the same template is written `liste_accordeon` in
        // page content and `liste_accordeon.twig` in the config, and both must map
        $bareTemplate = (string)preg_replace('/\.(twig|tpl\.html)$/', '', (string)$template);
        // `map-and-table` has no server-rendered form, so it still has to be dynamic. The
        // other four are shared Presentations now (ticket 37) and render server-side unless
        // `dynamic` is asked for -- which is what makes `template="card"` the same card here
        // as on a feed. Ticking Dynamic brings back the search, filters and pagination that
        // are the Vue renderer's, and only its.
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
        // every Content carries the computed `title` (ADR-0010); bf_titre is only a field
        // some forms happen to have, and naming it here made the default search miss on
        // every form that does not (ticket 11)
        $searchfields = empty($searchfields) ? [PageBody::TITLE] : $searchfields;
        // End dynamic

        $agendaMode = (!empty($arg['agenda']) || !empty($arg['datefilter']) || str_starts_with($template, 'agenda'));

        // Only keep "true" and "dynamic" value, so we can still do if params.search in twig
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

        // Ordre du tri (asc ou desc)
        $order = $get->get('order') ?? $arg['order'] ?? ((empty($arg['field']) && $agendaMode) ? 'desc' : 'asc');
        // Champ du formulaire utilisé pour le tri
        // sorted by the computed title (ADR-0010) rather than by bf_titre, a field only
        // some forms have. The agenda default still names a field, because sorting happens
        // before this action knows which form(s) it is listing and so before the start_date
        // role can be asked -- an entry without it simply sorts last, as it always did.
        $sortField = $get->get('field') ?? $arg['field'] ?? ($agendaMode ? 'bf_date_debut_evenement' : PageBody::TITLE);

        $vSearchManager = $this->getService(SearchManager::class);

        $vKeywords = $vSearchManager->aggregateKeywords($arg['keywords'] ?? null, $this->getRequest()->get('q'), $this->getRequest()->get('keywords'));

        return [
            // //////////////////
            // USER PARAMETERS
            // ////////////////

            // SELECTION DES FICHES

            // sélectionner seulement les fiches d'un utilisateur
            'user' => $arg['user'] ?? ((isset($arg['filteruserasowner']) && $arg['filteruserasowner'] == 'true') ?
                $this->getService(AuthenticationService::class)->getLoggedUserName() : null),

            // identifiant du formulaire (plusieures valeurs possibles, séparées par des virgules)
            // ticket 22: `id` was already the primary spelling; the French `idtypeannonce`
            // alias it also accepted is dropped rather than translated
            'id' => $arg['id'] ?? $get->get('id') ?? null,

            // to be able to refresh cache for external json
            'refresh' => $this->formatBoolean($get->all(), false, 'refresh'),

            // Paramètres pour une requete specifique
            'queries' => $vSearchManager->parseQuery($vSearchManager->aggregateQueries($arg, $get->all())),
            // filtrer sur des mots clefs
            'keywords' => $vKeywords,
            // filtrer les resultats sur une periode données si une date est indiquée
            'dateMin' => $this->formatDateMin($get->get('period') ?? $arg['period'] ?? null),

            // Afficher les fiches dans un ordre aléatoire
            'random' => $this->formatBoolean($arg, false, 'random'),
            // Ordre du tri (asc ou desc)
            'order' => $order,
            // Champ du formulaire utilisé pour le tri
            'field' => $sortField,
            // les tris disponibles par le bouton "Trier par"
            'sortfields' => $this->formatArray($get->get('sortfields') ?? $arg['sortfields'] ?? []),
            'sortfieldstitles' => $this->formatArray($get->get('sortfieldstitles') ?? $arg['sortfieldstitles'] ?? []),

            // Nombre maximal de résultats à afficher
            'nb' => $arg['nb'] ?? null,

            // Nombre de résultats affichés pour la pagination (permet d'activer la pagination)
            'pagination' => $arg['pagination'] ?? null,

            // Transfere les valeurs d'un champs vers un autre, afin de correspondre dans un template
            'fieldmapping' => $arg['fieldmapping'] ?? null,

            // paramètre de tri des fiches sur une date (en gardant la retrocompatibilité avec le paramètre agenda)
            'agenda' => $arg['datefilter'] ?? $arg['agenda'] ?? null,
            'datefilter' => $arg['datefilter'] ?? $arg['agenda'] ?? null,

            // Dynamic mean the template will be rendered from the front end in order to improve UX and perf
            // Only few bazar templates have been converted to javascript
            'dynamic' => $dynamic,
            'displayfields' => $displayFields,

            // fields that will be used in dynamic views
            'necessary_fields' => $this->formatArray($get->get('necessaryfields') ?? $arg['necessaryfields'] ?? $get->get('necessary_fields') ?? $arg['necessary_fields'] ?? []),
            // get comments , reactions and metadatas with entry
            'extrafields' => $this->formatBoolean($arg, false, 'extrafields'),

            // AFFICHAGE

            // Template pour l'affichage de la liste de fiches
            'template' => $template,

            // ajout du footer pour gérer la fiche (modifier, droits, etc,.. )
            'managementbar' => $this->formatBoolean($arg, true, 'managementbar'),

            // bouton de réinitialisation des filtres
            'resetfiltersbutton' => $this->formatBoolean($arg, false, 'resetfiltersbutton'),

            // ajout des options pour exporter les fiches
            'showexportbuttons' => $this->formatBoolean($arg, false, 'showexportbuttons'),

            // Affiche le formulaire de recherche en haut
            'search' => $search,
            'searchfields' => $searchfields,

            // Affiche le nombre de fiche en haut
            'shownumentries' => $this->formatBoolean($arg, false, 'shownumentries'),

            // affichage du nombre de fiches trouvées par les filtres
            'filtersresultnb' => $this->formatBoolean($arg, true, 'filtersresultnb'),

            // classe css a ajouter en rendu des templates liste
            'class' => $arg['class'] ?? '',

            // Number of columns for card template
            'columns' => $arg['columns'] ?? null,

            // FACETTES
            // Identifiants des champs utilisés pour les facettes
            // Plusieures valeurs possibles, séparées par des virgules, "all" pour toutes les facettes possibles
            // Exemple : {{entrylist groups="bf_ce_titre,bf_ce_pays,etc."..}}
            'groups' => $this->formatArray($get->get('groups') ?? $arg['groups'] ?? null),
            // Titres des boite de facettes. Plusieures valeurs possibles, séparées par des virgules
            // Exemple : {{entrylist titles="Titre,Pays,etc."..}}
            'titles' => $this->formatArray($get->get('groupstitles') ?? $arg['groupstitles'] ?? $get->get('titles') ?? $arg['titles'] ?? null),

            // déplier toutes les facettes
            'groupsexpanded' => $this->formatBoolean($get->get('groupsexpanded') ?? $arg, true, 'groupsexpanded'),

            'groupicons' => $this->formatArray($arg['groupicons'] ?? null),

            // ajout d'un filtre pour chercher du texte dans les resultats pour les facettes
            'filtertext' => $this->formatBoolean($arg, false, 'filtertext'),

            // facette à gauche ou à droite
            'filterposition' => $get->get('filterposition') ?? $arg['filterposition'] ?? 'right',
            // largeur colonne facettes
            'filtercolsize' => $get->get('filtercolsize') ?? $arg['filtercolsize'] ?? '3',

            // ICONS

            // Prefixe des classes CSS utilisees pour la carto et calendrier
            'iconprefix' => $get->has('iconprefix') ? trim($get->get('iconprefix')) : (isset($arg['iconprefix']) ? trim($arg['iconprefix']) : ($this->params->get('baz_marker_icon_prefix') ?? '')),
            // Champ utilise pour les icones des marqueurs
            'iconfield' => $iconField,
            // icone des marqueurs
            'icon' => $icon,

            // COLORS

            // Champ utilise pour la couleur des marqueurs
            'colorfield' => $colorField,
            // couleur des marqueurs
            'color' => $color,

            // ////////////////////
            // SYSTEM PARAMETERS
            // //////////////////

            // Iframe ?
            'isInIframe' => testUrlInIframe(),

            'selectedID' => $get->get('selectedID'),
        ];
    }

    public function run()
    {
        $this->debug = (bool)$this->getService(RuntimeConfig::class)->getValue('debug');

        // If the template is a map or a calendar, call the dedicated action so that
        // arguments can be properly formatted. The second first condition prevents infinite loops
        if (
            self::specialActionFromTemplate($this->arguments['template'], 'BAZARCARTO_TEMPLATES')
            && (!isset($this->arguments['calledBy']) || !in_array($this->arguments['calledBy'], ['EntryMapAction', 'EntryTableAction']))
        ) {
            return $this->callAction('entrymap', $this->arguments);
        } elseif (
            self::specialActionFromTemplate($this->arguments['template'], 'CALENDAR_TEMPLATES')
            && (!isset($this->arguments['calledBy']) || $this->arguments['calledBy'] !== 'CalendarAction')
        ) {
            return $this->callAction('calendar', $this->arguments);
        } elseif (
            self::specialActionFromTemplate($this->arguments['template'], 'BAZARTABLE_TEMPLATES')
            // `table` is a shared Presentation now and renders server-side (ticket 37);
            // entrytable exists only to compute the Vue table's columns, so it is a detour
            // worth making when that table is the one asked for, and only then
            && ($this->arguments['dynamic'] || $this->arguments['template'] === 'map-and-table')
            && (!isset($this->arguments['calledBy']) || $this->arguments['calledBy'] !== 'EntryTableAction')
        ) {
            // Ceci est bancal : entrylist action appelle entrytable action qui rappelle une deuxieme entrylist action.
            // L'objectif est de formater les arguments correctement pour les tables.
            // Ainsi on créé une action entrytable qui créée une deuxieme entrylist action avec les paramètres correctement formatés pour les tables
            // Cela a des effets de bords :
            // ex : si la entrylist action utilise des parametres de $_REQUEST pour définir ses arguments, alors ces arguments peuvent être dupliqués dans la deuxième entrylist action créée
            // ie :
            //		- 1ere entrylist action : entrytable ($arg + $_REQUEST)
            // 		- entrytable action : entrylist ($arg + $_REQUEST)
            // 		- 2eme entrylist action : entrylist (($arg + $_REQUEST) + $_REQUEST)

            return $this->callAction('entrytable', $this->arguments);
        }

        $bazarListService = $this->getService(BazarListService::class);
        $vForms = $bazarListService->getForms($this->arguments);

        if ($this->arguments['dynamic']) {
            if (isset($this->arguments['zoom'])) {
                $this->arguments['zoom'] = intval($this->arguments['zoom']);
            }
            $currentUser = $this->getService(AuthenticationService::class)->getLoggedUser();

            return $this->render("@core/entries/index-dynamic-templates/{$this->arguments['template']}.twig", [
                'param' => $this->arguments, // DEPRECATED but still there for retro-compatibility: use params (plural)
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

        // To handle multiple bazarlist in a same page, we need a specific ID per bazarlist
        // We use a global variable to count the number of entrylist action run on this page
        if (!isset($GLOBALS['_BAZAR_']['listindex'])) {
            $GLOBALS['_BAZAR_']['listindex'] = 0;
        }
        $GLOBALS['_BAZAR_']['listindex']++;
        $this->arguments['listindex'] = $GLOBALS['_BAZAR_']['listindex'];

        // TODO put in all bazar templates

        $this->getService(AssetRegistry::class)->addJsFile('javascripts/bazar.js', true, true);

        return $this->render('@core/entries/index.twig', [
            'listId' => $GLOBALS['_BAZAR_']['listindex'],
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
     * `url` is this same page with the facet selection -- and the page of it we are on --
     * taken out, which is what htmx appends the checked boxes to and what "reset" links to.
     * `action`/`params` are the same URL split for a plain submit, because a browser drops
     * the query string of a GET form's action and would lose the page with it.
     *
     * Textual rather than rebuilt from `query->all()`: a YesWiki URL carries its page as a
     * bare key (`?PageName&facet[x][]=y`), which `http_build_query()` would not write back.
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

    private function renderEntries($entries, $filters = [], $pForms = ''): string
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
        $data['filters'] = $filters; // in case some template need it
        $data['forms'] = $pForms;

        if (!empty($this->arguments['pagination']) && $this->arguments['pagination'] > 0) {
            $query = $this->getRequest()->query->all();
            // Preserved from the PEAR-Pager call this replaced (ticket 02), including the
            // `wiki` strip -- which looks like it drops the page tag outside rewrite mode,
            // but changing URL semantics is out of scope for a dead-code purge.
            unset($query['wiki']);
            $paginationUrl = $this->getService(UrlFormatter::class)->getBaseUrl() . '/' . basename((string)($_SERVER['PHP_SELF'] ?? 'index.php'));

            $paginator = new Paginator(
                $data['entries'],
                (int)$this->arguments['pagination'],
                Paginator::pageFromQuery($query),
                (int)$this->params->get('BAZ_DELTA')
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
            // a display that needs a role no form can answer renders nothing at all, with
            // nothing to explain it -- say which role is missing instead (ticket 11)
            $warning = $this->missingRoleWarning((string)$templateBaseName, $pForms);
            // the shared Presentations (ticket 37): rendered from Items by the same code
            // that renders a feed, so `template="card"` is one card and not two. Everything
            // around it -- the search form, the facets, the pager -- is still this action's.
            if (PresentationRenderer::knows($templateName)) {
                // ...the pager included: `pagination` cuts the entries down to one page
                // above, and a page of a list with no way to reach page two is a list
                // that silently lost most of itself.
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
     * Roles a display cannot do without: an agenda with no start date and a map with no
     * geolocation both come out empty, which reads as "no entries" rather than as
     * "this form does not say which field holds that" (ticket 11).
     *
     * @var array<string, list<string>>
     */
    private const TEMPLATE_REQUIRED_ROLES = [
        'agenda' => [FieldRole::START_DATE],
        'calendar' => [FieldRole::START_DATE],
        'map' => [FieldRole::GEOLOCATION],
        'gogocarto' => [FieldRole::GEOLOCATION],
        'gogomap' => [FieldRole::GEOLOCATION],
        'map-and-table' => [FieldRole::GEOLOCATION],
    ];

    /**
     * An alert naming the roles none of the listed forms can answer, or '' when they can.
     *
     * Only complains when *no* form has the role: listing several forms together, one of
     * which has no dates, is not a misconfiguration -- those entries simply do not show.
     *
     * @param array<int|string, mixed>|string $forms
     */
    private function missingRoleWarning(string $templateBaseName, $forms): string
    {
        $required = self::TEMPLATE_REQUIRED_ROLES[$templateBaseName] ?? null;
        if ($required === null || !is_array($forms) || empty($forms)) {
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
                    // if no explicit column list, display all fields
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

                // mask emails and unreadable values for non-admins
                if (!$this->getService(AclService::class)->isAdmin()) {
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
                                    if (empty($field->getPropertyName())) {
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
                    $colors[$entry['tag']] = getCustomValueForEntry($params['color'] ?? null, $params['colorfield'] ?? null, $entry, '');
                    $icons[$entry['tag']] = getCustomValueForEntry($params['icon'] ?? null, $params['iconfield'] ?? null, $entry, '');
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
            // which column carries the link to the entry: the field the form names its
            // entries with, not whichever one happens to be called bf_titre (ticket 11)
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
        $this->getService(AssetRegistry::class)->addCssFile('styles/bazar/bazarcarto.css');
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

            // couleur de marqueur
            $color = getCustomValueForEntry($params['color'], $params['colorfield'], $entry, $this->getService(RuntimeConfig::class)['baz_marker_color']);

            // icone de marqueur
            $icon = $params['iconprefix']
                    . getCustomValueForEntry($params['icon'], $params['iconfield'], $entry, $this->getService(RuntimeConfig::class)['baz_marker_icon']);

            if (is_numeric($vLatitude) && is_numeric($vLongitude)) {
                // on genere le point marqueur sur la carte
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
				marker[i].bindPopup(\'' . preg_replace("(\r\n|\n|\r|)", '', addslashes(renderEntryView($params['managementbar'], $entry))) . '\');
				';
                if ($params['spider'] == 'true' or $params['spider'] == '1') {
                    $markersjs .= 'map' . $params['listindex'] . '.addLayer(marker[i]);' . "\n" . 'oms.addMarker(marker[i]);' . "\n";
                } elseif ($params['cluster'] == 'true' or $params['cluster'] == '1') {
                    $markersjs .= 'markerscluster.addLayer(marker[i]);' . "\n";
                } else {
                    $markersjs .= 'map' . $params['listindex'] . '.addLayer(marker[i]);' . "\n";
                }
            } elseif (!empty($vGeometries)) {
                // fake marker for facetted search
                // TODO: this is way too hacky, need to find a way to search on marker and geometries in a better way
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

        // Pas de L.control.layers
        if (empty($params['providers']) && empty($params['layers'])) {
            $js .=
                '
			var provider = L.tileLayer.provider("' . $params['provider'] . '"' . $params['provider_credentials'] . ');
			';
        } else {
            // Avec un L.control.layers
            // Si param['provider'] existe, ce sera le baseLayer activé par défaut, sinon ce sera le 1er de la liste $params['providers'].
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
                            // URL: Attention au Blocage d’une requête multi-origines (Cross-Origin Request).
                            // Le plus simple est de recopier les data GeoJson dans une page du Wiki puis de l'appeler avec le handler "/raw".
                            // STYLE:
                            //	http://leafletjs.com/reference.html#path-options
                            //	http://leafletjs.com/reference.html#marker-options
                            if (!$leafletajaxIncluded) {
                                $leafletajaxIncluded = true;
                                $this->getService(AssetRegistry::class)->addJsFile('javascripts/vendor/leaflet-ajax/leaflet.ajax.min.js');
                            }

                            $styleJs = '';
                            $isVisibleByDefault = false;
                            if ($layerOptions != null) {
                                // extract 'visiblebydefault'
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
                                // add layer to the map
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
                $geometriesModuleJs .= 'var popup = \'' . preg_replace("(\r\n|\n|\r|)", '', addslashes(renderEntryView($params['managementbar'], $id))) . '\'' . "\n";
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

    private function formatDateMin($period)
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
    }

    /* Method to test if the current template is associated to a specific bazar actions
     * @param $templateName string (ex. "map","map.tpl.html","map.twig")
     * @param $constName string name of the constant array containing the right template names
     *                          "BAZARCARTO_TEMPLATES" or "CALENDAR_TEMPLATES"
     */
    public static function specialActionFromTemplate(string $templateName, string $constName): bool
    {
        switch ($constName) {
            case 'BAZARCARTO_TEMPLATES':
                $baseArray = self::BAZARCARTO_TEMPLATES;
                break;
            case 'CALENDAR_TEMPLATES':
                $baseArray = self::CALENDAR_TEMPLATES;
                break;
            case 'BAZARTABLE_TEMPLATES':
                $baseArray = self::BAZARTABLE_TEMPLATES;
                break;
            default:
                return false;
        }

        $templatesnames = [];
        foreach ($baseArray as $templateBaseName) {
            $templatesnames[] = $templateBaseName;
            $templatesnames[] = $templateBaseName . '.tpl.html';
            $templatesnames[] = $templateBaseName . '.twig';
        }

        return in_array($templateName, $templatesnames);
    }
}

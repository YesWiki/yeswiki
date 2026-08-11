<?php

/**
 * Yeswiki initialization class file.
 */

namespace YesWiki;

use Symfony\Component\Routing\Loader\AttributeClassLoader;
use Symfony\Component\Routing\Route;
use YesWiki\Admin\Service\ArchiveService;
use YesWiki\Kernel\Routing\ReservedTags;
use YesWiki\Kernel\Service\ConfigurationFileProvider;
use YesWiki\Kernel\Service\EnvironmentConfiguration;

class AttributeRouteControllerLoader extends AttributeClassLoader
{
    protected function configureRoute(Route $route, \ReflectionClass $class, \ReflectionMethod $method, object $attr): void
    {
        $route->setDefault('_controller', $class->getName() . '::' . $method->getName());
    }
}

/**
 * Yeswiki initialization class.
 */
class Init
{
    public $page = '';
    public $method = '';
    public $config = [];
    public $configFile;

    /**
     * Create a new Init instance.
     *
     * @param array $config initial config array (empty by default)
     *
     * @return void
     */
    public function __construct($config = [])
    {
        $this->configFile = ConfigurationFileProvider::getConfigFileFromEnv();

        $this->getRoute();
        $this->config = $this->getConfig($config);
        $this->setIframeHeaders();

        /* @todo : compare versions, start installer for update if necessary */
        if (!file_exists($this->configFile)) {
            $this->doInstall();
            exit;
        }
    }

    /**
     * Guess the page and the handler called by current url.
     *
     * @return void
     */
    public function getRoute()
    {
        $protocol = 'http://';
        if (!empty($_SERVER['HTTPS'])) {
            $protocol = 'https://';
        }
        $scriptlocation = str_replace('/index.php', '', $_SERVER['SCRIPT_NAME']);
        $uri = str_replace($scriptlocation, '', $_SERVER['REQUEST_URI']);
        $uri = preg_replace('~^/\??~', '', $uri);
        $uri = explode('&', $uri);
        $uri = explode('?', $uri[0]);
        $args = explode('/', rawurldecode($uri[0]));
        if (!empty($args[0]) or !empty($_GET['wiki'])) {
            // if old school wiki url
            if ($args[0] == 'index.php' or !empty($_GET['wiki'])) {
                // remove leading slash
                $wiki = empty($_GET['wiki']) ? '' : preg_replace('/^\//', '', urldecode($_GET['wiki']));
            } else {
                $a = explode('=', $args[0]);
                $wiki = urldecode($a[0]);
            }
            if (empty($wiki)) {
                // this will be redirected to install or to homepage later
            } elseif (ReservedTags::isReserved(explode('/', $wiki)[0])) {
                // A routed name (`api`, `doc`, ...) takes the whole rest of the path as its
                // method, because an API route has more segments than WN_TAG_HANDLER_CAPTURE's
                // single Tag/handler split can carry.
                //
                // Matching the FIRST SEGMENT is the point. This used to be `preg_match('^api')`
                // -- a bare prefix match, so a page tagged `apiculture` was parsed as the API
                // with method `culture` and was unreachable. Any tag merely starting with a
                // reserved name is an ordinary tag and falls through to the branches below.
                $this->page = ReservedTags::canonical(explode('/', $wiki)[0]);
                if (strpos($wiki, '/') !== false) {
                    // $wiki already contains the full path (e.g. 'api/ferme/wikis/upgrade' from $_REQUEST['wiki'])
                    $wikiParts = explode('/', $wiki);
                    array_shift($wikiParts); // remove the reserved name
                    $this->method = rtrim(implode('/', $wikiParts), '=');
                } else {
                    // $wiki is just the reserved name, extract method from URL path segments
                    array_shift($args); // remove the reserved name from the args
                    $this->method = rtrim(implode('/', $args), '=');
                }
            } elseif (preg_match('`^' . WN_TAG_HANDLER_CAPTURE . '$`u', $wiki, $matches)) {
                // split into page/method, checking wiki name & method name (XSS proof)
                list(, $this->page, $this->method) = $matches;
            } elseif (preg_match('`^' . WN_PAGE_TAG . '$`u', $wiki)) {
                // WikiPageName without method
                $this->page = $wiki;
                if (isset($args[1]) and !empty($args[1])) {
                    // Security (quick hack) : Check method syntax
                    if (preg_match('#^[A-Za-z0-9_]*$#', $args[1])) {
                        $this->method = $args[1];
                    }
                }
            } else {
                // invalid WikiPageName
                echo '<p>', _t('INCORRECT_PAGENAME'), '</p>';
                exit;
            }

            // TODO refactor this
            if (!$this->method) {
                $requestMethod = $_SERVER['REQUEST_METHOD'] ?? null;
                // We must manually parse the body data for the PUT or PATCH methods
                // See https://www.php.net/manual/fr/features.file-upload.put-method.php
                if (empty($_POST) && ($requestMethod == 'POST' || $requestMethod == 'PUT' || $requestMethod == 'PATCH')) {
                    $_POST = json_decode(file_get_contents('php://input'), true) ?? [];
                }

                header('Access-Control-Allow-Origin: *');
                header('Access-Control-Allow-Credentials: true');
                header('Access-Control-Allow-Headers: X-Requested-With, Location, Link, Slug, Accept, Content-Type');
                header('Access-Control-Expose-Headers: Location, Slug, Accept, Content-Type');
                header('Access-Control-Allow-Methods: POST, GET, OPTIONS, DELETE, PUT, PATCH');
                header('Access-Control-Max-Age: 86400');
            }
            $_GET['wiki'] = $this->page . ($this->method ? '/' . $this->method : '');
        }
    }

    /**
     * set headers for iframes.
     */
    /**
     * Default mail domain derived from the wiki's host ("www.foo.example.org" =>
     * "example.org"). The last-two-labels heuristic only makes sense for DNS names:
     * IP literals (a bare-IP dev/CI wiki would otherwise yield garbage like "0.1"
     * from 127.0.0.1) are kept whole. Was the global getMailDomain() in
     * src/email.inc.php until the PSR-4 refactor removed it (this is its only
     * caller).
     */
    private function deriveMailDomain(string $host): string
    {
        if (filter_var($host, FILTER_VALIDATE_IP) !== false
            || filter_var(trim($host, '[]'), FILTER_VALIDATE_IP) !== false) {
            return $host;
        }

        $host = preg_replace('/^www\./', '', $host) ?? $host;
        $parts = explode('.', $host);

        return implode('.', array_slice($parts, -2));
    }

    private function setIframeHeaders()
    {
        // set header for Content-Security-Policy
        $allowedMethods = $this->config['allowed_methods_in_iframe'] ?? 'all';

        if ($this->page === 'doc' || $allowedMethods === 'all' || (
            is_array($allowedMethods) && in_array($this->method, $allowedMethods, true)
        )) {
            // allow local ('self') and everyone (*)
            header("Content-Security-Policy: frame-ancestors 'self' *;");
        } else {
            // for old browsers
            header('X-frame-Options: deny');
            // disallow (CSP takes advantage on x-frame-options)
            header("Content-Security-Policy: frame-ancestors 'none';");
        }
    }

    /**
     * Utility function to merge the multidimentionnal config array the right way.
     *
     * @return array merged array
     */
    protected function array_merge_recursive_distinct(array &$array1, array &$array2)
    {
        $merged = $array1;

        foreach ($array2 as $key => &$value) {
            if (is_array($value) && isset($merged[$key]) && is_array($merged[$key])) {
                $merged[$key] = $this->array_merge_recursive_distinct($merged[$key], $value);
            } else {
                $merged[$key] = $value;
            }
        }

        return $merged;
    }

    /**
     * Check in the config file exists and provide default configuration.
     *
     * @param array $yeswikiConfig initial config array (empty by default)
     *
     * @return array the configuration
     */
    public function getConfig($yeswikiConfig = [])
    {
        $_rewrite_mode = detectRewriteMode();
        $yeswikiDefaultConfig = [
            'yeswiki_version' => '',
            'yeswiki_release' => '',
            'charset' => 'UTF-8',
            'debug' => false,
            // ticket 16: internal links load through htmx. Not a second code path -- the
            // non-boosted path is plain HTTP, which still answers every request without
            // HX-Request (search engines, curl, JS disabled). Turning it off only stops the
            // skeleton emitting hx-boost, which is the way out for a theme that does not meet
            // the contract.
            'htmx_navigation' => true,
            'db_driver' => 'mysql',
            'db_host' => 'localhost',
            'db_database' => '',
            'db_user' => '',
            'db_password' => '',
            'table_prefix' => 'yeswiki_',
            'base_url' => computeBaseURL($_rewrite_mode),
            'rewrite_mode' => $_rewrite_mode,
            'meta_keywords' => '',
            'meta_description' => '',
            'navigation_links' => 'DerniersChangements :: DerniersCommentaires :: ParametresUtilisateur',
            'pages_purge_time' => 365,
            'default_write_acl' => '*',
            'default_read_acl' => '*',
            'default_comment_acl' => 'comments-closed',
            // default false, unlike the pre-absorption accountactivationbyemail extension's
            // own default of true (ticket 07): that default only ever affected wikis that
            // explicitly installed the extension -- defaulting to true here would silently
            // gate new signups on every wiki upgrading to this version, whether or not it
            // ever used this feature before
            'signup_email_activation' => false,
            'user_activation_key_length' => 20,
            'comments_activated' => true,
            'comments_handler' => 'yeswiki',
            'preview_before_save' => false,
            'allow_raw_html' => true,
            'disallowed_html_tags' => ['title', 'textarea', 'style', 'xmp', 'noembed', 'noframes', 'script', 'plaintext'],
            'allowed_methods_in_iframe' => ['iframe', 'editiframe', 'render'],
            'revisionscount' => 30,
            'timezone' => 'Europe/Paris', // Only used if not set in yeswiki.config.php nor in php.ini
            'root_page' => 'PagePrincipale', // backup root_page if deleted from yeswiki.config.php
            'yeswiki_name' => '', // backup yeswiki_name if deleted from yeswiki.config.php
            'htmlPurifierActivated' => true,
            'htmlPurifierSafeIframeRegexp' => '~^https://.*~', // regex for domains allowed as <iframe> src ; very permissive by default, restrict for public wikis
            'favorites_activated' => true,
            'hide_keywords' => false,
            'use_alerte' => true, // alerte pour quitter le mode édition
            'use_hashcash' => true, // hashcash pour le mode edition
            'use_captcha' => false, // recaptcha
            'wiki_status' => 'running', // status of the wiki ('running','maintenance','hibernation')
            'favorite_theme' => THEME_PAR_DEFAUT,
            'favorite_style' => CSS_PAR_DEFAUT,
            'favorite_squelette' => SQUELETTE_PAR_DEFAUT,
            'favorite_background_image' => BACKGROUND_IMAGE_PAR_DEFAUT,
            'hide_action_template' => false,
            ArchiveService::PARAMS_KEY_IN_WAKKA => [
                ArchiveService::KEY_FOR_HIDE_CONFIG_VALUES => ArchiveService::DEFAULT_PARAMS_TO_ANONYMIZE,
                'authorize_bypass_preupdate_backup' => false,
                'preupdate_backup_activated' => true,
                'call_archive_async' => true,
                ArchiveService::KEY_FOR_PRIVATE_FOLDER => ArchiveService::PRIVATE_FOLDER_NAME_IN_ZIP,
                'max_nb_files' => 10,
            ],
            // qrcode generic config (ticket 14, formerly yeswiki-extension-qrcode's config.yaml)
            'qrcode_config' => [
                'relation_form_id' => 1300, // official reserved bazar form id for relations
                'default_relation_type' => 'contact',
                'default_entity_type' => 'personne',
                'default_entity_form' => '1',
                'default_user_form' => '1',
                'visualisation_refresh_period' => '30000',
            ],
            // {{attach}} generic config (ticket 17, formerly tools/attach's config.yaml).
            // attach_jplayer_skin dropped: jPlayer was replaced by native <audio controls>.
            // ext_freemind dropped: the FreeMind (.mm) viewer embedded a Flash .swf, dead
            // in every browser since ~2021.
            'image-small-width' => 140,
            'image-small-height' => 97,
            'image-medium-width' => 300,
            'image-medium-height' => 209,
            'image-big-width' => 780,
            'image-big-height' => 544,
            'authorized-extensions' => [
                // Images reconnues par PHP
                'jpg' => 'JPEG',
                'png' => 'PNG',
                'gif' => 'GIF',
                'jpeg' => 'JPEG',
                'webp' => 'WEBP',
                // Autres images (peuvent utiliser le tag <img>)
                'avif' => 'AVIF',
                'bmp' => 'BMP',
                'tif' => 'TIFF',
                'svg' => 'SVG',
                // Audio / Video
                'aiff' => 'AIFF',
                'anx' => 'Annodex',
                'axa' => 'Annodex Audio',
                'axv' => 'Annodex Video',
                'asf' => 'Windows Media',
                'avi' => 'AVI',
                'flac' => 'Free Lossless Audio Codec',
                'flv' => 'Flash Video',
                'json' => 'Json',
                'geojson' => 'GeoJson',
                'mid' => 'Midi',
                'mng' => 'MNG',
                'mka' => 'Matroska Audio',
                'mkv' => 'Matroska Video',
                'mov' => 'QuickTime',
                'mp3' => 'MP3',
                'mp4' => 'MPEG4',
                'mpg' => 'MPEG',
                'mscz' => 'MuseScore',
                'oga' => 'Ogg Audio',
                'ogg' => 'Ogg Vorbis',
                'ogv' => 'Ogg Video',
                'ogx' => 'Ogg Multiplex',
                'qt' => 'QuickTime',
                'ra' => 'RealAudio',
                'ram' => 'RealAudio',
                'rm' => 'RealAudio',
                'spx' => 'Ogg Speex',
                'swf' => 'Flash',
                'wav' => 'WAV',
                'wmv' => 'Windows Media',
                '3gp' => '3rd Generation Partnership Project',
                // Documents
                'abw' => 'Abiword',
                'ai' => 'Adobe Illustrator',
                'bz2' => 'BZip',
                'bin' => 'Binary Data',
                'blend' => 'Blender',
                'c' => 'C source',
                'cls' => 'LaTeX Class',
                'css' => 'Cascading Style Sheet',
                'csv' => 'Comma Separated Values',
                'deb' => 'Debian',
                'doc' => 'Word',
                'docx' => 'Word',
                'djvu' => 'DjVu',
                'dvi' => 'LaTeX DVI',
                'eps' => 'PostScript',
                'gz' => 'GZ',
                'h' => 'C header',
                'kml' => 'Keyhole Markup Language',
                'kmz' => 'Google Earth Placemark File',
                'md' => 'Markdown',
                'mm' => 'Mindmap',
                'pas' => 'Pascal',
                'pdf' => 'PDF',
                'pgn' => 'Portable Game Notation',
                'ppt' => 'PowerPoint',
                'pptx' => 'PowerPoint',
                'ps' => 'PostScript',
                'psd' => 'Photoshop',
                'pub' => 'Microsoft Publisher',
                'rpm' => 'RedHat/Mandrake/SuSE',
                'rtf' => 'RTF',
                'sdd' => 'StarOffice',
                'sdw' => 'StarOffice',
                'sit' => 'Stuffit',
                'sty' => 'LaTeX Style Sheet',
                'sxc' => 'OpenOffice.org Calc',
                'sxi' => 'OpenOffice.org Impress',
                'sxw' => 'OpenOffice.org',
                'tex' => 'LaTeX',
                'tgz' => 'TGZ',
                'torrent' => 'BitTorrent',
                'ttf' => 'TTF Font',
                'txt' => 'texte',
                'xcf' => 'GIMP multi-layer',
                'xspf' => 'XSPF',
                'xls' => 'Excel',
                'xlsx' => 'Excel',
                'xlsm' => 'Excel',
                // xml: 'XML' removed by default because no more xss cleaner
                'yaml' => 'YAML',
                'zip' => 'Zip',
                'scar' => 'SCAR',
                //  Open Document
                'odt' => 'opendocument text',
                'ods' => 'opendocument spreadsheet',
                'odp' => 'opendocument presentation',
                'odg' => 'opendocument graphics',
                'odc' => 'opendocument chart',
                'odf' => 'opendocument formula',
                'odb' => 'opendocument database',
                'odi' => 'opendocument image',
                'odm' => 'opendocument text-master',
                'ott' => 'opendocument text-template',
                'ots' => 'opendocument spreadsheet-template',
                'otp' => 'opendocument presentation-template',
                'otg' => 'opendocument graphics-template',
            ],
            'attach_config' => [
                'ext_images' => 'avif|gif|jpeg|png|jpg|svg|webp',
                'ext_audio' => 'mp3|aac',
                'ext_video' => 'mp4|webm|ogg',
                'ext_wma' => 'wma',
                'ext_pdf' => 'pdf',
                'ext_flashvideo' => 'flv',
                'ext_script' => 'php|php3|asp|asx|vb|vbs|js',
                'upload_path' => 'files',
                'cache_path' => 'cache',
                'update_symbole' => '',
                'fmDelete_symbole' => 'Supr',
                'fmRestore_symbole' => 'Rest',
                'fmTrash_symbole' => 'Corbeille',
            ],
            'attach-video-config' => [
                'default_video_service' => 'peertube',
                'default_peertube_instance' => 'https://framatube.org/',
            ],
            // contact/mail-sending generic config (ticket 18, formerly tools/contact's config.yaml)
            'contact_mail_func' => 'mail', // mail, sendmail ou smtp
            'contact_smtp_host' => '',
            'contact_smtp_port' => '',
            'contact_smtp_user' => '',
            'contact_smtp_pass' => '',
            'contact_smtp_secure' => '', // smtp secure (ssl,tls,...)
            'contact_use_long_wiki_urls_in_emails' => false, // add 'wiki=' in url
            'contact_reply_to' => '', // default mail to reply to
            'contact_debug' => 0, // debug mode (0 pour rien, 1 pour normal, 2 pour détaillé)
            'contact_passphrase' => '', // passphrase pour envoyer des mail (cron-triggered digests)
            'contact_disable_email_for_password' => false, // pour désactiver l'envoie d'email pour ré-initaliser un mot de passe (ex: LDAP, SSO)
            // import config (formerly yeswiki-extension-importer's config.yaml). Each entry of
            // dataSources says where Content is imported from and by which importer; there is
            // deliberately no default source. sync_secret guards /api/import/sync and, empty,
            // leaves that route refusing every call.
            'dataSources' => [],
            'sync_secret' => '',
            // autoupdate config (ticket 19, formerly tools/autoupdate's config.yaml): pages
            // re-provisioned from the default install content after a core upgrade, in case
            // their default content changed between versions
            'admin_pages_to_update' => [
                'BazaR',
                'GererSite',
                'GererDroits',
                'GererDroitsActions',
                'GererDroitsHandlers',
                'GererMisesAJour',
                'GererThemes',
                'GererConfig',
                'GererUtilisateurs',
                'TableauDeBord',
                'GererSauvegardes',
            ],
            // bazar generic config (ticket 24, formerly tools/bazar's config.yaml)
            'baz_menu' => 'formulaire,consulter,saisir,listes,importer,exporter',
            // herse: site-wide Basic Auth gate, off while empty (ticket 21)
            'herse_id' => '',
            'herse_password' => '',
            // webhooks generic config (ticket 20, formerly the webhooks extension's config.yaml)
            'webhooks_formats' => [
                'raw' => 'WEBHOOKS_FORMAT_RAW',
                'activitypub' => 'WEBHOOKS_FORMAT_ACTIVITYPUB',
                'mattermost' => 'WEBHOOKS_FORMAT_MATTERMOST',
                'slack' => 'WEBHOOKS_FORMAT_SLACK',
                'yeswiki' => 'WEBHOOKS_FORMAT_YESWIKI',
            ],
            // If no actor field is defined semantically, this value will be used
            'webhooks_activitypub_default_actor' => '',
            // If an actor is defined, we can override the base URL with this config
            'webhooks_activitypub_actors_base_url' => '',
            // Bot config (works for Mattermost if username and profile picture override is enabled)
            // See https://docs.mattermost.com/developer/webhooks-incoming.html
            'webhooks_bot_name' => 'YesWiki Bot',
            'webhooks_bot_icon' => '%base_url%styles/webhooks/default-bot.png',
            'BAZ_ENVOI_MAIL_ADMIN' => false,
            'BAZ_ADRESSE_MAIL_ADMIN' => 'noreply@%mail_domain%',
            // liste_accordeon.tpl.html and its sibling legacy display modes (agenda, blog,
            // carousel, tableau, timeline, trombinoscope, etc.) are NOT
            // dead: the actions-builder wizard's entrylist/bazaragenda/bazartableau/...
            // entries (docs/actions/entrylist.yaml and friends) still default-select these
            // exact template names, so they're relocated as-is rather than dropped.
            'default_bazar_template' => 'liste_accordeon.twig',
            'baz_semantic_types_mapping' => [
                'https://www.w3.org/ns/activitystreams' => 'activitystreams',
            ],
            'global_query' => 'true',
            'bazarIgnoreAcls' => true,
            'BAZ_RSS_NOMSITE' => '%yeswiki_name%',
            'BAZ_RSS_ADRESSESITE' => '%base_url%',
            'BAZ_RSS_DESCRIPTIONSITE' => '%meta_description%',
            'BAZ_NB_ENTREES_FLUX_RSS' => 20,
            'BAZ_RSS_LOGOSITE' => 'https:#yeswiki.net/favicon.ico',
            'BAZ_RSS_MANAGINGEDITOR' => 'contact@yeswiki.net (Mr YesWiki)',
            'BAZ_RSS_WEBMASTER' => '%BAZ_RSS_MANAGINGEDITOR%',
            'BAZ_RSS_CATEGORIE' => 'Economie Sociale et Solidaire',
            'BAZ_ETAT_VALIDATION' => '1',
            'BAZ_TYPE_AFFICHAGE_LISTE' => 'jma',
            'BAZ_DATE_VIDE' => false,
            'BAZ_NOMBRE_RES_PAR_PAGE' => 50,
            'BAZ_DELTA' => 12,
            'BAZ_MAX_CHECKBOXLISTE_SANS_FILTRE' => 6,
            'BAZ_MAX_CHECKBOXENTRY_WITHOUT_SELECTALL' => 7,
            'BAZ_MAX_CHECKBOXENTRY_DISPLAY_MODE' => 'list',
            'BAZ_MAX_CHECKBOXLIST_WITHOUT_FILTER' => false,
            'BAZ_MAX_RADIO_WITHOUT_FILTER' => 6,
            'BAZ_MAX_CHECKBOXLIST_WITHOUT_SELECTALL' => 7,
            'BAZ_MAX_CHECKBOXLIST_DISPLAY_MODE' => 'div',
            'BAZ_CHECKBOX_DRAG_AND_DROP_MAX_HEIGHT' => null,
            'baz_map_center_lat' => '46.22763',
            'baz_map_center_lon' => '2.213749',
            'baz_marker_icon_prefix' => '',
            'baz_provider' => 'OpenStreetMap.Mapnik',
            'baz_provider_id' => '',
            'baz_provider_pass' => '',
            'baz_marker_icon' => 'bullseye',
            'baz_marker_color' => 'darkred',
            'baz_small_marker' => '',
            'baz_map_zoom' => 5,
            'baz_map_width' => '100%',
            'baz_map_height' => '600px',
            'baz_show_nav' => 'true',
            'baz_wheel_zoom' => 'false',
            // marker.png itself has never actually existed anywhere in the repo -- a
            // pre-existing gap, not introduced by this relocation. The default marker
            // rendering (baz_marker_icon/baz_marker_icon_prefix) is a sprite icon name,
            // not an image file; this key is only consulted if an admin opts into a
            // custom marker image.
            'baz_marker_image_file' => 'src/assets/images/bazar/marker.png',
            'temp_tag_for_entry_creation' => 'unknown_entry_id',
        ];
        unset($_rewrite_mode);

        if (file_exists($this->configFile)) {
            include $this->configFile;
            // config files written before the rename define $wakkaConfig instead
            if (isset($wakkaConfig) && is_array($wakkaConfig)) {
                $yeswikiConfig = $wakkaConfig;
            }
        } else {
            // we must init language file without loading the page's settings.. to translate some default config settings
            $yeswikiDefaultConfig['root_page'] = _t('HOMEPAGE_WIKINAME');
            $yeswikiDefaultConfig['yeswiki_name'] = _t('MY_YESWIKI_SITE');
        }

        // Backwards compatibility: map keys of pre-ectoplasme config files to their new names
        $legacyKeyMapping = [
            'mysql_host' => 'db_host',
            'mysql_database' => 'db_database',
            'mysql_user' => 'db_user',
            'mysql_password' => 'db_password',
            'mysql_port' => 'db_port',
            'wakka_name' => 'yeswiki_name',
        ];
        foreach ($legacyKeyMapping as $oldKey => $newKey) {
            if (isset($yeswikiConfig[$oldKey]) && !isset($yeswikiConfig[$newKey])) {
                $yeswikiConfig[$newKey] = $yeswikiConfig[$oldKey];
            }
            unset($yeswikiConfig[$oldKey]);
        }
        // dropped keys and the removed wakka.php entry script, from pre-ectoplasme config files
        unset($yeswikiConfig['wakka_version']);
        if (!empty($yeswikiConfig['base_url'])) {
            $yeswikiConfig['base_url'] = str_replace('/wakka.php?wiki=', '/?', $yeswikiConfig['base_url']);
        }

        $yeswikiConfig = $this->array_merge_recursive_distinct($yeswikiDefaultConfig, $yeswikiConfig);

        // debug is a boolean now; existing config files may still carry the
        // historical 'yes'/'no' strings
        $yeswikiConfig['debug'] = filter_var($yeswikiConfig['debug'], FILTER_VALIDATE_BOOLEAN);

        // environment overrides (private/.env or real environment variables) win over
        // yeswiki.config.php; applied before the timezone/debug values are consumed below.
        // YesWikiKernel::build() applies the same overrides to extension parameters.
        $yeswikiConfig = EnvironmentConfiguration::apply($yeswikiConfig);

        // give a default timezone to avoid error
        if (!empty($yeswikiConfig['timezone'])) {
            date_default_timezone_set($yeswikiConfig['timezone']);
        } elseif (!empty($yeswikiDefaultConfig['timezone'])) {
            date_default_timezone_set($yeswikiDefaultConfig['timezone']);
        } elseif (!ini_get('date.timezone')) {
            // backup in last case
            date_default_timezone_set('GMT');
        }

        // check for locking
        if (file_exists('locked')) {
            // read password from lockfile
            $lines = file('locked');
            $lockpw = trim($lines[0]);

            // is authentification given?
            if (isset($_SERVER['PHP_AUTH_USER'])) {
                if (!(($_SERVER['PHP_AUTH_USER'] == 'admin') && ($_SERVER['PHP_AUTH_PW'] == $lockpw))) {
                    $ask = 1;
                }
            } else {
                $ask = 1;
            }

            if ($ask) {
                header('WWW-Authenticate: Basic realm="' . $yeswikiConfig['yeswiki_name'] . ' Install/Upgrade Interface"');
                header('HTTP/1.0 401 Unauthorized');
                echo _t('SITE_BEING_UPDATED');
                exit;
            }
        }

        // Display all errors if in debug mode
        if ($yeswikiConfig['debug']) {
            ini_set('display_errors', 1);
            error_reporting(E_ALL);
        }

        if (empty($yeswikiConfig['mail_domain'] ?? null)) {
            $yeswikiConfig['mail_domain'] = $this->deriveMailDomain(parse_url($yeswikiConfig['base_url'])['host'] ?? '');
        }

        if (!empty($yeswikiConfig['extra_headers'])) {
            foreach ($yeswikiConfig['extra_headers'] as $header) {
                header($header);
            }
        }

        return $yeswikiConfig;
    }

    /**
     * Initialize the cookie.
     *
     * @return string $CookiePath path to the cookie
     */
    public function initCookies()
    {
        // configuration du cookie de session
        // determine le chemin pour les cookies
        $urlParsed = parse_url($this->config['base_url']);
        $CookiePath = $urlParsed['path'];

        // Fixe la gestion des cookie sous les OS utilisant le \ comme separateur de chemin
        $CookiePath = str_replace('\\', '/', $CookiePath);

        foreach (['index.php'] as $anchor) {
            if (substr($CookiePath, -strlen($anchor)) == $anchor) {
                $CookiePath = substr($CookiePath, 0, strlen($CookiePath) - strlen($anchor));
            }
        }

        // ajoute un '/' terminal sauf si on est a la racine web et si nécessaire
        if (substr($CookiePath, -1) !== '/') {
            $CookiePath .= '/';
        }

        $sessionName = 'YesWiki-main';
        if ($CookiePath !== '/') {
            $sessionName = 'YesWiki-' . str_replace('/', '-', substr($CookiePath, 1, -1));
        }

        // test if session exists, because the wiki object is instanciated for every plugin
        if (!isset($_SESSION)) {
            $cookiesParam = session_get_cookie_params();
            $cookiesParam['path'] = $CookiePath;
            $cookiesParam['httponly'] = true;
            $cookiesParam['samesite'] = 'Lax';
            if (preg_match('`^https://`', $this->config['base_url'], $matches)) {
                $cookiesParam['secure'] = true;
            }
            session_set_cookie_params($cookiesParam);
            session_name($sessionName);
            session_start();
        }

        return $CookiePath;
    }

    /**
     * Start the install process.
     *
     * @return void
     */
    public function doInstall()
    {
        $controller = new Admin\Controller\InstallationController($this->config, $this->configFile);
        $controller->run();
    }
}

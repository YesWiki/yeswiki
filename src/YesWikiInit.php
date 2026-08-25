<?php

/** Yeswiki initialization class file. */

namespace YesWiki\Core;

use YesWiki\Admin\Service\ArchiveService;
use YesWiki\Kernel\Routing\ReservedTags;
use YesWiki\Kernel\Service\ConfigurationFileProvider;
use YesWiki\Kernel\Service\EnvironmentConfiguration;
use YesWiki\Kernel\Service\WikiUrls;

/** Yeswiki initialization class. */
class YesWikiInit
{
    public string $page = '';
    public string $method = '';

    /** @var array<string, mixed> */
    public array $config = [];

    public string $configFile;

    /**
     * Create a new YesWikiInit instance.
     *
     * @param array<string, mixed> $config initial config array (empty by default)
     */
    public function __construct(array $config = [])
    {
        $this->configFile = ConfigurationFileProvider::getConfigFileFromEnv();

        $this->getRoute();
        $this->config = $this->getConfig($config);
        $this->setIframeHeaders();

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
        $requestUri = $_SERVER['REQUEST_URI'] ?? '';
        $uri = is_string($requestUri) ? str_replace($scriptlocation, '', $requestUri) : '';
        $uri = preg_replace('~^/\??~', '', $uri) ?? '';
        $uri = explode('&', $uri);
        $uri = explode('?', $uri[0]);
        $args = explode('/', rawurldecode($uri[0]));
        if (!empty($args[0]) or !empty($_GET['wiki'])) {
            if ($args[0] == 'index.php' or !empty($_GET['wiki'])) {
                $wiki = empty($_GET['wiki']) ? '' : preg_replace('/^\//', '', urldecode($_GET['wiki']));
            } else {
                $a = explode('=', $args[0]);
                $wiki = urldecode($a[0]);
            }
            if (empty($wiki)) {
            } elseif (ReservedTags::isReserved(explode('/', $wiki)[0])) {
                $this->page = ReservedTags::canonical(explode('/', $wiki)[0]);
                if (strpos($wiki, '/') !== false) {
                    $wikiParts = explode('/', $wiki);
                    array_shift($wikiParts);
                    $this->method = rtrim(implode('/', $wikiParts), '=');
                } else {
                    array_shift($args);
                    $this->method = rtrim(implode('/', $args), '=');
                }
            } elseif (preg_match('`^' . WN_TAG_HANDLER_CAPTURE . '$`u', $wiki, $matches)) {
                list(, $this->page, $this->method) = $matches;
            } elseif (preg_match('`^' . WN_PAGE_TAG . '$`u', $wiki)) {
                $this->page = $wiki;
                if (isset($args[1]) and !empty($args[1])) {
                    if (preg_match('#^[A-Za-z0-9_]*$#', $args[1])) {
                        $this->method = $args[1];
                    }
                }
            } else {
                echo '<p>', _t('INCORRECT_PAGENAME'), '</p>';
                exit;
            }

            if (!$this->method) {
                $requestMethod = $_SERVER['REQUEST_METHOD'] ?? null;

                if (empty($_POST) && ($requestMethod == 'POST' || $requestMethod == 'PUT' || $requestMethod == 'PATCH')) {
                    $rawBody = file_get_contents('php://input');
                    $_POST = ($rawBody === false ? null : json_decode($rawBody, true)) ?? [];
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

    /** Default mail domain derived from the wiki's host ("www.foo.example.org" => "example.org"). */
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

    private function setIframeHeaders(): void
    {
        $allowedMethods = $this->config['allowed_methods_in_iframe'] ?? 'all';

        if ($this->page === 'doc' || $allowedMethods === 'all' || (
            is_array($allowedMethods) && in_array($this->method, $allowedMethods, true)
        )) {
            header("Content-Security-Policy: frame-ancestors 'self' *;");
        } else {
            header('X-frame-Options: deny');

            header("Content-Security-Policy: frame-ancestors 'none';");
        }
    }

    /**
     * Utility function to merge the multidimentionnal config array the right way.
     *
     * @param array<array-key, mixed> $array1
     * @param array<array-key, mixed> $array2
     *
     * @return array<array-key, mixed> merged array
     */
    protected function array_merge_recursive_distinct(array &$array1, array &$array2): array
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
     * @param array<string, mixed> $yeswikiConfig initial config array (empty by default)
     *
     * @return array<string, mixed> the configuration
     */
    public function getConfig(array $yeswikiConfig = []): array
    {
        $_rewrite_mode = WikiUrls::rewriteMode();
        $yeswikiDefaultConfig = [
            'yeswiki_version' => '',
            'yeswiki_release' => '',
            'charset' => 'UTF-8',
            'debug' => false,

            'htmx_navigation' => true,
            'db_driver' => 'mysql',
            'db_host' => 'localhost',
            'db_database' => '',
            'db_user' => '',
            'db_password' => '',
            'table_prefix' => 'yeswiki_',
            'base_url' => WikiUrls::baseUrl($_rewrite_mode),
            'rewrite_mode' => $_rewrite_mode,
            'meta_keywords' => '',
            'meta_description' => '',
            'navigation_links' => 'DerniersChangements :: DerniersCommentaires :: ParametresUtilisateur',
            'pages_purge_time' => 365,
            'default_write_acl' => '*',
            'default_read_acl' => '*',
            'default_comment_acl' => 'comments-closed',

            'signup_email_activation' => false,
            'user_activation_key_length' => 20,
            'comments_activated' => true,
            'comments_handler' => 'yeswiki',
            'preview_before_save' => false,

            'vditor_wiki_editor' => true,
            'allow_raw_html' => true,
            'disallowed_html_tags' => ['title', 'textarea', 'style', 'xmp', 'noembed', 'noframes', 'script', 'plaintext'],
            'allowed_methods_in_iframe' => ['iframe', 'editiframe', 'render'],
            'revisionscount' => 30,
            'timezone' => 'Europe/Paris',
            'root_page' => 'PagePrincipale',

            'other_languages' => [],
            'yeswiki_name' => '',
            'htmlPurifierActivated' => true,
            'htmlPurifierSafeIframeRegexp' => '~^https://.*~',
            'favorites_activated' => true,
            'hide_keywords' => false,
            'use_alerte' => true,
            'use_hashcash' => true,
            'use_captcha' => false,
            'wiki_status' => 'running',
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
                'max_nb_files' => 10,
            ],

            'qrcode_config' => [
                'relation_form_id' => 1300,
                'default_relation_type' => 'contact',
                'default_entity_type' => 'personne',
                'default_entity_form' => '1',
                'default_user_form' => '1',
                'visualisation_refresh_period' => '30000',
            ],

            'image-upload-format' => 'image/webp',

            'image-upload-max-width' => 1920,
            'image-upload-max-height' => 1920,
            'image-upload-quality' => 0.82,

            'image-upload-max-size' => 1048576,

            'image-render-max-width' => 1920,
            'image-render-max-height' => 1920,
            'image-small-width' => 140,
            'image-small-height' => 97,
            'image-medium-width' => 300,
            'image-medium-height' => 209,
            'image-big-width' => 780,
            'image-big-height' => 544,
            'authorized-extensions' => [
                'jpg' => 'JPEG',
                'png' => 'PNG',
                'gif' => 'GIF',
                'jpeg' => 'JPEG',
                'webp' => 'WEBP',

                'avif' => 'AVIF',
                'bmp' => 'BMP',
                'tif' => 'TIFF',
                'svg' => 'SVG',

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

                'yaml' => 'YAML',
                'zip' => 'Zip',
                'scar' => 'SCAR',

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

            'contact_mail_func' => 'mail',
            'contact_smtp_host' => '',
            'contact_smtp_port' => '',
            'contact_smtp_user' => '',
            'contact_smtp_pass' => '',
            'contact_smtp_secure' => '',
            'contact_use_long_wiki_urls_in_emails' => false,
            'contact_reply_to' => '',
            'contact_debug' => 0,
            'contact_passphrase' => '',
            'contact_disable_email_for_password' => false,

            'dataSources' => [],
            'sync_secret' => '',

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

            'baz_menu' => 'formulaire,consulter,saisir,listes,importer,exporter',

            'herse_id' => '',
            'herse_password' => '',

            'webhooks_formats' => [
                'raw' => 'WEBHOOKS_FORMAT_RAW',
                'activitypub' => 'WEBHOOKS_FORMAT_ACTIVITYPUB',
                'mattermost' => 'WEBHOOKS_FORMAT_MATTERMOST',
                'slack' => 'WEBHOOKS_FORMAT_SLACK',
                'yeswiki' => 'WEBHOOKS_FORMAT_YESWIKI',
            ],

            'webhooks_activitypub_default_actor' => '',

            'webhooks_activitypub_actors_base_url' => '',

            'webhooks_bot_name' => 'YesWiki Bot',
            'webhooks_bot_icon' => '%base_url%styles/webhooks/default-bot.png',
            'BAZ_ENVOI_MAIL_ADMIN' => false,
            'BAZ_ADRESSE_MAIL_ADMIN' => 'noreply@%mail_domain%',

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

            'baz_marker_image_file' => 'src/assets/images/bazar/marker.png',
            'temp_tag_for_entry_creation' => 'unknown_entry_id',
        ];
        unset($_rewrite_mode);

        if (file_exists($this->configFile)) {
            include $this->configFile;

            if (isset($wakkaConfig) && is_array($wakkaConfig)) {
                $yeswikiConfig = $wakkaConfig;
            }
        } else {
            $yeswikiDefaultConfig['root_page'] = _t('HOMEPAGE_WIKINAME');
            $yeswikiDefaultConfig['yeswiki_name'] = _t('MY_YESWIKI_SITE');
        }

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

        unset($yeswikiConfig['wakka_version']);
        if (!empty($yeswikiConfig['base_url'])) {
            $yeswikiConfig['base_url'] = str_replace('/wakka.php?wiki=', '/?', $yeswikiConfig['base_url']);
        }

        $yeswikiConfig = $this->array_merge_recursive_distinct($yeswikiDefaultConfig, $yeswikiConfig);

        $yeswikiConfig['debug'] = filter_var($yeswikiConfig['debug'], FILTER_VALIDATE_BOOLEAN);

        $yeswikiConfig = EnvironmentConfiguration::apply($yeswikiConfig);

        if (!empty($yeswikiConfig['timezone'])) {
            date_default_timezone_set($yeswikiConfig['timezone']);
        } elseif (!empty($yeswikiDefaultConfig['timezone'])) {
            date_default_timezone_set($yeswikiDefaultConfig['timezone']);
        } elseif (!ini_get('date.timezone')) {
            date_default_timezone_set('GMT');
        }

        if (file_exists('locked')) {
            $lines = file('locked') ?: [];
            $lockpw = trim($lines[0] ?? '');

            $ask = 0;
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
    /**
     * The cookie path, and this request's session.
     *
     * Called once per request, not once per process: a worker outlives the visitor whose session
     * it opened, so starting one at boot would serve every later visitor the first one's
     * `$_SESSION` (ADR-0024, single-binary 07).
     *
     * @return string
     */
    public function initCookies()
    {
        $urlParsed = parse_url(is_string($this->config['base_url'] ?? null) ? $this->config['base_url'] : '');
        $CookiePath = is_array($urlParsed) ? ($urlParsed['path'] ?? '') : '';

        $CookiePath = str_replace('\\', '/', $CookiePath);

        foreach (['index.php'] as $anchor) {
            if (substr($CookiePath, -strlen($anchor)) == $anchor) {
                $CookiePath = substr($CookiePath, 0, strlen($CookiePath) - strlen($anchor));
            }
        }

        if (substr($CookiePath, -1) !== '/') {
            $CookiePath .= '/';
        }

        $sessionName = 'YesWiki-main';
        if ($CookiePath !== '/') {
            $sessionName = 'YesWiki-' . str_replace('/', '-', substr($CookiePath, 1, -1));
        }

        if (session_status() !== PHP_SESSION_ACTIVE) {
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
            $this->seedSessionKeysVendorSingletonsAssume();
        }

        return $CookiePath;
    }

    /**
     * Keys a once-constructed vendor object believes it created, seeded on every session.
     *
     * `Tamtamchik\SimpleFlash`'s SessionManager sets `$_SESSION['flash_messages']` in a
     * constructor that a static facade runs once per process. Under php-fpm that is once per
     * request; under a worker (ADR-0024) the object outlives the session it initialised, so every
     * request after the first reads a key nothing put back.
     *
     * @return void
     */
    private function seedSessionKeysVendorSingletonsAssume()
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return;
        }

        $_SESSION['flash_messages'] ??= [];
    }

    /**
     * Start the install process.
     *
     * @return void
     */
    public function doInstall()
    {
        $controller = new \YesWiki\Admin\Controller\InstallationController($this->config, $this->configFile);
        $controller->run();
    }
}

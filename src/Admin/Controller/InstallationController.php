<?php

namespace YesWiki\Admin\Controller;

use YesWiki\Admin\Service\InstallationService;
use YesWiki\Kernel\Service\EnvironmentConfiguration;
use YesWiki\Kernel\Service\LanguageService;
use YesWiki\Kernel\Service\WikiUrls;

/** Web installer, run by Init::doInstall() when the configuration file does not exist yet. */
class InstallationController
{
    /** @var array<string, mixed> */
    protected array $config;
    protected string $configFile;
    /** @var array<string, mixed> the `config` values this request posted back, legacy keys already mapped */
    protected array $configPosted;
    /** @var array<string, mixed> configuration values the environment forces, which the form shows as read-only */
    protected array $env;
    protected string $step;
    protected string $baseUrl;
    protected \Twig\Environment $twig;
    protected string $adminName;
    protected string $adminEmail;
    protected string $adminPassword;
    protected string $adminPasswordConf;
    /** @var string either InstallationService::BACKUP_SQL_FILE or 'default' */
    protected string $contentSQL;

    /**
     * @param array<string, mixed> $config     configuration from Init::getConfig() (defaults + environment overrides)
     * @param string               $configFile path of the yeswiki.config.php file to create
     */
    public function __construct(array $config, string $configFile)
    {
        LanguageService::getInstance()->loadPreferredLanguage((object)['config' => $config]);

        $this->config = $config;
        $this->configFile = $configFile;
        $this->env = InstallationService::environmentForcedValues();

        if (isset($_POST['config']) && is_string($_POST['config'])) {
            $_POST['config'] = json_decode(html_entity_decode($_POST['config']), true) ?? [];
        }
        $this->configPosted = InstallationService::mapLegacyKeys($_POST['config'] ?? []);

        $this->config = EnvironmentConfiguration::apply(array_merge($this->config, $this->configPosted));

        $this->adminName = $this->postedOrEnv('admin_name', 'ADMIN_NAME');
        $this->adminEmail = $this->postedOrEnv('admin_email', 'ADMIN_EMAIL');
        $this->adminPassword = $this->postedOrEnv('admin_password', 'ADMIN_PASSWORD');
        $this->adminPasswordConf = $this->postedOrEnv('admin_password_conf', 'ADMIN_PASSWORD');
        $postedContentSQL = $_POST['contentSQL'] ?? null;
        $this->contentSQL = is_string($postedContentSQL)
            ? $postedContentSQL
            : (file_exists(InstallationService::backupFile()) ? InstallationService::BACKUP_SQL_FILE : 'default');

        $this->step = trim($_REQUEST['installAction'] ?? '') ?: 'default';
        $this->baseUrl = WikiUrls::baseUrl(true);
        $this->twig = $this->setupTwig();
    }

    public function run(): void
    {
        header('Content-Type: text/html; charset=UTF-8');
        if ($this->step === 'install') {
            $this->install();
        } else {
            echo $this->render('installation-form.twig');
        }
    }

    /** Run the installer; on failure display the checks done so far, the error, and a retry form carrying the posted values. */
    protected function install(): void
    {
        $service = (new InstallationService($this->config, $this->configFile))
            ->withAdminAccount($this->adminName, $this->adminEmail, $this->adminPassword, $this->adminPasswordConf)
            ->withContentFrom($this->contentSQL);

        try {
            $service->install();
        } catch (\Exception $ex) {
            echo $this->render('installation-database.twig', [
                'messages' => $service->messages(),
                'error' => $ex->getMessage(),
                'configPosted' => htmlspecialchars(json_encode($this->configPosted) ?: '{}', ENT_COMPAT, 'UTF-8'),
            ]);

            return;
        }

        $config = $service->config();
        header('Location: ' . $config['base_url'] . $config['root_page']);
        exit;
    }

    /** @param array<string, mixed> $extraOptions */
    protected function render(string $template, array $extraOptions = []): string
    {
        $options = array_merge([
            'template' => $template,
            'baseUrl' => $this->baseUrl,
            'config' => $this->config,
            'env' => $this->env,
            'locale' => LanguageService::getInstance()->preferredLanguage(),

            'installedLanguages' => $GLOBALS['installed_languages'] ?? $GLOBALS['available_languages'],
            'languagesList' => $GLOBALS['languages_list'],
            'availableDrivers' => InstallationService::availableDrivers(),
            'yeswikiVersion' => ucfirst(YESWIKI_VERSION) . ' ' . YESWIKI_RELEASE,
            'pattern' => WN_CAMEL_CASE_EVOLVED,
            'backupFound' => file_exists(InstallationService::backupFile()),
            'backupSqlFile' => InstallationService::BACKUP_SQL_FILE,
            'contentSQL' => $this->contentSQL,
            'adminName' => $this->adminName ?: 'WikiAdmin',
            'adminEmail' => $this->adminEmail,
            'adminPassword' => $this->adminPassword,
            'adminPasswordConf' => $this->adminPasswordConf,
            'adminEnvForced' => [
                'name' => $this->envValue('ADMIN_NAME') !== null,
                'email' => $this->envValue('ADMIN_EMAIL') !== null,
                'password' => $this->envValue('ADMIN_PASSWORD') !== null,
            ],
        ], $extraOptions);

        return $this->twig->render('installation.twig', $options);
    }

    /** The value this request posted under $postKey, or -- when it posted none, or something that is not a string -- what the environment says, or ''. */
    private function postedOrEnv(string $postKey, string $envName): string
    {
        $posted = $_POST[$postKey] ?? null;
        if (is_string($posted)) {
            return $posted;
        }

        return $this->envValue($envName) ?? '';
    }

    /** An environment value by variable name (private/.env values are putenv()ed by YesWikiLoader). */
    private function envValue(string $name): ?string
    {
        $value = getenv($name);

        return $value === false ? null : $value;
    }

    private function setupTwig(): \Twig\Environment
    {
        $twigLoader = new \Twig\Loader\FilesystemLoader(YESWIKI_PROGRAM_DIR . '/templates');
        $twig = new \Twig\Environment($twigLoader, [
            'debug' => (bool)($this->config['debug'] ?? false),
            'cache' => 'cache/templates/',
            'auto_reload' => true,
        ]);
        if (!empty($this->config['debug'])) {
            $twig->addExtension(new \Twig\Extension\DebugExtension());
        }
        $twig->addFunction(new \Twig\TwigFunction('_t', function ($key, $params = []) {
            return html_entity_decode(_t($key, $params));
        }));

        return $twig;
    }
}

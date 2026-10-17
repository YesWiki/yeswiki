<?php

namespace YesWiki\Bazar\Service;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use YesWiki\Core\Service\ConfigurationFileProvider;
use YesWiki\Core\Service\ConfigurationService;

/**
 * Each form's ActivityPub profile, kept in the `activitypub_profiles` configuration under the form id.
 */
class ActivityPubProfiles
{
    public const CONFIG_KEY = 'activitypub_profiles';
    public const MAX_FIELDS = 6;
    public const IMAGE_TYPES = [IMAGETYPE_PNG => 'png', IMAGETYPE_JPEG => 'jpg', IMAGETYPE_GIF => 'gif', IMAGETYPE_WEBP => 'webp'];
    public const MAX_IMAGE_BYTES = 5 * 1024 * 1024;

    protected $params;
    protected $configurationService;
    protected $configFile;
    protected $profiles;

    public function __construct(ParameterBagInterface $params, ConfigurationService $configurationService, string $configFile = '')
    {
        $this->params = $params;
        $this->configurationService = $configurationService;
        $this->configFile = $configFile !== '' ? $configFile : ConfigurationFileProvider::getConfigFileFromEnv();
    }

    /**
     * The profile of a form, with every key present.
     *
     * @return array{username:string,summary:string,icon:string,image:string,fields:array<string,string>}
     */
    public function get($formId): array
    {
        $profile = $this->all()[(string)$formId] ?? [];

        return [
            'username' => (string)($profile['username'] ?? ''),
            'summary' => (string)($profile['summary'] ?? ''),
            'icon' => (string)($profile['icon'] ?? ''),
            'image' => (string)($profile['image'] ?? ''),
            'fields' => is_array($profile['fields'] ?? null) ? array_map('strval', $profile['fields']) : [],
        ];
    }

    public function username($formId): string
    {
        return $this->get($formId)['username'];
    }

    public function formIdForUsername(string $username): ?string
    {
        foreach ($this->all() as $formId => $profile) {
            if ($username !== '' && strcasecmp((string)($profile['username'] ?? ''), $username) === 0) {
                return (string)$formId;
            }
        }

        return null;
    }

    /**
     * Checks a profile and writes it to the configuration file, throwing a translated message when it is refused.
     */
    public function save($formId, array $profile): array
    {
        $profile = $this->validate((string)$formId, $profile);

        if (!is_writable($this->configFile)) {
            throw new \Exception(_t('BAZ_ACTIVITYPUB_CONFIG_NOT_WRITABLE'));
        }
        $config = $this->configurationService->getConfiguration($this->configFile);
        $config->load();
        $profiles = isset($config[self::CONFIG_KEY]) && is_array($config[self::CONFIG_KEY]) ? $config[self::CONFIG_KEY] : [];
        $profiles[(string)$formId] = $profile;
        $config[self::CONFIG_KEY] = $profiles;
        if (!$this->configurationService->write($config)) {
            throw new \Exception(_t('BAZ_ACTIVITYPUB_CONFIG_NOT_WRITABLE'));
        }
        $this->profiles = array_map(fn ($p) => is_array($p) ? $p : [], $profiles);

        return $profile;
    }

    /**
     * Stores an uploaded image in the wiki's files and returns its path, or throws when it is not a usable image.
     */
    public function storeImage($formId, string $role, UploadedFile $upload): string
    {
        if (!$upload->isValid() || $upload->getSize() > self::MAX_IMAGE_BYTES) {
            throw new \Exception(_t('BAZ_ACTIVITYPUB_IMAGE_INVALID'));
        }
        $extension = self::IMAGE_TYPES[@getimagesize($upload->getPathname())[2] ?? 0] ?? null;
        if ($extension === null) {
            throw new \Exception(_t('BAZ_ACTIVITYPUB_IMAGE_INVALID'));
        }
        $attach = $this->params->has('attach_config') ? $this->params->get('attach_config') : [];
        $directory = rtrim((string)($attach['upload_path'] ?? 'files'), '/');
        $name = 'ActivityPub_' . (int)$formId . '_' . $role . '_' . bin2hex(random_bytes(4)) . '.' . $extension;
        $upload->move($directory, $name);
        @chmod($directory . '/' . $name, 0644);

        return $directory . '/' . $name;
    }

    protected function validate(string $formId, array $profile): array
    {
        $username = strtolower(trim((string)($profile['username'] ?? '')));
        if (!preg_match('/^[a-z0-9_]{1,30}$/', $username)) {
            throw new \Exception(_t('BAZ_ACTIVITYPUB_USERNAME_INVALID'));
        }
        $owner = $this->formIdForUsername($username);
        if ($owner !== null && $owner !== $formId) {
            throw new \Exception(_t('BAZ_ACTIVITYPUB_USERNAME_TAKEN', ['username' => $username]));
        }

        $images = [];
        foreach (['icon', 'image'] as $role) {
            $images[$role] = trim((string)($profile[$role] ?? ''));
            if ($images[$role] !== '' && !preg_match('~^https?://[^\s"<>]+$~i', $images[$role]) && !preg_match('~^(?!.*\.\.)[\w\-./]+$~', $images[$role])) {
                throw new \Exception(_t('BAZ_ACTIVITYPUB_IMAGE_URL_INVALID'));
            }
        }

        $fields = [];
        foreach (is_array($profile['fields'] ?? null) ? $profile['fields'] : [] as $name => $value) {
            $name = trim((string)$name);
            $value = trim((string)$value);
            if ($name !== '' && $value !== '' && count($fields) < self::MAX_FIELDS) {
                $fields[mb_substr($name, 0, 255)] = mb_substr($value, 0, 2048);
            }
        }

        return [
            'username' => $username,
            'summary' => mb_substr(trim((string)($profile['summary'] ?? '')), 0, 5000),
            'icon' => $images['icon'],
            'image' => $images['image'],
            'fields' => $fields,
        ];
    }

    protected function all(): array
    {
        if ($this->profiles === null) {
            $profiles = $this->params->has(self::CONFIG_KEY) ? $this->params->get(self::CONFIG_KEY) : [];
            $this->profiles = is_array($profiles) ? array_map(fn ($p) => is_array($p) ? $p : [], $profiles) : [];
        }

        return $this->profiles;
    }
}

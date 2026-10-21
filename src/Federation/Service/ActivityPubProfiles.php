<?php

namespace YesWiki\Federation\Service;

use Psr\Container\ContainerInterface;
use YesWiki\Content\Service\FormManager;

/** Each form's ActivityPub profile, kept in the `activitypub` metadata of the form's page. */
class ActivityPubProfiles
{
    public const MAX_FIELDS = 6;

    public function __construct(private readonly ContainerInterface $container)
    {
    }

    /**
     * The profile of a form, with every key present.
     *
     * @param array<string, mixed> $form
     *
     * @return array{username: string, summary: string, icon: string, image: string, fields: array<string, string>, approve_followers: bool, accept_unfollowed: bool, accept_non_public: bool, accept_replies: bool}
     */
    public function get(array $form): array
    {
        $profile = is_array($form['activitypub'] ?? null) ? $form['activitypub'] : [];
        $fields = [];
        foreach (is_array($profile['fields'] ?? null) ? $profile['fields'] : [] as $name => $value) {
            $fields[(string)$name] = is_scalar($value) ? (string)$value : '';
        }

        return [
            'username' => is_string($profile['username'] ?? null) ? $profile['username'] : '',
            'summary' => is_string($profile['summary'] ?? null) ? $profile['summary'] : '',
            'icon' => is_string($profile['icon'] ?? null) ? $profile['icon'] : '',
            'image' => is_string($profile['image'] ?? null) ? $profile['image'] : '',
            'fields' => $fields,
            'approve_followers' => !empty($profile['approve_followers']),
            'accept_unfollowed' => !empty($profile['accept_unfollowed']),
            'accept_non_public' => !empty($profile['accept_non_public']),
            'accept_replies' => !empty($profile['accept_replies']),
        ];
    }

    /** The id of the form whose username this is, or null. */
    public function formIdForUsername(string $username): ?string
    {
        if ($username === '') {
            return null;
        }
        foreach ($this->formManager()->getAll() as $form) {
            if (strcasecmp($this->get($form)['username'], $username) === 0) {
                return (string)$form['id'];
            }
        }

        return null;
    }

    /**
     * Checks a profile and stores it with the form, throwing a translated message when it is refused.
     *
     * @param array<string, mixed> $form
     * @param array<string, mixed> $profile
     *
     * @return array<string, mixed> the profile as stored
     */
    public function save(array $form, array $profile): array
    {
        $profile = $this->validate((string)$form['id'], $profile);
        $this->formManager()->saveActivitypubProfile($form['id'], $profile);

        return $profile;
    }

    /**
     * @param array<string, mixed> $profile
     *
     * @return array{username: string, summary: string, icon: string, image: string, fields: array<string, string>, approve_followers: bool, accept_unfollowed: bool, accept_non_public: bool, accept_replies: bool}
     */
    private function validate(string $formId, array $profile): array
    {
        $username = strtolower(trim(is_scalar($profile['username'] ?? null) ? (string)$profile['username'] : ''));
        if (!preg_match('/^[a-z0-9_]{1,30}$/', $username)) {
            throw new \Exception(_t('BAZ_ACTIVITYPUB_USERNAME_INVALID'));
        }
        $owner = $this->formIdForUsername($username);
        if ($owner !== null && $owner !== $formId) {
            throw new \Exception(_t('BAZ_ACTIVITYPUB_USERNAME_TAKEN', ['username' => $username]));
        }

        $images = [];
        foreach (['icon', 'image'] as $role) {
            $images[$role] = trim(is_scalar($profile[$role] ?? null) ? (string)$profile[$role] : '');
            if ($images[$role] !== '' && !preg_match('~^https?://[^\s"<>]+$~i', $images[$role]) && !preg_match('~^(?!.*\.\.)[\w\-./?=&%]+$~', $images[$role])) {
                throw new \Exception(_t('BAZ_ACTIVITYPUB_IMAGE_URL_INVALID'));
            }
        }

        $fields = [];
        foreach (is_array($profile['fields'] ?? null) ? $profile['fields'] : [] as $name => $value) {
            $name = trim((string)$name);
            $value = trim(is_scalar($value) ? (string)$value : '');
            if ($name !== '' && $value !== '' && count($fields) < self::MAX_FIELDS) {
                $fields[mb_substr($name, 0, 255)] = mb_substr($value, 0, 2048);
            }
        }

        return [
            'username' => $username,
            'summary' => mb_substr(trim(is_scalar($profile['summary'] ?? null) ? (string)$profile['summary'] : ''), 0, 5000),
            'icon' => $images['icon'],
            'image' => $images['image'],
            'fields' => $fields,
            'approve_followers' => !empty($profile['approve_followers']),
            'accept_unfollowed' => !empty($profile['accept_unfollowed']),
            'accept_non_public' => !empty($profile['accept_non_public']),
            'accept_replies' => !empty($profile['accept_replies']),
        ];
    }

    private function formManager(): FormManager
    {
        $formManager = $this->container->get(FormManager::class);
        if (!$formManager instanceof FormManager) {
            throw new \LogicException('No FormManager in the container');
        }

        return $formManager;
    }
}

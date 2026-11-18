<?php

namespace YesWiki\Identity\Service\SignIn;

use YesWiki\Kernel\Service\DbService;

/** The two keys tying a user page to its provider, and the guard that keeps them through any other write. */
class AccountLinkKeys
{
    public const PROVIDER_KEY = 'sso_provider';
    public const SUBJECT_KEY = 'sso_subject';

    public function __construct(private readonly DbService $db)
    {
    }

    /**
     * An account page's body, tied to the provider's subject and without a password.
     *
     * @param array<array-key, mixed> $body
     *
     * @return array<array-key, mixed>
     */
    public static function linked(array $body, string $providerId, string $subject): array
    {
        $body[self::PROVIDER_KEY] = $providerId;
        $body[self::SUBJECT_KEY] = $subject;
        $body['password'] = '';

        return $body;
    }

    /**
     * The body PageManager::save() stores for a user page: the link keys already stored, whatever the caller sent.
     *
     * @param array<array-key, mixed> $body
     *
     * @return array<array-key, mixed>
     */
    public function guardBody(string $tag, array $body): array
    {
        unset($body[self::PROVIDER_KEY], $body[self::SUBJECT_KEY]);

        return array_merge($body, $this->storedKeys($tag));
    }

    /** @return array<string, string> the link keys the account's current revision holds, read from the database */
    public function storedKeys(string $tag): array
    {
        $row = $this->db->loadSingle(
            "SELECT {$this->db->jsonExtract('p.body', '$.' . self::PROVIDER_KEY)} AS provider,
                {$this->db->jsonExtract('p.body', '$.' . self::SUBJECT_KEY)} AS subject
            FROM {$this->db->prefixTable('pages')} p
            WHERE p.tag = ? AND p.latest = 'Y' LIMIT 1",
            [$tag]
        );
        $keys = [];
        if (is_string($row['provider'] ?? null) && $row['provider'] !== '') {
            $keys[self::PROVIDER_KEY] = $row['provider'];
        }
        if (is_string($row['subject'] ?? null) && $row['subject'] !== '') {
            $keys[self::SUBJECT_KEY] = $row['subject'];
        }

        return $keys;
    }
}

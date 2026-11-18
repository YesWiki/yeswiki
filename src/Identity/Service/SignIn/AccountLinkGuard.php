<?php

namespace YesWiki\Identity\Service\SignIn;

use YesWiki\Kernel\Service\DbService;

/** Keeps the provider link stored in a user page whatever a write puts in its body. */
class AccountLinkGuard
{
    public function __construct(private readonly DbService $db)
    {
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
        unset($body[AccountLinks::PROVIDER_KEY], $body[AccountLinks::SUBJECT_KEY]);

        return array_merge($body, $this->storedKeys($tag));
    }

    /** @return array<string, string> the link keys the account's current revision holds, read from the database */
    public function storedKeys(string $tag): array
    {
        $row = $this->db->loadSingle(
            "SELECT {$this->db->jsonExtract('p.body', '$.' . AccountLinks::PROVIDER_KEY)} AS provider,
                {$this->db->jsonExtract('p.body', '$.' . AccountLinks::SUBJECT_KEY)} AS subject
            FROM {$this->db->prefixTable('pages')} p
            WHERE p.tag = ? AND p.latest = 'Y' LIMIT 1",
            [$tag]
        );
        $keys = [];
        if (is_string($row['provider'] ?? null) && $row['provider'] !== '') {
            $keys[AccountLinks::PROVIDER_KEY] = $row['provider'];
        }
        if (is_string($row['subject'] ?? null) && $row['subject'] !== '') {
            $keys[AccountLinks::SUBJECT_KEY] = $row['subject'];
        }

        return $keys;
    }
}

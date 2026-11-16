<?php

namespace YesWiki\Identity\Service\SignIn;

use Psr\Container\ContainerInterface;
use YesWiki\Content\Entity\PageType;
use YesWiki\Content\Service\PageManager;
use YesWiki\Identity\Entity\User;
use YesWiki\Identity\Service\UserManager;
use YesWiki\Kernel\Service\DbService;

/**
 * The tie between an account and its sign-in provider: two keys in the JSON body of its `user` page.
 *
 * Only PageManager::saveAccountLink(), which link() and UserManager::createLinked() call, writes them: PageManager::save() runs
 * guardBody() on every user page, so a provider cannot take over an account by any other write.
 */
class AccountLinks
{
    public const PROVIDER_KEY = 'sso_provider';
    public const SUBJECT_KEY = 'sso_subject';

    /** Lazily fetched services: injecting UserManager or PageManager would be a constructor cycle, as both reach back here. */
    public function __construct(
        private readonly DbService $db,
        private readonly ContainerInterface $container
    ) {
    }

    public function find(string $providerId, string $subject): ?User
    {
        $row = $this->db->loadSingle(
            "SELECT p.tag AS tag FROM {$this->db->prefixTable('pages')} p
            WHERE p.latest = 'Y' AND p.{$this->db->quoteIdentifier('type')} = ?
            AND {$this->db->jsonExtract('p.body', '$.' . self::PROVIDER_KEY)} = ?
            AND {$this->db->jsonExtract('p.body', '$.' . self::SUBJECT_KEY)} = ?
            LIMIT 1",
            [PageType::USER, $providerId, $subject]
        );

        return $row === null ? null : $this->users()->getOneByName($row['tag']);
    }

    public function findByEmail(string $email): ?User
    {
        $row = $this->db->loadSingle(
            "SELECT p.tag AS tag FROM {$this->db->prefixTable('pages')} p
            WHERE p.latest = 'Y' AND p.{$this->db->quoteIdentifier('type')} = ?
            AND LOWER({$this->db->jsonExtract('p.body', '$.email')}) = LOWER(?)
            LIMIT 1",
            [PageType::USER, $email]
        );

        return $row === null ? null : $this->users()->getOneByName($row['tag']);
    }

    public function subjectOf(User $user): ?string
    {
        $stored = $this->storedKeys($user->getName());

        return $stored[self::SUBJECT_KEY] ?? null;
    }

    /** Ties the account to the provider's subject and clears its password: from now on only the provider signs it in. */
    public function link(User $user, string $providerId, string $subject): void
    {
        $pages = $this->container->get(PageManager::class);
        $tag = $user->getName();
        $page = $pages->getOne($tag, null, true, true);
        if ($page === null) {
            throw new \RuntimeException("no account page for $tag");
        }
        // the write drops its per-request page caches itself, so the next read sees these values.
        $pages->saveAccountLink($tag, self::linked(is_array($page['body']) ? $page['body'] : [], $providerId, $subject));
        $user->setPassword('');
    }

    /**
     * An account page's body, tied to the provider's subject and without a password: from now on only the provider signs it in.
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
     * What PageManager::save() stores for a user page: the keys already stored, whatever the caller put in the body.
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

    /** @return array<string, int> accounts per provider id */
    public function countByProvider(): array
    {
        $rows = $this->db->loadAll(
            "SELECT {$this->db->jsonExtract('p.body', '$.' . self::PROVIDER_KEY)} AS provider, COUNT(*) AS total
            FROM {$this->db->prefixTable('pages')} p
            WHERE p.latest = 'Y' AND p.{$this->db->quoteIdentifier('type')} = ?
            AND {$this->db->jsonExtract('p.body', '$.' . self::PROVIDER_KEY)} IS NOT NULL
            GROUP BY provider",
            [PageType::USER]
        );
        $counts = [];
        foreach ($rows as $row) {
            if (is_string($row['provider']) && $row['provider'] !== '') {
                $counts[$row['provider']] = (int)$row['total'];
            }
        }

        return $counts;
    }

    /** The wiki's own accounts: those no provider took over, which name none. countByProvider() leaves them out. */
    public function countLocal(): int
    {
        $provider = $this->db->jsonExtract('p.body', '$.' . self::PROVIDER_KEY);
        $row = $this->db->loadSingle(
            "SELECT COUNT(*) AS total FROM {$this->db->prefixTable('pages')} p
            WHERE p.latest = 'Y' AND p.{$this->db->quoteIdentifier('type')} = ?
            AND ($provider IS NULL OR $provider = '')",
            [PageType::USER]
        );

        return (int)($row['total'] ?? 0);
    }

    /** @return array<string, string> the keys the account's current revision holds, read from the database */
    private function storedKeys(string $tag): array
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

    private function users(): UserManager
    {
        return $this->container->get(UserManager::class);
    }
}

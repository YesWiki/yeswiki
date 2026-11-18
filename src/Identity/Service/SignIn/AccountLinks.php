<?php

namespace YesWiki\Identity\Service\SignIn;

use YesWiki\Content\Entity\PageType;
use YesWiki\Content\Service\PageManager;
use YesWiki\Identity\Entity\User;
use YesWiki\Identity\Service\UserManager;
use YesWiki\Kernel\Service\DbService;

/** The tie between an account and its sign-in provider: two keys in the JSON body of its `user` page. */
class AccountLinks
{
    public function __construct(
        private readonly DbService $db,
        private readonly PageManager $pages,
        private readonly UserManager $users,
        private readonly AccountLinkKeys $guard
    ) {
    }

    public function find(string $providerId, string $subject): ?User
    {
        $row = $this->db->loadSingle(
            "SELECT p.tag AS tag FROM {$this->db->prefixTable('pages')} p
            WHERE p.latest = 'Y' AND p.{$this->db->quoteIdentifier('type')} = ?
            AND {$this->db->jsonExtract('p.body', '$.' . AccountLinkKeys::PROVIDER_KEY)} = ?
            AND {$this->db->jsonExtract('p.body', '$.' . AccountLinkKeys::SUBJECT_KEY)} = ?
            LIMIT 1",
            [PageType::USER, $providerId, $subject]
        );

        return $row === null ? null : $this->users->getOneByName($row['tag']);
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

        return $row === null ? null : $this->users->getOneByName($row['tag']);
    }

    public function subjectOf(User $user): ?string
    {
        $stored = $this->guard->storedKeys($user->getName());

        return $stored[AccountLinkKeys::SUBJECT_KEY] ?? null;
    }

    /** Ties the account to the provider's subject and clears its password. */
    public function link(User $user, string $providerId, string $subject): void
    {
        $tag = $user->getName();
        $page = $this->pages->getOne($tag, null, true, true);
        if ($page === null) {
            throw new \RuntimeException("no account page for $tag");
        }
        $this->pages->saveAccountLink($tag, AccountLinkKeys::linked(is_array($page['body']) ? $page['body'] : [], $providerId, $subject));
        $user->setPassword('');
    }

    /** @return array<string, int> accounts per provider id */
    public function countByProvider(): array
    {
        $rows = $this->db->loadAll(
            "SELECT {$this->db->jsonExtract('p.body', '$.' . AccountLinkKeys::PROVIDER_KEY)} AS provider, COUNT(*) AS total
            FROM {$this->db->prefixTable('pages')} p
            WHERE p.latest = 'Y' AND p.{$this->db->quoteIdentifier('type')} = ?
            AND {$this->db->jsonExtract('p.body', '$.' . AccountLinkKeys::PROVIDER_KEY)} IS NOT NULL
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

    /** The wiki's own accounts, those no provider took over. */
    public function countLocal(): int
    {
        $provider = $this->db->jsonExtract('p.body', '$.' . AccountLinkKeys::PROVIDER_KEY);
        $row = $this->db->loadSingle(
            "SELECT COUNT(*) AS total FROM {$this->db->prefixTable('pages')} p
            WHERE p.latest = 'Y' AND p.{$this->db->quoteIdentifier('type')} = ?
            AND ($provider IS NULL OR $provider = '')",
            [PageType::USER]
        );

        return (int)($row['total'] ?? 0);
    }
}

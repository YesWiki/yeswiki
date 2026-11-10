<?php

/**
 * What the sign-in specs read or arrange in the wiki under test, run from its folder:
 *   journal <action pattern>        the Journal rows whose action is LIKE the pattern, oldest first, as JSON
 *   forget <email>...               deletes the accounts that have these emails
 *   account <email>                 the name of the account that has this email, or null, as JSON
 *   groups <account>                the account's groups, as JSON
 *   add-to-group <group> <account>  creates the group when it does not exist
 *   forget-attempts <login>...      deletes the failed and throttled sign-ins of these logins, which the attempt limit counts
 */

use YesWiki\Core\YesWikiLoader;
use YesWiki\Identity\Service\GroupManager;
use YesWiki\Identity\Service\UserManager;
use YesWiki\Kernel\Service\DbService;
use YesWiki\Kernel\Service\JournalSchema;

require_once dirname(__DIR__, 3) . '/src/YesWikiLoader.php';

$services = YesWikiLoader::getWiki(true)->services;
$users = $services->get(UserManager::class);
$arguments = array_slice($argv, 2);

switch ($argv[1] ?? '') {
    case 'journal':
        $db = $services->get(DbService::class);
        $table = $db->quoteIdentifier($services->get(JournalSchema::class)->table());
        $rows = $db->loadAll("SELECT * FROM {$table} WHERE {$db->quoteIdentifier('action')} LIKE ? ORDER BY {$db->quoteIdentifier('id')}", [(string)($arguments[0] ?? '%')]);
        echo json_encode(array_map(fn (array $row): array => ['context' => json_decode((string)($row['context'] ?? ''), true)] + $row, $rows));
        break;

    case 'forget':
        foreach ($arguments as $email) {
            if ($user = $users->getOneByEmail($email)) {
                $users->delete($user);
            }
        }
        break;

    case 'account':
        $user = $users->getOneByEmail((string)($arguments[0] ?? ''));
        echo json_encode($user?->getName());
        break;

    case 'groups':
        $user = $users->getOneByName((string)($arguments[0] ?? ''));
        echo json_encode($user === null ? [] : array_values($users->groupsWhereIsMember($user, false)));
        break;

    case 'add-to-group':
        [$group, $account] = $arguments + ['', ''];
        $groups = $services->get(GroupManager::class);
        $groups->groupExists($group) ? $groups->addMembers($group, [$account]) : $groups->create($group, [$account]);
        break;

    case 'forget-attempts':
        $db = $services->get(DbService::class);
        $q = fn (string $column): string => $db->quoteIdentifier($column);
        $table = $q($services->get(JournalSchema::class)->table());
        foreach ($arguments as $login) {
            $db->query("DELETE FROM {$table} WHERE {$q('channel')} = 'audit' AND {$q('action')} IN ('login.failed', 'login.throttled') AND LOWER({$q('target')}) = ?", [mb_strtolower(trim($login))]);
        }
        break;

    default:
        fwrite(STDERR, "usage: php sso-wiki.php journal|forget|account|groups|add-to-group|forget-attempts ...\n");
        exit(1);
}

<?php

/**
 * Turns the wiki under test back into a Doryphore wiki that signed in through one login extension, then upgrades it again
 * (spec section 13). Run from the wiki's folder, by sign-in-upgrade.spec.ts:
 *   load '<fixture as JSON>'   {tools, config, accounts, pages, groups}: see below; prints what the migrations said, as JSON
 *   account <tag>              the account's body as the spec checks it, and its revisions' hashes, as JSON (null when none)
 *   accounts                   the tags of every account, sorted, as JSON
 *   providers                  the `sso_providers` section the wiki reads, client secrets left out, and the Doryphore keys still in
 *                              the file, as JSON
 *   reset                      takes the Doryphore keys and `sso_providers` out of the configuration, the database included.
 *
 * `load` puts back what Doryphore had and the upgrade reads: the extension's folder among the tools set aside, its keys in the
 * configuration, a `users` table holding the accounts (loginsso's `loginsso_id` column included), pages calling loginsso's actions,
 * and groups. It then forgets that the five migrations concerned ran, and runs the migrations: MigrateUsersToPages, DropUsersTable,
 * LoginExtensionsBecomeProviders and LoginssoActionsBecomeLinks do what an upgrade would.
 *
 * It refuses any wiki but the one tests/e2e/reset.sh installed for the run: the configured base_url must be YESWIKI_TEST_BASE_URL,
 * and the database the test one.
 */

use YesWiki\Content\Entity\PageBody;
use YesWiki\Content\Entity\PageType;
use YesWiki\Content\Service\PageManager;
use YesWiki\Core\YesWikiLoader;
use YesWiki\Identity\Security\LegacyPasswordHash;
use YesWiki\Identity\Service\GroupManager;
use YesWiki\Identity\Service\SignIn\DoryphoreSsoConversion;
use YesWiki\Identity\Service\SignIn\ProviderRegistry;
use YesWiki\Identity\Service\UserManager;
use YesWiki\Kernel\Service\ConfigurationFileProvider;
use YesWiki\Kernel\Service\ConfigurationService;
use YesWiki\Kernel\Service\DbService;
use YesWiki\Kernel\Service\DoryphoreLeftovers;
use YesWiki\Kernel\Service\MigrationService;
use YesWiki\Kernel\Service\TripleStore;
use YesWiki\Kernel\Service\WikiSettings;

require_once dirname(__DIR__, 3) . '/vendor/autoload.php';

/** What an upgrade from Doryphore runs, and the fixture makes run again. */
const UPGRADE_MIGRATIONS = [
    '20260723000004_MigrateUsersToPages',
    '20260723000005_DropUsersTable',
    '20261111100000_LoginExtensionsBecomeProviders',
    '20261111100100_LoginssoActionsBecomeLinks',
];

/** The keys of the three extensions, which `reset` takes out. */
const DORYPHORE_KEYS = [DoryphoreSsoConversion::SSO_KEY, ...DoryphoreSsoConversion::LDAP_KEYS, ...DoryphoreSsoConversion::CAS_KEYS];

function refuse(string $why): never
{
    fwrite(STDERR, "doryphore-fixture: {$why}\n");
    exit(2);
}

$configFile = ConfigurationFileProvider::getConfigFileFromEnv();
$configService = new ConfigurationService();
$configuration = $configService->getConfiguration($configFile);
$configuration->load();

// Only the wiki installed for this run: the developer's own wiki lives in the same folder under php-fpm.
$expectedBase = (string)getenv('YESWIKI_TEST_BASE_URL');
$base = (string)($configuration['base_url'] ?? '');
if ($expectedBase === '' || $base !== $expectedBase) {
    refuse("this wiki's base_url is '{$base}', not the test wiki's YESWIKI_TEST_BASE_URL ('{$expectedBase}'): nothing was changed.");
}
$driver = (string)($configuration['db_driver'] ?? 'mysql');
$testDatabase = (string)(getenv('YESWIKI_TEST_DB_NAME') ?: 'yeswiki_test');
if ($driver !== 'sqlite' && (string)($configuration['db_database'] ?? $configuration['mysql_database'] ?? '') !== $testDatabase) {
    refuse("this wiki's database is not the test database '{$testDatabase}': nothing was changed.");
}

/** Writes the configuration, a second later than the file was if need be: the web server's opcode cache tells a new file by its time. */
function writeConfiguration(ConfigurationService $service, mixed $configuration, string $file): void
{
    $before = (int)@filemtime($file);
    if (!$service->write($configuration)) {
        refuse('the configuration file could not be written');
    }
    clearstatcache();
    if ((int)filemtime($file) <= $before) {
        touch($file, $before + 1);
    }
}

/** Forgets the `sso_providers` the database holds, where the upgrade and the administration screen save them, over the file's. */
function forgetStoredProviders(): void
{
    YesWikiLoader::getWiki(true)->services->get(WikiSettings::class)->store([], [ProviderRegistry::SECTION]);
}

function withoutDoryphore(mixed $configuration): void
{
    foreach ([...DORYPHORE_KEYS, ProviderRegistry::SECTION] as $key) {
        unset($configuration[$key]);
    }
}

$command = $argv[1] ?? '';
if ($command === 'reset') {
    withoutDoryphore($configuration);
    writeConfiguration($configService, $configuration, $configFile);
    forgetStoredProviders();
    exit(0);
}
if ($command === 'providers') {
    $stored = YesWikiLoader::getWiki(true)->services->get(WikiSettings::class)->all();
    $section = array_key_exists(ProviderRegistry::SECTION, $stored) ? $stored[ProviderRegistry::SECTION] : $configuration[ProviderRegistry::SECTION] ?? null;
    foreach (is_array($section) ? $section : [] as $id => $provider) {
        unset($section[$id]['connection']['client_secret']);
    }
    echo json_encode([
        'providers' => $section,
        'doryphore_keys' => array_values(array_filter(DORYPHORE_KEYS, fn (string $key): bool => isset($configuration[$key]))),
    ]);
    exit(0);
}

/**
 * @var array{
 *   tools: list<string>,
 *   config: array<string, mixed>,
 *   accounts: list<array<string, string>>,
 *   pages: array<string, string>,
 *   groups: array<string, list<string>>
 * } $fixture
 */
$fixture = $command === 'load' ? json_decode($argv[2] ?? '', true, 512, JSON_THROW_ON_ERROR) : null;

if ($fixture !== null) {
    // A clean slate before the services read the configuration: what an earlier spec or extension left, then Doryphore's keys.
    withoutDoryphore($configuration);
    foreach ($fixture['config'] as $key => $value) {
        $configuration[$key] = $value;
    }
    writeConfiguration($configService, $configuration, $configFile);
    forgetStoredProviders();
}

$services = YesWikiLoader::getWiki(true)->services;
$db = $services->get(DbService::class);
$users = $services->get(UserManager::class);
$pages = $services->get(PageManager::class);

/** @return array<string, mixed>|null */
function account(DbService $db, string $tag): ?array
{
    $rows = $db->loadAll(
        "SELECT body, latest FROM {$db->prefixTable('pages')} WHERE tag = ? AND {$db->quoteIdentifier('type')} = ? ORDER BY id ASC",
        [$tag, PageType::USER]
    );
    $latest = array_values(array_filter($rows, fn (array $row): bool => $row['latest'] === 'Y'))[0] ?? null;
    if ($latest === null) {
        return null;
    }
    $body = PageBody::decode((string)$latest['body']);
    $password = is_string($body['password'] ?? null) ? $body['password'] : '';
    unset($body['password']);

    return [
        'body' => $body,
        'password' => $password === '' ? 'none' : (LegacyPasswordHash::isMd5($password) ? 'md5' : 'other'),
        'revisions' => count($rows),
        'md5_revisions' => count(array_filter($rows, function (array $row): bool {
            $password = PageBody::decode((string)$row['body'])['password'] ?? null;

            return LegacyPasswordHash::isMd5(is_string($password) ? $password : null);
        })),
    ];
}

switch ($command) {
    case 'account':
        echo json_encode(account($db, (string)($argv[2] ?? '')));
        exit(0);

    case 'accounts':
        $tags = array_map(
            fn (array $row): string => (string)$row['tag'],
            $db->loadAll("SELECT tag FROM {$db->prefixTable('pages')} WHERE latest = 'Y' AND {$db->quoteIdentifier('type')} = ? ORDER BY tag ASC", [PageType::USER])
        );
        echo json_encode($tags);
        exit(0);

    case 'load':
        break;

    default:
        fwrite(STDERR, "usage: php doryphore-fixture.php load '<json>'|account <tag>|accounts|providers|reset\n");
        exit(1);
}

// The accounts of an earlier load, or of an earlier spec under the same emails, would stand in for the ones Doryphore had.
foreach ($fixture['accounts'] as $legacy) {
    foreach ([$users->getOneByName($legacy['name']), $users->getOneByEmail($legacy['email'])] as $user) {
        if ($user !== null) {
            $users->delete($user);
        }
    }
}
foreach (array_keys($fixture['pages']) as $tag) {
    $pages->deleteOrphaned($tag);
}

// Doryphore's users table, with the column loginsso added to it.
$table = $db->prefixTable('users');
$db->query("DROP TABLE IF EXISTS {$table}");
$db->query("CREATE TABLE {$table} (name VARCHAR(80) NOT NULL, password VARCHAR(255) NOT NULL, email VARCHAR(255) NOT NULL, motto TEXT, "
    . 'revisioncount INT, changescount INT, doubleclickedit CHAR(1), signuptime VARCHAR(19), show_comments CHAR(1), '
    . 'loginsso_id VARCHAR(255) DEFAULT NULL, PRIMARY KEY (name))');
foreach ($fixture['accounts'] as $legacy) {
    $row = $legacy + ['motto' => '', 'signuptime' => '2024-03-01 09:00:00', 'loginsso_id' => null];
    $db->query(
        "INSERT INTO {$table} (name, password, email, motto, revisioncount, changescount, doubleclickedit, signuptime, show_comments, loginsso_id) VALUES (?, ?, ?, ?, 20, 50, 'Y', ?, 'N', ?)",
        [$row['name'], $row['password'], $row['email'], $row['motto'], $row['signuptime'], $row['loginsso_id']]
    );
}

// Pages as Doryphore's markup migration left them, still calling loginsso's actions.
foreach ($fixture['pages'] as $tag => $content) {
    $pages->save($tag, [PageBody::CONTENT => $content], '', true);
}

// The extension's folder, among the tools the move to Ectoplasme set aside.
$aside = YESWIKI_INSTANCE_DIR . '/' . DoryphoreLeftovers::ASIDE;
$hadAside = is_dir($aside);
$made = [];
foreach ($fixture['tools'] as $tool) {
    $folder = "{$aside}/tools/{$tool}";
    if (!is_dir($folder)) {
        mkdir($folder, 0755, true);
        $made[] = $folder;
    }
}

try {
    $triples = $services->get(TripleStore::class);
    foreach (UPGRADE_MIGRATIONS as $migration) {
        $triples->delete($migration, TripleStore::TYPE_URI, MigrationService::TRIPLES_MIGRATION_ID, '', '');
    }
    $messages = $services->get(MigrationService::class)->run();
} finally {
    // under php-fpm the wiki under test is the developer's checkout: leave no Doryphore folder behind
    foreach ($made as $folder) {
        rmdir($folder);
    }
    if (!$hadAside) {
        exec('rm -rf ' . escapeshellarg($aside));
    }
}

// Groups made by hand on Doryphore, once their members are accounts again.
$groups = $services->get(GroupManager::class);
foreach ($fixture['groups'] as $group => $members) {
    if ($groups->groupExists($group)) {
        $groups->delete($group);
    }
    $groups->create($group, $members);
}

echo json_encode(array_map(fn (array $message): string => "{$message['status']}: {$message['text']}", $messages->toArray()));
exit($messages->hasError() ? 3 : 0);

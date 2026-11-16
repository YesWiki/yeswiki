<?php

use YesWiki\Content\Entity\PageBody;
use YesWiki\Content\Entity\PageType;
use YesWiki\Core\YesWikiMigration;
use YesWiki\Identity\Security\LegacyPasswordHash;
use YesWiki\Identity\Service\SignIn\AccountLinks;
use YesWiki\Identity\Service\SignIn\DoryphoreSsoConversion;
use YesWiki\Identity\Service\SignIn\ProviderRegistry;
use YesWiki\Identity\SignIn\SsoConversionResult;
use YesWiki\Kernel\Entity\ConfigurationFile;
use YesWiki\Kernel\Service\ConfigurationFileProvider;
use YesWiki\Kernel\Service\ConfigurationService;
use YesWiki\Kernel\Service\DoryphoreLeftovers;

/**
 * Doryphore's loginsso, loginldap and logincas settings become `sso_providers`, and the accounts follow (spec section 11):
 * a lone loginsso provider turns `loginsso_id` into the account's link (unless it is only the email loginsso copied there when it was
 * installed), and a wiki LDAP signed in loses its md5 hashes, every revision included.
 * Bodies are rewritten in place, so no revision is added. The accounts and the configuration are one unit: the account rewrites
 * run in a transaction that commits only once the configuration is written, so a failure leaves the old keys for the next run to convert.
 * On a wiki running on its own `yeswiki.config.php`, the configuration is written to the database (replicas 04), and the old keys --
 * `sso_config` holds client secrets -- are then taken out of the file too, the one write to it the running wiki makes.
 * Neither the hashes nor `loginsso_id` nor the link keys reach the search index (the User form's password field contributes nothing), so no page is reindexed.
 */
class LoginExtensionsBecomeProviders extends YesWikiMigration
{
    private const LEGACY_SUBJECT = 'loginsso_id';

    public function run()
    {
        $this->convertWiki(YESWIKI_INSTANCE_DIR, ConfigurationFileProvider::getConfigFileFromEnv(), new DateTimeImmutable());
    }

    /**
     * @param ?list<string> $onlyAccounts the account tags to walk, every account when null: the tests walk only the ones they made
     *
     * @throws RuntimeException when the configuration cannot be written, after the accounts were rolled back, so the migration runs again
     */
    public function convertWiki(string $instanceDir, string $configFile, DateTimeImmutable $now, ?array $onlyAccounts = null): void
    {
        $service = $this->getService(ConfigurationService::class);
        $configuration = $service->getConfiguration($configFile);
        $configuration->load();
        $values = $configuration->__get('_parameters');

        $result = $this->getService(DoryphoreSsoConversion::class)->convert($values, DoryphoreLeftovers::toolsHad($instanceDir), $now);
        if (!$result->changesTheConfiguration()) {
            return;
        }

        foreach ($result->removedKeys as $key) {
            unset($configuration[$key]);
        }
        $configuration[ProviderRegistry::SECTION] = $result->providers;

        $counts = $this->dbService->transactional(function () use ($result, $onlyAccounts, $service, $configuration, $configFile): array {
            $counts = $this->convertAccounts($result, $onlyAccounts);
            if (!$this->writeConfiguration($service, $configuration)) {
                throw new RuntimeException("the login extensions' settings could not be converted, since {$configFile} could not be written: nothing was changed, and the migration runs again once it can be.");
            }

            return $counts;
        });

        foreach ($result->messages as $message) {
            $this->say($message);
        }
        $this->sayAccounts($result, $counts);

        $left = $this->keysLeftInTheFile($configFile, $result->removedKeys);
        if ($left !== [] && !$this->removeFromTheFile($configFile, $left)) {
            $this->say('the converted settings are in the database, but ' . basename($configFile) . ' could not be written: delete '
                . implode(', ', $left) . ' from it by hand' . (in_array('sso_config', $left, true) ? ', since sso_config holds client secrets' : '') . '.');
        }
    }

    /**
     * The converted keys the file still holds, when the configuration went to the database rather than to the file.
     *
     * @param list<string> $keys
     *
     * @return list<string>
     */
    private function keysLeftInTheFile(string $file, array $keys): array
    {
        $onDisk = new ConfigurationFile($file);
        $onDisk->load();

        return array_values(array_intersect($keys, array_keys($onDisk->__get('_parameters'))));
    }

    /**
     * Takes those keys out of the file as it is on disk, whatever the database says over it.
     *
     * @param list<string> $keys
     */
    protected function removeFromTheFile(string $file, array $keys): bool
    {
        $onDisk = new ConfigurationFile($file);
        $onDisk->load();
        foreach ($keys as $key) {
            unset($onDisk[$key]);
        }

        // built by hand, a ConfigurationService writes the file itself rather than the database
        return (new ConfigurationService())->write($onDisk, $file);
    }

    /** Written last, inside the accounts' transaction: a write that fails rolls them back. */
    protected function writeConfiguration(ConfigurationService $service, ConfigurationFile $configuration): bool
    {
        return $service->write($configuration);
    }

    /**
     * One account revision rewritten in place, inside the transaction.
     *
     * @param array<array-key, mixed> $body
     */
    protected function rewriteBody(string $id, array $body): void
    {
        $this->dbService->query("UPDATE {$this->dbService->prefixTable('pages')} SET body = ? WHERE id = ?", [PageBody::encode($body), $id]);
    }

    /**
     * @param ?list<string> $onlyAccounts
     *
     * @return array{linked: int, unlinked: int, cleared: int, copied: int}
     */
    private function convertAccounts(SsoConversionResult $result, ?array $onlyAccounts): array
    {
        $counts = ['linked' => 0, 'unlinked' => 0, 'cleared' => 0, 'copied' => 0];
        $linkTo = $this->linkTarget($result);
        $subjectIsTheEmail = $linkTo !== null && $this->identifiesByEmail($result->providers[$linkTo]);
        // who signed people in on Doryphore, not who is on now: the hashes may be the directory's unsalted passwords either way
        $clearMd5 = $result->doryphoreInCharge === DoryphoreSsoConversion::LOGINLDAP;
        if (($result->loginssoIds === [] && !$clearMd5) || $onlyAccounts === []) {
            return $counts;
        }

        $db = $this->dbService;
        $query = "SELECT id, tag, latest, body FROM {$db->prefixTable('pages')} WHERE {$db->quoteIdentifier('type')} = ?";
        $params = [PageType::USER];
        if ($onlyAccounts !== null) {
            $query .= ' AND tag IN (' . implode(', ', array_fill(0, count($onlyAccounts), '?')) . ')';
            $params = array_merge($params, $onlyAccounts);
        }

        $linked = [];
        $cleared = [];
        foreach ($db->loadAll($query . ' ORDER BY id ASC', $params) as $row) {
            $body = PageBody::decode((string)$row['body']);
            $changed = false;
            if ($clearMd5 && LegacyPasswordHash::isMd5(is_string($body['password'] ?? null) ? $body['password'] : null)) {
                $body['password'] = '';
                $cleared[(string)$row['tag']] = true;
                $changed = true;
            }
            if ($row['latest'] === 'Y' && array_key_exists(self::LEGACY_SUBJECT, $body)) {
                $subject = is_scalar($body[self::LEGACY_SUBJECT]) ? trim((string)$body[self::LEGACY_SUBJECT]) : '';
                unset($body[self::LEGACY_SUBJECT]);
                $changed = true;
                $alreadyLinked = is_string($body[AccountLinks::PROVIDER_KEY] ?? null) && $body[AccountLinks::PROVIDER_KEY] !== '';
                $email = is_string($body['email'] ?? null) ? trim($body['email']) : '';
                if ($subject !== '' && !$alreadyLinked && !$subjectIsTheEmail && strcasecmp($subject, $email) === 0) {
                    // loginsso's own migration copied every existing account's email here: nobody at the provider has that subject
                    $counts['copied']++;
                } elseif ($linkTo !== null && $subject !== '' && !$alreadyLinked && !isset($linked[$subject])) {
                    $body[AccountLinks::PROVIDER_KEY] = $linkTo;
                    $body[AccountLinks::SUBJECT_KEY] = $subject;
                    // as AccountLinks::link(): a linked account signs in through its provider only
                    $body['password'] = '';
                    $linked[$subject] = true;
                } elseif ($subject !== '' && !$alreadyLinked) {
                    $counts['unlinked']++;
                }
            }
            if ($changed) {
                $this->rewriteBody((string)$row['id'], $body);
            }
        }
        $counts['linked'] = count($linked);
        $counts['cleared'] = count($cleared);

        return $counts;
    }

    /**
     * Whether the provider's user ID is the email itself, as with a loginsso `id_sso_field` naming the email's field: an ID equal to
     * the account's email is then a real subject, and not the copy loginsso made when it was installed.
     *
     * @param array<string, mixed> $provider
     */
    private function identifiesByEmail(array $provider): bool
    {
        $attributes = is_array($provider['attributes'] ?? null) ? $provider['attributes'] : [];

        $id = $attributes['id'] ?? null;

        return $id !== null && $id === ($attributes['email'] ?? null);
    }

    /** The lone loginsso provider, when it is the one switched on: a link to a provider that is off would lock its account out. */
    private function linkTarget(SsoConversionResult $result): ?string
    {
        return count($result->loginssoIds) === 1 && ($result->providers[$result->loginssoIds[0]]['enabled'] ?? false) === true
            ? $result->loginssoIds[0]
            : null;
    }

    /** @param array{linked: int, unlinked: int, cleared: int, copied: int} $counts */
    private function sayAccounts(SsoConversionResult $result, array $counts): void
    {
        if ($counts['copied'] > 0) {
            $this->say("the user ID loginsso had stored for {$counts['copied']} account(s) was only their email, which loginsso copied into every existing account when it was installed: it was removed, and they keep their password. Match by email links them at their next sign-in through the provider.");
        }
        if ($counts['linked'] > 0) {
            $this->say("{$counts['linked']} account(s) linked to the provider {$this->linkTarget($result)} through the user ID loginsso had stored.");
        }
        if ($counts['unlinked'] > 0) {
            $this->say(count($result->loginssoIds) > 1
                ? "loginsso had several providers and did not record which one each account came from, so the stored user ID of {$counts['unlinked']} account(s) was removed: match by email ties them to their provider at their next sign-in."
                : "the stored loginsso user ID of {$counts['unlinked']} account(s) was removed, since loginsso's provider is off: match by email ties them to it once it is switched on.");
        }
        if ($counts['cleared'] > 0) {
            $this->say("{$counts['cleared']} account(s) lost their md5 password hash, every revision included, because LDAP signed people in on Doryphore and these may be the directory's own passwords: "
                . ($result->inCharge === DoryphoreSsoConversion::LOGINLDAP ? 'they sign in through LDAP, which links them.' : 'they sign in through their provider, or choose a new password if local accounts are on.'));
        }
    }
}

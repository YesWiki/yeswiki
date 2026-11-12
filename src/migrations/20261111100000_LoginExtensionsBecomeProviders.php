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
 * a lone loginsso provider turns `loginsso_id` into the account's link, and a wiki LDAP signed in loses its md5 hashes, every revision included.
 * Bodies are rewritten in place, so no revision is added. The accounts and the configuration are one unit: the account rewrites
 * run in a transaction that commits only once the configuration is written, so a failure leaves the old keys for the next run to convert.
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

        $result = $this->getService(DoryphoreSsoConversion::class)->convert($values, DoryphoreLeftovers::toolsHad($instanceDir), $this->baseUrl($values), $now);
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
     * @return array{linked: int, unlinked: int, cleared: int}
     */
    private function convertAccounts(SsoConversionResult $result, ?array $onlyAccounts): array
    {
        $counts = ['linked' => 0, 'unlinked' => 0, 'cleared' => 0];
        $linkTo = $this->linkTarget($result);
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
                if ($linkTo !== null && $subject !== '' && !$alreadyLinked && !isset($linked[$subject])) {
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

    /** The lone loginsso provider, when it is the one switched on: a link to a provider that is off would lock its account out. */
    private function linkTarget(SsoConversionResult $result): ?string
    {
        return count($result->loginssoIds) === 1 && ($result->providers[$result->loginssoIds[0]]['enabled'] ?? false) === true
            ? $result->loginssoIds[0]
            : null;
    }

    /** @param array{linked: int, unlinked: int, cleared: int} $counts */
    private function sayAccounts(SsoConversionResult $result, array $counts): void
    {
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

    /** @param array<array-key, mixed> $values */
    private function baseUrl(array $values): string
    {
        if (is_string($values['base_url'] ?? null) && $values['base_url'] !== '') {
            return $values['base_url'];
        }
        $configured = isset($this->params) && $this->params->has('base_url') ? $this->params->get('base_url') : '';

        return is_string($configured) ? $configured : '';
    }
}

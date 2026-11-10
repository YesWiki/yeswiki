<?php

use YesWiki\Content\Entity\PageBody;
use YesWiki\Content\Entity\PageType;
use YesWiki\Core\YesWikiMigration;
use YesWiki\Identity\Security\LegacyPasswordHash;
use YesWiki\Identity\Service\SignIn\AccountLinks;
use YesWiki\Identity\Service\SignIn\DoryphoreSsoConversion;
use YesWiki\Identity\Service\SignIn\ProviderRegistry;
use YesWiki\Identity\SignIn\SsoConversionResult;
use YesWiki\Kernel\Service\ConfigurationFileProvider;
use YesWiki\Kernel\Service\ConfigurationService;
use YesWiki\Kernel\Service\DoryphoreLeftovers;

/**
 * Doryphore's loginsso, loginldap and logincas settings become `sso_providers`, and the accounts follow (spec section 11):
 * a lone loginsso provider turns `loginsso_id` into the account's link, and a wiki LDAP signed in loses its md5 hashes, every revision included.
 * Bodies are rewritten in place, so no revision is added; running it again finds nothing left to convert.
 */
class LoginExtensionsBecomeProviders extends YesWikiMigration
{
    private const LEGACY_SUBJECT = 'loginsso_id';

    public function run()
    {
        $this->convertWiki(YESWIKI_INSTANCE_DIR, ConfigurationFileProvider::getConfigFileFromEnv(), new DateTimeImmutable());
    }

    /** @param ?list<string> $onlyAccounts the account tags to walk, every account when null: the tests walk only the ones they made */
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
        if (!$service->write($configuration)) {
            $this->say("the login extensions' settings could not be converted, since {$configFile} could not be written: nothing was changed, and sign-ins use local accounts until sso_providers is written by hand.");

            return;
        }
        foreach ($result->messages as $message) {
            $this->say($message);
        }

        $this->convertAccounts($result, $onlyAccounts);
    }

    /** @param ?list<string> $onlyAccounts */
    private function convertAccounts(SsoConversionResult $result, ?array $onlyAccounts): void
    {
        $linkTo = count($result->loginssoIds) === 1 && ($result->providers[$result->loginssoIds[0]]['enabled'] ?? false) === true
            ? $result->loginssoIds[0]
            : null;
        $clearMd5 = $result->inCharge === DoryphoreSsoConversion::LOGINLDAP;
        if ($result->loginssoIds === [] && !$clearMd5) {
            return;
        }

        $db = $this->dbService;
        $pages = $db->prefixTable('pages');
        $query = "SELECT id, tag, latest, body FROM {$pages} WHERE {$db->quoteIdentifier('type')} = ?";
        $params = [PageType::USER];
        if ($onlyAccounts !== null) {
            if ($onlyAccounts === []) {
                return;
            }
            $query .= ' AND tag IN (' . implode(', ', array_fill(0, count($onlyAccounts), '?')) . ')';
            $params = array_merge($params, $onlyAccounts);
        }
        $rows = $db->loadAll($query . ' ORDER BY id ASC', $params);

        $linked = [];
        $unlinked = 0;
        $cleared = [];
        $db->transactional(function () use ($db, $pages, $rows, $linkTo, $clearMd5, &$linked, &$unlinked, &$cleared): void {
            foreach ($rows as $row) {
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
                        $unlinked++;
                    }
                }
                if ($changed) {
                    $db->query("UPDATE {$pages} SET body = ? WHERE id = ?", [PageBody::encode($body), (string)$row['id']]);
                }
            }
        });

        if ($linked !== []) {
            $this->say(count($linked) . " account(s) linked to the provider {$linkTo} through the user ID loginsso had stored.");
        }
        if ($unlinked > 0) {
            $this->say($linkTo === null && count($result->loginssoIds) > 1
                ? "loginsso had several providers and did not record which one each account came from, so the stored user ID of {$unlinked} account(s) was removed: match by email ties them to their provider at their next sign-in."
                : "the stored loginsso user ID of {$unlinked} account(s) was removed, since loginsso's provider is off: match by email ties them to it once it is switched on.");
        }
        if ($cleared !== []) {
            $this->say(count($cleared) . " account(s) lost their md5 password hash, every revision included, because LDAP signed people in and these may be the directory's own passwords: they sign in through LDAP, which links them.");
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

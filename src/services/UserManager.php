<?php

namespace YesWiki\Core\Service;

use Exception;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use YesWiki\Bazar\Service\FormManager;
use YesWiki\Bazar\Service\SearchManager;
use YesWiki\Core\Controller\AuthController;
use YesWiki\Core\Controller\GroupController;
use YesWiki\Core\Entity\User;
use YesWiki\Core\Exception\DeleteUserException;
use YesWiki\Core\Exception\GroupNameDoesNotExistException;
use YesWiki\Core\Exception\UserEmailAlreadyUsedException;
use YesWiki\Core\Exception\UserNameAlreadyUsedException;
use YesWiki\Security\Controller\SecurityController;
use YesWiki\Wiki;

if (!function_exists('send_mail')) {
    require_once YESWIKI_SOURCE_DIR . '/src/email.inc.php';
}

class UserManager implements UserProviderInterface, PasswordUpgraderInterface
{
    protected $wiki;
    protected $dbService;
    protected $passwordHasherFactory;
    protected $securityController;
    protected $params;
    protected $tripleStore;

    private array $associatedEntryCache = [];
    private array $userTagCache = [];

    // Users are `pages` rows typed via this triple, the same convention FormManager uses
    // for forms (TRIPLES_FORM_TYPE) and EntryManager for bazar entries (TRIPLES_ENTRY_ID).
    public const TRIPLES_USER_TYPE = 'user';

    public const KEY_VOCABULARY = 'http://outils-reseaux.org/_vocabulary/key';
    // stored triple value is "$hashedKey$KEY_VALUE_SEPARATOR$issuedAtTimestamp"
    public const KEY_VALUE_SEPARATOR = ':';
    // password recovery links expire after 1 hour
    public const KEY_TTL = 3600;

    // PageManager and AclService are deliberately NOT constructor-injected here: PageManager
    // already depends on UserManager directly, and AclService depends on UserManager too
    // (its '+' registered-users ACL case calls getOneByName()) -- constructor-injecting
    // either back into UserManager would be a circular dependency. Fetched late via
    // $this->wiki->services->get() instead, the same workaround isInGroup() already uses
    // for AclService below.
    public function __construct(
        Wiki $wiki,
        DbService $dbService,
        ParameterBagInterface $params,
        PasswordHasherFactory $passwordHasherFactory,
        SecurityController $securityController,
        TripleStore $tripleStore
    ) {
        $this->wiki = $wiki;
        $this->dbService = $dbService;
        $this->passwordHasherFactory = $passwordHasherFactory;
        $this->securityController = $securityController;
        $this->params = $params;
        $this->tripleStore = $tripleStore;
    }

    public function isUserTag(string $tag): bool
    {
        if (empty($tag)) {
            return false;
        }
        if (!isset($this->userTagCache[$tag])) {
            $this->userTagCache[$tag] = !is_null($this->tripleStore->exist($tag, TripleStore::TYPE_URI, self::TRIPLES_USER_TYPE, '', ''));
        }

        return $this->userTagCache[$tag];
    }

    public function getAllUserTags(): array
    {
        return array_values(array_filter(array_map(function ($triple) {
            return $triple['resource'] ?? null;
        }, $this->tripleStore->getMatching(null, TripleStore::TYPE_URI, self::TRIPLES_USER_TYPE))));
    }

    public function userExist($name): bool
    {
        return !empty($this->getOneByName($name));
    }

    public function getOneByName($name, $password = null): ?User
    {
        // use !is_string($password) instead of !$password to allow $password == ""
        if (!$this->isUserTag($name)) {
            return null;
        }

        $page = $this->wiki->services->get(PageManager::class)->getOne($name, null, true, true);
        $user = $this->arrayToUser($page);
        if ($user !== null && is_string($password) && $user['password'] !== $password) {
            return null;
        }

        return $user;
    }

    public function getOneByEmail($mail, $password = null): ?User
    {
        $tag = $this->resolveTagFromEmail($mail);
        if ($tag === null) {
            return null;
        }

        return $this->getOneByName($tag, $password);
    }

    private function resolveTagFromEmail(string $email): ?string
    {
        $jsonExtract = $this->dbService->jsonExtract('p.body', '$.email');
        $sql = "SELECT p.tag AS tag FROM {$this->dbService->prefixTable('pages')} p
            WHERE p.latest = 'Y' AND {$jsonExtract} = '" . $this->dbService->escape($email) . "'
            AND EXISTS (
                SELECT 1 FROM {$this->dbService->prefixTable('triples')} t
                WHERE t.resource = p.tag
                    AND t.property = '" . $this->dbService->escape(TripleStore::TYPE_URI) . "'
                    AND t.value = '" . $this->dbService->escape(self::TRIPLES_USER_TYPE) . "'
            )
            LIMIT 1";
        $row = $this->dbService->loadSingle($sql);

        return $row['tag'] ?? null;
    }

    public function getAll($dbFields = ['name', 'password', 'email', 'motto', 'revisioncount', 'changescount', 'doubleclickedit', 'signuptime', 'show_comments']): array
    {
        // $dbFields was a partial-column-select optimization for the old flat `users`
        // table; kept for signature compatibility but not honored -- every User object
        // requires the full PROPS_LIST anyway (see User::__construct()), and decoding a
        // JSON body doesn't offer a meaningful partial-fetch optimization to replace it with.
        $pageManager = $this->wiki->services->get(PageManager::class);
        $users = [];
        foreach ($this->getAllUserTags() as $tag) {
            $user = $this->arrayToUser($pageManager->getOne($tag, null, true, true));
            if ($user !== null) {
                $users[] = $user;
            }
        }

        usort($users, function ($a, $b) {
            return strcmp($a['name'], $b['name']);
        });

        return $users;
    }

    /**
     * @param array|string $wikiNameOrUser array to create the wiki or wikiname
     * @param string email (optionnal if parameters by array)
     * @param string plainPassword (optionnal if parameters by array)
     *
     * @throws UserNameAlreadyUsedException|UserEmailAlreadyUsedException|\Exception
     */
    public function create($wikiNameOrUser, string $email = '', string $plainPassword = ''): ?User
    {
        if ($this->securityController->isWikiHibernated()) {
            throw new \Exception(_t('WIKI_IN_HIBERNATION'));
        }

        if (is_array($wikiNameOrUser)) {
            $userAsArray = array_merge($wikiNameOrUser, [
                'changescount' => '',
                'doubleclickedit' => '',
                'motto' => '',
                'revisioncount' => '',
                'show_comments' => '',
                'signuptime' => '',
            ]);
            $wikiName = $userAsArray['name'] ?? '';
            $wikiName = trim($wikiName);
            $userAsArray['name'] = $wikiName;
            $email = $userAsArray['email'] ?? '';
            $plainPassword = $userAsArray['password'] ?? '';
        } elseif (is_string($wikiNameOrUser)) {
            $wikiName = trim($wikiNameOrUser);
            $userAsArray = [
                'changescount' => '',
                'doubleclickedit' => '',
                'email' => $email,
                'motto' => '',
                'name' => $wikiName,
                'password' => '',
                'revisioncount' => '',
                'show_comments' => '',
                'signuptime' => '',
            ];
        } else {
            throw new \Exception('First parameter of UserManager->create should be string or array!');
        }

        if (empty($wikiName)) {
            throw new \Exception("'Name' parameter of UserManager->create should not be empty!");
        }
        // an EXISTING USER with this exact name -- normal "username taken" signup UX,
        // unchanged. Distinct from a same-named FORM/PAGE/ENTRY, handled below via
        // suggestFreeTag() instead of failing (ticket 04/05's collision-avoidance helper,
        // now genuinely needed since users share the same tag namespace as everything else)
        if (!empty($this->getOneByName($wikiName))) {
            throw new UserNameAlreadyUsedException();
        }
        if (empty($email)) {
            throw new \Exception("'email' parameter of UserManager->create should not be empty!");
        }
        if (!empty($this->getOneByEmail($email))) {
            throw new UserEmailAlreadyUsedException();
        }
        if (empty($plainPassword)) {
            throw new \Exception("'password' parameter of UserManager->create should not be empty!");
        }

        $tag = $this->wiki->services->get(PageManager::class)->suggestFreeTag($wikiName);

        $hasher = $this->passwordHasherFactory->getPasswordHasher($this->arrayToDraftUser($userAsArray));
        $hashedPassword = $hasher->hash($plainPassword);

        $body = $this->buildBody(array_merge($userAsArray, [
            'password' => $hashedPassword,
            'signuptime' => date('Y-m-d H:i:s'),
        ]));

        if (!$this->persistNewUserPage($tag, $body)) {
            return null;
        }

        return $this->getOneByName($tag);
    }

    /**
     * Migrates one row from the legacy `users` table, preserving its password hash
     * VERBATIM -- unlike create(), which always hashes a fresh plaintext password and so
     * must never be reused for migrating an already-hashed existing account (that would
     * silently invalidate every existing user's password).
     */
    public function migrateLegacyUser(array $legacyRow): void
    {
        $name = trim((string)($legacyRow['name'] ?? ''));
        if ($name === '' || $this->getOneByName($name)) {
            return;
        }

        $tag = $this->wiki->services->get(PageManager::class)->suggestFreeTag($name);
        $body = $this->buildBody($legacyRow);

        $this->persistNewUserPage($tag, $body);
    }

    private function persistNewUserPage(string $tag, array $body): bool
    {
        $pageManager = $this->wiki->services->get(PageManager::class);
        $saved = $pageManager->save($tag, $this->encodeBody($body), '', true);
        if ($saved !== 0) {
            return false;
        }

        // the TYPE_URI triple must be created BEFORE setOwner(): setOwner() calls
        // UserManager::getOneByName() internally to verify the target exists, and
        // getOneByName() only recognizes a tag as a user once this triple is in place --
        // otherwise setOwner() silently no-ops (found via the migration leaving every
        // migrated user's `owner` column empty). isUserTag()'s cache must ALSO be updated
        // explicitly here: migrateLegacyUser()/create() already called isUserTag($tag)
        // (via getOneByName()'s collision check) before this tag existed, caching a
        // negative result that creating the triple alone doesn't invalidate.
        $this->tripleStore->create($tag, TripleStore::TYPE_URI, self::TRIPLES_USER_TYPE, '', '');
        $this->userTagCache[$tag] = true;

        // the account owns itself -- save()'s auto-owner logic would otherwise leave this
        // empty for a self-registering, not-yet-logged-in user
        $pageManager->setOwner($tag, $tag);

        // the wiki's default_write_acl is '*' (everyone) -- without this override, anyone
        // could edit any other user's account page. Late-bound: see the constructor note
        // on why AclService can't be injected directly.
        $this->wiki->services->get(AclService::class)->save($tag, 'write', "%\n@admins");

        return true;
    }

    private function buildBody(array $fields): array
    {
        return [
            'email' => $fields['email'] ?? '',
            'motto' => empty($fields['motto']) ? '' : $fields['motto'],
            'revisioncount' => empty($fields['revisioncount']) ? '20' : $fields['revisioncount'],
            'changescount' => empty($fields['changescount']) ? '50' : $fields['changescount'],
            'doubleclickedit' => empty($fields['doubleclickedit']) ? 'Y' : $fields['doubleclickedit'],
            'signuptime' => $fields['signuptime'] ?? date('Y-m-d H:i:s'),
            'show_comments' => empty($fields['show_comments']) ? 'N' : $fields['show_comments'],
            'password' => $fields['password'] ?? '',
        ];
    }

    private function encodeBody(array $body): string
    {
        return json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /** Part of the Password recovery process: Handles the password recovery email process.
     *
     * Generates the password recovery key
     * Stores the (name, vocabulary, key) triple in triples table
     * Generates the recovery email
     * Sends it
     *
     * @return string The link sent to the user
     */
    public function sendPasswordRecoveryEmail(User $user)
    {
        // Generate the password recovery key
        $passwordHasher = $this->passwordHasherFactory->getPasswordHasher($user);
        $plainKey = $user['name'] . '_' . $user['email'] . random_bytes(16) . date('Y-m-d H:i:s');
        $hashedKey = $passwordHasher->hash($plainKey);
        // Erase the previous triples in the trible table
        $this->tripleStore->delete($user['name'], self::KEY_VOCABULARY, null, '', '');
        // Store the (name, vocabulary, key:issuedAt) triple in triples table
        $this->tripleStore->create($user['name'], self::KEY_VOCABULARY, $hashedKey . self::KEY_VALUE_SEPARATOR . time(), '', '');

        // Generate the recovery link
        $link = $this->wiki->Href('', 'MotDePassePerdu', [
            'a' => 'recover',
            'email' => $hashedKey,
            'u' => base64_encode($user['name']),
        ], false);

        // Send the email
        if (!boolval($this->wiki->config['contact_disable_email_for_password'])) {
            $pieces = parse_url($this->params->get('base_url'));
            $domain = isset($pieces['host']) ? $pieces['host'] : '';

            $message = _t('LOGIN_DEAR') . ' ' . $user['name'] . ",\n";
            $message .= _t('LOGIN_CLICK_FOLLOWING_LINK') . ' :' . "\n";
            $message .= '-----------------------' . "\n";
            $message .= $link . "\n";
            $message .= '-----------------------' . "\n";
            $message .= _t('LOGIN_THE_TEAM') . ' ' . $domain . "\n";

            $subject = _t('LOGIN_PASSWORD_LOST_FOR') . ' ' . $domain;

            send_mail($this->params->get('BAZ_ADRESSE_MAIL_ADMIN'), $this->params->get('BAZ_ADRESSE_MAIL_ADMIN'), $user['email'], $subject, $message);
        }

        return $link;
    }

    /**
     * Part of the Password recovery process: Deletes expired password recovery keys from the triples table.
     * Called periodically from YesWiki::Maintenance().
     */
    public function purgeExpiredPasswordRecoveryKeys(): void
    {
        foreach ($this->tripleStore->getMatching(null, self::KEY_VOCABULARY, null) as $triple) {
            $parts = explode(self::KEY_VALUE_SEPARATOR, $triple['value']);
            $issuedAt = count($parts) === 2 ? (int) $parts[1] : 0;
            if (time() - $issuedAt > self::KEY_TTL) {
                $this->tripleStore->delete($triple['resource'], self::KEY_VOCABULARY, $triple['value'], '', '');
            }
        }
    }

    /**
     * update user params
     * for e-mail check is existing e-mail.
     *
     * @param array $newValues (associative array)
     *
     * @throws \Exception
     * @throws UserEmailAlreadyUsedException
     */
    public function update(User $user, array $newValues): bool
    {
        if ($this->securityController->isWikiHibernated()) {
            throw new \Exception(_t('WIKI_IN_HIBERNATION'));
        }
        $newKeys = array_keys($newValues);
        $authorizedKeys = array_filter($newKeys, function ($key) {
            return in_array($key, [
                'changescount',
                'doubleclickedit',
                'email',
                'motto',
                // 'name', // name not currently updateable
                // 'password', // password not updateable by this method
                'revisioncount',
                'show_comments',
            ]);
        });
        if (isset($newValues['email'])) {
            if (empty($newValues['email'])) {
                throw new \Exception("\$newValues['email'] parameter of UserManager->update should not be empty!");
            } elseif ($user['email'] == $newValues['email']) {
                $authorizedKeys = array_filter($authorizedKeys, function ($item) {
                    return $item != 'email';
                });
            } elseif (!empty($this->getOneByEmail($newValues['email']))) {
                throw new UserEmailAlreadyUsedException();
            }
        }

        if (count($authorizedKeys) > 0) {
            $pageManager = $this->wiki->services->get(PageManager::class);
            $page = $pageManager->getOne($user['name'], null, true, true);
            if ($page) {
                $body = json_decode($page['body'] ?? '', true) ?? [];
                foreach ($authorizedKeys as $key) {
                    $body[$key] = $newValues[$key];
                }
                $pageManager->save($user['name'], $this->encodeBody($body), '', true);
            }
        }

        return true;
    }

    /**
     * delete a user
     * SHOULD NOT BE USE DIRECTLY => use UserController->delete().
     *
     * @throws DeleteUserException
     */
    public function delete(User $user)
    {
        if ($this->securityController->isWikiHibernated()) {
            throw new \Exception(_t('WIKI_IN_HIBERNATION'));
        }
        try {
            $this->wiki->services->get(PageManager::class)->deleteOrphaned($user['name']);
        } catch (\Exception $ex) {
            throw new DeleteUserException(_t('USER_DELETE_QUERY_FAILED') . '.');
        }
    }

    /** Lists the groups $this user is member of.
     *
     * @return string[] An array of group names
     */
    public function groupsWhereIsMember(User $user, bool $adminCheck = true)
    {
        $group_list = $this->tripleStore->getMatching(GROUP_PREFIX . '%', null, '%' . $user['name'] . '%', 'LIKE', '=', 'LIKE');
        $prefix_len = strlen(GROUP_PREFIX);
        $list = [];
        foreach ($group_list as $group) {
            $list[] = substr($group['resource'], $prefix_len);
        }

        return $list;
    }

    /** Tells if a user is member of the specified group.
     *
     * @param string      $groupName    The name of the group for which we are testing membership
     * @param string|null $username     if null check current user
     * @param array       $formerGroups former groups list to avoid loops
     *
     * @return bool True if the $user is member of $groupName, false otherwise
     */
    public function isInGroup(string $groupName, ?string $username = null, bool $admincheck = true, array $formerGroups = [])
    {
        // aclService could  not be loaded in __construct because AclService already loads UserManager
        try {
            $members = $this->wiki->services->get(GroupController::class)->getMembers($groupName);
        } catch (GroupNameDoesNotExistException $th) {
            $members = [];
        }

        return $this->wiki->services->get(AclService::class)->check(implode("\n", $members), $username, $admincheck, '', '', $formerGroups);
    }

    /**
     * get the entry that is linked to the username.
     */
    public function getAssociatedEntry($user = '')
    {
        if (empty($user)) {
            $user = $this->wiki->services->get(AuthController::class)->getLoggedUser();
            if (empty($user['name'])) {
                return null;
            }
            $user = $user['name'];
        }
        if (array_key_exists($user, $this->associatedEntryCache)) {
            return $this->associatedEntryCache[$user];
        }
        $vFormManager = $this->wiki->services->get(FormManager::class);
        $vSearchManager = $this->wiki->services->get(SearchManager::class);
        $formsIds = array_keys($vFormManager->getAll());
        // in case if a username is generated from a bazar entry, nomwiki should be the right id
        $entry = $vSearchManager->search([
            'queries' => [
                [
                    'name' => 'nomwiki',
                    'operator' => '==',
                    'values' => [$user],
                ],
            ],
            'formsIds' => $formsIds,
        ]);
        $found = null;
        if (!empty($entry)) {
            $candidate = array_pop($entry);
            if (!empty($candidate['id_fiche'])) {
                $found = $candidate;
            }
        }
        $this->associatedEntryCache[$user] = $found;

        return $found;
    }

    /* ~~~~~~~~~~~~~~~~~~ implements  PasswordUpgraderInterface ~~~~~~~~~~~~~~~~~~ */

    /**
     * Upgrades the hashed password of a user, typically for using a better hash algorithm.
     * This method should persist the new password in the user storage and update the $user object accordingly.
     * Because you don't want your users not being able to log in, this method should be opportunistic:
     * it's fine if it does nothing or if it fails without throwing any exception.
     *
     * @throws UnsupportedUserException if the user is not supported
     * @throws \Exception               if wiki is in hibernation
     */
    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        if ($this->securityController->isWikiHibernated()) {
            throw new \Exception(_t('WIKI_IN_HIBERNATION'));
        }
        if (!$this->supportsClass(get_class($user))) {
            throw new UnsupportedUserException();
        }
        try {
            $user->setPassword($newHashedPassword);
            $pageManager = $this->wiki->services->get(PageManager::class);
            $page = $pageManager->getOne($user['name'], null, true, true);
            if ($page) {
                $body = json_decode($page['body'] ?? '', true) ?? [];
                $body['password'] = $newHashedPassword;
                $pageManager->save($user['name'], $this->encodeBody($body), '', true);
            }
        } catch (\Throwable $th) {
            // only throw error in debug mode
            if ($this->wiki->GetConfigValue('debug')) {
                throw $th;
            }
        }
    }

    /* ~~~~~~~~~~~~~~~~~~ implements  UserProviderInterface ~~~~~~~~~~~~~~~~~~ */

    /**
     * Refreshes the user.
     *
     * It is up to the implementation to decide if the user data should be
     * totally reloaded (e.g. from the database), or if the UserInterface
     * object can just be merged into some internal array of users / identity
     * map.
     *
     * @throws UnsupportedUserException if the user is not supported
     * @throws UserNotFoundException    if the user is not found
     */
    public function refreshUser(UserInterface $user): UserInterface
    {
        if (!$this->supportsClass(get_class($user))) {
            throw new UnsupportedUserException();
        }

        // currently force refresh
        return $this->getOneByName($user->getName());
    }

    /**
     * Whether this provider supports the given user class.
     */
    public function supportsClass(string $class): bool
    {
        if (!class_exists($class)) {
            // prevent calling autoloader via 'is_a'
            return false;
        }

        return is_a($class, User::class, true);
    }

    /* ~~~~~~~~~~~~~~~~~~ end of implements ~~~~~~~~~~~~~~~~~~ */
    /**
     * @throws UserNotFoundException
     */
    public function loadUserByIdentifier(string $identifier): UserInterface
    {
        return $this->getOneByName($identifier);
    }

    /* ~~~~~~~~~~~~~~~~~~ DEPRECATED ~~~~~~~~~~~~~~~~~~ */

    /**
     * @deprecated Use AuthController::getLoggedUser
     */
    public function getLoggedUser()
    {
        return $this->wiki->services->get(AuthController::class)->getLoggedUser();
    }

    /**
     * @deprecated Use AuthController::getLoggedUserName
     */
    public function getLoggedUserName()
    {
        return $this->wiki->services->get(AuthController::class)->getLoggedUserName();
    }

    /**
     * @deprecated Use AuthController::login
     */
    public function login($user, $remember = 0)
    {
        $this->wiki->services->get(AuthController::class)->login($user, $remember);
    }

    /**
     * @deprecated Use AuthController::logout
     */
    public function logout()
    {
        $this->wiki->services->get(AuthController::class)->logout();
    }

    private function arrayToUser(?array $page): ?User
    {
        if (empty($page) || !isset($page['tag'])) {
            return null;
        }
        $body = json_decode($page['body'] ?? '', true);
        if (!is_array($body)) {
            return null;
        }

        return $this->arrayToDraftUser(array_merge($body, ['name' => $page['tag']]));
    }

    /**
     * Builds a User from a flat, name-keyed array (as opposed to arrayToUser(), which
     * decodes a `pages` row) -- used where a User object is needed before any page exists
     * yet (picking a password hasher during create(), which needs a User instance but
     * happens before suggestFreeTag() has even settled the final tag).
     */
    private function arrayToDraftUser(array $userAsArray): User
    {
        foreach (User::PROPS_LIST as $key) {
            if (!array_key_exists($key, $userAsArray)) {
                $userAsArray[$key] = null;
            }
        }

        // be carefull the User::__construct is really strict about list of properties that should set
        return new User($userAsArray);
    }
}

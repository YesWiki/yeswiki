<?php

namespace YesWiki\Identity\Service;

use Exception;
use Psr\Container\ContainerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use YesWiki\Content\Entity\ContentTypeSchema;
use YesWiki\Content\Entity\PageType;
use YesWiki\Content\Field\BazarField;
use YesWiki\Content\Service\FormManager;
use YesWiki\Content\Service\PageManager;
use YesWiki\Identity\Entity\User;
use YesWiki\Identity\Exception\DeleteUserException;
use YesWiki\Identity\Exception\GroupNameDoesNotExistException;
use YesWiki\Identity\Exception\UserEmailAlreadyUsedException;
use YesWiki\Identity\Exception\UserNameAlreadyUsedException;
use YesWiki\Identity\Exception\UserNameReservedException;
use YesWiki\Kernel\Routing\ReservedTags;
use YesWiki\Kernel\Service\DbService;
use YesWiki\Kernel\Service\HibernationService;
use YesWiki\Kernel\Service\Mailer;
use YesWiki\Kernel\Service\TripleStore;
use YesWiki\Kernel\Service\UrlFormatter;
use YesWiki\Search\Service\SearchManager;

class UserManager implements UserProviderInterface, PasswordUpgraderInterface
{
    protected ContainerInterface $container;
    protected $dbService;
    protected $passwordHasherFactory;
    protected $hibernationService;
    protected $params;
    protected $tripleStore;

    private array $associatedEntryCache = [];

    public const KEY_VOCABULARY = 'http://outils-reseaux.org/_vocabulary/key';
    // stored triple value is "$hashedKey$KEY_VALUE_SEPARATOR$issuedAtTimestamp"
    public const KEY_VALUE_SEPARATOR = ':';
    // password recovery links expire after 1 hour
    public const KEY_TTL = 3600;

    // PageManager and AclService are deliberately NOT constructor-injected here: PageManager
    // already depends on UserManager directly, and AclService depends on UserManager too
    // (its '+' registered-users ACL case calls getOneByName()) -- constructor-injecting
    // either back into UserManager would be a circular dependency. Fetched late via
    // $this->container->get() instead (see pageManager()), the same workaround isInGroup()
    // already uses for AclService below.
    protected UrlFormatter $urlFormatter;

    public function __construct(
        ContainerInterface $container,
        DbService $dbService,
        ParameterBagInterface $params,
        PasswordHasherFactory $passwordHasherFactory,
        HibernationService $hibernationService,
        TripleStore $tripleStore,
        UrlFormatter $urlFormatter
    ) {
        $this->urlFormatter = $urlFormatter;
        $this->container = $container;
        $this->dbService = $dbService;
        $this->passwordHasherFactory = $passwordHasherFactory;
        $this->hibernationService = $hibernationService;
        $this->params = $params;
        $this->tripleStore = $tripleStore;
    }

    /** See the note above the constructor: injecting it would be a cycle. */
    private function pageManager(): PageManager
    {
        return $this->container->get(PageManager::class);
    }

    public function isUserTag(string $tag): bool
    {
        if (empty($tag)) {
            return false;
        }

        return $this->pageManager()->isType($tag, PageType::USER);
    }

    public function getAllUserTags(): array
    {
        return $this->pageManager()->tagsOfType(PageType::USER);
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

        $page = $this->container->get(PageManager::class)->getOne($name, null, true, true);
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
            WHERE p.latest = 'Y' AND {$jsonExtract} = ?
            AND p.{$this->dbService->quoteIdentifier('type')} = ?
            LIMIT 1";
        $row = $this->dbService->loadSingle($sql, [$email, PageType::USER]);

        return $row['tag'] ?? null;
    }

    public function getAll($dbFields = ['name', 'password', 'email', 'motto', 'revisioncount', 'changescount', 'doubleclickedit', 'signuptime', 'show_comments']): array
    {
        // $dbFields was a partial-column-select optimization for the old flat `users`
        // table; kept for signature compatibility but not honored -- every User object
        // requires the full PROPS_LIST anyway (see User::__construct()), and decoding a
        // JSON body doesn't offer a meaningful partial-fetch optimization to replace it with.
        $pageManager = $this->container->get(PageManager::class);
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
     * @param string       $email          optional if parameters are passed as an array
     * @param string       $plainPassword  optional if parameters are passed as an array
     * @param string|null  $forcedTag      the tag to store the account at, when the caller has
     *                                     already settled it -- creating an account from the User
     *                                     form has to know the tag before it saves, because a
     *                                     field like the profile picture formats its upload
     *                                     against it (ticket 13). Defaults to suggesting one from
     *                                     the name, which is what every other caller wants.
     *
     * @throws UserNameAlreadyUsedException|UserEmailAlreadyUsedException|\Exception
     */
    public function create($wikiNameOrUser, string $email = '', string $plainPassword = '', ?string $forcedTag = null): ?User
    {
        if ($this->hibernationService->isWikiHibernated()) {
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
        // A RESERVED name is refused outright rather than suffixed (ticket 20). Registration
        // is the one creation path that cannot quietly resolve a bad tag, because an
        // account's name IS its tag -- buildBody() stores no second copy. Suffixing here
        // would hand someone the account `api-2` after they typed `api`, which is exactly
        // the silent rewrite the ticket forbids.
        if (ReservedTags::isReserved($wikiName)) {
            throw new UserNameReservedException(_t('RESERVED_TAG_CANNOT_BE_USED', ['tag' => $wikiName]));
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

        $tag = $forcedTag ?? $this->container->get(PageManager::class)->suggestFreeTag($wikiName);

        $hasher = $this->passwordHasherFactory->getPasswordHasher($this->arrayToDraftUser($userAsArray));
        $hashedPassword = $hasher->hash($plainPassword);

        // buildBody() names the account fields core owns; a webmaster can add fields to the
        // User form, and ticket 10 added a locked `profile_picture` in code. Those are the
        // caller's to carry, and dropping them would make the User form a schema the User
        // service does not honour (ticket 13). migrateLegacyUser() deliberately does not do
        // this: a legacy `users` row's extra columns are not profile fields.
        $body = array_merge(
            $this->extraProfileFields($userAsArray),
            $this->buildBody(array_merge($userAsArray, [
                'password' => $hashedPassword,
                'signuptime' => date('Y-m-d H:i:s'),
            ]))
        );

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

        $tag = $this->container->get(PageManager::class)->suggestFreeTag($name);
        $body = $this->buildBody($legacyRow);

        $this->persistNewUserPage($tag, $body);
    }

    private function persistNewUserPage(string $tag, array $body): bool
    {
        $pageManager = $this->container->get(PageManager::class);
        // the row is written as a user in one statement (ticket 27) -- when the type was a
        // separate triple, it had to be created BEFORE setOwner() below, because setOwner()
        // verifies its target through getOneByName(), which only recognises a tag as a user
        // once it is typed. That ordering hazard is gone: a row is never briefly untyped.
        // PageManager's type cache still has to be told, because getOneByName()'s collision
        // check ran before this tag existed and cached "no such row".
        $saved = $pageManager->save($tag, $body, '', true, null, PageType::USER);
        if ($saved !== 0) {
            return false;
        }
        $pageManager->cacheType($tag, PageType::USER);

        // the account owns itself -- save()'s auto-owner logic would otherwise leave this
        // empty for a self-registering, not-yet-logged-in user
        $pageManager->setOwner($tag, $tag);

        // the wiki's default_write_acl is '*' (everyone) -- without this override, anyone
        // could edit any other user's account page. Late-bound: see the constructor note
        // on why AclService can't be injected directly.
        $this->container->get(AclService::class)->save($tag, 'write', "%\n@admins");

        return true;
    }

    private function buildBody(array $fields): array
    {
        return [
            'email' => $fields['email'] ?? '',
            'motto' => $this->valueOrDefault($fields, 'motto', ''),
            // revisioncount/changescount are user-configurable display-count preferences, not
            // derived counters -- a legitimate stored 0 must not be treated as "unset" and
            // silently overwritten (empty(0) and empty('0') are both true in PHP, which
            // empty()-based checks got wrong here)
            'revisioncount' => $this->valueOrDefault($fields, 'revisioncount', '20'),
            'changescount' => $this->valueOrDefault($fields, 'changescount', '50'),
            'doubleclickedit' => $this->valueOrDefault($fields, 'doubleclickedit', 'Y'),
            'signuptime' => $fields['signuptime'] ?? date('Y-m-d H:i:s'),
            'show_comments' => $this->valueOrDefault($fields, 'show_comments', 'N'),
            'password' => $fields['password'] ?? '',
        ];
    }

    /**
     * Everything the caller supplied that buildBody() does not name -- minus the account's
     * identity, which is its tag and must not be stored a second time where it can drift.
     *
     * @param array<string, mixed> $fields
     *
     * @return array<string, mixed>
     */
    private function extraProfileFields(array $fields): array
    {
        return array_diff_key($fields, array_flip([
            'name', 'username', 'password',
            'email', 'motto', 'revisioncount', 'changescount',
            'doubleclickedit', 'signuptime', 'show_comments',
        ]));
    }

    private function valueOrDefault(array $fields, string $key, string $default)
    {
        return (isset($fields[$key]) && $fields[$key] !== '') ? $fields[$key] : $default;
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
        $link = $this->urlFormatter->href('', 'MotDePassePerdu', [
            'a' => 'recover',
            'email' => $hashedKey,
            'u' => base64_encode($user['name']),
        ], false);

        // Send the email
        if (!boolval($this->container->get(\YesWiki\Kernel\Service\RuntimeConfig::class)['contact_disable_email_for_password'])) {
            $pieces = parse_url($this->params->get('base_url'));
            $domain = isset($pieces['host']) ? $pieces['host'] : '';

            $message = _t('LOGIN_DEAR') . ' ' . $user['name'] . ",\n";
            $message .= _t('LOGIN_CLICK_FOLLOWING_LINK') . ' :' . "\n";
            $message .= '-----------------------' . "\n";
            $message .= $link . "\n";
            $message .= '-----------------------' . "\n";
            $message .= _t('LOGIN_THE_TEAM') . ' ' . $domain . "\n";

            $subject = _t('LOGIN_PASSWORD_LOST_FOR') . ' ' . $domain;

            // fetched lazily, not constructor-injected: Mailer itself depends on
            // UserManager, so a constructor dependency would be circular (ticket 18)
            $this->container->get(Mailer::class)->send(
                $this->params->get('BAZ_ADRESSE_MAIL_ADMIN'),
                $this->params->get('BAZ_ADRESSE_MAIL_ADMIN'),
                $user['email'],
                $subject,
                $message
            );
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
            $issuedAt = count($parts) === 2 ? (int)$parts[1] : 0;
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
        if ($this->hibernationService->isWikiHibernated()) {
            throw new \Exception(_t('WIKI_IN_HIBERNATION'));
        }
        $updatable = $this->updatableKeys();
        $authorizedKeys = array_filter(array_keys($newValues), function ($key) use ($updatable) {
            return in_array($key, $updatable, true);
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
            $pageManager = $this->container->get(PageManager::class);
            $page = $pageManager->getOne($user['name'], null, true, true);
            if ($page) {
                $body = $page['body'] ?? [];
                foreach ($authorizedKeys as $key) {
                    $body[$key] = $newValues[$key];
                }
                $pageManager->save($user['name'], $body, '', true);
            }
        }

        return true;
    }

    /**
     * The body keys update() will write -- core's own account preferences, plus whatever
     * the User form says an account holds.
     *
     * The form half is what makes the profile picture (and any field a webmaster adds
     * beside it) more than decoration: without it, the settings screen would offer inputs
     * whose values this method silently dropped. It stays an allow-list all the same --
     * this method takes a raw submission, and three keys must never be reachable through
     * one: the tag the account is stored under, the password hash (AuthenticationService
     * sets that, through the hasher) and the activation state that decides whether the
     * account may sign in at all.
     *
     * @return list<string>
     */
    private function updatableKeys(): array
    {
        $keys = [
            'changescount',
            'doubleclickedit',
            'email',
            'motto',
            'revisioncount',
            'show_comments',
        ];

        $form = $this->container->get(FormManager::class)->getByContentType(ContentTypeSchema::TYPE_USER);
        $never = ['name', 'username', 'password', 'activation_status', 'activation_key'];
        foreach ($form['prepared'] ?? [] as $field) {
            if (!$field instanceof BazarField) {
                continue;
            }
            $propertyName = $field->getPropertyName();
            if ($propertyName !== '' && !in_array($propertyName, $never, true)) {
                $keys[] = $propertyName;
            }
        }

        return array_values(array_unique($keys));
    }

    /**
     * delete a user
     * SHOULD NOT BE USE DIRECTLY => use UserOperationsService->delete().
     *
     * @throws DeleteUserException
     */
    public function delete(User $user)
    {
        if ($this->hibernationService->isWikiHibernated()) {
            throw new \Exception(_t('WIKI_IN_HIBERNATION'));
        }
        try {
            $this->container->get(PageManager::class)->deleteOrphaned($user['name']);
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
            $members = $this->container->get(GroupOperationsService::class)->getMembers($groupName);
        } catch (GroupNameDoesNotExistException $th) {
            $members = [];
        }

        return $this->container->get(AclService::class)->check(implode("\n", $members), $username, $admincheck, '', '', $formerGroups);
    }

    /**
     * get the entry that is linked to the username.
     */
    public function getAssociatedEntry($user = '')
    {
        if (empty($user)) {
            $user = $this->container->get(AuthenticationService::class)->getLoggedUser();
            if (empty($user['name'])) {
                return null;
            }
            $user = $user['name'];
        }
        if (array_key_exists($user, $this->associatedEntryCache)) {
            return $this->associatedEntryCache[$user];
        }
        $vFormManager = $this->container->get(FormManager::class);
        $vSearchManager = $this->container->get(SearchManager::class);
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
            if (!empty($candidate['tag'])) {
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
        if ($this->hibernationService->isWikiHibernated()) {
            throw new \Exception(_t('WIKI_IN_HIBERNATION'));
        }
        // `instanceof` rather than `supportsClass(get_class($user))`: the two ask the same
        // question -- supportsClass() is `is_a($class, User::class, true)` -- but only this one
        // narrows the parameter, so the calls below stopped being method.notFound (ticket 40)
        if (!$user instanceof User) {
            throw new UnsupportedUserException();
        }
        try {
            $user->setPassword($newHashedPassword);
            $pageManager = $this->container->get(PageManager::class);
            $page = $pageManager->getOne($user['name'], null, true, true);
            if ($page) {
                $body = $page['body'] ?? [];
                $body['password'] = $newHashedPassword;
                $pageManager->save($user['name'], $body, '', true);
            }
        } catch (\Throwable $th) {
            // only throw error in debug mode
            if ($this->container->get(\YesWiki\Kernel\Service\RuntimeConfig::class)->getValue('debug')) {
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
        // instanceof for the same reason as upgradePassword(): it narrows where
        // supportsClass(get_class($user)) only asserts
        if (!$user instanceof User) {
            throw new UnsupportedUserException();
        }

        // currently force refresh
        $refreshed = $this->getOneByName($user->getName());
        // `getOneByName()` is `?User` and this is declared `UserInterface`: an account deleted
        // between two requests returned null into Symfony's security layer, which is a
        // TypeError rather than the UserNotFoundException the docblock promises (ticket 40)
        if ($refreshed === null) {
            throw new UserNotFoundException("no user named '{$user->getName()}' any more");
        }

        return $refreshed;
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
        $user = $this->getOneByName($identifier);
        // same as refreshUser(): the return type is not nullable and Symfony's contract is that
        // an unknown identifier throws. Returning null made a login attempt with a username
        // nobody has fail as a TypeError inside the security layer (ticket 40).
        if ($user === null) {
            throw new UserNotFoundException("no user named '$identifier'");
        }

        return $user;
    }

    /* ~~~~~~~~~~~~~~~~~~ DEPRECATED ~~~~~~~~~~~~~~~~~~ */

    /**
     * @deprecated Use AuthenticationService::getLoggedUser
     */
    public function getLoggedUser()
    {
        return $this->container->get(AuthenticationService::class)->getLoggedUser();
    }

    /**
     * @deprecated Use AuthenticationService::getLoggedUserName
     */
    public function getLoggedUserName()
    {
        return $this->container->get(AuthenticationService::class)->getLoggedUserName();
    }

    /**
     * @deprecated Use AuthenticationService::login
     */
    public function login($user, $remember = 0)
    {
        $this->container->get(AuthenticationService::class)->login($user, $remember);
    }

    /**
     * @deprecated Use AuthenticationService::logout
     */
    public function logout()
    {
        $this->container->get(AuthenticationService::class)->logout();
    }

    private function arrayToUser(?array $page): ?User
    {
        if (empty($page) || !isset($page['tag'])) {
            return null;
        }
        // PageManager hands the body back decoded; a page with no body at all is not an
        // account (this used to be json_decode('') === null)
        $body = $page['body'] ?? [];
        if (!is_array($body) || empty($body)) {
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

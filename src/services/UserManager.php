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

    private $getOneByNameCacheResults;
    private array $associatedEntryCache = [];

    public const KEY_VOCABULARY = 'http://outils-reseaux.org/_vocabulary/key';

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
        $this->getOneByNameCacheResults = [];
    }

    public function userExist($name): bool
    {
        return !empty($this->getOneByName($name));
    }

    public function getOneByName($name, $password = null): ?User
    {
        // use !is_string($password) instead of !$password to allow $password == ""

        // Don't check the cache with isset(), because the value of the cache can be null
        if (!is_string($password) && array_key_exists($name, $this->getOneByNameCacheResults)) {
            $result = $this->getOneByNameCacheResults[$name];
        } else {
            $result = $this->dbService->loadSingle('select * from' . $this->dbService->prefixTable('users') . "where name = '" . $this->dbService->escape($name) . "' " . (!is_string($password) ? '' : "and password = '" . $this->dbService->escape($password) . "'") . ' limit 1');
            if (!is_string($password)) {
                $this->getOneByNameCacheResults[$name] = $result;
            }
        }

        return $this->arrayToUser($result);
    }

    public function getOneByEmail($mail, $password = null): ?User
    {
        return $this->arrayToUser($this->dbService->loadSingle('select * from' . $this->dbService->prefixTable('users') . "where email = '" . $this->dbService->escape($mail) . "' " . (!is_string($password) ? '' : "and password = '" . $this->dbService->escape($password) . "'") . ' limit 1'));
    }

    public function getAll($dbFields = ['name', 'password', 'email', 'motto', 'revisioncount', 'changescount', 'doubleclickedit', 'signuptime', 'show_comments']): array
    {
        if ($this->params->has('user_table_prefix') && !empty($this->params->get('user_table_prefix'))) {
            $prefix = $this->params->get('user_table_prefix');
        } else {
            $prefix = $this->params->get('table_prefix');
        }

        $selectDefinition = empty($dbFields) ? '*' : implode(', ', $dbFields);

        return array_map(
            function ($userAsArray) {
                return $this->arrayToUser($userAsArray, true);
            },
            $this->dbService->loadAll("select $selectDefinition from {$prefix}users order by name")
        );
    }

    /**
     * @param array|string $wikiNameOrUser array to create the wiki or wikiname
     * @param string email (optionnal if parameters by array)
     * @param string plainPassword (optionnal if parameters by array)
     *
     * @throws UserNameAlreadyUsedException|UserEmailAlreadyUsedException|\Exception
     */
    public function create($wikiNameOrUser, string $email = '', string $plainPassword = '')
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

        // clear both the trimmed name and any untrimmed variant stored in cache
        unset($this->getOneByNameCacheResults[$wikiName]);
        if (is_string($wikiNameOrUser) && $wikiNameOrUser !== $wikiName) {
            unset($this->getOneByNameCacheResults[$wikiNameOrUser]);
        } elseif (is_array($wikiNameOrUser)) {
            $originalName = $wikiNameOrUser['name'] ?? '';
            if ($originalName !== $wikiName) {
                unset($this->getOneByNameCacheResults[$originalName]);
            }
        }
        $user = $this->arrayToUser($userAsArray);
        $passwordHasher = $this->passwordHasherFactory->getPasswordHasher($user);
        $hashedPassword = $passwordHasher->hash($plainPassword);

        // Build columns and values arrays for cross-database compatibility
        $columns = ['signuptime', 'name', 'motto', 'email', 'password'];
        $values = [
            $this->dbService->now(),
            "'" . $this->dbService->escape($user['name']) . "'",
            "'" . (empty($user['motto']) ? '' : $this->dbService->escape($user['motto'])) . "'",
            "'" . $this->dbService->escape($user['email']) . "'",
            "'" . $this->dbService->escape($hashedPassword) . "'",
        ];

        if (!empty($user['changescount'])) {
            $columns[] = 'changescount';
            $values[] = "'" . $this->dbService->escape($user['changescount']) . "'";
        }
        if (!empty($user['doubleclickedit'])) {
            $columns[] = 'doubleclickedit';
            $values[] = "'" . $this->dbService->escape($user['doubleclickedit']) . "'";
        }
        if (!empty($user['revisioncount'])) {
            $columns[] = 'revisioncount';
            $values[] = "'" . $this->dbService->escape($user['revisioncount']) . "'";
        }
        if (!empty($user['show_comments'])) {
            $columns[] = 'show_comments';
            $values[] = "'" . $this->dbService->escape($user['show_comments']) . "'";
        }

        return $this->dbService->query(
            'INSERT INTO ' . $this->dbService->prefixTable('users') .
            '(' . implode(', ', $columns) . ') VALUES (' . implode(', ', $values) . ')'
        );
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
        // Store the (name, vocabulary, key) triple in triples table
        $this->tripleStore->create($user['name'], self::KEY_VOCABULARY, $hashedKey, '', '');

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
            $query = "UPDATE {$this->dbService->prefixTable('users')} SET ";
            $query .= implode(
                ', ',
                array_map(
                    function ($key) use ($newValues) {
                        return $this->dbService->quoteIdentifier($key) . " = '{$this->dbService->escape($newValues[$key])}' ";
                    },
                    $authorizedKeys
                )
            );
            $query .= "WHERE {$this->dbService->quoteIdentifier('name')} = '{$this->dbService->escape($user['name'])}' ";
            $query .= "AND {$this->dbService->quoteIdentifier('email')} = '{$this->dbService->escape($user['email'])}' ";
            $query .= "AND {$this->dbService->quoteIdentifier('password')} = '{$this->dbService->escape($user['password'])}' ";
            $this->dbService->query($query);
        }

        unset($this->getOneByNameCacheResults[$user['name']]);

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
        unset($this->getOneByNameCacheResults[$user['name']]);
        $query = "DELETE FROM {$this->dbService->prefixTable('users')} " .
            " WHERE {$this->dbService->quoteIdentifier('name')} = '{$this->dbService->escape($user['name'])}';";
        try {
            if (!$this->dbService->query($query)) {
                throw new DeleteUserException(_t('USER_DELETE_QUERY_FAILED') . '.');
            }
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
            $previousPassword = $user['password'];
            $user->setPassword($newHashedPassword);
            $query =
                'UPDATE ' . $this->dbService->prefixTable('users') . 'SET ' .
                "password = '" . $this->dbService->escape($newHashedPassword) . "'" .
                " WHERE name = '" . $this->dbService->escape($user['name']) . "' " .
                "AND email= '" . $this->dbService->escape($user['email']) . "' " .
                "AND password= '" . $this->dbService->escape($previousPassword) . "';";
            $this->dbService->query($query);
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

    private function arrayToUser(?array $userAsArray = null, bool $fillEmpty = false): ?User
    {
        if (empty($userAsArray)) {
            return null;
        }
        if ($fillEmpty) {
            foreach (User::PROPS_LIST as $key) {
                if (!array_key_exists($key, $userAsArray)) {
                    $userAsArray[$key] = null;
                }
            }
        }

        // be carefull the User::__construct is really strict about list of properties that should set
        return new User($userAsArray);
    }
}

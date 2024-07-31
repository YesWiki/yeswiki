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
use Throwable;
use YesWiki\Core\Controller\AuthController;
use YesWiki\Core\Entity\User;
use YesWiki\Core\Exception\DeleteUserException;
use YesWiki\Core\Exception\UserEmailAlreadyUsedException;
use YesWiki\Core\Exception\UserNameAlreadyUsedException;
use YesWiki\Security\Controller\SecurityController;
use YesWiki\Wiki;

if (! function_exists('send_mail')) {
    require_once 'includes/email.inc.php';
}

class UserManager implements UserProviderInterface, PasswordUpgraderInterface
{
    protected $wiki;
    protected $dbService;
    protected $passwordHasherFactory;
    protected $securityController;
    protected $params;
    protected $userlink;
    private $getOneByNameCacheResults;

    private const PW_SALT = 'FBcA';
    public const KEY_VOCABULARY = 'http://outils-reseaux.org/_vocabulary/key';

    public function __construct(
        Wiki $wiki,
        DbService $dbService,
        ParameterBagInterface $params,
        PasswordHasherFactory $passwordHasherFactory,
        SecurityController $securityController
    ) {
        $this->wiki = $wiki;
        $this->dbService = $dbService;
        $this->passwordHasherFactory = $passwordHasherFactory;
        $this->securityController = $securityController;
        $this->params = $params;
        $this->getOneByNameCacheResults = [];
        $this->userlink = "";
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

    public function getOneByName($name, $password = null): ?User
    {
        // use !is_string($password) instead of !$password to allow $password == ""
        if (!is_string($password) && isset($this->getOneByNameCacheResults[$name])) {
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
     * @throws UserNameAlreadyUsedException|UserEmailAlreadyUsedException|Exception
     */
    public function create($wikiNameOrUser, string $email = '', string $plainPassword = '')
    {
        $this->userlink = '';
        if ($this->securityController->isWikiHibernated()) {
            throw new Exception(_t('WIKI_IN_HIBERNATION'));
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
            throw new Exception('First parameter of UserManager->create should be string or array!');
        }

        if (empty($wikiName)) {
            throw new Exception("'Name' parameter of UserManager->create should not be empty!");
        }
        if (!empty($this->getOneByName($wikiName))) {
            throw new UserNameAlreadyUsedException();
        }
        if (empty($email)) {
            throw new Exception("'email' parameter of UserManager->create should not be empty!");
        }
        if (!empty($this->getOneByEmail($email))) {
            throw new UserEmailAlreadyUsedException();
        }
        if (empty($plainPassword)) {
            throw new Exception("'password' parameter of UserManager->create should not be empty!");
        }

        unset($this->getOneByNameCacheResults[$wikiName]);
        $user = $this->arrayToUser($userAsArray);
        $passwordHasher = $this->passwordHasherFactory->getPasswordHasher($user);
        $hashedPassword = $passwordHasher->hash($plainPassword);

        return $this->dbService->query(
            'INSERT INTO ' . $this->dbService->prefixTable('users') . 'SET ' .
                'signuptime = now(), ' .
                "name = '" . $this->dbService->escape($user['name']) . "', " .
                "motto = '" . (empty($user['motto']) ? '' : $this->dbService->escape($user['motto'])) . "', " .
                (empty($user['changescount']) ? '' : "changescount = '" . $this->dbService->escape($user['changescount']) . "', ") .
                (empty($user['doubleclickedit']) ? '' : "doubleclickedit = '" . $this->dbService->escape($user['doubleclickedit']) . "', ") .
                (empty($user['revisioncount']) ? '' : "revisioncount = '" . $this->dbService->escape($user['revisioncount']) . "', ") .
                (empty($user['show_comments']) ? '' : "show_comments = '" . $this->dbService->escape($user['show_comments']) . "', ") .
                "email = '" . $this->dbService->escape($user['email']) . "', " .
                "password = '" . $this->dbService->escape($hashedPassword) . "'"
        );
    }

    /*
     * Password recovery process (AKA reset password)
     * 1. A key is generated using name, email alongside with other stuff.
     * 2. The triple (user's name, specific key "vocabulary",key) is stored in triples table.
     * 3. In order to update h·er·is password, the user must provided that key.
     * 4. The new password is accepted only if the key matches with the value in triples table.
     * 5. The corresponding row is removed from triples table.
     */

    protected function generateUserLink($user)
    {
        // Generate the password recovery key
        $key = md5($user['name'] . '_' . $user['email'] . random_int(0, 10000) . date('Y-m-d H:i:s') . self::PW_SALT);
        $tripleStore = $this->wiki->services->get(TripleStore::class);
        // Erase the previous triples in the trible table
        $tripleStore->delete($user['name'], self::KEY_VOCABULARY, null, '', '');
        // Store the (name, vocabulary, key) triple in triples table
        $tripleStore->create($user['name'], self::KEY_VOCABULARY, $key, '', '');

        // Generate the recovery email
        $this->userlink = $this->wiki->Href('', 'MotDePassePerdu', [
            'a' => 'recover',
            'email' => $key,
            'u' => base64_encode($user['name'])
        ], false);
    }

    /**
     * Part of the Password recovery process: Handles the password recovery email process.
     *
     * Generates the password recovery key
     * Stores the (name, vocabulary, key) triple in triples table
     * Generates the recovery email
     * Sends it
     *
     * @return bool True if OK or false if any problems
     */
    public function sendPasswordRecoveryEmail(User $user, string $title): bool
    {
        $this->generateUserLink($user);
        $pieces = parse_url($this->params->get('base_url'));
        $domain = isset($pieces['host']) ? $pieces['host'] : '';

        $message = _t('LOGIN_DEAR') . ' ' . $user['name'] . ",\n";
        $message .= _t('LOGIN_CLICK_FOLLOWING_LINK') . ' :' . "\n";
        $message .= '-----------------------' . "\n";
        $message .= $this->userlink . "\n";
        $message .= '-----------------------' . "\n";
        $message .= _t('LOGIN_THE_TEAM') . ' ' . $domain . "\n";

        $subject = $title . ' ' . $domain;
        // Send the email
        return send_mail($this->params->get('BAZ_ADRESSE_MAIL_ADMIN'), $this->params->get('BAZ_ADRESSE_MAIL_ADMIN'), $user['email'], $subject, $message);
    }

    /**
     * Assessor for userlink field
     * 
     * @return string
     */
    public function getUserLink(): string
    {
        return $this->userlink;
    }

    /**
     * Assessor for userlink field
     *
     * @return string
     */
    public function getLastUserLink(User $user): string
    {
        $tripleStore = $this->wiki->services->get(TripleStore::class);
        $key = $tripleStore->getOne($user['name'], self::KEY_VOCABULARY, '', '');
        if ($key != null) {
            $this->userlink = $this->wiki->Href('', 'MotDePassePerdu', [
                'a' => 'recover',
                'email' => $key,
                'u' => base64_encode($user['name'])
            ], false);
        } else {
            $this->generateUserLink($user);
        }
        return $this->userlink;
    }

    /**
     * update user params
     * for e-mail check is existing e-mail.
     *
     * @param array $newValues (associative array)
     *
     * @throws Exception
     * @throws UserEmailAlreadyUsedException
     */
    public function update(User $user, array $newValues): bool
    {
        if ($this->securityController->isWikiHibernated()) {
            throw new Exception(_t('WIKI_IN_HIBERNATION'));
        }
        $newKeys = array_keys($newValues);
        $authorizedKeys = array_filter($newKeys, function ($key) {
            return in_array($key, [
                'changescount',
                'doubleclickedit',
                'email',
                'motto',
                //'name', // name not currently updateable
                // 'password', // password not updateable by this method
                'revisioncount',
                'show_comments',
            ]);
        });
        if (isset($newValues['email'])) {
            if (empty($newValues['email'])) {
                throw new Exception("\$newValues['email'] parameter of UserManager->update should not be empty!");
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
                        return "`$key` = \"{$this->dbService->escape($newValues[$key])}\" ";
                    },
                    $authorizedKeys
                )
            );
            $query .= "WHERE `name` = \"{$this->dbService->escape($user['name'])}\" ";
            $query .= "AND `email` = \"{$this->dbService->escape($user['email'])}\" ";
            $query .= "AND `password` = \"{$this->dbService->escape($user['password'])}\" ";
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
            throw new Exception(_t('WIKI_IN_HIBERNATION'));
        }
        unset($this->getOneByNameCacheResults[$user['name']]);
        $query = "DELETE FROM {$this->dbService->prefixTable('users')} " .
            " WHERE `name` = \"{$this->dbService->escape($user['name'])}\";";
        try {
            if (!$this->dbService->query($query)) {
                throw new DeleteUserException(_t('USER_DELETE_QUERY_FAILED') . '.');
            }
        } catch (Exception $ex) {
            throw new DeleteUserException(_t('USER_DELETE_QUERY_FAILED') . '.');
        }
    }

    /** Lists the groups $this user is member of.
     *
     * @return string[] An array of group names
     */
    public function groupsWhereIsMember(User $user, bool $adminCheck = true)
    {
        $groups = $this->wiki->GetGroupsList();
        $groups = array_filter($groups, function ($group) use ($user, $adminCheck) {
            return !empty($user['name']) && $this->isInGroup($group, $user['name'], $adminCheck);
        });

        return $groups;
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
        return $this->wiki->services->get(AclService::class)->check($this->wiki->GetGroupACL($groupName), $username, $admincheck, '', '', $formerGroups);
    }

    /* ~~~~~~~~~~~~~~~~~~ implements  PasswordUpgraderInterface ~~~~~~~~~~~~~~~~~~ */

    /**
     * Upgrades the hashed password of a user, typically for using a better hash algorithm.
     * This method should persist the new password in the user storage and update the $user object accordingly.
     * Because you don't want your users not being able to log in, this method should be opportunistic:
     * it's fine if it does nothing or if it fails without throwing any exception.
     *
     * @param PasswordAuthenticatedUserInterface|UserInterface $user
     *
     * @throws UnsupportedUserException if the user is not supported
     * @throws Exception                if wiki is in hibernation
     */
    public function upgradePassword($user, string $newHashedPassword)
    {
        if ($this->securityController->isWikiHibernated()) {
            throw new Exception(_t('WIKI_IN_HIBERNATION'));
        }
        if (!$this->supportsClass(get_class($user))) {
            throw new UnsupportedUserException();
        }
        try {
            $previousPassword = $user['password'];
            $user->setPassword($newHashedPassword);
            $query =
                'UPDATE ' . $this->dbService->prefixTable('users') . 'SET ' .
                'password = "' . $this->dbService->escape($newHashedPassword) . '"' .
                ' WHERE name = "' . $this->dbService->escape($user['name']) . '" ' .
                'AND email= "' . $this->dbService->escape($user['email']) . '" ' .
                'AND password= "' . $this->dbService->escape($previousPassword) . '";';
            $this->dbService->query($query);
        } catch (Throwable $th) {
            // only throw error in debug mode
            if ($this->wiki->GetConfigValue('debug') == 'yes') {
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
     * @return User
     *
     * @throws UnsupportedUserException if the user is not supported
     * @throws UserNotFoundException    if the user is not found
     */
    public function refreshUser(UserInterface $user)
    {
        if (!$this->supportsClass(get_class($user))) {
            throw new UnsupportedUserException();
        }
        // currently force refresh
        return $this->getOneByName($user->getName());
    }

    /**
     * Whether this provider supports the given user class.
     *
     * @return bool
     */
    public function supportsClass(string $class)
    {
        if (!class_exists($class)) {
            // prevent calling autoloader via 'is_a'
            return false;
        }

        return is_a($class, User::class, true);
    }

    /**
     * @return User
     *
     * @throws UserNotFoundException
     *
     * @deprecated since Symfony 5.3, use loadUserByIdentifier() instead
     */
    public function loadUserByUsername(string $username)
    {
        return $this->loadUserByIdentifier($username);
    }

    /* ~~~~~~~~~~~~~~~~~~ end of implements ~~~~~~~~~~~~~~~~~~ */
    /**
     * @return User
     *
     * @throws UserNotFoundException
     */
    public function loadUserByIdentifier(string $username)
    {
        return $this->getOneByName($username);
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
}
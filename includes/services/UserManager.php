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
use YesWiki\Bazar\Service\EntryManager;
use YesWiki\Bazar\Service\Guard;
use YesWiki\Core\Entity\User;
use YesWiki\Core\Exception\DeleteUserException;
use YesWiki\Core\Exception\UserEmailAlreadyUsedException;
use YesWiki\Core\Exception\UserNameAlreadyUsedException;
use YesWiki\Core\Service\PasswordHasherFactory;
use YesWiki\Security\Controller\SecurityController;
use YesWiki\Wiki;

class UserManager implements UserProviderInterface, PasswordUpgraderInterface
{
    protected $wiki;
    protected $dbService;
    protected $passwordHasherFactory;
    protected $securityController;
    protected $params;
    
    private $getOneByNameCacheResults;


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
    }

    private function arrayToUser(?array $userAsArray = null): ?User
    {
        if (empty($userAsArray)) {
            return null;
        }
        // be carefull the User::__construct is really strict about list of properties that should set
        return new User($userAsArray);
    }

    public function getOneByName($name, $password = null): ?User
    {
        // use !is_string($password) instead of !$password to allow $password == ""
        if (!is_string($password) && isset($getOneByNameCacheResults[$name])) {
            $result = $getOneByNameCacheResults[$name];
        } else {
            $result = $this->dbService->loadSingle('select * from' . $this->dbService->prefixTable('users') . "where name = '" . $this->dbService->escape($name) . "' " . (!is_string($password) ? "" : "and password = '" . $this->dbService->escape($password) . "'") . ' limit 1');
            if (!is_string($password)) {
                $getOneByNameCacheResults[$name] = $result;
            }
        }
        return $this->arrayToUser($result);
    }

    public function getOneByEmail($mail, $password = null): ?User
    {
        return $this->arrayToUser($this->dbService->loadSingle('select * from' . $this->dbService->prefixTable('users') . "where email = '" . $this->dbService->escape($mail) . "' " . (!is_string($password) ? "" : "and password = '" . $this->dbService->escape($password) . "'") . ' limit 1'));
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
                return $this->arrayToUser($userAsArray);
            },
            $this->dbService->loadAll("select $selectDefinition from {$prefix}users order by name")
        );
    }

    public function getLoggedUser()
    {
        return isset($_SESSION['user']) ? $_SESSION['user'] : '';
    }

    public function getLoggedUserName()
    {
        if ($user = $this->getLoggedUser()) {
            $name = $user["name"];
        } else {
            $name = $this->wiki->isCli() ? '' : $_SERVER["REMOTE_ADDR"];
        }
        return $name;
    }

    public function login($user, $remember = 0)
    {
        $_SESSION['user'] = [
            'name' => $user['name'],
            'email' => $user['email'],
            'password' => $user['password'],
        ];
        if (!$this->wiki->isCli()) {
            // prevent setting cookies in CLI (could be errors)
            $this->wiki->SetPersistentCookie('name', $user['name'], $remember);
            $this->wiki->SetPersistentCookie('password', $user['password'], $remember);
            $this->wiki->SetPersistentCookie('remember', $remember, $remember);
        }
    }

    public function logout()
    {
        $_SESSION['user'] = '';
        if (!$this->wiki->isCli()) {
            // prevent setting cookies in CLI (could be errors)
            $this->wiki->DeleteCookie('name');
            $this->wiki->DeleteCookie('password');
            $this->wiki->DeleteCookie('remember');
        }
    }

    /**
     * @param array|string $wikiNameOrUser array to create the wiki or wikiname
     * @param string email (optionnal if parameters by array)
     * @param string plainPassword (optionnal if parameters by array)
     * @throws UserNameAlreadyUsedException|UserEmailAlreadyUsedException|Exception
     */
    public function create($wikiNameOrUser, string $email ="", string $plainPassword ="")
    {
        if ($this->securityController->isWikiHibernated()) {
            throw new Exception(_t('WIKI_IN_HIBERNATION'));
        }

        if (is_array($wikiNameOrUser)) {
            $userAsArray = array_merge($wikiNameOrUser, [
                'changescount' => "",
                'doubleclickedit' => "",
                'motto' => "",
                'revisioncount' => "",
                'show_comments' => "",
                'signuptime' => ""
            ]);
            $wikiName = $userAsArray['name'] ?? "";
            $email = $userAsArray['email'] ?? "";
            $plainPassword = $userAsArray['password'] ?? "";
        } elseif (is_string($wikiNameOrUser)) {
            $wikiName = $wikiNameOrUser;
            $userAsArray = [
                'changescount' => "",
                'doubleclickedit' => "",
                'email' => $email,
                'motto' => "",
                'name' => $wikiName,
                'password' => "",
                'revisioncount' => "",
                'show_comments' => "",
                'signuptime' => ""
            ];
        } else {
            throw new Exception("First parameter of UserManager->create should be string or array!");
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
        
        $this->getOneByNameCacheResults = [];
        $user = $this->arrayToUser($userAsArray);
        $passwordHasher = $this->passwordHasherFactory->getPasswordHasher($user);
        $hashedPassword = $passwordHasher->hash($plainPassword);
        return $this->dbService->query(
            'INSERT INTO ' . $this->dbService->prefixTable('users') . 'SET ' .
            "signuptime = now(), " .
            "name = '" . $this->dbService->escape($user['name']) . "', " .
            "motto = '". (empty($user['motto']) ? '' : $this->dbService->escape($user['motto']))."', " .
            (empty($user['changescount']) ? '' : "changescount = '" .$this->dbService->escape($user['changescount'])."', ") .
            (empty($user['doubleclickedit']) ? '' : "doubleclickedit = '" .$this->dbService->escape($user['doubleclickedit'])."', ") .
            (empty($user['revisioncount']) ? '' : "revisioncount = '" .$this->dbService->escape($user['revisioncount'])."', ") .
            (empty($user['show_comments']) ? '' : "show_comments = '" .$this->dbService->escape($user['show_comments'])."', ") .
            "email = '" . $this->dbService->escape($user['email']) . "', " .
            "password = '" . $this->dbService->escape($hashedPassword) . "'"
        );
    }
 
    /**
     * update user params
     * for e-mail check is existing e-mail
     *
     * @param User $user
     * @param array $newValues (associative array)
     * @throws Exception
     * @throws UserEmailAlreadyUsedException
     * @return bool
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
                'revisioncount' => "",
                'show_comments' => ""
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
            foreach ($authorizedKeys as $idx => $key) {
                if ($idx > 0) {
                    $query .= ", ";
                }
                $query .= "`$key` = \"{$this->dbService->escape($newValues[$key])}\" ";
            }
            $query .= "WHERE `name` = \"{$this->dbService->escape($user['name'])}\" ";
            $query .= "AND `email` = \"{$this->dbService->escape($user['email'])}\" ";
            $query .= "AND `password` = \"{$this->dbService->escape($user['password'])}\" ";
            $this->dbService->query($query);
        }

        $this->getOneByNameCacheResults = [];
        return true;
    }

    /**
     * delete a user
     * SHOULD NOT BE USE DIRECTLY => use UserController->delete()
     * @param User $user
     * @throws DeleteUserException
     */
    public function delete(User $user)
    {
        if ($this->securityController->isWikiHibernated()) {
            throw new Exception(_t('WIKI_IN_HIBERNATION'));
        }
        $this->getOneByNameCacheResults = [];
        $query = "DELETE FROM {$this->dbService->prefixTable('users')} ".
            " WHERE `name` = \"{$this->dbService->escape($user['name'])}\";";
        try {
            if (!$this->dbService->query($query)) {
                throw new DeleteUserException(_t('USER_DELETE_QUERY_FAILED').'.');
            }
        } catch (Exception $ex) {
            throw new DeleteUserException(_t('USER_DELETE_QUERY_FAILED').'.');
        }
    }

    /** Lists the groups $this user is member of
     *
     * @param User $user
     * @param bool $adminCheck
     * @return string[] An array of group names
    */
    public function groupsWhereIsMember(User $user, bool $adminCheck = true)
    {
        $groups = $this->wiki->GetGroupsList();
        $groups = array_filter($groups, function ($group) use ($user, $adminCheck) {
            return $this->isInGroup($group, $user['name'], $adminCheck);
        });

        return $groups;
    }

    /** Tells if a user is member of the specified group.
     *
     * @param string $groupName The name of the group for which we are testing membership
     * @param string|null $username if null check current user
     * @param bool $admincheck
     *
     * @return boolean True if the $user is member of $groupName, false otherwise
    */
    public function isInGroup(string $groupName, ?string $username = null, bool $admincheck = true)
    {
        return $this->wiki->CheckACL($this->wiki->GetGroupACL($groupName), $username, $admincheck);
    }

    /* ~~~~~~~~~~~~~~~~~~ implements  PasswordUpgraderInterface ~~~~~~~~~~~~~~~~~~ */

    /**
     * Upgrades the hashed password of a user, typically for using a better hash algorithm.
     * This method should persist the new password in the user storage and update the $user object accordingly.
     * Because you don't want your users not being able to log in, this method should be opportunistic:
     * it's fine if it does nothing or if it fails without throwing any exception.
     * @param PasswordAuthenticatedUserInterface|UserInterface $user
     * @throws UnsupportedUserException if the user is not supported
     * @throws Exception if wiki is in hibernation
     * @param string $newHashedPassword
     */
    public function upgradePassword(PasswordAuthenticatedUserInterface|UserInterface $user, string $newHashedPassword)
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
                'password = "' . $this->dbService->escape($newHashedPassword) . '"'.
                ' WHERE name = "'.$this->dbService->escape($user['name']).'" '.
                'AND email= "'.$this->dbService->escape($user['email']).'" '.
                'AND password= "'.$this->dbService->escape($previousPassword).'";';
            $this->dbService->query($query);
        } catch (Throwable $th) {
            // only throw error in debug mode
            if ($this->wiki->GetConfigValue('debug')=='yes') {
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
}

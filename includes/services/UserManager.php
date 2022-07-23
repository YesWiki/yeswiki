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
use YesWiki\Core\Controller\AuthController;
use YesWiki\Core\Entity\User;
use YesWiki\Core\Exception\DeleteUserException;
use YesWiki\Core\Exception\UserEmailAlreadyUsedException;
use YesWiki\Core\Exception\UserNameAlreadyUsedException;
use YesWiki\Core\Service\AclService;
use YesWiki\Core\Service\PasswordHasherFactory;
use YesWiki\Security\Controller\SecurityController;
use YesWiki\Wiki;
use YesWiki\Core\Service\Mailer;

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
        if (!is_string($password) && isset($this->getOneByNameCacheResults[$name])) {
            $result = $this->getOneByNameCacheResults[$name];
        } else {
            $result = $this->dbService->loadSingle('select * from' . $this->dbService->prefixTable('users') . "where name = '" . $this->dbService->escape($name) . "' " . (!is_string($password) ? "" : "and password = '" . $this->dbService->escape($password) . "'") . ' limit 1');
            if (!is_string($password)) {
                $this->getOneByNameCacheResults[$name] = $result;
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
        
        unset($this->getOneByNameCacheResults[$wikiName]);
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
                'revisioncount',
                'show_comments'
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
                ", ",
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
     * SHOULD NOT BE USE DIRECTLY => use UserController->delete()
     * @param User $user
     * @throws DeleteUserException
     */
    public function delete(User $user)
    {
        if ($this->securityController->isWikiHibernated()) {
            throw new Exception(_t('WIKI_IN_HIBERNATION'));
        }
        unset($this->getOneByNameCacheResults[$user['name']]);
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
        // aclService could  not be loaded in __construct because AclService already loads UserManager
        return $this->wiki->services->get(AclService::class)->check($this->wiki->GetGroupACL($groupName), $username, $admincheck);
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
	 *	Get a new activation link
	*/

	public function getActivationLink ($pUser)
	{
		// Retrieve wiki and triplestore for further use
	
		$vWiki = $this->wiki;	
		$vTripleStore = $vWiki->services->get(TripleStore::class);

		// Let's create an activation key suitable for a mail

		$vKey = base64_encode(random_bytes ($vWiki->GetConfigValue ("user_activation_key_length")));

		// Store the key in the TripleStore
							
		$vTripleStore->Create ($pUser, TRIPLEPROPERTY_USER_ACTIVATIONKEY, $vKey, TRIPLERESSOURCEPREFIX_ACCOUNTSECURITY, TRIPLEPROPERTYPREFIX_ACCOUNTSECURITY);

		// Create and return the link
		
		return ($vWiki->GetConfigValue ("base_url")) . ACTIVATEUSERPAGE . "&" . ACTIONPARAMETER_ACTIVATEUSER_USER . "=" . $pUser . "&" . ACTIONPARAMETER_ACTIVATEUSER_KEY . "=" . (urlencode($vKey));
	}
	
	/** 
	 *	Get a new inactivation link
	 * @params : 
	 * - $pUser : the user name
	*/

	public function getInactivationLink ($pUser)
	{
		// Retrieve wiki and triplestore for further use
	
		$vWiki = $this->wiki;	
		$vTripleStore = $vWiki->services->get(TripleStore::class);

		// Let's create an activation key

		$vKey = base64_encode(random_bytes ($vWiki->GetConfigValue ("user_activation_key_length")));

		// Store the key in the TripleStore
							
		$vTripleStore->Create ($pUser, TRIPLEPROPERTY_USER_INACTIVATIONKEY, $vKey, TRIPLERESSOURCEPREFIX_ACCOUNTSECURITY, TRIPLEPROPERTYPREFIX_ACCOUNTSECURITY);

		// Create and return the link
		
		return ($vWiki->GetConfigValue ("base_url")) . INACTIVATEUSERPAGE . "&" . ACTIONPARAMETER_INACTIVATEUSER_USER . "=" . $pUser . "&" . ACTIONPARAMETER_INACTIVATEUSER_KEY . "=" .  (urlencode($vKey));
	}

	/* 
     * Send an activation link to a user
     * @params : 
     * - $pUser : the user name
	*/
	
	public function sendActivationLink ($pUser)
	{			
		// Ask for an activation link

		$vActivationLink = $this->getActivationLink ($pUser);
				
		// Send a mail to the user with the activation link
					
		$vUser = $this->getOneByName($pUser);
		
		$vMail = $vUser ["email"];
			
		return $this->wiki->services->get(Mailer::class)->sendEmailFromAdmin($vMail, 
					"Activation de votre compte", 
					"Cliquez sur ce lien ou copier/coller le dans la barre d'adresse de votre navigateur pour activer votre compte : " . 
						$vActivationLink, 
					"Cliquez sur ce lien ou copier/coller le dans la barre d'adresse de votre navigateur pour activer votre compte : " . 
						"<a href='" . $vActivationLink . "'>" . $vActivationLink . "</a>");
	}

	/* 
     * Indicates if the user account is currently activated
     * @params : 
     * - $pUser : the user name
	*/
	
	public function isActivated ($pUser)
	{			
		// Get activation status
	
		$vActivationStatus = $this->wiki->services->get(TripleStore::class)->GetOne ($pUser, TRIPLEPROPERTY_USER_ISACTIVATED, TRIPLERESSOURCEPREFIX_ACCOUNTSECURITY, TRIPLEPROPERTYPREFIX_ACCOUNTSECURITY);

		// Check if it is activated or not

		if ($vActivationStatus === TRIPLEVALUE_USER_ISACTIVATED_YES) $vIsActivated = true;
		else $vIsActivated = false;

		return $vIsActivated;
	}

	/* 
     * Activate a user account
     * @params : 
     * - $pUser : the user name
     * - $pKey : the activation key
     * - $pForce : boolean indicating if we want to ignore the key and force activation
	*/
	
	public function activateUser ($pUser, $pKey, $pForce = false)
	{	
		//////////////////////////////////////////
		// INITIALIZATION
		
		// Retrieve wiki and triplestore for further use
	
		$vWiki = $this->wiki;	
		$vTripleStore = $vWiki->services->get(TripleStore::class);

		// There is no alert message and no error yet. The user was not activated yet.
	
		$vAlertMessage = "";	
		$vError = false;
		$vActivated = false;	

		///////////////////////////////////////////
		// CHECK PARAMETERS

		// Validate user and key parameters

		if (empty ($pUser))
		{
			// Trying to call activation with bad parameters : should not occures since the user is using a link provided by yeswiki
			$vAlertMessage .= "Trying to call activation with bad parameters (empty user). ";
			$vError = true;
		}
			
		if (empty ($vWiki->LoadUser($pUser)))
		{
			// Trying to activate an inexistent user : the user might have been removed before
			$vAlertMessage .= "Trying to activate an inexistent user. ";
			$vError = true;
		}
		
		if (!$pForce) // We check the key if needed (through activation link)
		{
			if (empty ($pKey))
			{
				// Trying to call activation with bad parameters : should not occures since the user is using a link provided by yeswiki
				$vAlertMessage .= "Trying to call activation with bad parameters (empty key). ";
				$vError = true;
			}
			
			if (preg_match ('/[A-Za-z0-9+\/=]+/', $pKey) !== 1)
			{
				// The key has an invalid format. It should not occures since the user is using a link provided by yeswiki kernel
				$vAlertMessage .= "The activation key for user " . $pUser . "is in an invalid format : " . (base64_encode($pKey)) . " (base64 encoded). ";
				$vError = true;
			}
		}
	
		//////////////////////////////////////////
		// ACTIVATION 
	
		// if there was no error we continue the activation process
	
		if (!$vError)
		{	
			// Check the activation status of the user
			
			$vIsActivated = $vTripleStore->GetOne ($pUser, TRIPLEPROPERTY_USER_ISACTIVATED, TRIPLERESSOURCEPREFIX_ACCOUNTSECURITY, TRIPLEPROPERTYPREFIX_ACCOUNTSECURITY);

			if ($vIsActivated === TRIPLEVALUE_USER_ISACTIVATED_NO)
			{				
				// If the activation/inactivation key for the user exists or if we force activation (yeswiki core call)...
			
				if ($pForce || $vTripleStore->exist ($pUser, TRIPLEPROPERTY_USER_ACTIVATIONKEY, $pKey, TRIPLERESSOURCEPREFIX_ACCOUNTSECURITY, TRIPLEPROPERTYPREFIX_ACCOUNTSECURITY) !== null)
				{
	
					// ... We activate the account ...
								
					$vRes = $vTripleStore->update ($pUser, TRIPLEPROPERTY_USER_ISACTIVATED, TRIPLEVALUE_USER_ISACTIVATED_NO, TRIPLEVALUE_USER_ISACTIVATED_YES, TRIPLERESSOURCEPREFIX_ACCOUNTSECURITY, TRIPLEPROPERTYPREFIX_ACCOUNTSECURITY); 

					// 0 (succès)
					// 1 (échec)
					// 2 ($resource, $property, $oldvalue does not exist)
					// 3 ($resource, $property, $newvalue already exists)
							
					if ($vRes == 0) //succès
					{
						// We remove all obsolete activation keys from the database
					
						$vTripleStore->delete ($pUser, TRIPLEPROPERTY_USER_ACTIVATIONKEY, null, TRIPLERESSOURCEPREFIX_ACCOUNTSECURITY, TRIPLEPROPERTYPREFIX_ACCOUNTSECURITY);				

						$vActivated = true;						
					}		
					else
					{			
						$vError = true;						
						$vAlertMessage .= "Cannot update activation status for user " . $pUser . " (error code = " . $vRes . ")";						
					}							
				}
				else // ... else if the activation key is unknown we report it to the admin to deal with security issues
				{					
					// A activation link might have been clicked for a second time					
					$vAlertMessage .= "The activation key " . (base64_encode($pKey)) . " (base64 encoded) for user " . $pUser .  " is invalid. Security issue ? ";
					$vError = true;				
				}
			}
			else
			if ($vIsActivated === TRIPLEVALUE_USER_ISACTIVATED_YES)
			{
				// The account is already activated
				// An activation link might have been clicked for a second time
				// There is nothing to do
				
				$vActivated = true;
				
				echo ("The account is already activated. ");
			}
			else
			if ($vIsActivated == null)
			{				
				if ($pForce) // The activation status is not yet defined (account creation)
				{			
					// We activate the account 
								
					$vRes = $vTripleStore->create ($pUser, TRIPLEPROPERTY_USER_ISACTIVATED, TRIPLEVALUE_USER_ISACTIVATED_YES, TRIPLERESSOURCEPREFIX_ACCOUNTSECURITY, TRIPLEPROPERTYPREFIX_ACCOUNTSECURITY); 

					// 0 (succès)
					// 1 (échec)
					// 3 ($resource, $property, $value already exists) - should not occures since we tested it before
							
					if ($vRes == 0) //succès
					{
						// We remove all obsolete activation keys from the database
					
						$vTripleStore->delete ($pUser, TRIPLEPROPERTY_USER_ACTIVATIONKEY, null, TRIPLERESSOURCEPREFIX_ACCOUNTSECURITY, TRIPLEPROPERTYPREFIX_ACCOUNTSECURITY);				

						$vActivated = true;			
					}		
					else
					{		
						$vError = true;						
						$vAlertMessage .= "Cannot create activation status for user " . $pUser . " (error code = " . $vRes . ")";									
					}			
				}
				else
				{
					// It should not occures : the activation process should have added the key before.
					$vAlertMessage .= "It should not occures : the activation process should have added the IsActivated key before. ";
					$vError = true;
				}
			}
			else		
			{
				// Should not occures : security issue				
				$vAlertMessage .= "It should not occures : the DB might have been corrupted. ";
				$vError = true;
			}
		}
		
		if ($vAlertMessage !== "" && $vWiki->GetConfigValue ("debug") === "yes") echo ($vAlertMessage); // TODO : send $vAlertMessage to the security manager
		
		if ($vError && $vWiki->GetConfigValue ("debug") === "yes") echo ("Something wents wrong. ");

		return $vActivated;
	}	

	/* 
     * Inactivate a user account
     * @params : 
     * - $pUser : the user name
     * - $pKey : the activation key
     * - $pForce : boolean indicating if we want to ignore the key and force activation
	*/
	
	public function inactivateUser ($pUser, $pKey, $pForce = false)
	{				
		//////////////////////////////////////////
		// INITIALIZATION
		
		// Retrieve wiki and triplestore for further use
	
		$vWiki = $this->wiki;	
		$vTripleStore = $vWiki->services->get(TripleStore::class);

		// There is no alert message and no error yet. The user was not inactivated yet.
	
		$vAlertMessage = "";	
		$vError = false;
		$vInactivated = false;	

		///////////////////////////////////////////
		// CHECK PARAMETERS

		// Validate user and key parameters

		if (empty ($pUser))
		{
			// Trying to call inactivation with bad parameters : should not occures since the user is using a link provided by yeswiki
			$vAlertMessage .= "Trying to call inactivation with bad parameters (empty user). ";
			$vError = true;
		}
			
		if (empty ($vWiki->LoadUser($pUser)))
		{
			// Trying to inactivate an inexistent user : the user might have been removed before
			$vAlertMessage .= "Trying to inactivate an inexistent user. ";
			$vError = true;
		}
		
		if (!$pForce) // We check the key if needed (through activation link)
		{
			if (empty ($pKey))
			{
				// Trying to call inactivation with bad parameters : should not occures since the user is using a link provided by yeswiki
				$vAlertMessage .= "Trying to call inactivation with bad parameters (empty key). ";
				$vError = true;
			}
			
			if (preg_match ('/[A-Za-z0-9+\/=]+/', $pKey) !== 1)
			{
				// The key has an invalid format. It should not occures since the user is using a link provided by yeswiki kernel
				$vAlertMessage .= "The inactivation key for user " . $pUser . "is in an invalid format : " . (base64_encode($pKey)) . " (base64 encoded). ";
				$vError = true;
			}
		}

		//////////////////////////////////////////
		// INACTIVATION 
	
		// if there was no error we continue the inactivation process
	
		if (!$vError)
		{	
			// Check the activation status of the user
			
			$vIsActivated = $vTripleStore->GetOne ($pUser, TRIPLEPROPERTY_USER_ISACTIVATED, TRIPLERESSOURCEPREFIX_ACCOUNTSECURITY, TRIPLEPROPERTYPREFIX_ACCOUNTSECURITY);

			if ($vIsActivated === TRIPLEVALUE_USER_ISACTIVATED_YES)
			{			
				// If the activation/inactivation key for the user exists...
			
				if ($pForce || $vTripleStore->exist ($pUser, TRIPLEPROPERTY_USER_INACTIVATIONKEY, $pKey, TRIPLERESSOURCEPREFIX_ACCOUNTSECURITY, TRIPLEPROPERTYPREFIX_ACCOUNTSECURITY) !== null)
				{
	
					// ... We inactivate the account ...
								
					$vRes = $vTripleStore->update ($pUser, TRIPLEPROPERTY_USER_ISACTIVATED, TRIPLEVALUE_USER_ISACTIVATED_YES, TRIPLEVALUE_USER_ISACTIVATED_NO, TRIPLERESSOURCEPREFIX_ACCOUNTSECURITY, TRIPLEPROPERTYPREFIX_ACCOUNTSECURITY); 

					// 0 (succès)
					// 1 (échec)
					// 2 ($resource, $property, $oldvalue does not exist)
					// 3 ($resource, $property, $newvalue already exists)
							
					if ($vRes == 0) //succès
					{
						// We remove all obsolete inactivation keys from the database
					
						$vTripleStore->delete ($pUser, TRIPLEPROPERTY_USER_INACTIVATIONKEY, null, TRIPLERESSOURCEPREFIX_ACCOUNTSECURITY, TRIPLEPROPERTYPREFIX_ACCOUNTSECURITY);				

						$vInactivated = true;
					}		
					else
					{			
						$vError = true;						
						$vAlertMessage .= "Cannot update activation status for user " . $pUser . " (error code = " . $vRes . ")";						
					}							
				}
				else // ... else if the inactivation key is unknown we report it to the user and the admin
				{					
					// A inactivation link might have been clicked for a second time
					$vError = true;
					$vAlertMessage = "The inactivation key " . (base64_encode($pKey)) . " (base64 encoded) for user " . $pUser .  " is invalid. Security issue ?";
				}
			}
			else
			if ($vIsActivated === TRIPLEVALUE_USER_ISACTIVATED_NO)
			{
				// The account is already inactivated
				// An inactivation link might have been clicked for a second time
				// There is nothing to do
				
				$vInactivated = true;
			}
			else
			if ($vIsActivated == null)
			{				
				if ($pForce) // The activation status is not yet defined (account creation)
				{			
					// We create the status and inactivate the account 
								
					$vRes = $vTripleStore->create ($pUser, TRIPLEPROPERTY_USER_ISACTIVATED, TRIPLEVALUE_USER_ISACTIVATED_NO, TRIPLERESSOURCEPREFIX_ACCOUNTSECURITY, TRIPLEPROPERTYPREFIX_ACCOUNTSECURITY); 

					// 0 (succès)
					// 1 (échec)
					// 3 ($resource, $property, $value already exists) - should not occures since we tested it before
							
					if ($vRes == 0) //succès
					{
						// We remove all obsolete activation/inactivation keys from the database
					
						$vTripleStore->delete ($pUser, TRIPLEPROPERTY_USER_INACTIVATIONKEY, null, TRIPLERESSOURCEPREFIX_ACCOUNTSECURITY, TRIPLEPROPERTYPREFIX_ACCOUNTSECURITY);				

						$vInactivated = true;
					}		
					else
					{		
						$vError = true;						
						$vAlertMessage .= "Cannot create activation status for user " . $pUser . " (error code = " . $vRes . ")";									
					}			
				}
				else
				{
					// It should not occures : the inactivation process should have added the key before.
					$vAlertMessage .= "It should not occures : the inactivation process should have added the IsActivated key before. ";
					$vError = true;
				}
			}
			else		
			{
				// Should not occures : security issue
				// send $vAlertMessage to the security manager
				$vAlertMessage .= "It should not occures : the DB might have been corrupted. ";
				$vError = true;
			}
		}

		// If there is an alert me might signal it to the security manager - TO DO
		
		if ($vAlertMessage !== "" && $vWiki->GetConfigValue ("debug") === "yes") echo ($vAlertMessage); // TODO : send $vAlertMessage to the security manager
		
		if ($vError && $vWiki->GetConfigValue ("debug") === "yes") echo ("Something wents wrong. ");

		return $vInactivated;
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

<?php

namespace YesWiki\Core\Service;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use YesWiki\Bazar\Service\EntryManager;
use YesWiki\Bazar\Service\Guard;
use YesWiki\Security\Controller\SecurityController;
use YesWiki\Wiki;

class UserManager
{
    protected $wiki;
    protected $dbService;
    protected $securityController;
    protected $params;


    public function __construct(Wiki $wiki, DbService $dbService, ParameterBagInterface $params, SecurityController $securityController)
    {
        $this->wiki = $wiki;
        $this->dbService = $dbService;
        $this->securityController = $securityController;
        $this->params = $params;
    }

    public function getOneByName($name, $password = 0): ?array
    {
        return $this->dbService->loadSingle('select * from' . $this->dbService->prefixTable('users') . "where name = '" . $this->dbService->escape($name) . "' " . ($password === 0 ? "" : "and password = '" . $this->dbService->escape($password) . "'") . ' limit 1');
    }

    public function getOneByEmail($mail, $password = 0): ?array
    {
        return $this->dbService->loadSingle('select * from' . $this->dbService->prefixTable('users') . "where email = '" . $this->dbService->escape($mail) . "' " . ($password === 0 ? "" : "and password = '" . $this->dbService->escape($password) . "'") . ' limit 1');
    }

    public function getAll(): array
    {
        if ($this->params->has('user_table_prefix') && !empty($this->params->get('user_table_prefix'))) {
            $prefix = $this->params->get('user_table_prefix');
        } else {
            $prefix = $this->params->get('table_prefix');
        }

        return $this->dbService->loadAll('select * from ' . $prefix . 'users order by name');
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
            $name = $_SERVER["REMOTE_ADDR"];
        }
        return $name;
    }

    public function login($user, $remember = 0)
    {
        $_SESSION['user'] = $user;
        $this->wiki->SetPersistentCookie('name', $user['name'], $remember);
        $this->wiki->SetPersistentCookie('password', $user['password'], $remember);
        $this->wiki->SetPersistentCookie('remember', $remember, $remember);
    }

    public function logout()
    {
        $_SESSION['user'] = '';
        $this->wiki->DeleteCookie('name');
        $this->wiki->DeleteCookie('password');
        $this->wiki->DeleteCookie('remember');
    }

    public function create($wikiName, $email, $password)
    {
        if ($this->securityController->isWikiHibernated()) {
            throw new \Exception(_t('WIKI_IN_HIBERNATION'));
        }
        return $this->dbService->query(
            'INSERT INTO ' . $this->dbService->prefixTable('users') . 'SET ' .
            "signuptime = now(), " .
            "name = '" . $this->dbService->escape($wikiName) . "', " .
            "motto = '', " .
            "email = '" . $this->dbService->escape($email) . "', " .
            "password = md5('" . $this->dbService->escape($password) . "')"
        );
    }

    public function updateEmail($wikiName, $email)
    {
        if ($this->securityController->isWikiHibernated()) {
            throw new \Exception(_t('WIKI_IN_HIBERNATION'));
        }
        $user = $this->getOneByName($wikiName);
        if (!empty($user)) {
            $query =
                'UPDATE ' . $this->dbService->prefixTable('users') . 'SET ' .
                'email = "' . $this->dbService->escape($email) . '"'.
                ' WHERE name = "'.$this->dbService->escape($user['name']).'" '.
                'AND email= "'.$this->dbService->escape($user['email']).'" '.
                'AND password= "'.$this->dbService->escape($user['password']).'";';
            $this->dbService->query($query);
        }
    }
}

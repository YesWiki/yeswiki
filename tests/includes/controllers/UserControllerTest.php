<?php

namespace YesWiki\Test\Core\Controller;

use Throwable;
use YesWiki\Core\Controller\UserController;
use YesWiki\Core\Entity\User;
use YesWiki\Core\Exception\DeleteUserException;
use YesWiki\Core\Service\UserManager;
use YesWiki\Test\Core\YesWikiTestCase;
use YesWiki\Wiki;

require_once 'tests/YesWikiTestCase.php';

class UserControllerTest extends YesWikiTestCase
{
    /**
     * @covers UserController::__construct
     * @return Wiki $wiki
     */
    public function testUserControllerExisting(): Wiki
    {
        $wiki = $this->getWiki();
        $this->assertTrue($wiki->services->has(UserController::class));
        $this->assertTrue($wiki->services->has(UserManager::class));
        return $wiki;
    }

    /**
     * @depends testUserControllerExisting
     * @covers UserController::getFirstAdmin
     * @param Wiki $wiki
     * @return string $firstAdmin
     */
    public function testGetFirstAdmin(Wiki $wiki): string
    {
        $userController = $wiki->services->get(UserController::class);
        $firstAdmin = $userController->getFirstAdmin();
        $this->assertNotEmpty($firstAdmin);
        return $firstAdmin;
    }

    /**
     * @depends testUserControllerExisting
     * @depends testGetFirstAdmin
     * @covers UserController::delete
     * @dataProvider dataProviderTestDelete
     * @param string $connexionMode
     * @param bool $expectedResult
     * @param Wiki $wiki
     * @param string $firstAdmin
     */
    public function testDelete(string $connexionMode, bool $expectedResult, Wiki $wiki, string $firstAdmin)
    {
        $userController = $wiki->services->get(UserController::class);
        $userManager = $wiki->services->get(UserManager::class);

        // create a user
        do {
            $email = strtolower($this->randomString(10)).'@example.com';
        } while (!empty($userManager->getOneByEmail($email)));
        do {
            $name= $this->randomString(1, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ')
                .$this->randomString(25, 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789 -_');
        } while (!empty($userManager->getOneByName($name)));
        
        $password= $this->randomString(25, 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789 -_');

        
        $userManager->create($name, $email, $password);
        $user = $userManager->getOneByName($name);

        switch ($connexionMode) {
            case '!@admins':
                $userManager->login($user);
                break;
            // case '%':
                // not currently covered
            //     $userManager->login($user);
            //     break;
            case '@admins':
                $adminUser = $userManager->getOneByName($firstAdmin);
                $userManager->login($adminUser);
                break;
            case '!+':
            default:
                $userManager->logout();
                break;
        }

        $exceptionThrown = false;
        try {
            $userController->delete($user);
        } catch (DeleteUserException $ex) {
            $exceptionThrown = true;
        }

        $userDeleted = $userManager->getOneByName($name);

        // delete it after call to UserController::delete
        if (!empty($userDeleted)) {
            $userManager->delete($userDeleted);
        }
        $userManager->logout();

        // check tests
        if ($expectedResult) {
            $this->assertFalse($exceptionThrown);
            $this->assertNull($userDeleted);
        } else {
            $this->assertTrue($exceptionThrown);
            $this->assertInstanceOf(User::class, $userDeleted);
        }
    }

    public function dataProviderTestDelete()
    {
        // mode , mode, expected result
        return [
            'not connected' => ['!+',false],
            'not admin' => ['!@admins',false],
            'admin not current user' => ['@admins',true],
            // 'admin current user' => ['%',false], // not currently covered
        ];
    }

    public function dataProviderTestCreate()
    {
        // name, email, newValues, UserNameExist, EmailExist, Other Exception
        return [
            'email name all right' => ['newRandom','newRandom',[],false,false,false],
            'email with 5 chars ext' => ['newRandom','newRandom2',[],false,false,false],
            'name existing' => ['name of first user','newRandom',[],false,false,true],
            'empty name' => ['empty','newRandom',[],false,false,true],
            'email existing' => ['newRandom','email of first user',[],false,false,true],
            'empty email' => ['newRandom','empty',[],false,false,true],
        ];
    }

    /**
     * @depends testUserControllerExisting
     * @covers UserController::create
     * @dataProvider dataProviderTestCreate
     * @param string $name
     * @param string $email
     * @param array $newValues
     * @param bool $userNameExist
     * @param bool $emailExist
     * @param bool $otherException
     * @param Wiki $wiki
     */
    public function testCreate(
        string $name,
        string $email,
        array $newValues,
        bool $userNameExist,
        bool $emailExist,
        bool $otherException,
        Wiki $wiki
    ) {
        $userController = $wiki->services->get(UserController::class);
        $userManager = $wiki->services->get(UserManager::class);
        
        $users = $userManager->getAll();
        $firstUser = $users[array_key_first($users)];
        if ($name == 'newRandom') {
            do {
                $name= $this->randomString(1, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ')
                    .$this->randomString(25, 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789 -_');
            } while (!empty($userManager->getOneByName($name)));
        } elseif ($name == 'empty') {
            $name = "";
        } else {
            $name = $firstUser['name'];
        }
        if ($email == 'newRandom') {
            do {
                $email = strtolower($this->randomString(10)).'@example.com';
            } while (!empty($userManager->getOneByEmail($email)));
        } elseif ($email == 'newRandom2') {
            do {
                $email = strtolower($this->randomString(10)).'@xyz.earth';
            } while (!empty($userManager->getOneByEmail($email)));
        } elseif ($email == 'empty') {
            $email = "";
        } else {
            $email = $firstUser['email'];
        }
        $newValues['name'] = $name;
        $newValues['email'] = $email;
        $newValues['password'] = $this->randomString(25, 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789 -_');
        
        $exceptionThrown = false;
        $userNameAlreadyExist = false;
        $emailAlreadyExist = false;
        $exceptionMessage =  "";
        try {
            $userController->create($newValues);
            $user = $userManager->getOneByName($name);
        } catch (UserNameAlreadyUsedException $ex) {
            $userNameAlreadyExist = true;
        } catch (UserEmailAlreadyUsedException $ex) {
            $emailAlreadyExist = true;
        } catch (Throwable $ex) {
            $exceptionThrown = true;
            $exceptionMessage = $ex->getMessage();
        }
        try {
            if (!empty($user)) {
                $userManager->delete($user);
            }
        } catch (Throwable $th) {
        }

        if ($userNameExist) {
            $this->assertTrue($userNameAlreadyExist);
        } elseif ($emailExist) {
            $this->assertTrue($emailAlreadyExist);
        } elseif ($otherException) {
            $this->assertTrue($exceptionThrown);
        } else {
            $this->assertFalse($userNameAlreadyExist);
            $this->assertFalse($emailAlreadyExist);
            $this->assertEquals($exceptionMessage, "");
            $this->assertFalse($exceptionThrown);
            $this->assertInstanceOf(User::class, $user);
            $this->assertNotEmpty($user['name']);
            $this->assertEquals($user['name'], $name);
            $this->assertNotEmpty($user['email']);
            $this->assertEquals($user['email'], $email);
            foreach ([
                'changescount',
                'doubleclickedit',
                'motto',
                'revisioncount',
                'show_comments'
            ] as $propName) {
                if (isset($newValues[$propName])) {
                    $this->assertEquals($user[$propName], $newValues[$propName]);
                }
            }
        }
    }

    
    public function dataProviderTestSanitizeName()
    {
        // name,Other Exception
        return [
            'random string' => ['newRandom',false],
            'empty string' => ['',true],
            'not string' => ['',true],
            'tooo long string' => [$this->randomString(400),true],
            'forbidden \\' => ["{$this->randomString(2)}\\{$this->randomString(8)}",true],
            'forbidden /' => ["{$this->randomString(2)}/{$this->randomString(8)}",true],
            'forbidden <' => ["{$this->randomString(2)}<{$this->randomString(8)}",true],
            'forbidden >' => ["{$this->randomString(2)}>{$this->randomString(8)}",true],
            'forbidden begin !' => ["!{$this->randomString(8)}",true],
            'forbidden begin #' => ["#{$this->randomString(8)}",true],
            'forbidden begin @' => ["@{$this->randomString(8)}",true],
        ];
    }
    
    /**
     * @depends testUserControllerExisting
     * @depends testCreate
     * @depends testDelete
     * @covers UserController::sanitizeName
     * @dataProvider dataProviderTestSanitizeName
     * @param mixed $name
     * @param bool $otherException
     * @param Wiki $wiki
     */
    public function testSanitizeName($name, bool $otherException, Wiki $wiki)
    {
        $userController = $wiki->services->get(UserController::class);
        $userManager = $wiki->services->get(UserManager::class);
        if ($name == 'newRandom') {
            do {
                $name= $this->randomString(1, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ')
                    .$this->randomString(25, 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789 -_');
            } while (!empty($userManager->getOneByName($name)));
        }
        do {
            $email = strtolower($this->randomString(10)).'@example.com';
        } while (!empty($userManager->getOneByEmail($email)));
        $password = $this->randomString(25, 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789 -_');

        $exceptionThrown = false;
        $exceptionMessage =  "";
        try {
            $userController->create([
                'name' => $name,
                'email' => $email,
                'password' => $password
            ]);
            $user = $userManager->getOneByName($name);
        } catch (Throwable $ex) {
            $exceptionThrown = true;
            $exceptionMessage = $ex->getMessage();
        }
        try {
            if (!empty($user)) {
                $userManager->delete($user);
            }
        } catch (Throwable $th) {
        }

        if ($otherException) {
            $this->assertTrue($exceptionThrown);
        } else {
            $this->assertEquals($exceptionMessage, "");
            $this->assertFalse($exceptionThrown);
            $this->assertInstanceOf(User::class, $user);
        }
    }
    
    /**
     * gives a random string with ascii characters
     * @param int $length
     * @param string $charset optional list of chars
     * @return string
     */
    private function randomString(
        int $length,
        string $charset = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789'
    ): string {
        $output = "";
        $maxIndex = strlen($charset) -1;

        for ($i=0; $i < (max(1, $length)); $i++) {
            $output .= substr($charset, rand(0, $maxIndex), 1);
        }
        return $output;
    }
}

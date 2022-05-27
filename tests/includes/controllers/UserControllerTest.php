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

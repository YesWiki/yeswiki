<?php

namespace YesWiki\Test\Core\Service;

use YesWiki\Core\Service\UserManager;
use YesWiki\Test\Core\YesWikiTestCase;
use YesWiki\Login\UserSettingsAction;
use YesWiki\User;
use YesWiki\Wiki;

require_once 'tests/YesWikiTestCase.php';

class UserSettingsActionTest extends YesWikiTestCase
{
    /**
     * @return Wiki
     */
    public function testWikiExisting(): Wiki
    {
        $wiki = $this->getWiki();
        $this->assertTrue($wiki->services->has(Wiki::class));
        return $wiki->services->get(Wiki::class);
    }

    /**
     * @depends testWikiExisting
     * @covers UserSettingsAction::displayForm
     * @dataProvider displayFormProvider
     * @param Wiki $wiki
     */
    public function testDisplayForm($mode, Wiki $wiki)
    {
        switch ($mode) {
            case 'connected':
                $this->checkdisplayFormConnected($wiki);
                break;
            case 'not connected':
            default:
                $this->checkdisplayFormNotConnected($wiki);
                break;
        }
    }

    public function displayFormProvider()
    {
        // acl , expected
        return [
            'not connexted' => ['not connected'],
            'connected' => ['connected'],
            // 'admin' => ['admin'],
        ];
    }

    private function checkdisplayFormNotConnected(Wiki $wiki)
    {
        $output = $wiki->Format("{{usersettings}}");
        $rexExpStr = "/.*".implode('\s*', explode(' ', preg_quote('<input type="hidden" name="usersettings_action" value="signup" />', '/'))).".*/";
        $this->assertMatchesRegularExpression($rexExpStr, $output, "`usersettings_action` input badly set in user-signup-form.twig !");
        
        $rexExpStr = "/.*".implode('\s*', explode(' ', preg_quote('<input class="', '/').'.*'.preg_quote('" name="name"', '/'))).".*/";
        $this->assertMatchesRegularExpression($rexExpStr, $output, "`name` input badly set in user-signup-form.twig !");

        $rexExpStr = "/.*".implode('\s*', explode(' ', preg_quote('<input class="', '/').'.*'.preg_quote('" name="email"', '/'))).".*/";
        $this->assertMatchesRegularExpression($rexExpStr, $output, "`email` input badly set in user-signup-form.twig !");

        $rexExpStr = "/.*".implode('\s*', explode(' ', preg_quote('<input class="', '/').'.*'.preg_quote('" type="password" name="password"', '/'))).".*/";
        $this->assertMatchesRegularExpression($rexExpStr, $output, "`password` input badly set in user-signup-form.twig !");

        $rexExpStr = "/.*".implode('\s*', explode(' ', preg_quote('<input class="', '/').'.*'.preg_quote('" type="password" name="confpassword"', '/'))).".*/";
        $this->assertMatchesRegularExpression($rexExpStr, $output, "`confpassword` input badly set in user-signup-form.twig !");
    }

    private function checkdisplayFormConnected(Wiki $wiki)
    {
        $userManager = $wiki->services->get(UserManager::class);
        $users = $userManager->getAll();
        
        // use first user
        $user = $users[0];
        $email = $user['email'];
        $name = $user['name'];

        // login
        $userManager->login($user);

        $output = $wiki->Format("{{usersettings}}");
        // logout
        $userManager->logout();
        $this->assertIsArray($user);

        $rexExpStr = "/.*".implode('\s*', explode(' ', preg_quote('<input type="hidden" name="usersettings_action" value="update', '/'))).".*/";
        $this->assertMatchesRegularExpression($rexExpStr, $output, "`usersettings_action` input badly set for update in usersettings.twig !");

        $rexExpStr = "/.*".implode('\s*', explode(' ', preg_quote('<input type="hidden" name="usersettings_action" value="changepass"', '/'))).".*/";
        $this->assertMatchesRegularExpression($rexExpStr, $output, "`usersettings_action` input badly set for changepass in usersettings.twig !");

        $rexExpStr = "/.*".implode(
            '\s*',
            explode(
                ' ',
                preg_quote('<input class="', '/').'.*'.preg_quote('" name="email" ', '/').'(size\=".*" )?'.preg_quote('value="'.htmlentities($email).'"', '/')
            )
        ).".*/";
        $this->assertMatchesRegularExpression($rexExpStr, $output, "`email` input badly set in usersettings.twig !");

        $rexExpStr = "/.*".implode('\s*', explode(' ', preg_quote('<input type="hidden" name="csrf-token-update" value="', '/'))).".*/";
        $this->assertMatchesRegularExpression($rexExpStr, $output, "`csrf-token-update` input badly set in usersettings.twig !");

        $rexExpStr = "/.*".implode('\s*', explode(' ', preg_quote('<input type="hidden" name="csrf-token-changepass" value="', '/'))).".*/";
        $this->assertMatchesRegularExpression($rexExpStr, $output, "`csrf-token-changepass` input badly set in usersettings.twig !");

        $rexExpStr = "/.*".implode('\s*', explode(' ', preg_quote('<input class="', '/').'.*'.preg_quote('" type="password" name="password"', '/'))).".*/";
        $this->assertMatchesRegularExpression($rexExpStr, $output, "`password` input badly set in usersettings.twig !");

        $rexExpStr = "/.*".implode('\s*', explode(' ', preg_quote('<input class="', '/').'.*'.preg_quote('" type="password" name="oldpass"', '/'))).".*/";
        $this->assertMatchesRegularExpression($rexExpStr, $output, "`oldpass` input badly set in usersettings.twig !");
    }
    
    /**
     * @depends testWikiExisting
     * @depends testDisplayForm
     * @covers UserSettingsAction::displayForm
     * @param Wiki $wiki
     */
    public function testDisplayFormNotConnectedWithPostData(Wiki $wiki)
    {
        $email = strtolower($this->randomString(10)).'@example.com';
        $name= $this->randomString(25, 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789 -_');
        $_POST['email'] = $email;
        $_POST['name'] = $name;
        $output = $wiki->Format("{{usersettings}}");
        
        $rexExpStr = "/.*".implode(
            '\s*',
            explode(
                ' ',
                preg_quote('<input class="', '/').'.*'.preg_quote('" name="name" ', '/').'(size\=".*" )?'.preg_quote('value="'.htmlentities($name).'"', '/')
            )
        ).".*/";
        $this->assertMatchesRegularExpression($rexExpStr, $output, "`name` input badly set in user-signup-form.twig !");

        $rexExpStr = "/.*".implode(
            '\s*',
            explode(
                ' ',
                preg_quote('<input class="', '/').'.*'.preg_quote('" name="email" ', '/').'(size\=".*" )?'.preg_quote('value="'.htmlentities($email).'"', '/')
            )
        ).".*/";
        $this->assertMatchesRegularExpression($rexExpStr, $output, "`email` input badly set in user-signup-form.twig !");

        
        $rexExpStr = "/.*".implode('\s*', explode(' ', preg_quote('<input type="hidden" name="usersettings_action" value="signup" />', '/'))).".*/";
        $this->assertMatchesRegularExpression($rexExpStr, $output, "`usersettings_action` input badly set in user-signup-form.twig !");

        unset($_POST['email']);
        unset($_POST['name']);
    }

    /**
     * @depends testWikiExisting
     * @depends testDisplayForm
     * @covers UserSettingsAction::signup cover only bad request because right request do a redirect with exit which stops the test
     * @param Wiki $wiki
     */
    public function testSignup(Wiki $wiki)
    {
        $userManager = $wiki->services->get(UserManager::class);

        do {
            $email = strtolower($this->randomString(10)).'@example.com';
        } while (!empty($userManager->getOneByEmail($email)));
        do {
            $name= $this->randomString(1, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ')
                .$this->randomString(25, 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789 -_');
        } while (!empty($userManager->getOneByName($name)));
        
        $password= $this->randomString(25, 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789 -_');

        $_POST['email'] = $email;
        $_POST['name'] = $name;
        $_POST['password'] = $password;
        $_POST['confpassword'] = $password.'error'; // do not set rigth password to prevent redirecting and exit
        // YesWiki is not yet ready to prevent exit() when cli mode.
        $_REQUEST['usersettings_action'] = 'signup';

        $output = $wiki->Format("{{usersettings}}");

        unset($_POST['email']);
        unset($_POST['name']);
        unset($_POST['password']);
        unset($_POST['confpassword']);
        unset($_REQUEST['usersettings_action']);
        $user = $userManager->getOneByName($name);
        $this->assertIsNotArray($user);
        
        $rexExpStr = "/.*".implode(
            '\s*',
            explode(
                ' ',
                preg_quote('<input class="', '/').'.*'.preg_quote('" name="name" ', '/').'(size\=".*" )?'.preg_quote('value="'.htmlentities($name).'"', '/')
            )
        ).".*/";
        $this->assertMatchesRegularExpression($rexExpStr, $output, "`name` input badly set in user-signup-form.twig !");

        $rexExpStr = "/.*".implode(
            '\s*',
            explode(
                ' ',
                preg_quote('<input class="', '/').'.*'.preg_quote('" name="email" ', '/').'(size\=".*" )?'.preg_quote('value="'.htmlentities($email).'"', '/')
            )
        ).".*/";
        $this->assertMatchesRegularExpression($rexExpStr, $output, "`email` input badly set in user-signup-form.twig !");

        $rexExpStr = "/.*".implode('\s*', explode(' ', preg_quote('<input class="', '/').'.*'.preg_quote('" type="password" name="password"', '/'))).".*/";
        $this->assertMatchesRegularExpression($rexExpStr, $output, "`password` input badly set in user-signup-form.twig !");

        $rexExpStr = "/.*".implode('\s*', explode(' ', preg_quote('<input class="', '/').'.*'.preg_quote('" type="password" name="confpassword"', '/'))).".*/";
        $this->assertMatchesRegularExpression($rexExpStr, $output, "`confpassword` input badly set in user-signup-form.twig !");
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

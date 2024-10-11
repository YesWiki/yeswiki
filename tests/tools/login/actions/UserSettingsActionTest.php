<?php

namespace YesWiki\Test\Core\Service;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use YesWiki\Core\Controller\AuthController;
use YesWiki\Core\Entity\User;
use YesWiki\Core\Exception\ExitException;
use YesWiki\Core\Service\UserManager;
use YesWiki\Test\Core\YesWikiTestCase;
use YesWiki\Wiki;

require_once 'tests/YesWikiTestCase.php';

class UserSettingsActionTest extends YesWikiTestCase
{
    public function testWikiExisting(): Wiki
    {
        $wiki = $this->getWiki();
        $this->assertTrue($wiki->services->has(Wiki::class));

        return $wiki->services->get(Wiki::class);
    }

    /**
     * @depends testWikiExisting
     * @covers \UserSettingsAction::displayForm
     * @dataProvider displayFormProvider
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
            'not connected' => ['not connected'],
            'connected' => ['connected'],
            // 'admin' => ['admin'],
        ];
    }

    private function checkdisplayFormNotConnected(Wiki $wiki)
    {
        $this->ensureCacheFolderIsWritable();
        $output = $wiki->Format('{{usersettings}}');
        $rexExpStr = '/.*' . implode('\s*', explode(' ', preg_quote('<input type="hidden" name="usersettings_action" value="signup" />', '/'))) . '.*/';
        $this->assertMatchesRegularExpression($rexExpStr, $output, '`usersettings_action` input badly set in user-signup-form.twig !');

        $rexExpStr = '/.*' . implode('\s*', explode(' ', preg_quote('<input class="', '/') . '.*' . preg_quote('" name="name"', '/'))) . '.*/';
        $this->assertMatchesRegularExpression($rexExpStr, $output, '`name` input badly set in user-signup-form.twig !');

        $rexExpStr = '/.*' . implode('\s*', explode(' ', preg_quote('<input class="', '/') . '.*' . preg_quote('" name="email"', '/'))) . '.*/';
        $this->assertMatchesRegularExpression($rexExpStr, $output, '`email` input badly set in user-signup-form.twig !');

        $rexExpStr = '/.*' . implode('\s*', explode(' ', preg_quote('<input class="', '/') . '.*' . preg_quote('" type="password" name="password"', '/'))) . '.*/';
        $this->assertMatchesRegularExpression($rexExpStr, $output, '`password` input badly set in user-signup-form.twig !');

        $rexExpStr = '/.*' . implode('\s*', explode(' ', preg_quote('<input class="', '/') . '.*' . preg_quote('" type="password" name="confpassword"', '/'))) . '.*/';
        $this->assertMatchesRegularExpression($rexExpStr, $output, '`confpassword` input badly set in user-signup-form.twig !');
    }

    private function checkdisplayFormConnected(Wiki $wiki)
    {
        $userManager = $wiki->services->get(UserManager::class);
        $authController = $wiki->services->get(AuthController::class);
        $users = $userManager->getAll();

        // use first user
        $user = $users[0];
        $email = $user['email'];
        $name = $user['name'];

        $this->ensureCacheFolderIsWritable();

        // login
        $authController->login($user);

        $output = $wiki->Format('{{usersettings}}');
        // logout
        $authController->logout();
        $this->assertInstanceOf(User::class, $user);

        $rexExpStr = '/.*' . implode('\s*', explode(' ', preg_quote('<input type="hidden" name="usersettings_action" value="update', '/'))) . '.*/';
        $this->assertMatchesRegularExpression($rexExpStr, $output, '`usersettings_action` input badly set for update in usersettings.twig !');

        $rexExpStr = '/.*' . implode('\s*', explode(' ', preg_quote('<input type="hidden" name="usersettings_action" value="changepass"', '/'))) . '.*/';
        $this->assertMatchesRegularExpression($rexExpStr, $output, '`usersettings_action` input badly set for changepass in usersettings.twig !');

        $rexExpStr = '/.*' . implode(
            '\s*',
            explode(
                ' ',
                preg_quote('<input class="', '/') . '.*' . preg_quote('" name="email" ', '/') . '(size\=".*" )?' . preg_quote('value="' . htmlentities($email) . '"', '/')
            )
        ) . '.*/';
        $this->assertMatchesRegularExpression($rexExpStr, $output, '`email` input badly set in usersettings.twig !');

        $rexExpStr = '/.*' . implode('\s*', explode(' ', preg_quote('<input type="hidden" name="csrf-token-update" value="', '/'))) . '.*/';
        $this->assertMatchesRegularExpression($rexExpStr, $output, '`csrf-token-update` input badly set in usersettings.twig !');

        $rexExpStr = '/.*' . implode('\s*', explode(' ', preg_quote('<input type="hidden" name="csrf-token-changepass" value="', '/'))) . '.*/';
        $this->assertMatchesRegularExpression($rexExpStr, $output, '`csrf-token-changepass` input badly set in usersettings.twig !');

        $rexExpStr = '/.*' . implode('\s*', explode(' ', preg_quote('<input class="', '/') . '.*' . preg_quote('" type="password" name="password"', '/'))) . '.*/';
        $this->assertMatchesRegularExpression($rexExpStr, $output, '`password` input badly set in usersettings.twig !');

        $rexExpStr = '/.*' . implode('\s*', explode(' ', preg_quote('<input class="', '/') . '.*' . preg_quote('" type="password" name="oldpass"', '/'))) . '.*/';
        $this->assertMatchesRegularExpression($rexExpStr, $output, '`oldpass` input badly set in usersettings.twig !');
    }

    /**
     * @depends testWikiExisting
     * @depends testDisplayForm
     * @covers \UserSettingsAction::displayForm
     */
    public function testDisplayFormNotConnectedWithPostData(Wiki $wiki)
    {
        $email = strtolower($this->randomString(10)) . '@example.com';
        $name = $this->randomString(25, 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789 -_');
        $_POST['email'] = $email;
        $_POST['name'] = $name;

        $this->ensureCacheFolderIsWritable();

        $output = $wiki->Format('{{usersettings}}');

        $rexExpStr = '/.*' . implode(
            '\s*',
            explode(
                ' ',
                preg_quote('<input class="', '/') . '.*' . preg_quote('" name="name" ', '/') . '(size\=".*" )?' . preg_quote('value="' . htmlentities($name) . '"', '/')
            )
        ) . '.*/';
        $this->assertMatchesRegularExpression($rexExpStr, $output, '`name` input badly set in user-signup-form.twig !');

        $rexExpStr = '/.*' . implode(
            '\s*',
            explode(
                ' ',
                preg_quote('<input class="', '/') . '.*' . preg_quote('" name="email" ', '/') . '(size\=".*" )?' . preg_quote('value="' . htmlentities($email) . '"', '/')
            )
        ) . '.*/';
        $this->assertMatchesRegularExpression($rexExpStr, $output, '`email` input badly set in user-signup-form.twig !');

        $rexExpStr = '/.*' . implode('\s*', explode(' ', preg_quote('<input type="hidden" name="usersettings_action" value="signup" />', '/'))) . '.*/';
        $this->assertMatchesRegularExpression($rexExpStr, $output, '`usersettings_action` input badly set in user-signup-form.twig !');

        unset($_POST['email']);
        unset($_POST['name']);
    }

    public function dataProvidertestSignup()
    {
        // mode , suffix, expected result
        return [
            'bad signup' => ['error', false],
            'good signup' => ['', true],
        ];
    }

    /**
     * @depends testWikiExisting
     * @depends testDisplayForm
     * @dataProvider dataProvidertestSignup
     * @covers \UserSettingsAction::signup
     *
     * @param string $suffix
     * @param bool   $expectedResult
     */
    public function testSignup($suffix, $expectedResult, Wiki $wiki)
    {
        $userManager = $wiki->services->get(UserManager::class);
        $authController = $wiki->services->get(AuthController::class);
        $params = $wiki->services->get(ParameterBagInterface::class);
        if ($params->get('use_captcha')) {
            // is currently not possible to test with captach activated
            $this->assertTrue($params->get('use_captcha'));
        } else {
            do {
                $email = strtolower($this->randomString(10)) . '@example.com';
            } while (!empty($userManager->getOneByEmail($email)));
            do {
                $name = $this->randomString(1, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ')
                    . $this->randomString(25, 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789 -_');
            } while (!empty($userManager->getOneByName($name)));

            $password = $this->randomString(25, 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789 -_');

            $_POST['email'] = $email;
            $_POST['name'] = $name;
            $_POST['password'] = $password;
            $_POST['confpassword'] = $password . $suffix;
            $_REQUEST['usersettings_action'] = 'signup';

            $this->ensureCacheFolderIsWritable();

            $exitExceptionCaught = false;
            try {
                $output = $wiki->Format('{{usersettings}}');
            } catch (ExitException $e) {
                $exitExceptionCaught = true;
            }

            unset($_POST['email']);
            unset($_POST['name']);
            unset($_POST['password']);
            unset($_POST['confpassword']);
            unset($_REQUEST['usersettings_action']);
            $user = $userManager->getOneByName($name);
            $connectedUser = $authController->getLoggedUser();
            //clean user before tests
            if (!empty($user['name'])) {
                $userManager->delete($user);
            }

            if ($expectedResult) {
                $this->assertTrue($exitExceptionCaught);
                $this->assertInstanceOf(User::class, $user);
                $this->assertIsArray($connectedUser);
                $this->assertNotEmpty($connectedUser['name']);
                $this->assertEquals($connectedUser['name'], $user['name']);
            } else {
                $this->assertFalse($exitExceptionCaught);
                $this->assertIsNotArray($user);
                $this->assertNotInstanceOf(User::class, $user);

                $rexExpStr = '/.*' . implode(
                    '\s*',
                    explode(
                        ' ',
                        preg_quote('<input class="', '/') . '.*' . preg_quote('" name="name" ', '/') . '(size\=".*" )?' . preg_quote('value="' . htmlentities($name) . '"', '/')
                    )
                ) . '.*/';
                $this->assertMatchesRegularExpression($rexExpStr, $output, '`name` input badly set in user-signup-form.twig !');

                $rexExpStr = '/.*' . implode(
                    '\s*',
                    explode(
                        ' ',
                        preg_quote('<input class="', '/') . '.*' . preg_quote('" name="email" ', '/') . '(size\=".*" )?' . preg_quote('value="' . htmlentities($email) . '"', '/')
                    )
                ) . '.*/';
                $this->assertMatchesRegularExpression($rexExpStr, $output, '`email` input badly set in user-signup-form.twig !');

                $rexExpStr = '/.*' . implode('\s*', explode(' ', preg_quote('<input class="', '/') . '.*' . preg_quote('" type="password" name="password"', '/'))) . '.*/';
                $this->assertMatchesRegularExpression($rexExpStr, $output, '`password` input badly set in user-signup-form.twig !');

                $rexExpStr = '/.*' . implode('\s*', explode(' ', preg_quote('<input class="', '/') . '.*' . preg_quote('" type="password" name="confpassword"', '/'))) . '.*/';
                $this->assertMatchesRegularExpression($rexExpStr, $output, '`confpassword` input badly set in user-signup-form.twig !');
            }
        }
    }

    /**
     * gives a random string with ascii characters.
     *
     * @param string $charset optional list of chars
     */
    private function randomString(
        int $length,
        string $charset = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789'
    ): string {
        $output = '';
        $maxIndex = strlen($charset) - 1;

        for ($i = 0; $i < (max(1, $length)); $i++) {
            $output .= substr($charset, rand(0, $maxIndex), 1);
        }

        return $output;
    }

    /**
     * ensure the cache folder is writable before tests.
     */
    private function ensureCacheFolderIsWritable()
    {
        // cache folder should be writable to ensure that twig template cache system works
        $this->assertTrue(is_dir('cache'), 'The cache folder is not existing !');
        $this->assertTrue(is_writable('cache'), 'The cache folder is not writable !');
    }
}

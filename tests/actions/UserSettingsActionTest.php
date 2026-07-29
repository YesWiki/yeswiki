<?php

namespace YesWiki\Test\Core\Service;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Depends;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Request;
use YesWiki\Identity\Entity\User;
use YesWiki\Identity\Service\AuthenticationService;
use YesWiki\Identity\Service\UserManager;
use YesWiki\Kernel\Exception\ExitException;
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

    #[Depends('testWikiExisting')]
    #[DataProvider('displayFormProvider')]
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

    public static function displayFormProvider()
    {
        // acl , expected
        return [
            'not connected' => ['not connected'],
            'connected' => ['connected'],
        ];
    }

    private function checkdisplayFormNotConnected(Wiki $wiki)
    {
        $this->ensureCacheFolderIsWritable();
        $output = $wiki->services->get(\YesWiki\Render\Service\MarkdownFormatterService::class)->format('{{usersettings}}');
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
        $authenticationService = $wiki->services->get(AuthenticationService::class);
        $users = $userManager->getAll();

        // use first user
        $user = $users[0];
        $email = $user['email'];
        $name = $user['name'];

        $this->ensureCacheFolderIsWritable();

        // login
        $authenticationService->login($user);

        $output = $wiki->services->get(\YesWiki\Render\Service\MarkdownFormatterService::class)->format('{{usersettings}}');
        // logout
        $authenticationService->logout();
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

    #[Depends('testWikiExisting')]
    #[Depends('testDisplayForm')]
    public function testDisplayFormNotConnectedWithPostData(Wiki $wiki)
    {
        $email = strtolower($this->randomString(10)) . '@example.com';
        $name = $this->randomString(25, 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789 -_');
        $_POST['email'] = $email;
        $_POST['name'] = $name;
        $this->refreshRequest($wiki);

        $this->ensureCacheFolderIsWritable();

        $output = $wiki->services->get(\YesWiki\Render\Service\MarkdownFormatterService::class)->format('{{usersettings}}');

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

    public static function dataProvidertestSignup()
    {
        // mode , suffix, expected result
        return [
            'bad signup' => ['error', false],
            'good signup' => ['', true],
        ];
    }

    #[Depends('testWikiExisting')]
    #[Depends('testDisplayForm')]
    #[DataProvider('dataProvidertestSignup')]
    public function testSignup($suffix, $expectedResult, Wiki $wiki)
    {
        $userManager = $wiki->services->get(UserManager::class);
        $authenticationService = $wiki->services->get(AuthenticationService::class);
        $params = $wiki->services->get(ParameterBagInterface::class);
        if ($params->get('use_captcha')) {
            // is currently not possible to test with captach activated
            $this->assertTrue($params->get('use_captcha'));
        } else {
            do {
                $email = strtolower($this->randomString(10)) . '@example.com';
            } while (!empty($userManager->getOneByEmail($email)));
            do {
                // trim: UserManager::create() trims the name, so a random name with a trailing
                // space would be stored trimmed and no longer be found by getOneByName()
                $name = trim($this->randomString(1, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ')
                    . $this->randomString(25, 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789 -_'));
            } while (!empty($userManager->getOneByName($name)));

            $password = $this->randomString(25, 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789 -_');

            $_POST['email'] = $email;
            $_POST['name'] = $name;
            $_POST['password'] = $password;
            $_POST['confpassword'] = $password . $suffix;
            // must be $_POST (not $_REQUEST): UserSettingsAction resolves the
            // action name from the Symfony Request's GET+POST bags, which are
            // built from $_GET/$_POST, never from $_REQUEST
            $_POST['usersettings_action'] = 'signup';
            $this->refreshRequest($wiki);

            $this->ensureCacheFolderIsWritable();

            $exitExceptionCaught = false;
            try {
                $output = $wiki->services->get(\YesWiki\Render\Service\MarkdownFormatterService::class)->format('{{usersettings}}');
            } catch (ExitException $e) {
                $exitExceptionCaught = true;
            }

            unset($_POST['email']);
            unset($_POST['name']);
            unset($_POST['password']);
            unset($_POST['confpassword']);
            unset($_POST['usersettings_action']);
            // (string) cast: PHPStan remembers the pre-creation getOneByName($name) null from
            // the uniqueness loop above and would flag the null check below as always-false --
            // but the {{usersettings}} action run just created this very user
            $user = $userManager->getOneByName((string)$name);
            $connectedUser = $authenticationService->getLoggedUser();
            // clean user before tests
            if ($user !== null) {
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
                $this->assertNull($user);

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

        for ($i = 0; $i < max(1, $length); $i++) {
            $output .= substr($charset, rand(0, $maxIndex), 1);
        }

        return $output;
    }

    /**
     * Wiki::$request (a Symfony Request) is built once from the superglobals
     * when Wiki is constructed and is never re-synced afterwards; mutating
     * $_POST/$_GET in a test has no effect on it unless it is rebuilt.
     */
    private function refreshRequest(Wiki $wiki)
    {
        $wiki->request = Request::createFromGlobals();
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

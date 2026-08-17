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
use YesWiki\YesWikiRuntime;

require_once 'tests/YesWikiTestCase.php';

class UserSettingsActionTest extends YesWikiTestCase
{
    public function testWikiExisting(): YesWikiRuntime
    {
        $wiki = $this->getWiki();
        $this->assertTrue($wiki->services->has(YesWikiRuntime::class));

        return $wiki->services->get(YesWikiRuntime::class);
    }

    #[Depends('testWikiExisting')]
    #[DataProvider('displayFormProvider')]
    public function testDisplayForm($mode, YesWikiRuntime $wiki)
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
        return [
            'not connected' => ['not connected'],
            'connected' => ['connected'],
        ];
    }

    private function checkdisplayFormNotConnected(YesWikiRuntime $wiki)
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

    private function checkdisplayFormConnected(YesWikiRuntime $wiki)
    {
        $userManager = $wiki->services->get(UserManager::class);
        $authenticationService = $wiki->services->get(AuthenticationService::class);
        $users = $userManager->getAll();

        $user = $users[0];
        $email = $user['email'];
        $name = $user['name'];

        $this->ensureCacheFolderIsWritable();

        $authenticationService->login($user);

        $output = $wiki->services->get(\YesWiki\Render\Service\MarkdownFormatterService::class)->format('{{usersettings}}');

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
    public function testDisplayFormNotConnectedWithPostData(YesWikiRuntime $wiki)
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
        return [
            'bad signup' => ['error', false],
            'good signup' => ['', true],
        ];
    }

    #[Depends('testWikiExisting')]
    #[Depends('testDisplayForm')]
    #[DataProvider('dataProvidertestSignup')]
    public function testSignup($suffix, $expectedResult, YesWikiRuntime $wiki)
    {
        $userManager = $wiki->services->get(UserManager::class);
        $authenticationService = $wiki->services->get(AuthenticationService::class);
        $params = $wiki->services->get(ParameterBagInterface::class);
        if ($params->get('use_captcha')) {
            $this->assertTrue($params->get('use_captcha'));
        } else {
            do {
                $email = strtolower($this->randomString(10)) . '@example.com';
            } while (!empty($userManager->getOneByEmail($email)));
            do {
                $name = trim($this->randomString(1, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ')
                    . $this->randomString(25, 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789 -_'));
            } while (!empty($userManager->getOneByName($name)));

            $password = $this->randomString(25, 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789 -_');

            $_POST['email'] = $email;
            $_POST['name'] = $name;
            $_POST['password'] = $password;
            $_POST['confpassword'] = $password . $suffix;

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

            $user = $userManager->getOneByName((string)$name);
            $connectedUser = $authenticationService->getLoggedUser();

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
     * Wiki::$request (a Symfony Request) is built once from the superglobals when Wiki is constructed and is never re-synced afterwards; mutating $_POST/$_GET in a test has no effect on it unless it is rebuilt.
     */
    private function refreshRequest(YesWikiRuntime $wiki)
    {
        $wiki->services->get(\YesWiki\Kernel\Service\CurrentRequest::class)->replace(Request::createFromGlobals());
    }

    /** ensure the cache folder is writable before tests. */
    private function ensureCacheFolderIsWritable()
    {
        $this->assertTrue(is_dir('cache'), 'The cache folder is not existing !');
        $this->assertTrue(is_writable('cache'), 'The cache folder is not writable !');
    }
}

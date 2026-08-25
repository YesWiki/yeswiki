<?php

namespace YesWiki\Test\Identity;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Depends;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Request;
use YesWiki\Content\Entity\ContentTypeSchema;
use YesWiki\Content\Entity\FieldRole;
use YesWiki\Content\Service\FieldRoleResolver;
use YesWiki\Content\Service\FormManager;
use YesWiki\Core\YesWikiRuntime;
use YesWiki\Files\Service\AttachedFilePaths;
use YesWiki\Identity\Action\LoginAction;
use YesWiki\Identity\Entity\User;
use YesWiki\Identity\Service\AuthenticationService;
use YesWiki\Identity\Service\AvatarService;
use YesWiki\Identity\Service\UserManager;
use YesWiki\Kernel\Service\CurrentRequest;
use YesWiki\Kernel\Service\PageContext;
use YesWiki\Kernel\Service\UrlFormatter;
use YesWiki\Render\Service\MarkdownFormatterService;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/** The account button: an icon on the way in, your face once you are in. */
class AccountFaceTest extends YesWikiTestCase
{
    private const PROBE = 'AccountFaceTestUser';
    private const PICTURED = 'AccountFacePicturedUser';

    public function testWikiExisting(): YesWikiRuntime
    {
        $wiki = $this->getWiki();
        $GLOBALS['yeswikiServices'] = $wiki->services;
        $this->assertTrue($wiki->services->has(AvatarService::class));

        return $wiki;
    }

    #[Depends('testWikiExisting')]
    public function testTheFaceIsTheFirstTwoLettersOfTheName(YesWikiRuntime $wiki): void
    {
        $avatars = $wiki->services->get(AvatarService::class);

        $this->assertSame('FL', $avatars->forName('FlorianSchmitt')->initials);
        $this->assertSame('AB', $avatars->forName('ab')->initials, 'upper-cased, whatever was typed');
        $this->assertSame('X', $avatars->forName('X')->initials, 'a one-letter name has one letter');
        $this->assertSame('ZO', $avatars->forName('Zoé')->initials, 'counted in characters, not bytes');

        $this->assertSame('?', $avatars->forName('')->initials);
    }

    #[Depends('testWikiExisting')]
    public function testTheColourIsAFunctionOfTheName(YesWikiRuntime $wiki): void
    {
        $avatars = $wiki->services->get(AvatarService::class);

        $once = $avatars->forName('SomeAccount');
        $again = $avatars->forName('SomeAccount');
        $this->assertSame($once->background, $again->background, 'the same account is the same colour, always');
        $this->assertNotSame(
            $once->background,
            $avatars->forName('SomeOtherAccount')->background,
            'two accounts that are told apart by their letters are told apart by their colour too'
        );
    }

    /**
     * @return array<string, array{string}>
     */
    public static function nameProvider(): array
    {
        $names = [];
        foreach (['a', 'Bob', 'CharlieDelta', 'Éric', 'zzz', 'Wiki', 'x9', '__system__'] as $name) {
            $names[$name] = [$name];
        }

        return $names;
    }

    /**
     * The point of picking between black and white rather than always writing in white: a light background with white letters on it is a blank disc.
     */
    #[Depends('testWikiExisting')]
    #[DataProvider('nameProvider')]
    public function testTheLettersAreLegibleOnWhateverColourCameOut(string $name, YesWikiRuntime $wiki): void
    {
        $avatar = $wiki->services->get(AvatarService::class)->forName($name);

        $this->assertMatchesRegularExpression('/^#[0-9a-f]{6}$/', $avatar->background);
        $this->assertContains($avatar->foreground, ['#000000', '#ffffff']);

        $contrast = $this->contrastRatio($avatar->background, $avatar->foreground);
        $other = $this->contrastRatio($avatar->background, $avatar->foreground === '#000000' ? '#ffffff' : '#000000');
        $this->assertGreaterThanOrEqual($other, $contrast, "{$name}: the other of black and white reads better");

        $this->assertGreaterThanOrEqual(3.0, $contrast, "{$name}: {$avatar->background} is not enough to read on");
    }

    /**
     * The picture, when there is one, comes from whichever field the User form says plays the image role -- not from a field name read out of the body.
     */
    #[Depends('testWikiExisting')]
    public function testTheUserFormIsWhatSaysWhereThePictureIs(YesWikiRuntime $wiki): void
    {
        $form = $wiki->services->get(FormManager::class)->getByContentType(ContentTypeSchema::TYPE_USER);
        $this->assertNotNull($form, 'accounts are a form (ticket 10)');

        $propertyName = $wiki->services->get(FieldRoleResolver::class)->propertyName($form, FieldRole::IMAGE);
        $this->assertNotNull($propertyName, 'the User form must have a field that can hold a face');
        $this->assertTrue(
            ContentTypeSchema::isLocked(ContentTypeSchema::TYPE_USER, 'profile_picture'),
            'the profile picture is core structure: a webmaster may relabel or reorder it, not delete it'
        );
    }

    /**
     * A picture, once there is one -- through the field the form nominates, stored by the service that guards which keys a submission may write.
     */
    #[Depends('testWikiExisting')]
    public function testAPictureIsStoredThroughTheFormAndComesBackAsTheFace(YesWikiRuntime $wiki): void
    {
        $userManager = $wiki->services->get(UserManager::class);
        $form = $wiki->services->get(FormManager::class)->getByContentType(ContentTypeSchema::TYPE_USER);
        $propertyName = (string)$wiki->services->get(FieldRoleResolver::class)->propertyName($form, FieldRole::IMAGE);

        $userManager->create(self::PICTURED, 'account-face-picture@example.tld', 'Aa1!aaaaProbe');
        $user = $userManager->getOneByName(self::PICTURED);
        if ($user === null) {
            $this->fail('the account under test was not created');
        }

        try {
            $avatars = $wiki->services->get(AvatarService::class);
            $this->assertNull($avatars->forName(self::PICTURED)->imageUrl, 'no picture yet');

            $userManager->update($user, [$propertyName => 'https://example.org/face.png']);
            $this->assertSame(
                'https://example.org/face.png',
                $avatars->forName(self::PICTURED)->imageUrl,
                "the User form's own fields must survive an update()"
            );

            $this->assertStringContainsString(
                'https://example.org/face.png',
                $this->renderedButtonFor($wiki, $user),
                'and the button draws it instead of the initials'
            );
        } finally {
            $probe = $userManager->getOneByName(self::PICTURED);
            if (!empty($probe)) {
                $userManager->delete($probe);
            }
        }
    }

    /**
     * An uploaded picture is found where the ACCOUNT keeps its files, not where the page doing the drawing keeps its own.
     */
    #[Depends('testWikiExisting')]
    public function testAnUploadedPictureIsFoundUnderTheAccountNotTheCurrentPage(YesWikiRuntime $wiki): void
    {
        if (!function_exists('imagecreatetruecolor')) {
            $this->markTestSkipped('resizing a picture needs GD');
        }

        $userManager = $wiki->services->get(UserManager::class);
        $form = $wiki->services->get(FormManager::class)->getByContentType(ContentTypeSchema::TYPE_USER);
        $propertyName = (string)$wiki->services->get(FieldRoleResolver::class)->propertyName($form, FieldRole::IMAGE);

        $userManager->create(self::PICTURED, 'account-face-picture@example.tld', 'Aa1!aaaaProbe');
        $user = $userManager->getOneByName(self::PICTURED);
        if ($user === null) {
            $this->fail('the account under test was not created');
        }

        $fileName = 'profile_picture_20260804090000_20260804090000.png';
        $written = null;
        try {
            $written = $this->writeFixturePicture($wiki, self::PICTURED, $fileName);
            $userManager->update($user, [$propertyName => $fileName]);

            $wiki->services->get(PageContext::class)->setTag('SomeUnrelatedPage');
            $imageUrl = $wiki->services->get(AvatarService::class)->forName(self::PICTURED)->imageUrl;

            $this->assertNotNull($imageUrl, 'the picture is the account, not the page it is drawn on');
            $relative = str_replace($wiki->services->get(UrlFormatter::class)->getBaseUrl() . '/', '', $imageUrl);
            $this->assertFileExists($relative, 'the address must point at a file that is really there');
            $this->assertStringContainsString('128_128', $relative, 'and at a thumbnail, not the original upload');
        } finally {
            $wiki->services->get(PageContext::class)->setTag('');
            if ($written !== null && file_exists($written)) {
                unlink($written);
            }
            $probe = $userManager->getOneByName(self::PICTURED);
            if (!empty($probe)) {
                $userManager->delete($probe);
            }
        }
    }

    #[Depends('testWikiExisting')]
    public function testSignedOutTheButtonIsALinkToTheAccountScreen(YesWikiRuntime $wiki): void
    {
        $wiki->services->get(AuthenticationService::class)->logout();
        $rendered = $wiki->services->get(MarkdownFormatterService::class)->format('{{login}}');

        $this->assertStringContainsString('account-link', $rendered);
        $this->assertStringContainsString('?user', $rendered);

        $this->assertStringNotContainsString('name="password"', $rendered);
        $this->assertStringNotContainsString('yw-avatar', $rendered, 'there is no face to draw yet');
    }

    #[Depends('testWikiExisting')]
    public function testSignedInTheButtonIsYourFace(YesWikiRuntime $wiki): void
    {
        $userManager = $wiki->services->get(UserManager::class);
        $authentication = $wiki->services->get(AuthenticationService::class);

        $user = $userManager->getOneByName(self::PROBE);
        if (empty($user)) {
            $userManager->create(self::PROBE, 'account-face@example.tld', 'Aa1!aaaaProbe');
            $user = $userManager->getOneByName(self::PROBE);
        }
        $this->assertInstanceOf(User::class, $user);

        $authentication->login($user);
        try {
            $rendered = $wiki->services->get(MarkdownFormatterService::class)->format('{{login}}');

            $this->assertStringContainsString('yw-avatar', $rendered);
            $this->assertStringContainsString('AC', $rendered, 'the first two letters of AccountFaceTestUser');
            $this->assertStringContainsString('--yw-avatar-bg', $rendered, 'the colour is data, so it is inline');
            $this->assertStringContainsString(self::PROBE, $rendered, 'the link says whose account it is');
        } finally {
            $authentication->logout();
            $probe = $userManager->getOneByName(self::PROBE);
            if (!empty($probe)) {
                $userManager->delete($probe);
            }
        }
    }

    /** Every screen whose job is signing someone in must still render the fields. */
    #[Depends('testWikiExisting')]
    public function testTheFormIsStillWhatASignInScreenRenders(YesWikiRuntime $wiki): void
    {
        $wiki->services->get(AuthenticationService::class)->logout();
        $rendered = $wiki->services->get(MarkdownFormatterService::class)
            ->format('{{login template="login-form.twig"}}');

        $this->assertStringContainsString('login-form', $rendered);
        $this->assertStringContainsString('name="password"', $rendered);
    }

    /**
     * @return array<string, array{string, bool}>
     */
    public static function returnUrlProvider(): array
    {
        return [
            'another site' => ['https://not-your-wiki.example/', false],
            'protocol-relative' => ['//not-your-wiki.example/', false],
            'backslash-relative' => ['/\\not-your-wiki.example/', false],
            'a script' => ['javascript:alert(1)', false],
            'nothing at all' => ['', false],
            'a path on this server' => ['/?SomePage', true],
        ];
    }

    #[Depends('testWikiExisting')]
    #[DataProvider('returnUrlProvider')]
    public function testOnlyThisWikiIsWorthReturningTo(string $candidate, bool $expected, YesWikiRuntime $wiki): void
    {
        $this->assertSame($expected, $wiki->services->get(UrlFormatter::class)->isInternal($candidate));
    }

    #[Depends('testWikiExisting')]
    public function testThisWikiIsWorthReturningTo(YesWikiRuntime $wiki): void
    {
        $urlFormatter = $wiki->services->get(UrlFormatter::class);
        $this->assertTrue($urlFormatter->isInternal($urlFormatter->href('', 'SomePage')));
        $this->assertTrue($urlFormatter->isInternal($urlFormatter->href('edit', 'SomePage', ['x' => '1'])));
    }

    /**
     * The round trip: the button remembers the page it was clicked from, and the sign-in that follows ends there rather than on the account screen.
     */
    #[Depends('testWikiExisting')]
    public function testTheButtonRemembersWhereYouWereAndSignInGoesBack(YesWikiRuntime $wiki): void
    {
        $urlFormatter = $wiki->services->get(UrlFormatter::class);
        $currentRequest = $wiki->services->get(CurrentRequest::class);
        $previous = $currentRequest->get();

        $somePage = $urlFormatter->href('', 'SomePage');
        try {
            $currentRequest->replace(Request::create($somePage));
            $arguments = $this->loginArguments($wiki);
            $this->assertStringContainsString('?user', $arguments['accounturl']);
            $this->assertStringContainsString(
                urlencode($somePage),
                $arguments['accounturl'],
                'the account link has to say where to come back to'
            );

            $currentRequest->replace(Request::create($urlFormatter->href('', 'user', [
                LoginAction::RETURN_PARAM => $somePage,
            ], false)));
            $arguments = $this->loginArguments($wiki);
            $this->assertSame($somePage, $arguments['loggedinurl']);
            $this->assertStringNotContainsString(
                LoginAction::RETURN_PARAM . '=',
                $arguments['accounturl'],
                'the account screen does not offer to send you back to the account screen'
            );

            $currentRequest->replace(Request::create($urlFormatter->href('', 'user', [
                LoginAction::RETURN_PARAM => 'https://not-your-wiki.example/',
            ], false)));
            $arguments = $this->loginArguments($wiki);

            $this->assertTrue(
                $urlFormatter->isInternal($arguments['loggedinurl']),
                'signing in must not land on another site, whoever wrote the link'
            );
            $this->assertStringStartsNotWith('https://not-your-wiki.example', $arguments['loggedinurl']);
        } finally {
            $currentRequest->replace($previous);
        }
    }

    /** `{{login}}` as this account sees it. */
    private function renderedButtonFor(YesWikiRuntime $wiki, User $user): string
    {
        $authentication = $wiki->services->get(AuthenticationService::class);
        $authentication->login($user);
        try {
            return (string)$wiki->services->get(MarkdownFormatterService::class)->format('{{login}}');
        } finally {
            $authentication->logout();
        }
    }

    /**
     * A real (tiny) picture in the account's own upload directory -- where a file field would have put it, asked through the same service the field asks.
     */
    private function writeFixturePicture(YesWikiRuntime $wiki, string $tag, string $fileName): string
    {
        $pageContext = $wiki->services->get(PageContext::class);
        $previousTag = $pageContext->getTag();
        $pageContext->setTag($tag);
        try {
            $path = $wiki->services->get(AttachedFilePaths::class)->uploadPath() . '/' . $fileName;
        } finally {
            $pageContext->setTag($previousTag);
        }

        $image = imagecreatetruecolor(200, 120);
        $colour = imagecolorallocate($image, 30, 120, 200);
        if ($colour === false) {
            $this->fail('GD could not allocate a colour for the fixture picture');
        }
        imagefill($image, 0, 0, $colour);
        imagepng($image, $path);

        return $path;
    }

    /**
     * @return array<string, mixed>
     */
    private function loginArguments(YesWikiRuntime $wiki): array
    {
        $action = new LoginAction();
        $action->setServices($wiki->services);
        $action->setParams($wiki->services->get(ParameterBagInterface::class));

        $formatArguments = (new \ReflectionClass($action))->getMethod('formatArguments');

        return (array)$formatArguments->invoke($action, []);
    }

    /** WCAG contrast ratio, the standard the foreground is chosen against. */
    private function contrastRatio(string $first, string $second): float
    {
        $luminances = array_map(function (string $hex): float {
            $channels = [];
            foreach ([1, 3, 5] as $offset) {
                $channel = hexdec(substr($hex, $offset, 2)) / 255;
                $channels[] = $channel <= 0.03928 ? $channel / 12.92 : (($channel + 0.055) / 1.055) ** 2.4;
            }

            return 0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2];
        }, [$first, $second]);

        sort($luminances);

        return ($luminances[1] + 0.05) / ($luminances[0] + 0.05);
    }
}

<?php

namespace YesWiki\Test\Core\Service;

use PHPUnit\Framework\Attributes\Depends;
use Symfony\Component\HttpFoundation\Request;
use YesWiki\Content\Entity\ContentTypeSchema;
use YesWiki\Content\Entity\PageBody;
use YesWiki\Content\Service\ContentTypeResolver;
use YesWiki\Content\Service\FileManager;
use YesWiki\Content\Service\FormManager;
use YesWiki\Content\Service\PageManager;
use YesWiki\Identity\Service\AclService;
use YesWiki\Identity\Service\AuthenticationService;
use YesWiki\Identity\Service\UserManager;
use YesWiki\Kernel\Exception\ExitException;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/**
 * The page editor edits the form of the row's **own** Content type.
 *
 * It used to reach for the Page form whatever it was editing, so opening an account or an
 * uploaded file at /edit offered the wiki markup editor and wrote `content`, `title` and
 * `keywords` into that row -- giving an account a page-shaped body it should never have.
 * An account is not wiki markup: the User form has no `content` field, so there is no
 * markup to edit and none is written.
 */
class ContentTypeEditorTest extends YesWikiTestCase
{
    private const USER_NAME = 'ContentTypeEditorTestUser';
    private const FILE_STORED = 'content-type-editor-test.txt';

    private static ?string $fileTag = null;

    public static function tearDownAfterClass(): void
    {
        $wiki = self::getWiki();
        $user = $wiki->services->get(UserManager::class)->getOneByName(self::USER_NAME);
        if ($user !== null) {
            $wiki->services->get(UserManager::class)->delete($user);
        }
        if (self::$fileTag !== null) {
            $wiki->services->get(PageManager::class)->deleteOrphaned(self::$fileTag);
            self::$fileTag = null;
        }
        $path = FileManager::STORAGE_DIR . '/' . self::FILE_STORED;
        if (file_exists($path)) {
            unlink($path);
        }
    }

    /** Runs the edit handler against $tag as an admin and returns the rendered output. */
    private function renderEditor(string $tag): string
    {
        $wiki = $this->getWiki();
        $pageManager = $wiki->services->get(PageManager::class);

        $admin = current(array_filter(
            $wiki->services->get(UserManager::class)->getAll(),
            fn ($u) => $wiki->services->get(AclService::class)->isAdmin($u['name'])
        ));
        $this->assertNotFalse($admin, 'need an existing admin user to exercise write access');
        $wiki->services->get(AuthenticationService::class)->login($admin);
        // an account page's ACL is closed by default and the harness's session handling
        // makes the admin bypass unreliable here; open the row explicitly so the test
        // exercises the editor rather than the permission check
        $wiki->services->get(AclService::class)->save($tag, 'write', '*');
        $wiki->services->get(AclService::class)->save($tag, 'read', '*');

        $wiki->services->get(\YesWiki\Kernel\Service\PageContext::class)->setTag($tag);
        $wiki->services->get(\YesWiki\Kernel\Service\PageContext::class)->setPage($pageManager->getOne($tag));
        $GLOBALS['yeswikiServices'] = $wiki->services;

        $_POST = [];
        $wiki->services->get(\YesWiki\Kernel\Service\CurrentRequest::class)->replace(Request::createFromGlobals());

        return $wiki->services->get(\YesWiki\Kernel\Service\Performer::class)->run('edit', 'handler', []);
    }

    public function testTheResolverAnswersWithEachRowsOwnForm(): void
    {
        $wiki = $this->getWiki();
        $resolver = $wiki->services->get(ContentTypeResolver::class);
        $formManager = $wiki->services->get(FormManager::class);

        $user = $wiki->services->get(UserManager::class)->create(self::USER_NAME, 'content-type-editor@example.com', 'Passw0rd!123');
        $this->assertNotNull($user, 'the test account should have been created');

        $this->assertSame(ContentTypeSchema::TYPE_USER, $resolver->typeOf(self::USER_NAME));

        $userForm = $formManager->getByContentType(ContentTypeSchema::TYPE_USER);
        $resolved = $resolver->formFor(self::USER_NAME);
        $this->assertNotNull($userForm);
        $this->assertNotNull($resolved);
        $this->assertSame(
            $userForm['id'],
            $resolved['id'],
            'an account row is described by the User form, not the Page form'
        );
    }

    #[Depends('testTheResolverAnswersWithEachRowsOwnForm')]
    public function testEditingAnAccountOffersNoMarkupEditor(): void
    {
        $output = $this->renderEditor(self::USER_NAME);

        try {
            $this->assertStringNotContainsString(
                'aceditor-textarea',
                $output,
                'an account is not wiki markup: it must not get the prose editor'
            );
            $this->assertStringNotContainsString('name="body"', $output);
            // the User form's own fields are what an account is edited through
            $this->assertMatchesRegularExpression('/name="(email|profile_picture)"/', $output);
        } finally {
            $this->getWiki()->services->get(AuthenticationService::class)->logout();
        }
    }

    #[Depends('testTheResolverAnswersWithEachRowsOwnForm')]
    public function testSavingAnAccountWritesNoContentIntoItsBody(): void
    {
        $wiki = $this->getWiki();
        $pageManager = $wiki->services->get(PageManager::class);

        $this->renderEditor(self::USER_NAME);
        $page = $pageManager->getOne(self::USER_NAME);
        $this->assertIsArray($page);

        try {
            // a submission that carries markup anyway -- a crafted POST, or a browser
            // that still had the old form -- must not put it in the account's body
            $_POST = [
                'submit' => 'Sauver',
                'previous' => $page['id'],
                'body' => '===== not wiki markup =====',
                'email' => 'moved@example.com',
            ];
            $wiki->services->get(\YesWiki\Kernel\Service\CurrentRequest::class)->replace(Request::createFromGlobals());
            try {
                $wiki->services->get(\YesWiki\Kernel\Service\Performer::class)->run('edit', 'handler', []);
            } catch (ExitException $e) {
                // a save redirects
            }

            $reloaded = $pageManager->getOne(self::USER_NAME);
            $this->assertIsArray($reloaded);
            $this->assertArrayNotHasKey(
                PageBody::CONTENT,
                $reloaded['body'],
                'an account must never acquire a page-shaped body'
            );
            $this->assertArrayNotHasKey(PageBody::KEYWORDS, $reloaded['body']);
            $this->assertSame('moved@example.com', $reloaded['body']['email'] ?? null, 'its own fields do save');
            $this->assertArrayHasKey('password', $reloaded['body'], 'and the rest of the account survives');
        } finally {
            $_POST = [];
            $wiki->services->get(AuthenticationService::class)->logout();
        }
    }

    public function testEditingAFileOffersNoMarkupEditorEither(): void
    {
        $wiki = $this->getWiki();
        if (!is_dir(FileManager::STORAGE_DIR)) {
            $this->markTestSkipped('no file storage directory on this install');
        }
        file_put_contents(FileManager::STORAGE_DIR . '/' . self::FILE_STORED, 'hello');
        $file = $wiki->services->get(FileManager::class)->create(
            'content type editor test.txt',
            self::FILE_STORED,
            'PagePrincipale',
            5,
            'text/plain'
        );
        $fileTag = (string)$file['tag'];
        self::$fileTag = $fileTag;

        // a file's attributes are body fields, which is what makes it downloadable at all
        // and what its title is computed from (ADR-0002's ticket-09 amendment)
        $this->assertSame(self::FILE_STORED, $file['stored_filename']);
        $this->assertNotNull($wiki->services->get(FileManager::class)->getPhysicalPath($fileTag));

        $output = $this->renderEditor($fileTag);

        try {
            $this->assertStringNotContainsString('aceditor-textarea', $output);
            $this->assertMatchesRegularExpression('/name="original_filename"/', $output);
        } finally {
            $wiki->services->get(AuthenticationService::class)->logout();
        }
    }
}

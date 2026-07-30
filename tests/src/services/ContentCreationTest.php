<?php

namespace YesWiki\Test\Content\Service;

use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use YesWiki\Content\Entity\ContentTypeSchema;
use YesWiki\Content\Entity\PageBody;
use YesWiki\Content\Field\FileContentField;
use YesWiki\Content\Service\ContentCreator;
use YesWiki\Content\Service\ContentTypeResolver;
use YesWiki\Content\Service\FileManager;
use YesWiki\Content\Service\FormManager;
use YesWiki\Content\Service\PageManager;
use YesWiki\Content\Service\TripleStore;
use YesWiki\Identity\Service\AclService;
use YesWiki\Identity\Service\AuthenticationService;
use YesWiki\Identity\Service\UserManager;
use YesWiki\Kernel\Service\CurrentRequest;
use YesWiki\Search\Service\TagsManager;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/**
 * Creating a Content by filling in the form that describes it (ticket 13).
 *
 * Ticket 10 made Page, User and File forms; until this, the one thing you could not do
 * with those forms was create one. What the tests pin down is that creation goes through
 * the type's own persistence and not through the entry path: a page comes out with no
 * type triple (carrying none is what makes it a page) and its keywords indexed, and an
 * account comes out with everything signup gives it -- hashed password, self ownership,
 * a write ACL that is not the wiki's `*` default.
 */
class ContentCreationTest extends YesWikiTestCase
{
    private const PAGE_TITLE = 'Une page née du formulaire';
    private const ACCOUNT_NAME = 'ContentCreationTestAccount';

    /** @var string[] */
    private static array $createdTags = [];

    public static function tearDownAfterClass(): void
    {
        $pageManager = self::getWiki()->services->get(PageManager::class);
        foreach (self::$createdTags as $tag) {
            $pageManager->deleteOrphaned($tag);
        }
    }

    /** @return array<string, mixed> */
    private function builtInForm(string $contentType): array
    {
        $form = $this->getWiki()->services->get(FormManager::class)->getByContentType($contentType);
        $this->assertNotNull($form, "the {$contentType} form should exist -- run ./yeswicli migrate");

        return $form;
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    private function createFrom(string $contentType, array $data): array
    {
        $form = $this->builtInForm($contentType);
        $created = $this->getWiki()->services->get(ContentCreator::class)
            ->create((string)$form['id'], array_merge(['antispam' => '1'], $data));
        self::$createdTags[] = (string)$created['tag'];

        return $created;
    }

    public function testThePageFormCreatesAPageAndNotAnEntry(): void
    {
        $created = $this->createFrom(ContentTypeSchema::TYPE_PAGE, [
            'title' => self::PAGE_TITLE,
            'content' => 'du contenu écrit dans le formulaire',
            'keywords' => 'formulaire, page',
        ]);

        $tag = (string)$created['tag'];
        $this->assertNotSame('', $tag);

        $resolver = $this->getWiki()->services->get(ContentTypeResolver::class);
        $this->assertSame(
            ContentTypeSchema::TYPE_PAGE,
            $resolver->typeOf($tag),
            'a page carries no type triple at all -- that absence is what makes it a page'
        );

        $page = $this->getWiki()->services->get(PageManager::class)->getOne($tag, null, true, true);
        $this->assertIsArray($page);
        $body = $page['body'] ?? [];
        $this->assertSame(self::PAGE_TITLE, $body[PageBody::TITLE] ?? null);
        $this->assertSame('du contenu écrit dans le formulaire', $body[PageBody::CONTENT] ?? null);
        $this->assertSame(['formulaire', 'page'], $body[PageBody::KEYWORDS] ?? null, 'a page stores its keywords as a list');
        $this->assertArrayNotHasKey('form_id', $body, 'a page does not name a form: its type triple does');
    }

    public function testThePageFormIndexesTheKeywordsItWasGiven(): void
    {
        $created = $this->createFrom(ContentTypeSchema::TYPE_PAGE, [
            'title' => 'Une page indexée',
            'content' => 'peu importe',
            'keywords' => 'indexation',
        ]);

        $triples = $this->getWiki()->services->get(TripleStore::class)
            ->getAll((string)$created['tag'], TagsManager::TAG_PROPERTY, '', '');
        $this->assertContains('indexation', array_column($triples, 'value'), 'triples hold the derived keyword index');
    }

    public function testTheUserFormCreatesAnAccountWithEverySignupGuarantee(): void
    {
        $wiki = $this->getWiki();
        $userManager = $wiki->services->get(UserManager::class);
        if ($userManager->getOneByName(self::ACCOUNT_NAME)) {
            $this->markTestSkipped('an account of that name already exists on this wiki');
        }

        $created = $this->createFrom(ContentTypeSchema::TYPE_USER, [
            'username' => self::ACCOUNT_NAME,
            'password' => 'un-mot-de-passe-solide',
            'email' => 'content-creation-test@example.org',
        ]);

        $tag = (string)$created['tag'];
        $user = $userManager->getOneByName($tag);
        $this->assertNotNull($user, 'the account must be an account, not just a page');

        $this->assertTrue(
            $wiki->services->get(AuthenticationService::class)->checkPassword('un-mot-de-passe-solide', $user),
            'the password is hashed once, by UserManager -- hashing it twice would lock the account out'
        );
        $this->assertSame($tag, $wiki->services->get(PageManager::class)->getOne($tag, null, true, true)['owner'] ?? null);

        $writeAcl = $wiki->services->get(AclService::class)->load($tag, 'write');
        $this->assertStringContainsString('@admins', $writeAcl['list'] ?? '', "an account must not inherit the wiki's default write ACL");
    }

    public function testEveryBuiltInTypeCanBeCreatedFromItsForm(): void
    {
        foreach ([ContentTypeSchema::TYPE_PAGE, ContentTypeSchema::TYPE_USER, ContentTypeSchema::TYPE_FILE] as $type) {
            $this->assertTrue(ContentCreator::supports($type), "the {$type} form should create Content");
        }
        $this->assertTrue(ContentCreator::supports(null), 'an ordinary bazar form is unaffected');
    }

    public function testTheFileFormCreatesAFileWhoseBytesAreOnDisk(): void
    {
        $source = tempnam(sys_get_temp_dir(), 'yw-upload-') . '.txt';
        file_put_contents($source, "des octets bien réels\n");

        $request = Request::create('/', 'POST');
        $request->files->set('file_content', new UploadedFile($source, 'un-fichier-de-test.txt', 'text/plain', null, true));
        $this->getWiki()->services->get(CurrentRequest::class)->replace($request);

        try {
            $created = $this->createFrom(ContentTypeSchema::TYPE_FILE, []);
        } finally {
            $this->getWiki()->services->get(CurrentRequest::class)->replace(Request::createFromGlobals());
        }

        $this->assertSame('un-fichier-de-test.txt', $created['original_filename'] ?? null);
        $path = $this->getWiki()->services->get(FileManager::class)->getPhysicalPath((string)$created['tag']);
        $this->assertNotNull($path, 'a file Content must name bytes that are actually on disk');
        $this->assertStringStartsWith(FileManager::STORAGE_DIR, $path, 'the bytes live under private/, never the web root');
        $this->assertSame("des octets bien réels\n", file_get_contents($path));
        @unlink($path);
    }

    /**
     * The derived attributes were locked *text fields* until ticket 13. A text field the
     * form did not submit yields an empty string, so saving a File from its own edit form
     * wrote "" over both filenames and 404'd the file it was editing.
     */
    public function testTheFileFormAsksForBytesAndNotForDerivedAttributes(): void
    {
        $names = array_column($this->builtInForm(ContentTypeSchema::TYPE_FILE)['template'], 'name');

        $this->assertContains('file_content', $names);
        foreach (['original_filename', 'stored_filename', 'uploaded_from', 'size', 'mime_type'] as $derived) {
            $this->assertNotContains($derived, $names, "$derived is computed from the upload, not typed in");
        }
    }

    public function testEditingAFileWithoutUploadingLeavesItsBytesAlone(): void
    {
        $field = null;
        foreach ($this->builtInForm(ContentTypeSchema::TYPE_FILE)['prepared'] as $candidate) {
            if ($candidate instanceof FileContentField) {
                $field = $candidate;
            }
        }
        $this->assertNotNull($field, 'the File form must declare its file-content field');

        $this->getWiki()->services->get(CurrentRequest::class)->replace(Request::create('/', 'POST'));

        $this->assertSame(
            [],
            $field->formatValuesBeforeSave(['original_filename' => 'deja-la.txt']),
            'no upload means no change: an edit that touches something else must not blank the file'
        );
    }

    public function testTheFileFormRefusesASubmissionCarryingNoBytes(): void
    {
        $this->getWiki()->services->get(CurrentRequest::class)->replace(Request::create('/', 'POST'));

        $this->expectExceptionMessage(_t('ERROR_NO_FILE_UPLOADED'));
        $this->createFrom(ContentTypeSchema::TYPE_FILE, ['original_filename' => 'rien.txt']);
    }
}

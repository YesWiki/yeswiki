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
use YesWiki\Identity\Service\AclService;
use YesWiki\Identity\Service\AuthenticationService;
use YesWiki\Identity\Service\UserManager;
use YesWiki\Kernel\Service\CurrentRequest;
use YesWiki\Kernel\Service\TripleStore;
use YesWiki\Search\Service\SearchIndexer;
use YesWiki\Search\Service\TagsManager;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/** Creating a Content by filling in the form that describes it (ticket 13). */
class ContentCreationTest extends YesWikiTestCase
{
    private const PAGE_TITLE = 'Une page née du formulaire';
    private const ACCOUNT_NAME = 'ContentCreationTestAccount';

    /**
     * @var string[]
     */
    private static array $createdTags = [];

    public static function tearDownAfterClass(): void
    {
        $pageManager = self::getWiki()->services->get(PageManager::class);
        foreach (self::$createdTags as $tag) {
            $pageManager->deleteOrphaned($tag);
        }
    }

    /**
     * @return array<string, mixed>
     */
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

        $wiki = $this->getWiki();
        $wiki->services->get(SearchIndexer::class)->index((string)$created['tag']);

        $this->assertContains(
            ['keyword' => 'indexation', 'tag' => (string)$created['tag']],
            $wiki->services->get(TagsManager::class)->pairs(['indexation']),
            'search_keywords holds the derived keyword index'
        );
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

    /** The derived attributes were locked *text fields* until ticket 13. */
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

    /**
     * The whole point of Page/User/File being forms: a webmaster adds a field and it behaves like any other.
     */
    public function testAFieldAWebmasterAddedToTheUserFormIsStored(): void
    {
        $wiki = $this->getWiki();
        $formManager = $wiki->services->get(FormManager::class);
        $form = $this->builtInForm(ContentTypeSchema::TYPE_USER);
        $original = $form['template'];

        $form['template'] = array_merge($original, [
            ['type' => 'texte', 'name' => 'pronouns', 'label' => 'Pronoms'],
        ]);
        $formManager->update($form);

        try {
            $created = $this->createFrom(ContentTypeSchema::TYPE_USER, [
                'username' => self::ACCOUNT_NAME . 'Extra',
                'password' => 'un-autre-mot-de-passe',
                'email' => 'content-creation-extra@example.org',
                'pronouns' => 'iel',
            ]);

            $body = $wiki->services->get(PageManager::class)
                ->getOne((string)$created['tag'], null, true, true)['body'] ?? [];
            $this->assertSame('iel', $body['pronouns'] ?? null);

            $this->assertArrayHasKey('password', $body);
            $this->assertSame('content-creation-extra@example.org', $body['email'] ?? null);
        } finally {
            $form['template'] = $original;
            $formManager->update($form);
        }
    }

    /** The other half of "exactly like other forms": the two things that stay refused. */
    public function testABuiltInFormStillCannotBeEmptiedOrDeleted(): void
    {
        $formManager = $this->getWiki()->services->get(FormManager::class);
        $id = (string)$this->builtInForm(ContentTypeSchema::TYPE_PAGE)['id'];

        foreach (['clear', 'delete'] as $operation) {
            try {
                $formManager->{$operation}($id);
                $this->fail("{$operation}() must refuse a built-in Content type's form");
            } catch (\Exception $e) {
                $this->assertStringContainsString('built-in', $e->getMessage());
            }
        }

        $this->assertNotNull($formManager->getByContentType(ContentTypeSchema::TYPE_PAGE), 'and it is still there');
    }

    /**
     * Which form describes a row is decided by the row's type triple, so a second Page form would leave getByContentType() picking arbitrarily between the two.
     */
    public function testABuiltInTypeStillHasExactlyOneForm(): void
    {
        $formManager = $this->getWiki()->services->get(FormManager::class);

        $this->expectExceptionMessage('already exists');
        $formManager->create([
            'id' => (string)$this->firstFreeFormId($formManager),
            'label' => 'Une seconde forme des pages',
            'description' => '',
            ContentTypeSchema::CONTENT_TYPE => ContentTypeSchema::TYPE_PAGE,
            'template' => '',
        ]);
    }

    private function firstFreeFormId(FormManager $formManager): int
    {
        $id = 9800;
        while ($formManager->getOne((string)$id) !== null) {
            $id++;
        }

        return $id;
    }

    public function testALockedFieldStillCannotBeDeletedOrRetyped(): void
    {
        $formManager = $this->getWiki()->services->get(FormManager::class);
        $form = $this->builtInForm(ContentTypeSchema::TYPE_PAGE);
        $original = $form['template'];

        $form['template'] = [['type' => 'hidden', 'name' => 'title', 'label' => 'Titre']];
        $formManager->update($form);

        try {
            $repaired = $this->builtInForm(ContentTypeSchema::TYPE_PAGE)['template'];
            $byName = array_column($repaired, null, 'name');

            $this->assertArrayHasKey('content', $byName, 'a deleted locked field comes back');
            $this->assertArrayHasKey('keywords', $byName);
            $this->assertSame('texte', $byName['title']['type'] ?? null, 'a retyped locked field keeps its declared type');
        } finally {
            $form['template'] = $original;
            $formManager->update($form);
        }
    }
}

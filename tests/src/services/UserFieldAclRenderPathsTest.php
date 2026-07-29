<?php

namespace YesWiki\Test\Identity\Service;

use YesWiki\Content\Entity\ContentTypeSchema;
use YesWiki\Content\Entity\PageBody;
use YesWiki\Content\Service\FormManager;
use YesWiki\Content\Service\PageManager;
use YesWiki\Identity\Service\UserManager;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/**
 * ADR-0003 puts the password hash in the versioned body and protects it with Field ACL
 * rather than by where it is stored, which makes one thing load-bearing: **every** path
 * that reads a users-type body has to go through the check. A path that skips it is a
 * credential leak, not a display bug -- and history is a path, so an old revision must
 * redact exactly like the current one.
 *
 * Ticket 10 widened the check from a hardcoded key list to the User form's own template,
 * so these cover both halves: the floor that no template can weaken, and the Field ACL a
 * webmaster sets on a field they added.
 */
class UserFieldAclRenderPathsTest extends YesWikiTestCase
{
    private const NAME = 'UserFieldAclRenderPathsTestAccount';
    private const OTHER = 'UserFieldAclRenderPathsTestOther';

    protected function tearDown(): void
    {
        $wiki = $this->getWiki();
        $userManager = $wiki->services->get(UserManager::class);
        foreach ([self::NAME, self::OTHER] as $name) {
            $user = $userManager->getOneByName($name);
            if ($user) {
                $userManager->delete($user);
            }
            $wiki->services->get(PageManager::class)->deleteOrphaned($name);
        }
        parent::tearDown();
    }

    private function createAccount(string $name, string $email): void
    {
        $created = $this->getWiki()->services->get(UserManager::class)
            ->create($name, $email, 'Aa1!aaaaRegression');
        $this->assertNotNull($created, "could not create $name");
    }

    /**
     * The current view, read by somebody who is neither the owner nor an admin -- the
     * ordinary case, and the one every other path has to match.
     */
    public function testThePasswordHashIsNeverReadableThroughAnOrdinaryPageRead(): void
    {
        $this->createAccount(self::NAME, 'ufarp-1@example.tld');
        $pageManager = $this->getWiki()->services->get(PageManager::class);

        $page = $pageManager->getOne(self::NAME, null, false, false, self::OTHER);

        $this->assertIsArray($page);
        $this->assertSame('', $page['body']['password'] ?? null, 'the hash must never reach a reader');
    }

    /** Not even to the account's own owner: no UI ever needs to show a hash back. */
    public function testThePasswordHashIsNotReadableEvenByTheOwner(): void
    {
        $this->createAccount(self::NAME, 'ufarp-2@example.tld');
        $pageManager = $this->getWiki()->services->get(PageManager::class);

        $page = $pageManager->getOne(self::NAME, null, false, false, self::NAME);

        $this->assertIsArray($page);
        $this->assertSame('', $page['body']['password'] ?? null);
    }

    /**
     * History is a render path. A hash left readable in an old revision is the same leak
     * as one left readable in the current view -- ADR-0003 says so explicitly.
     */
    public function testHistoricalRevisionsRedactTheSameWayAsTheCurrentOne(): void
    {
        $this->createAccount(self::NAME, 'ufarp-3@example.tld');
        $wiki = $this->getWiki();
        $pageManager = $wiki->services->get(PageManager::class);

        // a second revision, so there is a genuine history to read back through
        sleep(1);
        $userManager = $wiki->services->get(UserManager::class);
        $user = $userManager->getOneByName(self::NAME);
        $this->assertNotNull($user);
        $userManager->update($user, ['motto' => 'changed']);

        $revisions = $pageManager->getRevisions(self::NAME);
        $this->assertGreaterThan(1, count($revisions), 'need more than one revision to test history');

        foreach ($revisions as $revision) {
            $historical = $pageManager->getById($revision['id']);
            $this->assertIsArray($historical);
            $this->assertSame(
                '',
                $historical['body']['password'] ?? null,
                "revision {$revision['id']} leaked the hash"
            );
        }
    }

    /** The unredacted value still has to be reachable, or authentication cannot work. */
    public function testTheRealHashIsStillReachableThroughAnAclBypassingRead(): void
    {
        $this->createAccount(self::NAME, 'ufarp-4@example.tld');
        $pageManager = $this->getWiki()->services->get(PageManager::class);

        $page = $pageManager->getOne(self::NAME, null, false, true);

        $this->assertIsArray($page);
        $this->assertNotSame('', $page['body']['password'] ?? '', 'auth reads must still see the hash');
    }

    /**
     * Ticket 10's addition: a field the webmaster added to the User form, with a
     * restrictive read ACL, is redacted by the same mechanism -- no new code per field.
     */
    public function testAWebmasterAddedFieldWithARestrictiveAclIsRedacted(): void
    {
        $wiki = $this->getWiki();
        $formManager = $wiki->services->get(FormManager::class);
        $userForm = $formManager->getByContentType(ContentTypeSchema::TYPE_USER);
        $this->assertNotNull($userForm, 'the User form should exist -- run ./yeswicli migrate');

        $this->createAccount(self::NAME, 'ufarp-5@example.tld');
        $pageManager = $wiki->services->get(PageManager::class);

        try {
            $formManager->update([
                'id' => $userForm['id'],
                'label' => $userForm['label'],
                'template' => array_merge($userForm['template'], [[
                    'type' => 'texte',
                    'name' => 'private_note',
                    'label' => 'Note privée',
                    'read_access' => '@admins',
                    'write_access' => '@admins',
                ]]),
            ]);

            // put a value in that field on the account
            $stored = $pageManager->getOne(self::NAME, null, false, true);
            $this->assertIsArray($stored);
            $body = $stored['body'];
            $body['private_note'] = 'not for everyone';
            $pageManager->save(self::NAME, $body, '', true);

            $asStranger = $pageManager->getOne(self::NAME, null, false, false, self::OTHER);
            $this->assertIsArray($asStranger);
            $this->assertSame('', $asStranger['body']['private_note'] ?? null, 'the field ACL must be applied');

            $asService = $pageManager->getOne(self::NAME, null, false, true);
            $this->assertIsArray($asService);
            $this->assertSame('not for everyone', $asService['body']['private_note'] ?? null);
        } finally {
            $formManager->update([
                'id' => $userForm['id'],
                'label' => $userForm['label'],
                'template' => $userForm['template'],
            ]);
        }
    }

    /**
     * The floor: a webmaster who opens the password field's ACL right up still cannot
     * make the hash readable. Protection-by-template would be protection a webmaster can
     * switch off, which is what ADR-0003 rejected about protection-by-location.
     */
    public function testATemplateCannotOpenUpThePasswordField(): void
    {
        $wiki = $this->getWiki();
        $formManager = $wiki->services->get(FormManager::class);
        $userForm = $formManager->getByContentType(ContentTypeSchema::TYPE_USER);
        $this->assertNotNull($userForm);

        $this->createAccount(self::NAME, 'ufarp-6@example.tld');

        try {
            $opened = array_map(function (array $field) {
                if (($field['name'] ?? '') === 'password') {
                    $field['read_access'] = '*';
                }

                return $field;
            }, $userForm['template']);
            $formManager->update([
                'id' => $userForm['id'],
                'label' => $userForm['label'],
                'template' => $opened,
            ]);

            $page = $wiki->services->get(PageManager::class)
                ->getOne(self::NAME, null, false, false, self::OTHER);

            $this->assertIsArray($page);
            $this->assertSame('', $page['body']['password'] ?? null, 'the hash must stay hidden regardless');
        } finally {
            $formManager->update([
                'id' => $userForm['id'],
                'label' => $userForm['label'],
                'template' => $userForm['template'],
            ]);
        }
    }

    /** The body is JSON now (ticket 09); the redacted value must survive a round trip. */
    public function testRedactionDoesNotCorruptTheStoredBody(): void
    {
        $this->createAccount(self::NAME, 'ufarp-7@example.tld');
        $pageManager = $this->getWiki()->services->get(PageManager::class);

        $redacted = $pageManager->getOne(self::NAME, null, false, false, self::OTHER);
        $this->assertIsArray($redacted);
        // reading a redacted page must not write the blanks back
        $real = $pageManager->getOne(self::NAME, null, false, true);
        $this->assertIsArray($real);

        $this->assertNotSame('', $real['body']['password'] ?? '');
        $this->assertSame(
            $real['body'],
            PageBody::decode(PageBody::encode($real['body'])),
            'the stored body must round-trip unchanged'
        );
    }
}

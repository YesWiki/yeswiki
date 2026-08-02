<?php

namespace YesWiki\Test\Content;

use YesWiki\Content\Exception\ReservedTagException;
use YesWiki\Content\Service\DuplicationManager;
use YesWiki\Content\Service\PageManager;
use YesWiki\Identity\Exception\UserNameReservedException;
use YesWiki\Identity\Service\AclService;
use YesWiki\Identity\Service\AuthenticationService;
use YesWiki\Identity\Service\UserManager;
use YesWiki\Kernel\Routing\ReservedTags;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/**
 * Ticket 20: a tag the router owns is refused everywhere a tag is chosen.
 *
 * Two mechanisms, deliberately different. Where a tag is GENERATED (users, forms, entries,
 * files) suggestFreeTag() treats reserved like taken and suffixes away from it, so no caller
 * has to know the list. Where a human TYPES one, it is refused with a message that says
 * *reserved* rather than *taken* -- and PageManager::save() throws as the backstop, so a
 * caller that invents a tag without asking cannot write Content nothing can ever reach.
 */
class ReservedTagEnforcementTest extends YesWikiTestCase
{
    /** Something reserved, whatever the reserved list happens to be. */
    private function aReservedTag(): string
    {
        return ReservedTags::NAMES[0];
    }

    public function testSuggestFreeTagTreatsAReservedTagAsUnavailable(): void
    {
        $pageManager = $this->getWiki()->services->get(PageManager::class);
        $reserved = $this->aReservedTag();

        $suggested = $pageManager->suggestFreeTag($reserved);

        $this->assertNotSame($reserved, $suggested, 'a reserved tag must never be handed back as free');
        $this->assertFalse(ReservedTags::isReserved($suggested), 'the alternative must itself not be reserved');
        // and there is no row there -- reserved is not the same as taken
        $this->assertFalse($pageManager->tagExists($reserved));
    }

    public function testSavingContentOnAReservedTagIsRefused(): void
    {
        $pageManager = $this->getWiki()->services->get(PageManager::class);
        $reserved = $this->aReservedTag();

        $this->expectException(ReservedTagException::class);
        try {
            $pageManager->save($reserved, ['content' => 'this must never be written'], '', true);
        } finally {
            $this->assertFalse(
                $pageManager->tagExists($reserved),
                'the refused save must not have written a row'
            );
        }
    }

    public function testACaseVariantOfAReservedTagIsRefusedToo(): void
    {
        $pageManager = $this->getWiki()->services->get(PageManager::class);
        $reserved = mb_strtoupper($this->aReservedTag());

        $this->expectException(ReservedTagException::class);
        $pageManager->save($reserved, ['content' => 'nor this'], '', true);
    }

    public function testRenamingContentOntoAReservedTagIsRefused(): void
    {
        $pageManager = $this->getWiki()->services->get(PageManager::class);
        $source = 'ReservedTagEnforcementRenameSource';

        try {
            $pageManager->save($source, ['content' => 'a page to try to rename'], '', true);

            $this->expectException(ReservedTagException::class);
            $pageManager->renameTag($source, $this->aReservedTag());
        } finally {
            $pageManager->deleteOrphaned($source);
        }
    }

    /**
     * The ticket's explicit rule: a username is never silently rewritten into an acceptable
     * one. Registration is the only creation path that refuses instead of suggesting,
     * because an account's name IS its tag (UserManager::buildBody() stores no second copy).
     * Suffixing to `api-2` here would hand someone an account under a name they never typed.
     */
    public function testRegisteringUnderAReservedNameIsRefusedRatherThanSilentlyRenamed(): void
    {
        $wiki = $this->getWiki();
        $userManager = $wiki->services->get(UserManager::class);
        $pageManager = $wiki->services->get(PageManager::class);
        $reserved = $this->aReservedTag();

        if ($userManager->getOneByName($reserved)) {
            $this->markTestSkipped("an account named '{$reserved}' already exists on this wiki");
        }

        try {
            $userManager->create([
                'name' => $reserved,
                'email' => 'reserved-tag-enforcement@example.org',
                'password' => 'un-mot-de-passe-solide',
            ]);
            $this->fail('registering under a reserved name must be refused');
        } catch (UserNameReservedException $refused) {
            $this->assertStringContainsString($reserved, $refused->getMessage());
        }

        // and nothing was created under a suffixed name behind their back
        $this->assertNull($userManager->getOneByName($reserved));
        $this->assertFalse($pageManager->tagExists($reserved . '-2'));
        $this->assertFalse($pageManager->tagExists($reserved . '2'));
    }

    /**
     * The message matters as much as the refusal: "someone already has this" sends a
     * webmaster looking for a page that does not exist.
     */
    public function testDuplicatingOntoAReservedTagSaysReservedRatherThanTaken(): void
    {
        $wiki = $this->getWiki();
        $duplicationManager = $wiki->services->get(DuplicationManager::class);
        $reserved = $this->aReservedTag();

        // the reserved check sits behind the admin check, so without a session this would
        // skip and assert nothing -- which is the hole, not the test
        $this->loginAsAdmin();
        try {
            $duplicationManager->checkPostData([
                'type' => 'page',
                'newTag' => $reserved,
                'duplicate-action' => 'open',
            ]);
            $this->fail('duplicating onto a reserved tag must be refused');
        } catch (\PHPUnit\Framework\AssertionFailedError $failure) {
            throw $failure;
        } catch (\Throwable $thrown) {
            $message = $thrown->getMessage();
            $this->assertStringContainsString(
                $reserved,
                $message,
                'the message must name the tag that was refused'
            );
            $this->assertStringNotContainsString(
                _t('ALREADY_EXISTING'),
                $message,
                'a reserved tag is not a taken one -- saying "already exists" sends the webmaster hunting for a page that is not there'
            );
            $this->assertStringContainsString(
                _t('RESERVED_TAG_CANNOT_BE_USED', ['tag' => $reserved]),
                $message
            );
        } finally {
            $wiki->services->get(AuthenticationService::class)->logout();
        }
    }

    private function loginAsAdmin(): void
    {
        $wiki = $this->getWiki();
        $aclService = $wiki->services->get(AclService::class);
        $admin = current(array_filter(
            $wiki->services->get(UserManager::class)->getAll(),
            fn ($user) => $aclService->isAdmin($user['name'])
        ));
        $this->assertNotFalse($admin, 'need an existing admin on this wiki');
        $wiki->services->get(AuthenticationService::class)->login($admin);
    }
}

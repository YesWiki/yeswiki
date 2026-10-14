<?php

namespace YesWiki\Test\Login\Actions;

use YesWiki\Core\Service\UserManager;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/**
 * The user list filters on a real date only, and prints names as text.
 */
class ListusersActionTest extends YesWikiTestCase
{
    private const USER = 'ListusersActionTestUser';

    private $wiki;

    protected function setUp(): void
    {
        $this->wiki = $this->getWiki();
        $GLOBALS['wiki'] = $this->wiki;
        $userManager = $this->wiki->services->get(UserManager::class);
        if (!$userManager->getOneByName(self::USER)) {
            $userManager->create([
                'name' => self::USER,
                'email' => 'listusersactiontest@example.com',
                'password' => 'a-long-enough-password',
            ]);
        }
    }

    protected function tearDown(): void
    {
        $userManager = $this->wiki->services->get(UserManager::class);
        if ($user = $userManager->getOneByName(self::USER)) {
            $userManager->delete($user);
        }
    }

    public function testAPeriodThatIsNotADateIsIgnored()
    {
        $output = $this->wiki->Format('{{listusers period="2999-01-01 OR 1"}}');

        $this->assertStringContainsString(self::USER, $output);
    }

    public function testADatePeriodStillFilters()
    {
        $this->assertStringContainsString(self::USER, $this->wiki->Format('{{listusers period="2000-01-01"}}'));
        $this->assertStringNotContainsString(self::USER, $this->wiki->Format('{{listusers period="2999-01-01"}}'));
    }
}

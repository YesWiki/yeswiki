<?php

namespace YesWiki\Test\Core\Service;

use YesWiki\Core\Service\AclService;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

class AclServiceTest extends YesWikiTestCase
{
    public function testACLServiceExisting(): AclService
    {
        $wiki = $this->getWiki();
        $this->assertTrue($wiki->services->has(AclService::class));

        return $wiki->services->get(AclService::class);
    }

    /**
     * @depends testACLServiceExisting
     * @dataProvider checkAclProvider
     * @covers \AclService::check
     */
    public function testCheckAcl(string $acl, $expected, AclService $aclService)
    {
        $this->assertSame($expected, $aclService->check($acl));
    }

    public function checkAclProvider()
    {
        // acl , expected
        return [
            'public' => ['*', true],
            'connected' => ['+', false],
            'admin' => ['@admins', false],
        ];
    }
}

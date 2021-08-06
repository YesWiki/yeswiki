<?php

namespace YesWiki\Test\Core\Service;

use PHPUnit\Framework\TestCase;
use YesWiki\Core\Service\AclService;
use YesWiki\Core\YesWikiLoader;
use YesWiki\Wiki;

class AclServiceTest extends TestCase
{
    /**
     * @return AclService
     */
    public function testACLServiceExisting(): AclService
    {
        require_once 'includes/YesWikiLoader.php';
        $wiki = YesWikiLoader::getWiki();
        $this->assertTrue($wiki->services->has(AclService::class));
        return $wiki->services->get(AclService::class);
    }

    /**
     * @depends testACLServiceExisting
     * @dataProvider checkAclProvider
     * @covers AclService::check
     * @param AclService $aclService
     */
    public function testCheckAcl(string $acl, $expected, AclService $aclService)
    {
        $this->assertSame($expected, $aclService->check($acl));
    }
    
    public function checkAclProvider()
    {
        // acl , expected
        return [
            'public' => ['*',true],
            'connected' => ['+',false],
            'admin' => ['@admins',false],
        ];
    }
}

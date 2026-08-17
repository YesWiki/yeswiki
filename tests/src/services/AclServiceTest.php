<?php

namespace YesWiki\Test\Core\Service;

use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Depends;
use YesWiki\Identity\Service\AclService;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

#[CoversMethod(AclService::class, 'check')]
class AclServiceTest extends YesWikiTestCase
{
    public function testACLServiceExisting(): AclService
    {
        $wiki = $this->getWiki();
        $this->assertTrue($wiki->services->has(AclService::class));

        return $wiki->services->get(AclService::class);
    }

    #[Depends('testACLServiceExisting')]
    #[DataProvider('checkAclProvider')]
    public function testCheckAcl(string $acl, $expected, AclService $aclService)
    {
        $this->assertSame($expected, $aclService->check($acl));
    }

    public static function checkAclProvider()
    {
        return [
            'public' => ['*', true],
            'connected' => ['+', false],
            'admin' => ['@admins', false],
        ];
    }
}

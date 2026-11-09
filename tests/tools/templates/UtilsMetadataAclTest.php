<?php

namespace YesWiki\Test\Templates\Service;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use YesWiki\Bazar\Controller\EntryController;
use YesWiki\Bazar\Service\EntryManager;
use YesWiki\Core\Service\AclService;
use YesWiki\Templates\Service\Utils;
use YesWiki\Wiki;

require_once 'includes/autoload.inc.php';
require_once 'includes/YesWiki.php';

class UtilsMetadataAclTest extends TestCase
{
    private const PAGE = [
        'tag' => 'MetadataRestrictedPage',
        'body' => '<h1>Restricted heading</h1>{{attach file="private.png"}}',
    ];

    private function metadata(bool $allowed): array
    {
        $wiki = (new \ReflectionClass(Wiki::class))->newInstanceWithoutConstructor();
        $wiki->services = new ContainerBuilder();
        $acl = $this->getMockBuilder(AclService::class)->disableOriginalConstructor()->getMock();
        $acl->method('hasAccess')->with('read', self::PAGE['tag'])->willReturn($allowed);
        $wiki->services->set(AclService::class, $acl);
        $entries = $this->getMockBuilder(EntryManager::class)->disableOriginalConstructor()->getMock();
        $wiki->services->set(EntryManager::class, $entries);
        $controller = $this->getMockBuilder(EntryController::class)->disableOriginalConstructor()->getMock();
        $wiki->services->set(EntryController::class, $controller);
        $params = new ParameterBag(['opengraph_image' => 'https://example.org/public-logo.png']);
        $utils = new class($params, $wiki) extends Utils {
            public bool $imageRead = false;

            protected function getResizedFilename(string $fileName, array $page, string $tag, string $width, string $height, bool $extractFullFileName = false): string
            {
                $this->imageRead = true;

                return 'https://example.org/private-image.png';
            }
        };

        return [$utils, $entries, $controller];
    }

    public function testDeniedPageMetadataDoesNotReadOrRenderTheEntry(): void
    {
        [$utils, $entries, $controller] = $this->metadata(false);
        $entries->expects($this->never())->method('isEntry');
        $entries->expects($this->never())->method('getOne');
        $controller->expects($this->never())->method('view');

        $this->assertSame('', $utils->getTitleFromBody(self::PAGE));
        $this->assertSame('', $utils->getDescriptionFromBody(self::PAGE, 'Restricted heading'));
        $this->assertSame('https://example.org/public-logo.png', $utils->getImageFromBody(self::PAGE, '1200', '630'));
        $this->assertFalse($utils->imageRead);
    }

    public function testReadableOrdinaryPageKeepsItsHeadingAndImage(): void
    {
        [$utils, $entries, $controller] = $this->metadata(true);
        $entries->expects($this->once())->method('isEntry')->willReturn(false);
        $entries->expects($this->never())->method('getOne');
        $controller->expects($this->never())->method('view');

        $this->assertSame('Restricted heading', $utils->getTitleFromBody(self::PAGE));
        $this->assertSame('https://example.org/private-image.png', $utils->getImageFromBody(self::PAGE, '1200', '630'));
        $this->assertTrue($utils->imageRead);
    }

    public function testReadableEntryKeepsItsTitleAndDescription(): void
    {
        [$utils, $entries, $controller] = $this->metadata(true);
        $entries->expects($this->exactly(2))->method('isEntry')->willReturn(true);
        $entries->expects($this->exactly(2))->method('getOne')->willReturn(['bf_titre' => 'Entry title', 'bf_description' => 'Entry description']);
        $controller->expects($this->never())->method('view');

        $this->assertSame('Entry title', $utils->getTitleFromBody(self::PAGE));
        $this->assertSame('Entry description', $utils->getDescriptionFromBody(self::PAGE, 'Entry title'));
    }

    public function testReadableEntryCanStillRenderItsDescriptionFallback(): void
    {
        [$utils, $entries, $controller] = $this->metadata(true);
        $entries->expects($this->once())->method('isEntry')->willReturn(true);
        $entry = ['bf_titre' => 'Entry title'];
        $entries->expects($this->once())->method('getOne')->willReturn($entry);
        $controller->expects($this->once())->method('view')->with($entry, '', 0)->willReturn('<p>Rendered description</p>');

        $this->assertSame('Rendered description', $utils->getDescriptionFromBody(self::PAGE, 'Entry title'));
    }

    public function testMissingPageTagDoesNotExposeMetadata(): void
    {
        [$utils, $entries, $controller] = $this->metadata(false);
        $entries->expects($this->never())->method('isEntry');
        $controller->expects($this->never())->method('view');
        $page = ['body' => '<h1>Unscoped content</h1>'];

        $this->assertSame('', $utils->getTitleFromBody($page));
        $this->assertSame('', $utils->getDescriptionFromBody($page, ''));
        $this->assertSame('https://example.org/public-logo.png', $utils->getImageFromBody($page, '1200', '630'));
        $this->assertFalse($utils->imageRead);
    }
}

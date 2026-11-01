<?php

namespace YesWiki\Test\Render;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use YesWiki\Content\Service\EntryManager;
use YesWiki\Identity\Service\AclService;
use YesWiki\Kernel\Service\PerformableArguments;
use YesWiki\Kernel\Service\UrlFormatter;
use YesWiki\Render\Service\TemplateHelperService;

require_once 'src/constants.php';

class MetadataReadAccessTest extends TestCase
{
    private const PAGE = [
        'tag' => 'MetadataRestrictedPage',
        'body' => ['content' => '<h1>Restricted heading</h1>{{attach file="private.webp"}}'],
    ];

    /** @return array{0: TemplateHelperService&object{imageRead: bool}, 1: EntryManager&\PHPUnit\Framework\MockObject\MockObject} */
    private function helper(bool $allowed): array
    {
        $container = new ContainerBuilder();
        $acl = $this->createMock(AclService::class);
        $acl->method('hasAccess')->with('read', self::PAGE['tag'])->willReturn($allowed);
        $container->set(AclService::class, $acl);
        $entries = $this->createMock(EntryManager::class);
        $container->set(EntryManager::class, $entries);
        $params = new ParameterBag(['opengraph_image' => 'https://example.org/public-logo.png']);

        $helper = new class($params, $container, $this->createStub(UrlFormatter::class), $this->createStub(PerformableArguments::class)) extends TemplateHelperService {
            public bool $imageRead = false;

            protected function getResizedFilename(string $fileName, array $page, string $tag, string $width, string $height, bool $extractFullFileName = false): string
            {
                $this->imageRead = true;

                return 'https://example.org/private-image.png';
            }
        };

        return [$helper, $entries];
    }

    public function testDeniedPageGivesNoTitleDescriptionOrImage(): void
    {
        [$helper, $entries] = $this->helper(false);
        $entries->expects($this->never())->method('isEntry');
        $entries->expects($this->never())->method('getOne');

        $this->assertSame('', $helper->getTitleFromBody(self::PAGE));
        $this->assertSame('', $helper->getDescriptionFromBody(self::PAGE, 'Restricted heading'));
        $this->assertSame('https://example.org/public-logo.png', $helper->getImageFromBody(self::PAGE, '1200', '630'));
        $this->assertFalse($helper->imageRead);
    }

    public function testReadablePageKeepsItsHeadingAndWebpImage(): void
    {
        [$helper, $entries] = $this->helper(true);
        $entries->expects($this->atLeastOnce())->method('isEntry')->willReturn(false);

        $this->assertSame('Restricted heading', $helper->getTitleFromBody(self::PAGE));
        $this->assertSame('https://example.org/private-image.png', $helper->getImageFromBody(self::PAGE, '1200', '630'));
        $this->assertTrue($helper->imageRead);
    }

    public function testPageWithoutTagGivesNothing(): void
    {
        [$helper, $entries] = $this->helper(false);
        $entries->expects($this->never())->method('isEntry');
        $page = ['body' => ['content' => '<h1>Unscoped content</h1>']];

        $this->assertSame('', $helper->getTitleFromBody($page));
        $this->assertSame('', $helper->getDescriptionFromBody($page, ''));
        $this->assertSame('https://example.org/public-logo.png', $helper->getImageFromBody($page, '1200', '630'));
        $this->assertFalse($helper->imageRead);
    }
}

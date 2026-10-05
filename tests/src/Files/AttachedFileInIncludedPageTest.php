<?php

namespace YesWiki\Test\Files;

use YesWiki\Files\Service\AttachedFilePaths;
use YesWiki\Files\Service\Storage;
use YesWiki\Kernel\Service\InclusionStack;
use YesWiki\Kernel\Service\PageContext;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/** A legacy attachment written in an included page is looked up on that page before the host's. */
class AttachedFileInIncludedPageTest extends YesWikiTestCase
{
    public function testTheIncludedPageOwnsTheNamesItWrites(): void
    {
        $services = $this->getWiki()->services;
        $paths = $services->get(AttachedFilePaths::class);
        if (!$paths->isSafeMode()) {
            $this->markTestSkipped('the dev wiki keeps uploads in per-page directories');
        }
        $storage = $services->get(Storage::class);
        $context = $services->get(PageContext::class);
        $inclusions = $services->get(InclusionStack::class);
        $base = rtrim((string)$paths->config()['upload_path'], '/');
        $included = 'AfiipFooter' . uniqid();
        $file = "{$base}/{$included}_logo_20240101000000_20240101000000.png";
        $storage->write($file, 'logo');
        $previousTag = $context->getTag();
        $context->setTag('AfiipHost' . uniqid());

        $withoutInclusion = $paths->fullFilename('logo.png');
        $inclusions->register($included);
        try {
            $this->assertSame('', $withoutInclusion);
            $this->assertSame($file, $paths->fullFilename('logo.png'));
        } finally {
            $inclusions->unregisterLast();
            $context->setTag($previousTag);
            $storage->delete($file);
        }
    }
}

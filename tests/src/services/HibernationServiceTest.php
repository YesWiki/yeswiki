<?php

namespace YesWiki\Test\Core\Service;

use PHPUnit\Framework\Attributes\CoversMethod;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use YesWiki\Core\Service\HibernationService;
use YesWiki\Core\Service\TemplateEngine;
use YesWiki\Test\Core\ForcedParameterBag;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';
require_once 'tests/ForcedParameterBag.php';

/**
 * Regression tests for ticket 15 (security-core-split): HibernationService is the new,
 * standalone home for the hibernation check formerly bundled inside SecurityController.
 */
#[CoversMethod(HibernationService::class, 'isWikiHibernated')]
#[CoversMethod(HibernationService::class, 'getMessageWhenHibernated')]
class HibernationServiceTest extends YesWikiTestCase
{
    private function buildService($wiki, string $wikiStatus): HibernationService
    {
        $realParams = $wiki->services->get(ParameterBagInterface::class);
        $forcedParams = new ForcedParameterBag($realParams, ['wiki_status' => $wikiStatus]);

        return new HibernationService($forcedParams, $wiki->services->get(TemplateEngine::class));
    }

    public function testIsWikiHibernatedForEachStatus()
    {
        $wiki = $this->getWiki();

        foreach (['hibernate', 'archiving', 'updating'] as $status) {
            $this->assertTrue($this->buildService($wiki, $status)->isWikiHibernated(), "wiki_status '{$status}' must be considered hibernated");
        }
        foreach (['running', '', 'unknown'] as $status) {
            $this->assertFalse($this->buildService($wiki, $status)->isWikiHibernated(), "wiki_status '{$status}' must not be considered hibernated");
        }
    }

    public function testGetMessageWhenHibernatedRendersHibernationNotice()
    {
        $wiki = $this->getWiki();
        $message = $this->buildService($wiki, 'hibernate')->getMessageWhenHibernated();

        $this->assertStringContainsString(_t('WIKI_IN_HIBERNATION'), $message);
    }
}

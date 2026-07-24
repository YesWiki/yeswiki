<?php

namespace YesWiki\Test\Core\Service;

use PHPUnit\Framework\Attributes\CoversMethod;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use YesWiki\Core\Service\HibernationService;
use YesWiki\Core\Service\TemplateEngine;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

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
        $forcedParams = new class ($realParams, $wikiStatus) implements ParameterBagInterface {
            public function __construct(private ParameterBagInterface $real, private string $wikiStatus)
            {
            }

            public function get(string $name): \UnitEnum|array|string|int|float|bool|null
            {
                return $name === 'wiki_status' ? $this->wikiStatus : $this->real->get($name);
            }

            public function has(string $name): bool
            {
                return $name === 'wiki_status' ? true : $this->real->has($name);
            }

            public function clear(): void
            {
                $this->real->clear();
            }
            public function add(array $parameters): void
            {
                $this->real->add($parameters);
            }
            public function all(): array
            {
                return $this->real->all();
            }
            public function remove(string $name): void
            {
                $this->real->remove($name);
            }
            public function set(string $name, $value): void
            {
                $this->real->set($name, $value);
            }
            public function resolve(): void
            {
                $this->real->resolve();
            }
            public function resolveValue(mixed $value): mixed
            {
                return $this->real->resolveValue($value);
            }
            public function escapeValue(mixed $value): mixed
            {
                return $this->real->escapeValue($value);
            }
            public function unescapeValue(mixed $value): mixed
            {
                return $this->real->unescapeValue($value);
            }
        };

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

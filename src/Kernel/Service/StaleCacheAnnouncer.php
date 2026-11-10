<?php

namespace YesWiki\Kernel\Service;

use Psr\Container\ContainerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use YesWiki\Kernel\Entity\Event;

/**
 * Says which addresses a shared cache may now serve stale, when the wiki lets one keep its pages (`http_cache_ttl`): `cache.stale` with
 * the URLs of a page or entry that was saved or deleted, for an operator whose CDN has a purge API (replicas 17). A listing that shows
 * the entry is not among them; the TTL is what bounds it.
 */
class StaleCacheAnnouncer implements EventSubscriberInterface
{
    public const EVENT = 'cache.stale';

    public function __construct(
        private readonly ParameterBagInterface $params,
        private readonly ContainerInterface $container,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return array_fill_keys(['page.created', 'page.updated', 'page.deleted', 'entry.created', 'entry.updated', 'entry.deleted'], 'announce');
    }

    public function announce(Event $event): void
    {
        $ttl = $this->params->has(HttpCacheHeaders::TTL) ? $this->params->get(HttpCacheHeaders::TTL) : 0;
        $tag = $event->getData()['id'] ?? '';
        if (!is_numeric($ttl) || (int)$ttl <= 0 || !is_string($tag) || $tag === '') {
            return;
        }
        $urls = $this->container->get(UrlFormatter::class);
        $this->container->get(EventDispatcher::class)->yesWikiDispatch(self::EVENT, [
            'tag' => $tag,
            'urls' => array_values(array_unique([$urls->href('', $tag, null, false), $urls->href('show', $tag, null, false), $urls->href('rss', $tag, null, false)])),
        ]);
    }
}

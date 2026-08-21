<?php

namespace YesWiki\Content\Service;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use YesWiki\Kernel\Service\TripleStore;

class FavoritesManager
{
    public const TYPE_URI = 'https://yeswiki.net/vocabulary/favorite';

    protected ParameterBagInterface $params;
    protected TripleStore $tripleStore;

    public function __construct(ParameterBagInterface $params, TripleStore $tripleStore)
    {
        $this->params = $params;
        $this->tripleStore = $tripleStore;
    }

    public function isUserFavorite(string $userName, string $tag): bool
    {
        if (empty($userName)) {
            throw new \Exception('userName should not be empty !');
        }
        if (empty($tag)) {
            throw new \Exception('tag should not be empty !');
        }
        $value = "%\\\"user\\\":\\\"{$userName}\\\"%";
        $triples = $this->tripleStore->getMatching(
            $tag,
            self::TYPE_URI,
            $value,
            '=',
            '=',
            'LIKE'
        );

        return count($triples) > 0;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getUserFavorites(string $userName): array
    {
        if (empty($userName)) {
            throw new \Exception('userName should not be empty !');
        }
        $value = "%\\\"user\\\":\\\"{$userName}\\\"%";
        $triples = $this->tripleStore->getMatching(
            null,
            self::TYPE_URI,
            $value,
            '=',
            '=',
            'LIKE'
        );

        return $triples;
    }

    public function areFavoritesActivated(): bool
    {
        return (bool)$this->params->get('favorites_activated');
    }
}

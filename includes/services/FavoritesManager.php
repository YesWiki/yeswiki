<?php

namespace YesWiki\Core\Service;

use Exception;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class FavoritesManager
{
    public const TYPE_URI = 'https://yeswiki.net/vocabulary/favorite';

    protected $params;
    protected $tripleStore;

    public function __construct(ParameterBagInterface $params, TripleStore $tripleStore)
    {
        $this->params = $params;
        $this->tripleStore = $tripleStore;
    }

    public function isUserFavorite(string $userName, string $tag): bool
    {
        if (empty($userName)) {
            throw new Exception('userName should not be empty !');
        }
        if (empty($tag)) {
            throw new Exception('tag should not be empty !');
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

        return is_array($triples) && count($triples) > 0;
    }

    public function getUserFavorites(string $userName): array
    {
        if (empty($userName)) {
            throw new Exception('userName should not be empty !');
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

        return is_array($triples) && count($triples) > 0 ? $triples : [];
    }

    public function areFavoritesActivated(): bool
    {
        return $this->params->get('favorites_activated');
    }
}

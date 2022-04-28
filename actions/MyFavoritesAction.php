<?php

use YesWiki\Core\Service\FavoritesManager;
use YesWiki\Core\Service\UserManager;
use YesWiki\Core\YesWikiAction;

class MyFavoritesAction extends YesWikiAction
{
    protected $favoritesManager;
    protected $userManager;

    public function formatArguments($arg)
    {
        return [
        ];
    }

    public function run()
    {
        // get Services
        $this->favoritesManager  = $this->getService(FavoritesManager::class);
        $this->userManager  = $this->getService(UserManager::class);

        $user = $this->userManager->getLoggedUser();
        $currentUser = empty($user) ? null : $user['name'];

        $favorites = empty($currentUser) ? [] : $this->favoritesManager->getUserFavorites($currentUser) ;

        return $this->render('@core/actions/my-favorites.twig', [
            'areFavoritesActivated' => $this->favoritesManager->areFavoritesActivated(),
            'currentUser' => $currentUser,
            'favorites' => $favorites,
        ]) ;
    }
}

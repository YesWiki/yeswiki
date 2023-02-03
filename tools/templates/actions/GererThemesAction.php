<?php

/**
 * Cette action à pour but de gérer massivement les droits sur les pages d'un wiki.
 * Les pages s'affichent et sont modifiées en fonction du squelette qu'elles utilisent (définis par l'utilisateur).
*/

use YesWiki\Core\Service\PageManager;
use YesWiki\Core\YesWikiAction;

class GererThemesAction extends YesWikiAction
{
  protected $pageManager;

  public function run()
  {
    if (!$this->wiki->UserIsAdmin()) {
      return $this->render('@templates/alert-message.twig',[
        'type' => 'danger',
        'message' => _t('ACLS_RESERVED_FOR_ADMINS')
      ]);
    }
    
    // get services
    $this->pageManager = $this->getService(PageManager::class);

    require_once 'tools/templates/libs/templates.functions.php';

    $errorMessage = '';
    if (isset($_POST['theme_modifier'])) {
      try {
        $this->modifyTheme();
      } catch (Exception $th) {
        if ($th->getCode() === 1){
          $errorMessage .= $th->getMessage();
        } else {
          throw $th;
        }
      }
    }

    $pages = [];
    $pagesThemes = [];
    foreach($this->pageManager->getAll() as $page){
      if (!empty($page['tag'])){
        // TODO use a service instead of  recup_meta
        $pages[$page['tag']] = $page['tag'];
        $pagesThemes[$page['tag']] = recup_meta($page['tag']);
      }
    }
    // TODO use a template instead
    $themeSelector = theme_selector('post');

    $hibernated = $this->isWikiHibernated();
    return $this->render(
      '@templates/gerer-themes-action.twig',
      compact(['errorMessage','pages','pagesThemes','themeSelector','hibernated'])
    );

  }

  /**
   * @throws Exception with code 1
   */
  protected function modifyTheme(){

    if (!isset($_POST['selectpage'])) {
      throw new Exception(_t('ACLS_NO_SELECTED_PAGE'),1);
    } elseif (!is_array($_POST['selectpage'])){
      throw new Exception('select page should be an array',1);
    } else {
      $pagesTags = array_filter($_POST['selectpage'],'is_string');
      foreach ($pagesTags as $pageTag) {
          if (!empty($_POST['typemaj']) && $_POST['typemaj'] === 'reinitialiser') {
            $this->pageManager->setMetadata($pageTag, [
                'theme' => null, 
                'style' => null, 
                'squelette' => null
              ]);
          } else {
            $this->pageManager->setMetadata($pageTag, [
                'theme' => $this->sanitizePost('theme_select'), 
                'style' => $this->sanitizePost('style_select'), 
                'squelette' => $this->sanitizePost('squelette_select')
              ]);
          }
      }
    }
  }

  /**
   * sanitize string from POST or return null
   * @param string $key
   * @return null|string
   */
  protected function sanitizePost(string $key): ?string
  {
    return !empty($_POST[$key]) && is_string($_POST[$key]) ? $_POST[$key] : null;
  }

}

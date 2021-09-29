<?php

namespace YesWiki\Security\Controller;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use YesWiki\Core\Service\TemplateEngine;
use YesWiki\Core\Service\UserManager;
use YesWiki\Core\YesWikiController;

class SecurityController extends YesWikiController
{
    protected $params;
    protected $templateEngine;

    public function __construct(
        TemplateEngine $templateEngine,
        ParameterBagInterface $params
    ) {
        $this->templateEngine = $templateEngine;
        $this->params = $params;
    }

    /**
     * check if wiki_status is hibernated
     * @return bool true is in hibernation
     */
    public function isWikiHibernated(): bool
    {
        return (in_array($this->params->get('wiki_status'), ['hibernate']));
    }

    /**
     * get alert message when hibernated
     * @return string
     */
    public function getMessageWhenHibernated():string
    {
        $message = [
            'type' => 'info',
            'message' => _t('WIKI_IN_HIBERNATION') . "<br/>"
        ];
        return $this->templateEngine->render('@templates/alert-message-with-back.twig', $message);
    }

    /**
     * check if password for editing is required
     * @return array [bool $state,string $output]
     */
    public function isGrantedPasswordForEditing():array
    {
        $state = !$this->isPasswordForEditingModeActivated() || $this->hasRightPasswordForExisting();
        $message = ($state) ? ''
            : $this->renderNotGrantedPasswordForEditing();
        return [$state,$message];
    }

    /**
     * check if PasswordForEditing mode is activated
     * @return bool
     */
    private function isPasswordForEditingModeActivated(): bool
    {
        return $this->params->has('password_for_editing') &&
            !empty($this->params->get('password_for_editing')) &&
            !$this->getService(UserManager::class)->getLoggedUser() ; // UserManager not loaded in construct to prevent circular references
    }

    /**
     * check if password for editing is correct
     * @return bool
     */
    private function hasRightPasswordForExisting():bool
    {
        return isset($_POST['password_for_editing']) &&
             $_POST['password_for_editing'] == $this->params->get('password_for_editing') ;
    }

    /**
     * render form to ask right password for editing
     * @return string
     */
    private function renderNotGrantedPasswordForEditing(): string
    {
        return $this->templateEngine->render('@security/wrong-password-for-editing.twig',
            [
                'wrongPassword' => isset($_POST['password_for_editing']),
                'passwordForEditingMessage' => ($this->params->has('password_for_editing_message') && 
                    !empty($this->params->get('password_for_editing_message')))
                    ? $this->params->get('password_for_editing_message') : null,
                'time' => $_REQUEST['time'] ?? null,
                'handler' => testUrlInIframe() ? 'editiframe' : 'edit',
            ]);
    }
}

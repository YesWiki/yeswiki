<?php

namespace YesWiki\Core\Service;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use YesWiki\Core\Controller\AuthController;
use YesWiki\Wiki;

class PasswordForEditingService
{
    protected $params;
    protected $templateEngine;
    protected $wiki;

    public function __construct(Wiki $wiki, ParameterBagInterface $params, TemplateEngine $templateEngine)
    {
        $this->wiki = $wiki;
        $this->params = $params;
        $this->templateEngine = $templateEngine;
    }

    /**
     * check if password for editing is required.
     *
     * @return array [bool $state,string $output]
     */
    public function isGrantedPasswordForEditing(): array
    {
        $state = !$this->isPasswordForEditingModeActivated() || $this->hasRightPasswordForExisting();
        $message = ($state) ? ''
            : $this->renderNotGrantedPasswordForEditing();

        return [$state, $message];
    }

    /**
     * check if PasswordForEditing mode is activated.
     */
    private function isPasswordForEditingModeActivated(): bool
    {
        return $this->params->has('password_for_editing')
            && !empty($this->params->get('password_for_editing'))
            // AuthController not loaded in constructor to prevent circular references
            && !$this->wiki->services->get(AuthController::class)->getLoggedUser();
    }

    /**
     * check if password for editing is correct.
     */
    private function hasRightPasswordForExisting(): bool
    {
        $val = $this->wiki->request->request->get('password_for_editing');
        return isset($val) && $val == $this->params->get('password_for_editing');
    }

    /**
     * render form to ask right password for editing.
     */
    private function renderNotGrantedPasswordForEditing(): string
    {
        return $this->templateEngine->render(
            '@core/wrong-password-for-editing.twig',
            [
                'wrongPassword' => $this->wiki->request->request->has('password_for_editing'),
                'passwordForEditingMessage' => ($this->params->has('password_for_editing_message') &&
                    !empty($this->params->get('password_for_editing_message')))
                    ? $this->params->get('password_for_editing_message') : null,
                'time' => $this->wiki->request->get('time'),
                'handler' => testUrlInIframe() ? 'editiframe' : 'edit',
            ]
        );
    }
}

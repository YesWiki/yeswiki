<?php

namespace YesWiki\Identity\Service;

use Psr\Container\ContainerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use YesWiki\Kernel\Service\WikiUrls;
use YesWiki\Render\Service\TemplateEngine;

class PasswordForEditingService
{
    protected ParameterBagInterface $params;
    protected TemplateEngine $templateEngine;
    protected ContainerInterface $container;

    public function __construct(ContainerInterface $container, ParameterBagInterface $params, TemplateEngine $templateEngine)
    {
        $this->container = $container;
        $this->params = $params;
        $this->templateEngine = $templateEngine;
    }

    /**
     * check if password for editing is required.
     *
     * @return array{bool, string} the verdict, and the form to render when it is false
     */
    public function isGrantedPasswordForEditing(): array
    {
        $state = !$this->isPasswordForEditingModeActivated() || $this->hasRightPasswordForExisting();
        $message = ($state) ? ''
            : $this->renderNotGrantedPasswordForEditing();

        return [$state, $message];
    }

    /** check if PasswordForEditing mode is activated. */
    private function isPasswordForEditingModeActivated(): bool
    {
        return $this->params->has('password_for_editing')
            && !empty($this->params->get('password_for_editing'))

            && !$this->container->get(AuthenticationService::class)->getLoggedUser();
    }

    /** check if password for editing is correct. */
    private function hasRightPasswordForExisting(): bool
    {
        $val = $this->container->get(\YesWiki\Kernel\Service\CurrentRequest::class)->get()->request->get('password_for_editing');

        return isset($val) && $val == $this->params->get('password_for_editing');
    }

    /** render form to ask right password for editing. */
    private function renderNotGrantedPasswordForEditing(): string
    {
        return $this->templateEngine->render(
            '@core/wrong-password-for-editing.twig',
            [
                'wrongPassword' => $this->container->get(\YesWiki\Kernel\Service\CurrentRequest::class)->get()->request->has('password_for_editing'),
                'passwordForEditingMessage' => ($this->params->has('password_for_editing_message')
                    && !empty($this->params->get('password_for_editing_message')))
                    ? $this->params->get('password_for_editing_message') : null,
                'time' => $this->container->get(\YesWiki\Kernel\Service\CurrentRequest::class)->get()->get('time'),
                'handler' => WikiUrls::iframeSuffixFor() ? 'editiframe' : 'edit',
            ]
        );
    }
}

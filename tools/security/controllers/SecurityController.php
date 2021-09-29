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
    protected $textes;

    public function __construct(
        TemplateEngine $templateEngine,
        ParameterBagInterface $params
    ) {
        $this->templateEngine = $templateEngine;
        $this->params = $params;
        $this->textes = null;
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

    /**
     * check captcha before save edit
     * @param string $mode 'page' or 'entry'
     * @return array [bool $state,string $error]
     */
    public function checkCaptchaBeforeSave(string $mode = 'page'):array
    {
        if ($this->params->get('use_captcha')) {
            if (($mode != 'entry' && isset($_POST['submit']) && $_POST['submit'] == 'Sauver')
                || ($mode == 'entry' && !empty($_POST['bf_titre']))) {
                if (!defined("CAPTCHA_INCLUDE")){
                    define("CAPTCHA_INCLUDE", true);
                }
                include_once 'tools/security/captcha.php';
                if (isset($textes)){
                    $this->textes = $textes;
                }
                if (empty($_POST['captcha'])) {
                    $error = '<div class="alert alert-danger"><a href="#" data-dismiss="alert" class="close">&times;</a>'._t('CAPTCHA_ERROR_PAGE_UNSAVED').'</div>';
                    $_POST['submit'] = '';
                    if ($mode == 'entry') unset($_POST['bf_titre']);
                } elseif (!empty($_POST['captcha'])) {
                    $wdcrypt = cryptWord($_POST['captcha']);
                    if ($wdcrypt != $_POST['captcha_hash']) {
                        $error = '<div class="alert alert-danger"><a href="#" data-dismiss="alert" class="close">&times;</a>'._t('CAPTCHA_ERROR_WRONG_WORD').'</div>';
                        $_POST['submit'] = '';
                        if ($mode == 'entry') unset($_POST['bf_titre']);
                    }
                }
                unset($_POST['captcha']);
                unset($_POST['captcha_hash']);
            }
        }

        return [empty($error), $error ?? null];
    }
    
    /**
     * render captcha if needed
     * @param string &$output
     */
    public function renderCaptcha(string &$output)
    {
        if ($this->params->get('use_captcha')) {
            $champsCaptcha = $this->renderCaptchaField();
            $output = preg_replace(
                '/\<div class="form-actions">.*<button type=\"submit\" name=\"submit\"/Uis',
                $champsCaptcha.'<div class="form-actions">'."\n".'<button type="submit" name="submit"',
                $output
            );
        }
    }

    /**
     * render captcha field if needed
     * @return string
     */
    public function renderCaptchaField(): string
    {
        $champsCaptcha = '';
        if ($this->params->get('use_captcha')) {
            if (!defined("CAPTCHA_INCLUDE")){
                define("CAPTCHA_INCLUDE", true);
            }
            include_once 'tools/security/captcha.php';
            if (isset($textes)){
                $this->textes = $textes;
            }
            $crypt = cryptWord($this->textes[array_rand($this->textes)]);

            // afficher les champs de formulaire et de l'image
            $champsCaptcha = $this->templateEngine->render('@security/captcha-field.twig',
                [
                    'baseUrl' => $this->wiki->getBaseUrl(),
                    'crypt' => $crypt,
                ]);
        }
        return $champsCaptcha;
    }
}

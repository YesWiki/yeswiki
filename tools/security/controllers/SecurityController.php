<?php

namespace YesWiki\Security\Controller;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use YesWiki\Core\Controller\AuthController;
use YesWiki\Core\Service\TemplateEngine;
use YesWiki\Core\YesWikiController;

class SecurityController extends YesWikiController
{
    // this value cannot be changed because use by extensions
    public const EDIT_PAGE_SUBMIT_VALUE = 'Sauver';

    protected $captchaController;
    protected $params;
    protected $templateEngine;

    public function __construct(
        CaptchaController $captchaController,
        TemplateEngine $templateEngine,
        ParameterBagInterface $params
    ) {
        $this->captchaController = $captchaController;
        $this->templateEngine = $templateEngine;
        $this->params = $params;
    }

    /**
     * check if wiki_status is hibernated.
     *
     * @return bool true is in hibernation
     */
    public function isWikiHibernated(): bool
    {
        return in_array($this->params->get('wiki_status'), ['hibernate', 'archiving', 'updating']);
    }

    /**
     * get alert message when hibernated.
     */
    public function getMessageWhenHibernated(): string
    {
        $message = [
            'type' => 'info',
            'message' => _t('WIKI_IN_HIBERNATION') . '<br/>',
        ];

        return $this->templateEngine->render('@templates/alert-message-with-back.twig', $message);
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
        return $this->params->has('password_for_editing') &&
            !empty($this->params->get('password_for_editing')) &&
            !$this->getService(AuthController::class)->getLoggedUser(); // AuthController not loaded in construct to prevent circular references
    }

    /**
     * check if password for editing is correct.
     */
    private function hasRightPasswordForExisting(): bool
    {
        return isset($_POST['password_for_editing']) &&
             $_POST['password_for_editing'] == $this->params->get('password_for_editing');
    }

    /**
     * render form to ask right password for editing.
     */
    private function renderNotGrantedPasswordForEditing(): string
    {
        return $this->templateEngine->render(
            '@security/wrong-password-for-editing.twig',
            [
                'wrongPassword' => isset($_POST['password_for_editing']),
                'passwordForEditingMessage' => ($this->params->has('password_for_editing_message') &&
                    !empty($this->params->get('password_for_editing_message')))
                    ? $this->params->get('password_for_editing_message') : null,
                'time' => $_REQUEST['time'] ?? null,
                'handler' => testUrlInIframe() ? 'editiframe' : 'edit',
            ]
        );
    }

    /**
     * check captcha before save edit.
     *
     * @param string $mode 'page' or 'entry'
     *
     * @return array [bool $state,string $error]
     */
    public function checkCaptchaBeforeSave(string $mode = 'page'): array
    {
        if (!$this->wiki->UserIsAdmin() && $this->params->get('use_captcha')) {
            if (($mode != 'entry' && isset($_POST['submit']) && $_POST['submit'] == self::EDIT_PAGE_SUBMIT_VALUE)
                || ($mode == 'entry' && !empty($_POST['bf_titre']))) {
                /**
                 * @var string $error message if error
                 */
                $error = '';
                if (empty($_POST['captcha'])) {
                    $error = _t('CAPTCHA_ERROR_PAGE_UNSAVED');
                } elseif (!$this->captchaController->check(
                    $_POST['captcha'] ?? '',
                    $_POST['captcha_hash'] ?? ''
                )) {
                    $error = _t('CAPTCHA_ERROR_WRONG_WORD');
                }
                // clean if error
                if (!empty($error)) {
                    $_POST['submit'] = '';
                    if ($mode == 'entry') {
                        unset($_POST['bf_titre']);
                    }
                }
                unset($_POST['captcha']);
                unset($_POST['captcha_hash']);
            }
        }

        return [empty($error), $error ?? null];
    }

    /**
     * render captcha if needed.
     *
     * @param string &$output
     */
    public function renderCaptcha(string &$output)
    {
        if (!$this->wiki->UserIsAdmin() && $this->params->get('use_captcha')) {
            $champsCaptcha = $this->renderCaptchaField();
            $matches = [];
            if (preg_match_all('/(\<div class="form-actions">.*<button type=\"submit\" name=\"submit\")/Uis', $output, $matches)) {
                foreach ($matches[0] as $key => $match) {
                    $output = str_replace(
                        $match,
                        $champsCaptcha . $matches[1][$key],
                        $output
                    );
                }
            }
        }
    }

    /**
     * render captcha field if needed.
     */
    public function renderCaptchaField(): string
    {
        $champsCaptcha = '';
        if (!$this->wiki->UserIsAdmin() && $this->params->get('use_captcha')) {
            // afficher les champs de formulaire et de l'image
            $hash = $this->captchaController->generateHash();
            $champsCaptcha = $this->templateEngine->render(
                '@security/captcha-field.twig',
                [
                    'baseUrl' => $this->wiki->getBaseUrl(),
                    'crypt' => $hash,
                    'cryptBase64' => base64_encode($hash),
                ]
            );
        }

        return $champsCaptcha;
    }

    /**
     * retrieve input using filter to prevent injection from other php script
     * emulate $filter = FILTER_SANITIZE_STRING because deprecated since php8.1.
     *
     * @param int       $filter  same as filter_input
     * @param string    $format  'string', 'int', 'bool', 'array', '' (empty = not formatted)
     * @param array|int $options same as filter_input
     *
     * @return mixed
     */
    public function filterInput(
        int $type,
        string $varName,
        int $filter = FILTER_DEFAULT,
        bool $emulateFilterSanitizeString = false,
        string $format = '',
        $options = 0
    ) {
        /**
         * @var int $sanitizedFilter
         */
        $sanitizedFilter = $emulateFilterSanitizeString ? FILTER_UNSAFE_RAW : $filter;
        /**
         * @var string $sanitizedFormat
         */
        $sanitizedFormat = $emulateFilterSanitizeString ? 'string' : $format;
        /**
         * @var mixed $rawInputFiltered
         */
        $rawInputFiltered = filter_input($type, $varName, $filter, $options);

        /**
         * @var mixed $result
         */
        $result = null;
        switch ($sanitizedFormat) {
            case 'string':
                $result = (
                    in_array($rawInputFiltered, [false, null], true)
                    || !is_scalar($rawInputFiltered)
                )
                    ? ''
                    : (
                        $emulateFilterSanitizeString
                        ? htmlspecialchars(strip_tags(strval($rawInputFiltered)))
                        : strval($rawInputFiltered)
                    );
                break;
            case 'int':
                $result = (
                    in_array($rawInputFiltered, [false, null], true)
                    || !is_scalar($rawInputFiltered)
                )
                    ? 0
                    : intval($rawInputFiltered);
                break;
            case 'bool':
                $result = in_array($rawInputFiltered, [false, null, 0, 'false', '0'], true)
                    ? false
                    : (
                        in_array($rawInputFiltered, [true, 'true', 1], true)
                        ? true
                        : boolval($rawInputFiltered)
                    );
                break;
            default:
                $result = $rawInputFiltered;
                break;
        }

        return $result;
    }
}

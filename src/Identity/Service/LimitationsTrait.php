<?php

namespace YesWiki\Identity\Service;

/**
 * Reads a numeric limit out of the config, or falls back to a default.
 *
 * In its own file because a trait is a type like any other, and the autoloader can only find
 * a type in the file named after it. It used to live at the top of AuthenticationService.php,
 * which meant `UserOperationsService` could only use it when something had already loaded
 * AuthenticationService -- a comment there even said so. Load UserOperationsService first and
 * PHP fatals with "Trait LimitationsTrait not found", which is what happens on any request
 * that reaches user operations without authenticating first.
 */
trait LimitationsTrait
{
    /**
     * init and store limitations in limitations array.
     */
    private function initLimitationHelper(string $parameterName, string $limitationKey, $type, $default, string $errorMessageKey)
    {
        $this->limitations[$limitationKey] = $default;
        if ($this->params->has($parameterName)) {
            $parameter = $this->params->get($parameterName);
            if (!filter_var($parameter, FILTER_VALIDATE_INT)) {
                trigger_error(_t($errorMessageKey));
            } else {
                $this->limitations[$limitationKey] = $parameter;
            }
        }
    }
}

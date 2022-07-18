<?php

namespace YesWiki\Core\Trait;

trait LimitationsTrait
{
    /**
     * init and store limitations in limitations array
     * @param string $parameterName
     * @param string $limitationKey
     * @param mixed $type
     * @param mixed $default
     * @param string $errorMessageKey
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

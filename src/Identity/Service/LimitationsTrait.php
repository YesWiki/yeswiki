<?php

namespace YesWiki\Identity\Service;

/** Reads a numeric limit out of the config, or falls back to a default. */
trait LimitationsTrait
{
    /** Init and store one limitation in the limitations array. */
    private function initLimitationHelper(
        string $parameterName,
        string $limitationKey,
        int $default,
        string $errorMessageKey
    ): void {
        $this->limitations[$limitationKey] = $default;
        if (!$this->params->has($parameterName)) {
            return;
        }

        $configured = filter_var($this->params->get($parameterName), FILTER_VALIDATE_INT);
        if ($configured === false) {
            trigger_error(_t($errorMessageKey));

            return;
        }

        $this->limitations[$limitationKey] = $configured;
    }
}

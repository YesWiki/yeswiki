<?php

namespace YesWiki\Identity\Service;

/** Reads a numeric limit out of the config, or falls back to a default. */
trait LimitationsTrait
{
    /**
     * Init and store one limitation in the limitations array.
     *
     * The `$type` parameter this used to take was always `FILTER_VALIDATE_INT` at all three
     * call sites and was never read -- the filter below is hard-coded. It is gone.
     */
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

        // `=== false` rather than `!`, so a configured 0 reads as the integer it is instead of
        // being reported as "not an integer"
        $configured = filter_var($this->params->get($parameterName), FILTER_VALIDATE_INT);
        if ($configured === false) {
            trigger_error(_t($errorMessageKey));

            return;
        }

        $this->limitations[$limitationKey] = $configured;
    }
}

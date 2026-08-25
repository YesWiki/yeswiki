<?php

namespace YesWiki\Kernel\Health;

/**
 * Declares what a module knows how to check about itself (ADR-0026).
 *
 * The module that owns the subject owns the check: Search declares `ext-intl`, Files declares
 * whether the bucket answers, Admin declares the update checks, an extension declares its own.
 * One controller that knew about every module would violate ADR-0013's dependency rule to do it.
 */
interface ProvidesHealthChecks
{
    /**
     * @return list<HealthCheck> may be empty -- a provider that decides this wiki has nothing
     *                           to check says so by returning nothing, not by being skipped
     */
    public function healthChecks(): array;
}

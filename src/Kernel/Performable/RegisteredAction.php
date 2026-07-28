<?php

namespace YesWiki\Kernel\Performable;

/**
 * An action: invoked as `{{name}}` from page content.
 *
 * Separate from RegisteredHandler so the container can tag the two independently -- a class
 * implementing the base marker alone would be ambiguous, and one implementing both would be
 * registered twice.
 */
interface RegisteredAction extends RegisteredPerformable
{
}

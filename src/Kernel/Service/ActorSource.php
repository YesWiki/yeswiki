<?php

namespace YesWiki\Kernel\Service;

/**
 * Who is acting -- so that Kernel can name them without knowing how a wiki authenticates anyone (ADR-0013).
 */
interface ActorSource
{
    /** The acting user's name, their address when nobody is signed in, or '' off a request. */
    public function currentActor(): string;
}

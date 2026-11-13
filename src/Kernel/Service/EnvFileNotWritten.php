<?php

namespace YesWiki\Kernel\Service;

use Symfony\Component\DependencyInjection\Attribute\Exclude;

/** private/.env could not be written: carries what it should hold, for the operator to put there by hand. */
#[Exclude]
final class EnvFileNotWritten extends \Exception
{
    public function __construct(public readonly string $path, public readonly string $contents)
    {
        parent::__construct("Could not write $path. Create it with these lines, then reload:\n\n$contents");
    }
}

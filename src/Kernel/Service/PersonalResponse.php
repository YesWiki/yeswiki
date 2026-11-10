<?php

namespace YesWiki\Kernel\Service;

/** Whether this response is somebody's own, so that no shared cache may keep it: a signed-in reader, a CSRF token, a flash message (replicas 17). */
class PersonalResponse implements RequestScopedState
{
    private bool $personal = false;

    public function startNewRequest(): void
    {
        $this->personal = false;
    }

    public function mark(): void
    {
        $this->personal = true;
    }

    public function isMarked(): bool
    {
        return $this->personal;
    }
}

<?php

namespace YesWiki\Content\Service;

use Symfony\Component\HttpFoundation\Request;

/**
 * Detects the bazar "fast access" entry view: a request for exactly one entry as
 * pre-rendered html (`api/entries/html/{tag}?fields=html_output`). Lived on the
 * monolithic ApiController (isEntryViewFastAccess/isEntryViewFastAccessHelper)
 * before ticket 08 split it; EmailField and EntryApiController both need it.
 */
class EntryFastAccessService
{
    public function isFastAccess(mixed $output, mixed $selectedEntries, array $get): bool
    {
        return $output == 'html'
            && !empty($selectedEntries) && is_string($selectedEntries) && count(explode(',', $selectedEntries)) == 1
            && !empty($get['fields']) && $get['fields'] == 'html_output';
    }

    /** Same check, derived from the raw request (historic isEntryViewFastAccessHelper()). */
    public function isFastAccessRequest(Request $request): bool
    {
        $queryAll = $request->query->all();
        $route = array_key_first($queryAll);
        if (substr((string)$route, strlen('api/entries/html'), 1) == '/') {
            $output = substr((string)$route, strlen('api/entries/'), strlen('html'));
            $selectedEntries = substr((string)$route, strlen('api/entries/html/'));
        } else {
            $output = '';
            $selectedEntries = '';
        }

        return $this->isFastAccess($output, $selectedEntries, $queryAll);
    }
}

<?php

namespace YesWiki\Core\Service;

class StringUtilService
{
    public static function folderToNamespace(string $folder): string
    {
        if (preg_match_all('/[a-zA-Z0-9]+/', $folder, $matches) === false) {
            return '';
        }

        return implode('', array_map(function ($input) {return ucfirst(strtolower($input)); }, $matches[0]));
    }
}

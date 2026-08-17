<?php

namespace YesWiki\Kernel\Service;

class StringUtilService
{
    public static function folderToNamespace(string $folder): string
    {
        if (preg_match_all('/[a-zA-Z0-9]+/', $folder, $matches) === false) {
            return '';
        }

        return implode('', array_map(function ($input) {return ucfirst(strtolower($input)); }, $matches[0]));
    }

    /** Cryptographically random string over $charset (historic Wiki::generateRandomString()). */
    public static function generateRandomString(
        int $length = 30,
        string $charset = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+-_*=.:,?'
    ): string {
        $randomString = '';
        $maxIndex = strlen($charset) - 1;

        if ($length < 1) {
            $length = 30;
        }

        for ($i = 0; $i < $length; $i++) {
            $randomString .= substr($charset, random_int(0, $maxIndex), 1);
        }

        return $randomString;
    }

    /**
     * Replace recursively all the indexed (list-like) arrays of $array1 with the corresponding indexed array of $array2, leaving associative keys merged (historic Wiki::replaceRecursivelyIndexedArrays()).
     *
     * @param array<mixed>|null $array1 untyped on purpose: recursing into a key absent
     *                                  from $array1 hands the next call a null reference
     *                                  that the assignment below auto-vivifies
     * @param array<mixed>      $array2
     */
    public static function replaceRecursivelyIndexedArrays(&$array1, array &$array2): void
    {
        foreach ($array2 as $key => $val) {
            if (is_array($val)) {
                if (!self::isAssocArray($val)) {
                    if (!isset($array1[$key]) || $array1[$key] != $val) {
                        $array1[$key] = $val;
                    }
                } else {
                    $subarray1 = &$array1[$key];
                    $subarray2 = &$array2[$key];
                    self::replaceRecursivelyIndexedArrays($subarray1, $subarray2);
                }
            }
        }
    }

    /**
     * NOT array_is_list(): the historic implementation treats an empty array as associative, and replaceRecursivelyIndexedArrays() depends on that.
     *
     * @param array<mixed> $arr
     */
    private static function isAssocArray(array $arr): bool
    {
        return array_keys($arr) !== range(0, count($arr) - 1);
    }
}

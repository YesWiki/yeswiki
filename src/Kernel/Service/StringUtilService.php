<?php

namespace YesWiki\Kernel\Service;

class StringUtilService
{
    /** $string with its accents and every other HTML entity removed. */
    public static function withoutDiacritics(string $str, string $charset = YW_CHARSET): string
    {
        $str = htmlentities($str, ENT_NOQUOTES, $charset);
        $str = (string)preg_replace('#&([A-za-z])(?:acute|cedil|caron|circ|grave|orn|ring|slash|th|tilde|uml);#', '\1', $str);
        $str = (string)preg_replace('#&([A-za-z]{2})(?:lig);#', '\1', $str);
        $str = (string)preg_replace('#&[^;]+;#', '', $str);

        return $str;
    }

    /** $string as a filename: no accents, no dangerous characters, lowercase, single dashes. */
    public static function asFilename(string $string = ''): string
    {
        $dangerous_characters = [' ', '"', "'", '&', '/', '\\', '?', '#', '(', ')', '+'];
        $string = (string)str_replace($dangerous_characters, '-', self::withoutDiacritics((string)$string));

        return mb_strtolower((string)preg_replace('/--+/u', '-', $string), YW_CHARSET);
    }

    /**
     * Every sub-array of $array, at any depth, whose $key is $value.
     *
     * @return list<mixed>
     */
    public static function searchNested(mixed $array, string $key, mixed $value): array
    {
        $results = [];

        if (is_array($array)) {
            if (isset($array[$key]) && $array[$key] == $value) {
                $results[] = $array;
            }

            foreach ($array as $subarray) {
                $results = array_merge($results, self::searchNested($subarray, $key, $value));
            }
        }

        return $results;
    }

    /** $string with its accented characters folded to the bare letter, for a CSS class or a filter key. */
    public static function withoutAccents(string $string): string
    {
        return (string)preg_replace(
            '~&([a-z]{1,2})(?:acute|cedil|circ|grave|lig|orn|ring|slash|th|tilde|uml|caron);~i',
            '$1',
            htmlentities(html_entity_decode($string), ENT_QUOTES, YW_CHARSET)
        );
    }

    /** $string cut at the last whole word that fits in $width characters. */
    public static function truncateOnWord(string $string, int $width): string
    {
        $parts = preg_split('/([\s\n\r]+)/', $string, 0, PREG_SPLIT_DELIM_CAPTURE);
        $parts = $parts === false ? [] : $parts;

        $length = 0;
        $lastPart = 0;
        for (; $lastPart < count($parts); $lastPart++) {
            $length += strlen($parts[$lastPart]);
            if ($length > $width) {
                break;
            }
        }

        return implode(array_slice($parts, 0, $lastPart));
    }

    /** Cut $text to $length characters on a word boundary, appending $append when it had to cut. */
    public static function truncate(string $text, int $length = 100, string $append = '&hellip;'): string
    {
        $string = trim($text);
        if (strlen($string) <= $length) {
            return $string;
        }

        return explode("\n", wordwrap($string, $length), 2)[0] . $append;
    }

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

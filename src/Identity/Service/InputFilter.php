<?php

namespace YesWiki\Identity\Service;

use YesWiki\Core\YesWikiController;

/**
 * What remains here after ticket 15's split (hibernation -> HibernationService, password-for-editing -> PasswordForEditingService, captcha -> CaptchaController): a generic, dependency-free input-sanitization utility that doesn't belong to any single security sub-concern, plus the edit-page submit-value constant referenced across the whole codebase.
 */
class InputFilter extends YesWikiController
{
    // this value cannot be changed because use by extensions
    public const EDIT_PAGE_SUBMIT_VALUE = 'Sauver';

    /**
     * Sanitize raw input values.
     *
     * @$pRawInputFiltered : the original value returned by PHP filter input
     *
     * @$pSanitizedFormat : the format to check
     * supported format string, int, bool
     * if the format is not specified, the function return the original $pRawInputFiltered
     */
    private function sanitize(mixed $pRawInputFiltered, string $pSanitizedFormat, bool $pEmulateFilterSanitizeString): mixed
    {
        $result = null;
        switch ($pSanitizedFormat) {
            case 'string':
                $result = (
                    in_array($pRawInputFiltered, [false, null], true)
                    || !is_scalar($pRawInputFiltered)
                )
                    ? ''
                    : (
                        $pEmulateFilterSanitizeString
                        ? htmlspecialchars(strip_tags(strval($pRawInputFiltered)))
                        : strval($pRawInputFiltered)
                    );
                break;
            case 'int':
                $result = (
                    in_array($pRawInputFiltered, [false, null], true)
                    || !is_scalar($pRawInputFiltered)
                )
                    ? 0
                    : intval($pRawInputFiltered);
                break;
            case 'bool':
                $result = in_array($pRawInputFiltered, [false, null, 0, 'false', '0'], true)
                    ? false
                    : (
                        in_array($pRawInputFiltered, [true, 'true', 1], true)
                        ? true
                        : boolval($pRawInputFiltered)
                    );
                break;
            default:
                $result = $pRawInputFiltered;
                break;
        }

        return $result;
    }

    /**
     * retrieve input using filter to prevent injection from other php script
     * emulate $filter = FILTER_SANITIZE_STRING because deprecated since php8.1.
     *
     * @param INPUT_GET|INPUT_POST|INPUT_COOKIE|INPUT_SERVER|INPUT_ENV $type    same as filter_input
     * @param int                                                      $filter  same as filter_input
     * @param string                                                   $format  'string', 'int', 'bool', 'array', '' (empty = not formatted)
     * @param array<string, mixed>|int                                 $options same as filter_input
     *
     * @return mixed the sanitized value, or null when an array was required and nothing came in
     */
    public function filterInput(
        int $type,
        string $varName,
        int $filter = FILTER_DEFAULT,
        bool $emulateFilterSanitizeString = false,
        string $format = '',
        $options = 0
    ) {
        $sanitizedFilter = $emulateFilterSanitizeString ? FILTER_UNSAFE_RAW : $filter;
        $sanitizedFormat = $emulateFilterSanitizeString ? 'string' : $format;

        $rawInputFiltered = filter_input($type, $varName, $sanitizedFilter, $options);

        $flags = is_array($options) ? intval($options['flags'] ?? 0) : 0;
        if (($flags & FILTER_REQUIRE_ARRAY) !== 0 || ($flags & FILTER_FORCE_ARRAY) !== 0) {
            if (!is_array($rawInputFiltered)) {
                return null;
            }

            $vSanitizedArray = [];
            foreach ($rawInputFiltered as $vKey => $vValue) {
                $vSanitizedArray[$vKey] = $this->sanitize($vValue, $sanitizedFormat, $emulateFilterSanitizeString);
            }

            return $vSanitizedArray;
        }

        return $this->sanitize($rawInputFiltered, $sanitizedFormat, $emulateFilterSanitizeString);
    }
}

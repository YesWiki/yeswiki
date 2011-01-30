<?php
/**
 * Rule checking the value's length
 *
 * PHP version 5
 *
 * LICENSE:
 *
 * Copyright (c) 2006-2009, Alexey Borzov <avb@php.net>,
 *                          Bertrand Mansion <golgote@mamasam.com>
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions
 * are met:
 *
 *    * Redistributions of source code must retain the above copyright
 *      notice, this list of conditions and the following disclaimer.
 *    * Redistributions in binary form must reproduce the above copyright
 *      notice, this list of conditions and the following disclaimer in the
 *      documentation and/or other materials provided with the distribution.
 *    * The names of the authors may not be used to endorse or promote products
 *      derived from this software without specific prior written permission.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS
 * IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO,
 * THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR
 * PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT OWNER OR
 * CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL,
 * EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO,
 * PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR
 * PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY
 * OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING
 * NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS
 * SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 * @category   HTML
 * @package    HTML_QuickForm2
 * @author     Alexey Borzov <avb@php.net>
 * @author     Bertrand Mansion <golgote@mamasam.com>
 * @license    http://opensource.org/licenses/bsd-license.php New BSD License
 * @version    SVN: $Id: Length.php 289087 2009-10-02 10:37:12Z avb $
 * @link       http://pear.php.net/package/HTML_QuickForm2
 */

/**
 * Base class for HTML_QuickForm2 rules
 */
require_once 'HTML/QuickForm2/Rule.php';

/**
 * Rule checking the value's length
 *
 * The rule needs an "allowed length" parameter for its work, it can be either
 *  - a scalar: the value will be valid if it is exactly this long
 *  - an array: the value will be valid if its length is between the given values
 *    (inclusive). If one of these evaluates to 0, then length will be compared
 *    only with the remaining one.
 *
 * Parameters can be passed to {@link HTML_QuickForm2_Rule::setConfig() setConfig()} in
 * either of the following formats
 *  - scalar (if no parameters were registered with Factory then it is treated as
 *    an exact length, if 'min' or 'max' was already registered then it is treated
 *    as 'max' or 'min', respectively)
 *  - array(minlength, maxlength)
 *  - array(['min' => minlength, ]['max' => maxlength])
 * and also may be passed to {@link HTML_QuickForm2_Factory::registerRule()} in
 * either of the following formats
 *  - scalar (exact length)
 *  - array(minlength, maxlength)
 *  - array(['min' => minlength, ]['max' => maxlength])
 * global config registered with the Factory overrides options set for the
 * particular Rule instance.
 *
 * The Rule considers empty fields as valid and doesn't try to compare their
 * lengths with provided limits.
 *
 * For convenience this Rule is also registered with the names 'minlength' and
 * 'maxlength' (having, respectively, 'max' and 'min' parameters set to 0):
 * <code>
 * $password->addRule('minlength', 'The password should be at least 6 characters long', 6);
 * $message->addRule('maxlength', 'Your message is too verbose', 1000);
 * </code>
 *
 * @category   HTML
 * @package    HTML_QuickForm2
 * @author     Alexey Borzov <avb@php.net>
 * @author     Bertrand Mansion <golgote@mamasam.com>
 * @version    Release: 0.3.0
 */
class HTML_QuickForm2_Rule_Length extends HTML_QuickForm2_Rule
{
   /**
    * Validates the element's value
    *
    * @return   bool    whether length of the element's value is within allowed range
    * @throws   HTML_QuickForm2_InvalidArgumentException if a bogus $registeredType
    *           was passed to constructor or bogus allowed length(s) were used
    *           for rule configuration
    * @throws   HTML_QuickForm2_Exception if rule configuration is missing
    */
    protected function checkValue($value)
    {
        if (!empty($this->registeredType)) {
            $config = HTML_QuickForm2_Factory::getRuleConfig($this->registeredType);
        } else {
            $config = null;
        }
        $allowedLength = $this->findAllowedLength($config);

        if (0 == ($valueLength = strlen($value))) {
            return true;
        }
        if (is_scalar($allowedLength)) {
            return $valueLength == $allowedLength;
        } else {
            return (!empty($allowedLength['min'])? $valueLength >= $allowedLength['min']: true) &&
                   (!empty($allowedLength['max'])? $valueLength <= $allowedLength['max']: true);
        }
    }

   /**
    * Adds the 'min' and 'max' fields from one array to the other
    *
    * @param    array   Rule configuration, array with 'min' and 'max' keys
    * @param    array   Additional configuration, fields will be added to
    *                   $length if it doesn't contain such a key already
    * @return   array
    */
    protected function mergeMinMaxLength($length, $config)
    {
        if (array_key_exists('min', $config) || array_key_exists('max', $config)) {
            if (!array_key_exists('min', $length) && array_key_exists('min', $config)) {
                $length['min'] = $config['min'];
            }
            if (!array_key_exists('max', $length) && array_key_exists('max', $config)) {
                $length['max'] = $config['max'];
            }
        } else {
            if (!array_key_exists('min', $length)) {
                $length['min'] = reset($config);
            }
            if (!array_key_exists('max', $length)) {
                $length['max'] = end($config);
            }
        }
        return $length;
    }

   /**
    * Searches in global config and Rule's options for allowed length limits
    *
    * @param    mixed   config returned by {@link HTML_QuickForm2_Factory::getRuleConfig()},
    *                   if applicable
    * @return   int|array
    * @throws   HTML_QuickForm2_Exception   if length limits weren't found anywhere
    * @throws   HTML_QuickForm2_InvalidArgumentException if bogus length limits
    *           were provided
    */
    protected function findAllowedLength($globalConfig)
    {
        if (0 == count($globalConfig) && 0 == count($this->config)) {
            throw new HTML_QuickForm2_Exception(
                'Length Rule requires an allowed length parameter'
            );
        }
        if (!is_array($globalConfig)) {
            $length = $globalConfig;
        } else {
            $length = $this->mergeMinMaxLength(array(), $globalConfig);
        }

        if (is_array($this->config)) {
            if (!isset($length)) {
                $length = $this->mergeMinMaxLength(array(), $this->config);
            } else {
                $length = $this->mergeMinMaxLength($length, $this->config);
            }

        } elseif (isset($this->config)) {
            if (!isset($length)) {
                $length = $this->config;
            } elseif (is_array($length)) {
                if (!array_key_exists('min', $length)) {
                    $length['min'] = $this->config;
                } else {
                    $length['max'] = $this->config;
                }
            }
        }

        if (is_array($length)) {
            $length += array('min' => 0, 'max' => 0);
        }
        if (is_array($length) && ($length['min'] < 0 || $length['max'] < 0) ||
            !is_array($length) && $length < 0)
        {
            throw new HTML_QuickForm2_InvalidArgumentException(
                'Length Rule requires parameters to be nonnegative, ' .
                preg_replace('/\s+/', ' ', var_export($length, true)) . ' given'
            );
        } elseif (is_array($length) && $length['min'] == 0 && $length['max'] == 0 ||
                  !is_array($length) && 0 == $length)
        {
            throw new HTML_QuickForm2_InvalidArgumentException(
                'Length Rule requires at least one non-zero parameter, ' .
                preg_replace('/\s+/', ' ', var_export($length, true)) . ' given'
            );
        }

        if (!empty($length['min']) && !empty($length['max'])) {
            if ($length['min'] > $length['max']) {
                list($length['min'], $length['max']) = array($length['max'], $length['min']);
            } elseif ($length['min'] == $length['max']) {
                $length = $length['min'];
            }
        }
        return $length;
    }
}
?>

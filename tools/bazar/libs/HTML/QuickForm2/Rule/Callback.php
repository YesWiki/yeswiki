<?php
/**
 * Rule checking the value via a callback function (method)
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
 * @version    SVN: $Id: Callback.php 289087 2009-10-02 10:37:12Z avb $
 * @link       http://pear.php.net/package/HTML_QuickForm2
 */

/**
 * Base class for HTML_QuickForm2 rules
 */
require_once 'HTML/QuickForm2/Rule.php';

/**
 * Rule checking the value via a callback function (method)
 *
 * The Rule needs a valid callback as a configuration parameter for its work, it
 * may also be given additional arguments to pass to the callback alongside the
 * element's value.
 *
 * Parameters can be passed to {@link HTML_QuickForm2_Rule::setConfig() setConfig()} in
 * either of the following formats
 *  - callback or arguments (the semantics depend on whether the Rule was
 *    registered in the {@link HTML_QuickForm2_Factory Factory} with the
 *    callback already given)
 *  - array(['callback' => callback, ]['arguments' => array(...)])
 * and also may be passed to {@link HTML_QuickForm2_Factory::registerRule()} in
 * either of the following formats
 *  - callback
 *  - array(['callback' => callback, ]['arguments' => array(...)])
 * global config registered with the Factory overrides options set for the
 * particular Rule instance. In any case you are advised to use the associative
 * array format to prevent ambiguity.
 *
 * The callback will be called with element's value as the first argument, if
 * additional arguments were provided they'll be passed as well. It is expected
 * to return false if the value is invalid and true if it is valid.
 *
 * Checking that the value is not empty:
 * <code>
 * $str->addRule('callback', 'The field should not be empty', 'strlen');
 * </code>
 * Checking that the value is in the given array:
 * <code>
 * $meta->addRule('callback', 'Unknown variable name',
 *                array('callback' => 'in_array',
 *                      'arguments' => array(array('foo', 'bar', 'baz'))));
 * </code>
 * The same, but with rule registering first:
 * <code>
 * HTML_QuickForm2_Factory::registerRule(
 *     'in_array', 'HTML_QuickForm2_Rule_Callback',
 *     'HTML/QuickForm2/Rule/Callback.php', 'in_array'
 * );
 * $meta->addRule('in_array', 'Unknown variable name', array(array('foo', 'bar', 'baz')));
 * </code>
 *
 * @category   HTML
 * @package    HTML_QuickForm2
 * @author     Alexey Borzov <avb@php.net>
 * @author     Bertrand Mansion <golgote@mamasam.com>
 * @version    Release: 0.3.0
 */
class HTML_QuickForm2_Rule_Callback extends HTML_QuickForm2_Rule
{
   /**
    * Set to true if callback function was registered in Factory
    * @var  bool
    */
    protected $registeredCallback = false;

   /**
    * Validates the element's value
    *
    * @return   bool    the value returned by a callback function
    * @throws   HTML_QuickForm2_InvalidArgumentException if a bogus $registeredType
    *           was passed to constructor or a bogus callback was provided
    * @throws   HTML_QuickForm2_NotFoundException if the callback is missing
    */
    protected function checkValue($value)
    {
        if (!empty($this->registeredType)) {
            $config = HTML_QuickForm2_Factory::getRuleConfig($this->registeredType);
        } else {
            $config = null;
        }
        $callback  = $this->findCallback($config);
        $arguments = $this->findArguments($config);
        if (!is_callable($callback, false, $callbackName)) {
            throw new HTML_QuickForm2_InvalidArgumentException(
                'Callback Rule requires a valid callback, \'' . $callbackName .
                '\' was given'
            );
        }
        return call_user_func_array($callback, array_merge(array($value), $arguments));
    }

   /**
    * Searches in global config and Rule's options for a callback function to use
    *
    * @param    mixed   config returned by {@link HTML_QuickForm2_Factory::getRuleConfig()},
    *                   if applicable
    * @return   callback
    * @throws   HTML_QuickForm2_NotFoundException   if a callback wasn't found anywhere
    */
    protected function findCallback($globalConfig)
    {
        $this->registeredCallback = false;
        if (!empty($globalConfig)) {
            if (!is_array($globalConfig) ||
                !isset($globalConfig['callback']) && !isset($globalConfig['arguments']))
            {
                $this->registeredCallback = true;
                return $globalConfig;
            } elseif (isset($globalConfig['callback'])) {
                $this->registeredCallback = true;
                return $globalConfig['callback'];
            }
        }
        if (is_array($this->config) && isset($this->config['callback'])) {
            return $this->config['callback'];
        } elseif (!empty($this->config)) {
            return $this->config;
        } else {
            throw new HTML_QuickForm2_NotFoundException(
                'Callback Rule requires a callback to check value with'
            );
        }
    }

   /**
    * Searches in global config and Rule's options for callback's additional arguments
    *
    * @param    mixed   config returned by {@link HTML_QuickForm2_Factory::getRuleConfig()},
    *                   if applicable
    * @return   array   additional arguments to pass to a callback
    */
    protected function findArguments($globalConfig)
    {
        if (is_array($globalConfig) && isset($globalConfig['arguments'])) {
            return $globalConfig['arguments'];
        }
        if (is_array($this->config) && isset($this->config['arguments'])) {
            return $this->config['arguments'];
        } elseif ($this->registeredCallback && !empty($this->config)) {
            return $this->config;
        }
        return array();
    }
}
?>

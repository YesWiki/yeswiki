<?php
/**
 * Rule comparing the value of the field with some other value
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
 * @version    SVN: $Id: Compare.php 289087 2009-10-02 10:37:12Z avb $
 * @link       http://pear.php.net/package/HTML_QuickForm2
 */

/**
 * Base class for HTML_QuickForm2 rules
 */
require_once 'HTML/QuickForm2/Rule.php';

/**
 * Rule comparing the value of the field with some other value
 *
 * The Rule needs two configuration parameters for its work
 *  - comparison operator (defaults to equality)
 *  - operand to compare with; this can be either a constant or another form
 *    element (its value will be used)
 *
 * Parameters can be passed to {@link HTML_QuickForm2_Rule::setConfig() setConfig()} in
 * either of the following formats
 *  - operand
 *  - array([operator, ]operand)
 *  - array(['operator' => operator, ]['operand' => operand])
 * and also may be passed to {@link HTML_QuickForm2_Factory::registerRule()} in
 * either of the following formats
 *  - operator
 *  - array(operator[, operand])
 *  - array(['operator' => operator, ]['operand' => operand])
 * global config registered with the Factory overrides options set for the
 * particular Rule instance.
 *
 * Note that 'less than [or equal]' and 'greater than [or equal]' operators
 * compare the operands numerically, since this is considered as more useful
 * approach by the authors.
 *
 * For convenience, this Rule is already registered in the Factory with the
 * names 'eq', 'neq', 'lt', 'gt', 'lte', 'gte' corresponding to the relevant
 * operators:
 * <code>
 * $password->addRule('eq', 'Passwords do not match', $passwordRepeat);
 * $orderQty->addRule('lte', 'Should not order more than 10 of these', 10);
 * </code>
 *
 * @category   HTML
 * @package    HTML_QuickForm2
 * @author     Alexey Borzov <avb@php.net>
 * @author     Bertrand Mansion <golgote@mamasam.com>
 * @version    Release: 0.3.0
 */
class HTML_QuickForm2_Rule_Compare extends HTML_QuickForm2_Rule
{
   /**
    * Possible comparison operators
    * @var array
    */
    protected $operators = array('==', '!=', '===', '!==', '<', '<=', '>', '>=');


   /**
    * Validates the element's value
    *
    * @return   bool    whether (element_value operator operand) expression is true
    * @throws   HTML_QuickForm2_InvalidArgumentException if a bogus $registeredType
    *           was passed to constructor or a bogus comparison operator is used
    *           for configuration
    * @throws   HTML_QuickForm2_Exception if an operand to compare with is missing
    */
    protected function checkValue($value)
    {
        if (!empty($this->registeredType)) {
            $config = HTML_QuickForm2_Factory::getRuleConfig($this->registeredType);
        } else {
            $config = null;
        }
        $operator = $this->findOperator($config);
        $operand  = $this->findOperand($config);
        if (!in_array($operator, array('===', '!=='))) {
            $compareFn = create_function('$a, $b', 'return floatval($a) ' . $operator . ' floatval($b);');
        } else {
            $compareFn = create_function('$a, $b', 'return strval($a) ' . $operator . ' strval($b);');
        }
        return $compareFn($value, $operand instanceof HTML_QuickForm2_Node?
                                  $operand->getValue(): $operand);
    }


   /**
    * Finds a comparison operator to use in global config and Rule's options
    *
    * @param    mixed   config returned by {@link HTML_QuickForm2_Factory::getRuleConfig()},
    *                   if applicable
    * @return   string  operator to use, defaults to '==='
    * @throws   HTML_QuickForm2_InvalidArgumentException if a bogus comparison
    *           operator is used for configuration
    */
    protected function findOperator($globalConfig)
    {
        if (!empty($globalConfig)) {
            if (!is_array($globalConfig)) {
                $operator = $globalConfig;
            } elseif (isset($globalConfig['operator'])) {
                $operator = $globalConfig['operator'];
            } else {
                $operator = array_shift($globalConfig);
            }
        }
        if (empty($operator)) {
            if (is_array($this->config) && isset($this->config['operator'])) {
                $operator = $this->config['operator'];
            } elseif (!is_array($this->config) || count($this->config) < 2) {
                return '===';
            } else {
                reset($this->config);
                $operator = current($this->config);
            }
        }
        if (!in_array($operator, $this->operators)) {
            throw new HTML_QuickForm2_InvalidArgumentException(
                'Compare Rule requires a valid comparison operator, ' .
                preg_replace('/\s+/', ' ', var_export($operator, true)) . ' given'
            );
        }
        if (in_array($operator, array('==', '!='))) {
            return $operator . '=';
        }
        return $operator;
    }


   /**
    * Finds an operand to compare element's value with in global config and Rule's options
    *
    * @param    mixed   config returned by {@link HTML_QuickForm2_Factory::getRuleConfig()},
    *                   if applicable
    * @return   mixed   an operand to compare with
    * @throws   HTML_QuickForm2_Exception if an operand is missing
    */
    protected function findOperand($globalConfig)
    {
        if (count($globalConfig) > 1) {
            if (isset($globalConfig['operand'])) {
                return $globalConfig['operand'];
            } else {
                return end($globalConfig);
            }
        }
        if (0 == count($this->config)) {
            throw new HTML_QuickForm2_Exception(
                'Compare Rule requires an argument to compare with'
            );
        } elseif (!is_array($this->config)) {
            return $this->config;
        } elseif (isset($this->config['operand'])) {
            return $this->config['operand'];
        } else {
            return end($this->config);
        }
    }
}
?>

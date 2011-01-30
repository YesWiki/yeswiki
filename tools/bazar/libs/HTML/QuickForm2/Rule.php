<?php
/**
 * Base class for HTML_QuickForm2 rules
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
 * @version    SVN: $Id: Rule.php 289087 2009-10-02 10:37:12Z avb $
 * @link       http://pear.php.net/package/HTML_QuickForm2
 */

/**
 * Abstract base class for HTML_QuickForm2 rules
 *
 * This class provides methods that allow chaining several rules together.
 * Its validate() method executes the whole rule chain starting from this rule.
 *
 * @category   HTML
 * @package    HTML_QuickForm2
 * @author     Alexey Borzov <avb@php.net>
 * @author     Bertrand Mansion <golgote@mamasam.com>
 * @version    Release: 0.3.0
 */
abstract class HTML_QuickForm2_Rule
{
   /**
    * An element whose value will be validated by this rule
    * @var  HTML_QuickForm2_Node
    */
    protected $owner;

   /**
    * An error message to display if validation fails
    * @var  string
    */
    protected $message;

   /**
    * Configuration data for the rule
    * @var  mixed
    */
    protected $config;

   /**
    * Rules chained to this via "and" and "or" operators
    *
    * The contents can be described as "disjunctive normal form", where an outer
    * array represents a disjunction of conjunctive clauses represented by inner
    * arrays.
    *
    * @var  array
    */
    protected $chainedRules = array(array());

   /**
    * Type that was provided to Factory when creating this Rule instance
    *
    * Used to get the common configuration data for the Rules of that type from
    * Factory.
    *
    * @var  string
    */
    protected $registeredType = null;


   /**
    * Class constructor
    *
    * @param    HTML_QuickForm2_Node    Element to validate
    * @param    string                  Error message to display if validation fails
    * @param    mixed                   Additional data for the rule
    * @param    string                  Type that was provided to Factory when
    *                                   creating this Rule instance, shouldn't
    *                                   be set if instantiating the Rule object
    *                                   manually.
    */
    public function __construct(HTML_QuickForm2_Node $owner, $message = '',
                                $options = null, $registeredType = null)
    {
        $this->setOwner($owner);
        $this->setMessage($message);
        $this->setConfig($options);
        $this->registeredType = $registeredType;
    }

   /**
    * Sets additional configuration data for the rule
    *
    * @param    mixed                   Rule configuration data (rule-dependent)
    * @return   HTML_QuickForm2_Rule
    * @deprecated   Deprecated since 0.3.0, use {@link setConfig()}
    */
    public function setOptions($options)
    {
        return $this->setConfig($options);
    }

   /**
    * Returns the rule's configuration data
    *
    * @return   mixed
    * @deprecated   Deprecated since 0.3.0, use {@link getConfig()}
    */
    public function getOptions()
    {
        return $this->getConfig();
    }

   /**
    * Sets configuration data for the rule
    *
    * @param    mixed   Rule configuration data (specific for a Rule)
    * @return   HTML_QuickForm2_Rule
    * @throws   HTML_QuickForm2_InvalidArgumentException    in case of invalid
    *               configuration data
    */
    public function setConfig($config)
    {
        $this->config = $config;
        return $this;
    }

   /**
    * Returns the rule's configuration data
    *
    * @return   mixed   Configuration data (specific for a Rule)
    */
    public function getConfig()
    {
        return $this->config;
    }

   /**
    * Sets the error message output by the rule
    *
    * @param    string                  Error message to display if validation fails
    * @return   HTML_QuickForm2_Rule
    */
    public function setMessage($message)
    {
        $this->message = (string)$message;
        return $this;
    }

   /**
    * Returns the error message output by the rule
    *
    * @return   string  Error message
    */
    public function getMessage()
    {
        return $this->message;
    }

   /**
    * Sets the element that will be validated by this rule
    *
    * @param    HTML_QuickForm2_Node    Element to validate
    * @todo     We should consider removing the rule from previous owner
    */
    public function setOwner(HTML_QuickForm2_Node $owner)
    {
        $this->owner = $owner;
    }

   /**
    * Adds a rule to the chain with an "and" operator
    *
    * Evaluation is short-circuited, next rule will not be evaluated if the
    * previous one returns false. The method is named this way because "and" is
    * a reserved word in PHP.
    *
    * @param    HTML_QuickForm2_Rule
    * @return   HTML_QuickForm2_Rule    first rule in the chain (i.e. $this)
    * @throws   HTML_QuickForm2_InvalidArgumentException    when trying to add
    *           a "required" rule to the chain
    */
    public function and_(HTML_QuickForm2_Rule $next)
    {
        if ($next instanceof HTML_QuickForm2_Rule_Required) {
            throw new HTML_QuickForm2_InvalidArgumentException(
                'and_(): Cannot add a "required" rule'
            );
        }
        $this->chainedRules[count($this->chainedRules) - 1][] = $next;
        return $this;
    }

   /**
    * Adds a rule to the chain with an "or" operator
    *
    * Evaluation is short-circuited, next rule will not be evaluated if the
    * previous one returns true. The method is named this way because "or" is
    * a reserved word in PHP.
    *
    * @param    HTML_QuickForm2_Rule
    * @return   HTML_QuickForm2_Rule    first rule in the chain (i.e. $this)
    * @throws   HTML_QuickForm2_InvalidArgumentException    when trying to add
    *           a "required" rule to the chain
    */
    public function or_(HTML_QuickForm2_Rule $next)
    {
        if ($next instanceof HTML_QuickForm2_Rule_Required) {
            throw new HTML_QuickForm2_InvalidArgumentException(
                'or_(): Cannot add a "required" rule'
            );
        }
        $this->chainedRules[] = array($next);
        return $this;
    }

   /**
    * Performs validation
    *
    * The whole rule chain is executed. Note that the side effect of this
    * method is setting the error message on element if validation fails
    *
    * @return   boolean     Whether the element is valid
    */
    public function validate()
    {
        $globalValid = false;
        $localValid  = $this->checkValue($this->owner->getValue());
        foreach ($this->chainedRules as $item) {
            foreach ($item as $multiplier) {
                if (!$localValid) {
                    break;
                }
                $localValid = $localValid && $multiplier->validate();
            }
            $globalValid = $globalValid || $localValid;
            if ($globalValid) {
                break;
            }
            $localValid = true;
        }
        if (!$globalValid && strlen($this->message) && !$this->owner->getError()) {
            $this->owner->setError($this->message);
        }
        return $globalValid;
    }

   /**
    * Validates the element's value
    *
    * Note that the error message will be set for an element if such message
    * exists in the rule and that method returns false
    *
    * @param    mixed   Form element's value
    * @return   boolean Whether the value is valid according to the rule
    */
    abstract protected function checkValue($value);
}
?>

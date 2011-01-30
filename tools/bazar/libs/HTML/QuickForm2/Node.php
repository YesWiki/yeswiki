<?php
/**
 * Base class for all HTML_QuickForm2 elements
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
 * @version    SVN: $Id: Node.php 289072 2009-10-01 20:26:57Z avb $
 * @link       http://pear.php.net/package/HTML_QuickForm2
 */

/**
 * HTML_Common2 - base class for HTML elements
 */
require_once 'HTML/Common2.php';

/**
 * Exception classes for HTML_QuickForm2
 */
require_once 'HTML/QuickForm2/Exception.php';

/**
 * Static factory class for QuickForm2 elements
 */
require_once 'HTML/QuickForm2/Factory.php';

/**
 * Abstract base class for all QuickForm2 Elements and Containers
 *
 * This class is mostly here to define the interface that should be implemented
 * by the subclasses. It also contains static methods handling generation
 * of unique ids for elements which do not have ids explicitly set.
 *
 * @category   HTML
 * @package    HTML_QuickForm2
 * @author     Alexey Borzov <avb@php.net>
 * @author     Bertrand Mansion <golgote@mamasam.com>
 * @version    Release: 0.3.0
 */
abstract class HTML_QuickForm2_Node extends HTML_Common2
{
   /**
    * Array containing the parts of element ids
    * @var array
    */
    protected static $ids = array();

   /**
    * Element's "frozen" status
    * @var boolean
    */
    protected $frozen = false;

   /**
    * Whether element's value should persist when element is frozen
    * @var boolean
    */
    protected $persistent = false;

   /**
    * Element containing current
    * @var HTML_QuickForm2_Container
    */
    protected $container = null;

   /**
    * Contains options and data used for the element creation
    * @var  array
    */
    protected $data = null;

   /**
    * Validation rules for element
    * @var  array
    */
    protected $rules = array();

   /**
    * Error message (usually set via Rule if validation fails)
    * @var  string
    */
    protected $error = null;

   /**
    * Class constructor
    *
    * @param    string  Element name
    * @param    mixed   Attributes (either a string or an array)
    * @param    array   Element data (label, options and data used for element creation)
    */
    public function __construct($name = null, $attributes = null, $data = null)
    {
        parent::__construct($attributes);
        $this->setName($name);
        // Autogenerating the id if not set on previous steps
        if ('' == $this->getId()) {
            $this->setId();
        }
        if (!is_null($data)) {
            $this->data = $data;
        }
    }


   /**
    * Generates an id for the element
    *
    * Called when an element is created without explicitly given id
    *
    * @param  string   Element name
    * @return string   The generated element id
    */
    protected static function generateId($elementName)
    {
        $tokens    =  strlen($elementName)?
                      explode('[', str_replace(']', '', $elementName)):
                      array('qfauto');
        $container =& self::$ids;
        $id        =  '';

        do {
            $token = array_shift($tokens);
            // Handle the 'array[]' names
            if ('' === $token) {
                if (empty($container)) {
                    $token = 0;
                } else {
                    $keys  = array_keys($container);
                    $token = end($keys);
                    while (isset($container[$token])) {
                        $token++;
                    }
                }
            }
            $id .= '-' . $token;
            if (!isset($container[$token])) {
                $container[$token] = array();
            }
            $container =& $container[$token];
        } while (!empty($tokens));

        // Append the final index
        $index = count($keys = array_keys($container))? end($keys): 0;
        while (isset($container[$index])) {
            $index++;
        }
        $container[$index] = array();
        $id .= '-' . $index;

        return substr($id, 1);
    }


   /**
    * Stores the explicitly given id to prevent duplicate id generation
    *
    * @param    string  Element id
    */
    protected static function storeId($id)
    {
        $tokens    =  explode('-', $id);
        $container =& self::$ids;

        do {
            $token = array_shift($tokens);
            if (!isset($container[$token])) {
                $container[$token] = array();
            }
            $container =& $container[$token];
        } while (!empty($tokens));
    }


   /**
    * Returns the element's type
    *
    * @return   string
    */
    abstract public function getType();


   /**
    * Returns the element's name
    *
    * @return   string
    */
    abstract public function getName();


   /**
    * Sets the element's name
    *
    * @param    string
    * @return   HTML_QuickForm2_Node
    */
    abstract public function setName($name);


   /**
    * Returns the element's id
    *
    * @return   string
    */
    abstract public function getId();


   /**
    * Sets the elements id
    *
    * Please note that elements should always have an id in QuickForm2 and
    * therefore it will not be possible to remove the element's id or set it to
    * an empty value. If id is not explicitly given, it will be autogenerated.
    *
    * @param    string  Element's id, will be autogenerated if not given
    * @return   HTML_QuickForm2_Node
    */
    abstract public function setId($id = null);


   /**
    * Returns the element's value
    *
    * @return   mixed
    */
    abstract public function getValue();


   /**
    * Sets the element's value
    *
    * @param    mixed
    * @return   HTML_QuickForm2_Node
    */
    abstract public function setValue($value);


   /**
    * Returns the element's label(s)
    *
    * @return   string|array
    */
    public function getLabel()
    {
        if (isset($this->data['label'])) {
            return $this->data['label'];
        }
        return null;
    }


   /**
    * Sets the element's label(s)
    *
    * @param    string|array    Label for the element (may be an array of labels)
    * @return   HTML_QuickForm2_Node
    */
    public function setLabel($label)
    {
        $this->data['label'] = $label;
        return $this;
    }


   /**
    * Changes the element's frozen status
    *
    * @param    bool    Whether the element should be frozen or editable. If
    *                   omitted, the method will not change the frozen status,
    *                   just return its current value
    * @return   bool    Old value of element's frozen status
    */
    public function toggleFrozen($freeze = null)
    {
        $old = $this->frozen;
        if (null !== $freeze) {
            $this->frozen = (bool)$freeze;
        }
        return $old;
    }


   /**
    * Changes the element's persistent freeze behaviour
    *
    * If persistent freeze is on, the element's value will be kept (and
    * submitted) in a hidden field when the element is frozen.
    *
    * @param    bool    New value for "persistent freeze". If omitted, the
    *                   method will not set anything, just return the current
    *                   value of the flag.
    * @return   bool    Old value of "persistent freeze" flag
    */
    public function persistentFreeze($persistent = null)
    {
        $old = $this->persistent;
        if (null !== $persistent) {
            $this->persistent = (bool)$persistent;
        }
        return $old;
    }


   /**
    * Adds the link to the element containing current
    *
    * @param    HTML_QuickForm2_Container  Element containing the current one,
    *                                      null if the link should really be
    *                                      removed (if removing from container)
    * @throws   HTML_QuickForm2_InvalidArgumentException   If trying to set a
    *                               child of an element as its container
    */
    protected function setContainer(HTML_QuickForm2_Container $container = null)
    {
        if (null !== $container) {
            $check = $container;
            do {
                if ($this === $check) {
                    throw new HTML_QuickForm2_InvalidArgumentException(
                        'Cannot set an element or its child as its own container'
                    );
                }
            } while ($check = $check->getContainer());
            if (null !== $this->container && $container !== $this->container) {
                $this->container->removeChild($this);
            }
        }
        $this->container = $container;
        if (null !== $container) {
            $this->updateValue();
        }
    }


   /**
    * Returns the element containing current
    *
    * @return   HTML_QuickForm2_Container|null
    */
    public function getContainer()
    {
        return $this->container;
    }

   /**
    * Returns the data sources for this element
    *
    * @return   array
    */
    protected function getDataSources()
    {
        if (empty($this->container)) {
            return array();
        } else {
            return $this->container->getDataSources();
        }
    }

   /**
    * Called when the element needs to update its value from form's data sources
    */
    abstract protected function updateValue();

   /**
    * Adds a validation rule
    *
    * @param    HTML_QuickForm2_Rule|string     Validation rule or rule type
    * @param    string                          Message to display if validation fails
    * @param    mixed                           Additional data for the rule
    * @return   HTML_QuickForm2_Rule            The added rule
    * @throws   HTML_QuickForm2_InvalidArgumentException    if $rule is of a
    *               wrong type or rule name isn't registered with Factory
    * @throws   HTML_QuickForm2_NotFoundException   if class for a given rule
    *               name cannot be found
    */
    public function addRule($rule, $message = '', $options = null)
    {
        if ($rule instanceof HTML_QuickForm2_Rule) {
            $rule->setOwner($this);
        } elseif (is_string($rule)) {
            $rule = HTML_QuickForm2_Factory::createRule($rule, $this, $message, $options);
        } else {
            throw new HTML_QuickForm2_InvalidArgumentException(
                'addRule() expects either a rule type or ' .
                'a HTML_QuickForm2_Rule instance'
            );
        }

        $this->rules[] = $rule;
        return $rule;
    }


   /**
    * Creates a validation rule
    *
    * This method is mostly useful when when chaining several rules together
    * via {@link HTML_QuickForm2_Rule::and_()} and {@link HTML_QuickForm2_Rule::or_()}
    * methods:
    * <code>
    * $first->addRule('nonempty', 'Fill in either first or second field')
    *     ->or_($second->createRule('nonempty'));
    * </code>
    *
    * @param    string                  Rule type
    * @param    string                  Message to display if validation fails
    * @param    mixed                   Additional data for the rule
    * @return   HTML_QuickForm2_Rule    The created rule
    * @throws   HTML_QuickForm2_InvalidArgumentException If rule type is unknown
    * @throws   HTML_QuickForm2_NotFoundException        If class for the rule
    *           can't be found and/or loaded from file
    */
    public function createRule($type, $message = '', $options = null)
    {
        return HTML_QuickForm2_Factory::createRule($type, $this, $message, $options);
    }


   /**
    * Checks whether an element is required
    *
    * @return   boolean
    */
    public function isRequired()
    {
        foreach ($this->rules as $rule) {
            if ($rule instanceof HTML_QuickForm2_Rule_Required) {
                return true;
            }
        }
        return false;
    }


   /**
    * Performs the server-side validation
    *
    * @return   boolean     Whether the element is valid
    */
    protected function validate()
    {
        foreach ($this->rules as $rule) {
            if (strlen($this->error)) {
                break;
            }
            $rule->validate();
        }
        return !strlen($this->error);
    }

   /**
    * Sets the error message to the element
    *
    * @param    string
    * @return   HTML_QuickForm2_Node
    */
    public function setError($error = null)
    {
        $this->error = (string)$error;
        return $this;
    }

   /**
    * Returns the error message for the element
    *
    * @return   string
    */
    public function getError()
    {
        return $this->error;
    }
}
?>

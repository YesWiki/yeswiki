<?php
/**
 * Validates values using regular expressions
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
 * @version    SVN: $Id: Regex.php 289087 2009-10-02 10:37:12Z avb $
 * @link       http://pear.php.net/package/HTML_QuickForm2
 */

/**
 * Base class for HTML_QuickForm2 rules
 */
require_once 'HTML/QuickForm2/Rule.php';

/**
 * Validates values using regular expressions
 *
 * The Rule needs one configuration parameter for its work: a Perl-compatible
 * regular expression. This expression can be passed either to
 * {@link HTML_QuickForm2_Rule::setConfig() setConfig()} or to
 * {@link HTML_QuickForm2_Factory::registerRule()}. Regular expression
 * registered with the Factory overrides one set for the particular Rule
 * instance via setConfig().
 *
 * The Rule can also validate file uploads, in this case the regular expression
 * is applied to upload's 'name' field.
 *
 * The Rule considers empty fields (file upload fields with UPLOAD_ERR_NO_FILE)
 * as valid and doesn't try to test them with the regular expression.
 *
 * @category   HTML
 * @package    HTML_QuickForm2
 * @author     Alexey Borzov <avb@php.net>
 * @author     Bertrand Mansion <golgote@mamasam.com>
 * @version    Release: 0.3.0
 */
class HTML_QuickForm2_Rule_Regex extends HTML_QuickForm2_Rule
{
   /**
    * Validates the element's value
    *
    * @return   bool    whether element's value matches given regular expression
    * @throws   HTML_QuickForm2_InvalidArgumentException if a bogus $registeredType
    *           was passed to constructor
    * @throws   HTML_QuickForm2_Exception if regular expression is missing
    */
    protected function checkValue($value)
    {
        if (!empty($this->registeredType)) {
            $regex = HTML_QuickForm2_Factory::getRuleConfig($this->registeredType);
        } else {
            $regex = null;
        }
        if (null === $regex) {
            $regex = $this->getConfig();
        }
        if (!is_string($regex)) {
            throw new HTML_QuickForm2_Exception(
                'Regex Rule requires a regular expression, ' .
                preg_replace('/\s+/', ' ', var_export($regex, true)) . ' given'
            );
        }

        if ($this->owner instanceof HTML_QuickForm2_Element_InputFile) {
            if (!isset($value['error']) || UPLOAD_ERR_NO_FILE == $value['error']) {
                return true;
            }
            $value = $value['name'];
        } elseif (!strlen($value)) {
            return true;
        }
        return preg_match($regex . 'D', $value);
    }
}
?>
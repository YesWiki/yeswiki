<?php
/**
 * Rule checking that uploaded file is of the correct MIME type
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
 * @version    SVN: $Id: MimeType.php 289087 2009-10-02 10:37:12Z avb $
 * @link       http://pear.php.net/package/HTML_QuickForm2
 */

/**
 * Rule checking that uploaded file is of the correct MIME type
 *
 * The Rule needs one configuration parameter for its work: a string with a
 * desired MIME type or array of such strings. The parameter may be passed to
 * {@link HTML_QuickForm2_Rule::setConfig() setConfig()} or to
 * {@link HTML_QuickForm2_Factory::registerRule()}. Parameter registered with the
 * Factory overrides one set for the particular Rule instance via setConfig().
 *
 * The Rule considers missing file uploads (UPLOAD_ERR_NO_FILE) valid.
 *
 * @category   HTML
 * @package    HTML_QuickForm2
 * @author     Alexey Borzov <avb@php.net>
 * @author     Bertrand Mansion <golgote@mamasam.com>
 * @version    Release: 0.3.0
 */
class HTML_QuickForm2_Rule_MimeType extends HTML_QuickForm2_Rule
{
   /**
    * Validates the element's value
    *
    * @return   bool    whether uploaded file's MIME type is correct
    * @throws   HTML_QuickForm2_InvalidArgumentException if a bogus $registeredType
    *           was passed to constructor or bogus MIME type(s) provided
    */
    protected function checkValue($value)
    {
        if (!empty($this->registeredType)) {
            $mime = HTML_QuickForm2_Factory::getRuleConfig($this->registeredType);
        } else {
            $mime = null;
        }
        if (null === $mime) {
            $mime = $this->getConfig();
        }
        if (0 == count($mime) || !is_string($mime) && !is_array($mime)) {
            throw new HTML_QuickForm2_InvalidArgumentException(
                'MimeType Rule requires MIME type(s), ' .
                preg_replace('/\s+/', ' ', var_export($mime, true)) . ' given'
            );
        }

        if (!isset($value['error']) || UPLOAD_ERR_NO_FILE == $value['error']) {
            return true;
        }
        return is_array($mime)? in_array($value['type'], $mime):
                                $value['type'] == $mime;
    }


   /**
    * Sets the element that will be validated by this rule
    *
    * @param    HTML_QuickForm2_Element_InputFile   File upload field to validate
    * @throws   HTML_QuickForm2_InvalidArgumentException    if trying to use
    *           this Rule on something that isn't a file upload field
    */
    public function setOwner(HTML_QuickForm2_Node $owner)
    {
        if (!$owner instanceof HTML_QuickForm2_Element_InputFile) {
            throw new HTML_QuickForm2_InvalidArgumentException(
                'MimeType Rule can only validate file upload fields, '.
                get_class($owner) . ' given'
            );
        }
        parent::setOwner($owner);
    }
}
?>

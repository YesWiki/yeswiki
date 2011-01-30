<?php
/**
 * Rule checking that uploaded file size does not exceed the given limit
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
 * @version    SVN: $Id: MaxFileSize.php 289087 2009-10-02 10:37:12Z avb $
 * @link       http://pear.php.net/package/HTML_QuickForm2
 */

/**
 * Rule checking that uploaded file size does not exceed the given limit
 *
 * The Rule needs one configuration parameter for its work: the size limit.
 * This limit can be passed either to
 * {@link HTML_QuickForm2_Rule::setConfig() setConfig()} or to
 * {@link HTML_QuickForm2_Factory::registerRule()}. Limit registered with the
 * Factory overrides one set for the particular Rule instance via setConfig().
 *
 * Note that if file upload failed due to upload_max_filesize php.ini setting
 * or MAX_FILE_SIZE form field, then this rule won't even be called, due to
 * File element's built-in validation setting the error message.
 *
 * The Rule considers missing file uploads (UPLOAD_ERR_NO_FILE) valid.
 *
 * @category   HTML
 * @package    HTML_QuickForm2
 * @author     Alexey Borzov <avb@php.net>
 * @author     Bertrand Mansion <golgote@mamasam.com>
 * @version    Release: 0.3.0
 */
class HTML_QuickForm2_Rule_MaxFileSize extends HTML_QuickForm2_Rule
{
   /**
    * Validates the element's value
    *
    * @return   bool    whether uploaded file's size is within given limit
    * @throws   HTML_QuickForm2_InvalidArgumentException if a bogus $registeredType
    *           was passed to constructor or a bogus size limit was provided
    */
    protected function checkValue($value)
    {
        if (!empty($this->registeredType)) {
            $limit = HTML_QuickForm2_Factory::getRuleConfig($this->registeredType);
        } else {
            $limit = null;
        }
        if (null === $limit) {
            $limit = $this->getConfig();
        }
        if (0 >= $limit) {
            throw new HTML_QuickForm2_InvalidArgumentException(
                'MaxFileSize Rule requires a positive size limit, ' .
                preg_replace('/\s+/', ' ', var_export($limit, true)) . ' given'
            );
        }

        if (!isset($value['error']) || UPLOAD_ERR_NO_FILE == $value['error']) {
            return true;
        }
        return ($limit >= @filesize($value['tmp_name']));
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
                'MaxFileSize Rule can only validate file upload fields, '.
                get_class($owner) . ' given'
            );
        }
        parent::setOwner($owner);
    }
}
?>

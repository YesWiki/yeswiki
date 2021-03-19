<?php
/**
 * Yeswiki start file
 *
 * Instantiates the main YesWiki class, loads the extensions,
 * and runs the current page
 *
 * @category Wiki
 * @package  YesWiki
 * @author   2018 Florian Schmitt <mrflos@lilo.org>
 * @license  GNU/GPL version 3
 * @link     https://yeswiki.net
 *
 * All rights reserved.
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions
 * are met:
 * 1. Redistributions of source code must retain the above copyright
 * notice, this list of conditions and the following disclaimer.
 * 2. Redistributions in binary form must reproduce the above copyright
 * notice, this list of conditions and the following disclaimer in the
 * documentation and/or other materials provided with the distribution.
 * 3. The name of the author may not be used to endorse or promote products
 * derived from this software without specific prior written permission.
 *
 * THIS SOFTWARE IS PROVIDED BY THE AUTHOR ``AS IS'' AND ANY EXPRESS OR
 * IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES
 * OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED.
 * IN NO EVENT SHALL THE AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT,
 * INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT
 * NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
 * DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY
 * THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF
 * THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 */

use Doctrine\Common\Annotations\AnnotationRegistry;

spl_autoload_register(function ($className) {
    $classNameArray = explode('\\', $className);
    // Autoload services
    if (isset($classNameArray[2])) {
        if ($classNameArray[1] === 'Core') {
            if ($classNameArray[2] === 'Service') {
                require 'includes/services/' . $classNameArray[3] . '.php';
            } elseif ($classNameArray[2] === 'Controller') {
                require 'includes/controllers/' . $classNameArray[3] . '.php';
            } elseif (file_exists('includes/' . $classNameArray[2] . '.php')) {
                require 'includes/' . $classNameArray[2] . '.php';
            }
        } else {
            $extension = strtolower($classNameArray[1]);
            if ($classNameArray[2] === 'Service') {
                require 'tools/' . $extension . '/services/' . $classNameArray[3] . '.php';
            } elseif ($classNameArray[2] === 'Field') {
                if ($extension == 'custom') {
                    require 'custom/fields/' . $classNameArray[3] . '.php';
                } else {
                    require 'tools/' . $extension . '/fields/' . $classNameArray[3] . '.php';
                }
            } elseif ($classNameArray[2] === 'Controller') {
                if ($extension == 'custom') {
                    require 'custom/controllers/' . $classNameArray[3] . '.php';
                } else {
                    require 'tools/' . $extension . '/controllers/' . $classNameArray[3] . '.php';
                }
            }
        }
    }
});

$loader = require_once 'vendor/autoload.php';
AnnotationRegistry::registerLoader([$loader, 'loadClass']);

require_once 'includes/YesWiki.php';
$wiki = new \YesWiki\Wiki();
$wiki->Run($wiki->tag, $wiki->method);

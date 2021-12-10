<?php
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
                if ($extension == 'custom') {
                    require 'custom/services/' . $classNameArray[3] . '.php';
                } else {
                    require 'tools/' . $extension . '/services/' . $classNameArray[3] . '.php';
                }
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
            } elseif ($classNameArray[2] === 'Commands') {                
                if ($extension == 'custom') {
                    require 'custom/commands/' . $classNameArray[3] . '.php';
                } else {
                    require 'tools/' . $extension . '/commands/' . $classNameArray[3] . '.php';
                }
            }
        }
    }
});

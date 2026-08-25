<?php

namespace YesWiki\Core;

use Symfony\Component\Routing\Loader\AttributeClassLoader;
use Symfony\Component\Routing\Route;

class AttributeRouteControllerLoader extends AttributeClassLoader
{
    /**
     * @param \ReflectionClass<object> $class
     */
    protected function configureRoute(Route $route, \ReflectionClass $class, \ReflectionMethod $method, object $attr): void
    {
        $route->setDefault('_controller', $class->getName() . '::' . $method->getName());
    }
}

<?php
namespace Zizaco\ConfideMongo;

use Illuminate\Container\Container;
use Mockery as m;
use PHPUnit\Framework\TestCase as BaseTestCase;
use ReflectionClass;
use ReflectionMethod;

abstract class TestCase extends BaseTestCase
{
    /**
     * {@inheritdoc}
     */
    protected function tearDown(): void
    {
        $this->addToAssertionCount(m::getContainer()->mockery_getExpectationCount());

        m::close();
        Container::setInstance(new Container);
    }

    /**
     * Actually runs a protected method of the given object.
     * @param       $obj
     * @param       $method
     * @param array $args
     *
     * @return mixed
     */
    protected function callProtected($obj, $method, $args = array())
    {
        $methodObj = new ReflectionMethod(get_class($obj), $method);
        $methodObj->setAccessible(true);

        if (is_object($args)) {
            $args = [$args];
        } else {
            $args = (array) $args;
        }

        return $methodObj->invokeArgs($obj, $args);
    }

    /**
     * Call a protected property of an object
     *
     * @param  mixed  $obj      Object Instance
     * @param  string $property Property name
     * @param  mixed  $value    Value to be set
     */
    protected function setProtected($obj, $property, $value)
    {
        $class = new ReflectionClass($obj);
        $property = $class->getProperty($property);
        $property->setAccessible(true);

        if (is_string($obj)) { // static
            $property->setValue($value);
            return;
        }

        $property->setValue($obj, $value);
    }

    /**
     * Set an instance on IoC Container.
     *
     * @param array|string  $abstract IoC binding to be used
     * @param Object|string $concrete Concrete Object to be used
     */
    protected function setInstance($abstract, $concrete)
    {
        $container = Container::getInstance();
        $container->instance($abstract, $concrete);

        Container::setInstance($container);
    }
}

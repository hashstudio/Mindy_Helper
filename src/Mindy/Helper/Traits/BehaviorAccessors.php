<?php
/**
 *
 *
 * All rights reserved.
 *
 * @author Falaleev Maxim
 * @email max@studio107.ru
 * @version 1.0
 * @company Studio107
 * @site http://studio107.ru
 * @date 05/09/14.09.2014 17:00
 */

namespace Mindy\Helper\Traits;

use Closure;
use Mindy\Exception\Exception;
use Mindy\Base\Interfaces\IBehavior;
use Mindy\Base\Mindy;
use Mindy\Helper\Creator;
use Mindy\Utils\BaseList;

/**
 * Class BehaviorAccessors
 * @package Mindy\Helper
 */
trait BehaviorAccessors
{
    /**
     * @var array the behaviors that should be attached to the module.
     * The behaviors will be attached to the module when {@link init} is called.
     * Please refer to {@link CModel::behaviors} on how to specify the value of this property.
     */
    public $behaviors = [];
    /**
     * @var
     */
    private $_m;

    /**
     * Returns a property value, an event handler list or a behavior based on its name.
     * Do not call this method. This is a PHP magic method that we override
     * to allow using the following syntax to read a property or obtain event handlers:
     * <pre>
     * $value=$component->propertyName;
     * $handlers=$component->eventName;
     * </pre>
     * @param string $name the property name or event name
     * @return mixed the property value, event handlers attached to the event, or the named behavior
     * @throws Exception if the property or event is not defined
     * @see __set
     */
    public function __get($name)
    {
        return $this->__getInternal($name);
    }

    public function __getInternal($name)
    {
        $getter = 'get' . $name;
        if (method_exists($this, $getter)) {
            return $this->$getter();
        } elseif (isset($this->_m[$name])) {
            return $this->_m[$name];
        } elseif (is_array($this->_m)) {
            foreach ($this->_m as $object) {
                if ($object->getEnabled() && (property_exists($object, $name) || $object->canGetProperty($name))) {
                    return $object->$name;
                }
            }
        }
        throw new Exception(Mindy::t('base', 'Property "{class}.{property}" is not defined.', [
            '{class}' => get_class($this),
            '{property}' => $name
        ]));
    }

    /**
     * Sets value of a component property.
     * Do not call this method. This is a PHP magic method that we override
     * to allow using the following syntax to set a property or attach an event handler
     * <pre>
     * $this->propertyName=$value;
     * $this->eventName=$callback;
     * </pre>
     * @param string $name the property name or the event name
     * @param mixed $value the property value or callback
     * @return mixed
     * @throws Exception if the property/event is not defined or the property is read only.
     * @see __get
     */
    public function __set($name, $value)
    {
        return $this->__setInternal($name, $value);
    }

    public function __setInternal($name, $value)
    {
        $setter = 'set' . $name;
        if (method_exists($this, $setter)) {
            return $this->$setter($value);
        } elseif (strncasecmp($name, 'on', 2) === 0 && method_exists($this, $name)) {
            // duplicating getEventHandlers() here for performance
            $name = strtolower($name);
            if (!isset($this->_e[$name])) {
                $this->_e[$name] = new BaseList;
            }
            return $this->_e[$name]->add($value);
        } elseif (is_array($this->_m)) {
            foreach ($this->_m as $object) {
                if ($object->getEnabled() && (property_exists($object, $name) || $object->canSetProperty($name))) {
                    return $object->$name = $value;
                }
            }
        }
        if (method_exists($this, 'get' . $name)) {
            throw new Exception(Mindy::t('base', 'Property "{class}.{property}" is read only.', [
                '{class}' => get_class($this),
                '{property}' => $name
            ]));
        } else {
            throw new Exception(Mindy::t('base', 'Property "{class}.{property}" is not defined.', [
                '{class}' => get_class($this),
                '{property}' => $name
            ]));
        }
    }

    /**
     * Checks if a property value is null.
     * Do not call this method. This is a PHP magic method that we override
     * to allow using isset() to detect if a component property is set or not.
     * @param string $name the property name or the event name
     * @return boolean
     */
    public function __isset($name)
    {
        return $this->__issetInternal($name);
    }

    public function __issetInternal($name)
    {
        $getter = 'get' . $name;
        if (method_exists($this, $getter)) {
            return $this->$getter() !== null;
        } elseif (strncasecmp($name, 'on', 2) === 0 && method_exists($this, $name)) {
            $name = strtolower($name);
            return isset($this->_e[$name]) && $this->_e[$name]->getCount();
        } elseif (is_array($this->_m)) {
            if (isset($this->_m[$name])) {
                return true;
            }
            foreach ($this->_m as $object) {
                if ($object->getEnabled() && (property_exists($object, $name) || $object->canGetProperty($name))) {
                    return $object->$name !== null;
                }
            }
        }
        return false;
    }

    /**
     * Sets a component property to be null.
     * Do not call this method. This is a PHP magic method that we override
     * to allow using unset() to set a component property to be null.
     * @param string $name the property name or the event name
     * @throws Exception if the property is read only.
     * @return mixed
     */
    public function __unset($name)
    {
        return $this->__unsetInternal($name);
    }

    public function __unsetInternal($name)
    {
        $setter = 'set' . $name;
        if (method_exists($this, $setter)) {
            $this->$setter(null);
        } elseif (strncasecmp($name, 'on', 2) === 0 && method_exists($this, $name)) {
            unset($this->_e[strtolower($name)]);
        } elseif (is_array($this->_m)) {
            if (isset($this->_m[$name])) {
                $this->detachBehavior($name);
            } else {
                foreach ($this->_m as $object) {
                    if ($object->getEnabled()) {
                        if (property_exists($object, $name)) {
                            return $object->$name = null;
                        } elseif ($object->canSetProperty($name)) {
                            return $object->$setter(null);
                        }
                    }
                }
            }
        } elseif (method_exists($this, 'get' . $name)) {
            throw new Exception(Mindy::t('base', 'Property "{class}.{property}" is read only.',
                ['{class}' => get_class($this), '{property}' => $name]));
        }
    }

    /**
     * Calls the named method which is not a class method.
     * Do not call this method. This is a PHP magic method that we override
     * to implement the behavior feature.
     * @param string $name the method name
     * @param array $parameters method parameters
     * @throws Exception if current class and its behaviors do not have a method or closure with the given name
     * @return mixed the method return value
     */
    public function __call($name, $parameters)
    {
        return $this->__callInternal($name, $parameters);
    }

    public function __callInternal($name, $parameters)
    {
        if ($this->_m !== null) {
            foreach ($this->_m as $object) {
                if ($object->getEnabled() && method_exists($object, $name)) {
                    return call_user_func_array(array($object, $name), $parameters);
                }
            }
        }
        if (class_exists('Closure', false) && ($this->canGetProperty($name) || property_exists($this, $name)) && $this->$name instanceof Closure) {
            return call_user_func_array($this->$name, $parameters);
        }
        throw new Exception(Mindy::t('base', '{class} and its behaviors do not have a method or closure named "{name}".',
            ['{class}' => get_class($this), '{name}' => $name]));
    }

    /**
     * Returns the named behavior object.
     * The name 'asa' stands for 'as a'.
     * @param string $behavior the behavior name
     * @return IBehavior the behavior object, or null if the behavior does not exist
     */
    public function asa($behavior)
    {
        return isset($this->_m[$behavior]) ? $this->_m[$behavior] : null;
    }

    /**
     * Attaches a list of behaviors to the component.
     * Each behavior is indexed by its name and should be an instance of
     * {@link IBehavior}, a string specifying the behavior class, or an
     * array of the following structure:
     * <pre>
     * array(
     *     'class'=>'path.to.BehaviorClass',
     *     'property1'=>'value1',
     *     'property2'=>'value2',
     * )
     * </pre>
     * @param array $behaviors list of behaviors to be attached to the component
     */
    public function attachBehaviors($behaviors)
    {
        foreach ($behaviors as $name => $behavior) {
            $this->attachBehavior($name, $behavior);
        }
    }

    /**
     * Detaches all behaviors from the component.
     */
    public function detachBehaviors()
    {
        if ($this->_m !== null) {
            foreach ($this->_m as $name => $behavior) {
                $this->detachBehavior($name);
            }
            $this->_m = null;
        }
    }

    /**
     * Attaches a behavior to this component.
     * This method will create the behavior object based on the given
     * configuration. After that, the behavior object will be initialized
     * by calling its {@link IBehavior::attach} method.
     * @param string $name the behavior's name. It should uniquely identify this behavior.
     * @param mixed $behavior the behavior configuration. This is passed as the first
     * parameter to {@link YiiBase::createComponent} to create the behavior object.
     * You can also pass an already created behavior instance (the new behavior will replace an already created
     * behavior with the same name, if it exists).
     * @return IBehavior the behavior object
     */
    public function attachBehavior($name, $behavior)
    {
        if (!($behavior instanceof IBehavior)) {
            $behavior = Creator::createObject($behavior);
        }
        $behavior->setEnabled(true);
        $behavior->attach($this);
        return $this->_m[$name] = $behavior;
    }

    /**
     * Detaches a behavior from the component.
     * The behavior's {@link IBehavior::detach} method will be invoked.
     * @param string $name the behavior's name. It uniquely identifies the behavior.
     * @return IBehavior the detached behavior. Null if the behavior does not exist.
     */
    public function detachBehavior($name)
    {
        if (isset($this->_m[$name])) {
            $this->_m[$name]->detach($this);
            $behavior = $this->_m[$name];
            unset($this->_m[$name]);
            return $behavior;
        }
    }

    /**
     * Enables all behaviors attached to this component.
     */
    public function enableBehaviors()
    {
        if ($this->_m !== null) {
            foreach ($this->_m as $behavior) {
                $behavior->setEnabled(true);
            }
        }
    }

    /**
     * Disables all behaviors attached to this component.
     */
    public function disableBehaviors()
    {
        if ($this->_m !== null) {
            foreach ($this->_m as $behavior) {
                $behavior->setEnabled(false);
            }
        }
    }

    /**
     * Enables an attached behavior.
     * A behavior is only effective when it is enabled.
     * A behavior is enabled when first attached.
     * @param string $name the behavior's name. It uniquely identifies the behavior.
     */
    public function enableBehavior($name)
    {
        if (isset($this->_m[$name])) {
            $this->_m[$name]->setEnabled(true);
        }
    }

    /**
     * Disables an attached behavior.
     * A behavior is only effective when it is enabled.
     * @param string $name the behavior's name. It uniquely identifies the behavior.
     */
    public function disableBehavior($name)
    {
        if (isset($this->_m[$name])) {
            $this->_m[$name]->setEnabled(false);
        }
    }

    /**
     * Determines whether a property is defined.
     * A property is defined if there is a getter or setter method
     * defined in the class. Note, property names are case-insensitive.
     * @param string $name the property name
     * @return boolean whether the property is defined
     * @see canGetProperty
     * @see canSetProperty
     */
    public function hasProperty($name)
    {
        return method_exists($this, 'get' . $name) || method_exists($this, 'set' . $name);
    }

    /**
     * Determines whether a property can be read.
     * A property can be read if the class has a getter method
     * for the property name. Note, property name is case-insensitive.
     * @param string $name the property name
     * @return boolean whether the property can be read
     * @see canSetProperty
     */
    public function canGetProperty($name)
    {
        return method_exists($this, 'get' . $name);
    }

    /**
     * Determines whether a property can be set.
     * A property can be written if the class has a setter method
     * for the property name. Note, property name is case-insensitive.
     * @param string $name the property name
     * @return boolean whether the property can be written
     * @see canGetProperty
     */
    public function canSetProperty($name)
    {
        return method_exists($this, 'set' . $name);
    }

    /**
     * Evaluates a PHP expression or callback under the context of this component.
     *
     * Valid PHP callback can be class method name in the form of
     * array(ClassName/Object, MethodName), or anonymous function (only available in PHP 5.3.0 or above).
     *
     * If a PHP callback is used, the corresponding function/method signature should be
     * <pre>
     * function foo($param1, $param2, ..., $component) { ... }
     * </pre>
     * where the array elements in the second parameter to this method will be passed
     * to the callback as $param1, $param2, ...; and the last parameter will be the component itself.
     *
     * If a PHP expression is used, the second parameter will be "extracted" into PHP variables
     * that can be directly accessed in the expression. See {@link http://us.php.net/manual/en/function.extract.php PHP extract}
     * for more details. In the expression, the component object can be accessed using $this.
     *
     * A PHP expression can be any PHP code that has a value. To learn more about what an expression is,
     * please refer to the {@link http://www.php.net/manual/en/language.expressions.php php manual}.
     *
     * @param mixed $_expression_ a PHP expression or PHP callback to be evaluated.
     * @param array $_data_ additional parameters to be passed to the above expression/callback.
     * @return mixed the expression result
     * @since 1.1.0
     */
    public function evaluateExpression($_expression_, $_data_ = array())
    {
        if (is_string($_expression_)) {
            extract($_data_);
            return eval('return ' . $_expression_ . ';');
        } else {
            $_data_[] = $this;
            return call_user_func_array($_expression_, $_data_);
        }
    }
}

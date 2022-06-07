<?php
/*
 * @Author: He.Bin 
 * @Date: 2022-05-23 18:30:17 
 * @Last Modified by: He.Bin
 * @Last Modified time: 2022-06-02 16:31:28
 */

declare(strict_types=1);

namespace Netflying\Payment\data;

use ArrayIterator;
use Netflying\Payment\exception\FieldNullException;
use Netflying\Payment\exception\FieldInstanceException;
use Netflying\Payment\exception\MethodCallableException;
use Netflying\Payment\exception\ClassPropertyException;

/**
 * 数据对象模型基础类
 * 知识点:
 *  new self()  当前类
 *  new static(); 返回调用类,可能是子类
 * 约定:
 *  null值都是作为无意义值，应该需要强制被处理掉，或不作数。比如属性或数据中不允许出现null值。
 *  @property array $fields  数据的结构化字段['字段名'=>'字段类型:String,int,float,Object']。对应自动生成setter,getter方法及使用静态方式调用。
 *  @property array $_propMap 结构化字段详情数据。
 *  @property array $instance 启用静态方式调用时fields结构化方法
 * 特性:
 *  @method _toArray() 对象可转为数据,对象可作为数据形式循环遍历，映射toArray()
 *  @method _toJson() 对象可转为json,映射toObject()
 *  @method count() 获取公开成员数量
 *  对象 echo $Model 可直接打印输入为字符串
 */
class Model implements \JsonSerializable, \ArrayAccess, \IteratorAggregate, \Countable
{
    /**
     * 类型强制转换的有效类型,8种基础类型.
     * 允许强制有: int,bool,float,double,real,string,array,object。
     * 注意: array类型 以驼峰形式的成员属性作为新的下属模型
     *      object类型 以驼峰形式的成员属性的值作为基类
     * @var array
     */
    protected $_propType = [
        'int',
        'bool',
        'float',
        'double',
        'real',
        'string',
        'array',
        'object'
    ];
    //$fields字段值为null是否可忽略, 0忽略;1,不允许忽略必填有值(除了null以外的值)
    protected $nullRequire = 1;
    /**
     * 定义公开成员字段模型
     * 结构示例
     * ['key' => '类型强制转换‘ ... ]
     */
    protected $fields = [];
    // Data Structure details 公开成员字段数据
    protected $_propMap = [];
    // fields default value 属性默认限定,不能为null，默认设为null，说明调用者需要必填值覆盖
    protected $fieldsNull = [];
    //模型字段不满足要求(异常)提示
    protected $_propInit = [];
    //Array of attributes to setter functions, fields=>key 所对应的set自定义方法
    protected $setters = [];
    //Array of attributes to getter functions, fields=>key 所对应的get自定义方法
    protected $getters = [];
    /**
     * @var null|static Instance[singleton] object
     */
    protected static $instance = null;

    /**
     * 构造数据以字段模型对应形式传入
     *
     * @param array $data
     */
    public function __construct(array $data = [])
    {
        $this->setterFields($data);
    }
    /**
     * Get instance [单例]
     * @param array $options
     * @return static
     */
    public static function instance($options = []): Model
    {
        if (is_null(self::$instance)) {
            self::$instance = new static($options);
        }
        return self::$instance;
    }
    /**
     * Returns array representation of object
     * 数组形式返回public成员字段
     * @return array
     */
    public function _toArray()
    {
        return $this->_convertToArray($this->_propMap);
    }
    /**
     * Converts Params to Array
     * 转数组模型
     * @param $param
     * @return array
     */
    private function _convertToArray($param)
    {
        $ret = array();
        foreach ($param as $k => $v) {
            if (is_null($v)) {
                continue;
            }
            if ($v instanceof Model) {
                $ret[$k] = $v->_toArray();
            } elseif (is_array($v) && sizeof($v) <= 0) {
                $ret[$k] = array();
            } elseif (is_array($v)) {
                $ret[$k] = $this->_convertToArray($v);
            } else {
                $ret[$k] = $v;
            }
        }
        // If the array is empty, which means an empty object,
        // we need to convert array to StdClass object to properly
        // represent JSON String
        if (sizeof($ret) <= 0) {
            $ret = new Model();
        }
        return $ret;
    }
    /**
     * Converts the input key into a valid Setter Method Name
     * 将成员字段转成员设置类方法
     * @param $key
     * @return string
     */
    private static function convertToCamelCase($key): string
    {
        return str_replace(' ', '', ucwords(str_replace(array('_', '-'), ' ', $key)));
    }
    /**
     * 设置类公开成员字段值
     *
     * @param array $data
     * @return void
     */
    protected function setterFields(array $data = [])
    {
        if (empty($data)) {
            $fieldKeys = array_keys($this->fields);
            $dataKeys = array_keys($this->_propMap);
            if ($fieldKeys == $dataKeys) {
                return $this;
            }
        }
        $mode = $this->setterMode($this->fields, $data, $this->fieldsNull);
        $this->_propMap = $mode;
        return $this;
    }
    /**
     * 根据数据模型设置模型值
     *
     * @param array $mode ['key'=>'可转类型']
     * @param array $data ['key'=>'值']
     * @param array $defaults ['key'=>'默认值,当$data中未定义时']
     * @param array $flag 递归标识
     * @return array
     */
    protected function setterMode(array $mode, array $data = [], array $defaults = [], $flag = [])
    {
        $mode = (array)$mode;
        $output = [];
        $hasIdxArr = false;
        foreach ($mode as $k => $type) {
            $type = strtolower($type);
            if (!in_array($type, $this->_propType)) {
                continue;
            }
            //数字型置后处理
            if (is_numeric($k)) {
                $hasIdxArr = true;
                continue;
            }
            $val = isset($data[$k]) ? (is_object($data[$k]) ? clone $data[$k] : $data[$k]) : (isset($defaults[$k]) ? (is_object($defaults[$k]) ? clone $defaults[$k] : $defaults[$k]) : null);
            $val = $this->modeProperty($k, $type, $val);
            if ($val === false) {
                continue;
            }
            $output[$k] = $val;
        }
        if ($hasIdxArr && !empty($data) && !empty($flag['ref'])) {
            $refKey = $flag['ref'];
            foreach ($data as $key => $value) {
                //限定
                $modeLen = count($mode);
                if ($modeLen < 1) {
                    continue;
                }
                $mKey = $key;
                if (isset($mode[$key])) {
                    $type = $mode[$key];
                    $dataKey = $refKey . $key;
                } else {
                    $mKey = $modeLen - 1; //如果超出模型定义,取最后一个模型定义
                    $type = $mode[$mKey];
                    $dataKey = $refKey . ($mKey);
                }
                $value = $this->modeProperty($dataKey, $type, $value);
                if ($value === false) {
                    continue;
                }
                $output[$key] = $value;
            }
        }

        if (empty($mode) && !empty($data)) {
            //无定义,自由模式
            foreach ($data as $k => $v) {
                $output[$k] = $v;
            }
        }
        return $output;
    }
    /**
     * array|object类型数据限定
     *
     * @param [type] $key
     * @param [type] $type
     * @param [type] $val
     * @return void
     */
    protected function modeProperty($key, $type, $val)
    {
        if (in_array($type, ['array', 'object'])) {
            $ivar = lcfirst(self::convertToCamelCase($key));
            $ivarNull = $ivar . 'Null';
            $classIvar = isset($this->$ivar) ? $this->$ivar : ''; //array|object都有对应属性取值,为空不限
            $classIvarNull = isset($this->$ivarNull) ? $this->$ivarNull : (is_object($type) ? null : []); //array可以没有proteryNull属性默认限定取值
            if ($type == 'object') {
                if (!empty($classIvar) && !empty($val) && !($val instanceof $classIvar)) {
                    $class = get_class($this);
                    throw new FieldInstanceException([
                        'class' => $class,
                        'field' => $key,
                        'property' => $classIvar
                    ]);
                }
            } else {
                // if (!empty($classIvar)&&!is_array($classIvar)) {} 
                // if (!empty($val)&&!is_array($val)) {}
                // if (!empty($classIvarNull)&&!is_array($classIvarNull)) {}
                $val = $this->setterMode(empty($classIvar) ? [] : $classIvar, $val, $classIvarNull, ['ref' => $key]);
            }
        }
        if (is_null($val)) {
            if ($this->nullRequire != 0) {
                $class = get_class($this);
                throw new FieldNullException([
                    'class' => $class,
                    'field' => $key
                ]);
            }
            return false; //continue; 不允许null值,直接跳过
        }
        return $val;
    }

    /**
     * 设置模型字段值
     *
     * @param [string] $key 模型字段key
     * @param [string|array|object|int] $value 如果为array|object需要取同名模型方法递归处理
     * @return this
     */
    protected function setter($key, $value)
    {
        if (isset($this->fields[$key])) {
            $type = $this->fields[$key];
            $value = $this->modeProperty($key, $type, $value); //对象内容限定
            $isArrObj = in_array($type, ['array', 'object']);
            if ($isArrObj || (!$isArrObj && !is_array($value) && !is_object($value))) {
                $this->_propMap[$key] = $value;  //基础内容限定
            } else {
                throw new FieldInstanceException(['key' => $key, 'type' => $type, 'value_type' => gettype($value)]);
            }
        }
        return $this;
    }
    protected function getter($key)
    {
        if ($this->isSetter($key)) {
            return $this->_propMap[$key];
        }
        return null;
    }
    /**
     * 去除成员变量
     *
     * @param [type] $key
     * @return void
     */
    protected function unSetter($key)
    {
        unset($this->_propMap[$key]);
    }
    /**
     * key是否为成员变量
     *
     * @param [type] $key
     * @return boolean
     */
    protected function isSetter($key)
    {
        return isset($this->_propMap[$key]);
    }
    /**
     * self::$fields auto create ->getter,->setter method
     *
     * @param string $method
     * @param array $args
     * @return bool|string|DataModel
     */
    public function __call($method, $args)
    {
        //is callable
        $call = '_' . $method;
        if (method_exists($this, $call)) {
            $reflection = new \ReflectionMethod($this, $call);
            if (!$reflection->isPublic()) {
                throw new MethodCallableException(['method' => $method]);
            }
            return call_user_func_array([$this, $call], $args);
        }
        return $this->fieldsSetterGetter($method, $args);
    }
    /**
     * self::$fields -> auto create ::getter,::setter method
     *
     * @param string $method
     * @param array $args
     * @return bool|string|DataModel
     */
    public static function __callStatic($method, $args)
    {
        $instance = self::instance();
        //is callable
        $call = '_' . $method;
        if (method_exists($instance, $call)) {
            $reflection = new \ReflectionMethod($instance, $call);
            if (!$reflection->isPublic()) {
                throw new MethodCallableException(['method' => $method]);
            }
            return call_user_func_array([$instance, $call], $args);
        }
        return $instance->fieldsSetterGetter($method, $args);
    }
    /**
     *  Can fields correspond to getter,setter method rules. e.g: getName(),setName();
     *
     * @param string $method
     * @return bool|int;
     */
    private function fieldsSetterGetter($method, $args)
    {
        if (empty($this->fields)) {
            throw new ClassPropertyException(['method' => $method]);
        }
        foreach ($this->fields as $k => $v) {
            if (is_string($k) && !is_numeric($k)) {
                $getter = !empty($this->getters[$k]) ? $this->getters[$k] : 'get' . self::convertToCamelCase($k);
                if ($method == $getter) {
                    return $this->getter($k);
                }
                $setter = !empty($this->setters[$k]) ? $this->setters[$k] : 'set' . self::convertToCamelCase($k);
                if ($method == $setter) {
                    $value = isset($args[0]) ? $args[0] : null;
                    return $this->setter($k, $value);
                }
            }
        }
        throw new ClassPropertyException(['method' => $method]);
    }

    // The magic method for setting properties is risky. Disable it.
    // /**
    //  * Magic Get Method
    //  *
    //  * @param $key
    //  * @return mixed
    //  */
    // public function __get($key)
    // {
    //     if ($this->__isset($key)) {
    //         return $this->_propMap[$key];
    //     }
    //     return null;
    // }
    // /**
    //  * Magic Set Method
    //  *
    //  * @param $key
    //  * @param $value
    //  */
    // public function __set($key, $value)
    // {
    //     if (!is_array($value) && $value === null) {
    //         $this->__unset($key);
    //     } else {
    //         $this->_propMap[$key] = $value;
    //     }
    // }
    // /**
    //  * Magic isSet Method
    //  *
    //  * @param $key
    //  * @return bool
    //  */
    // public function __isset($key)
    // {
    //     return isset($this->_propMap[$key]);
    // }
    // /**
    //  * Magic Unset Method
    //  *
    //  * @param $key
    //  */
    // public function __unset($key)
    // {
    //     unset($this->_propMap[$key]);
    // }

    public function _toJson($options = JSON_UNESCAPED_UNICODE)
    {
        return json_encode($this->_toArray(), $options);
    }

    public function __toString()
    {
        return $this->_toJson();
    }
    //serialize
    // public function __sleep()
    // {
    // }
    // //unserialize
    // public function __wakeup()
    // { 
    // }

    // JsonSerializable
    public function jsonSerialize()
    {
        return $this->_toArray();
    }
    // ArrayAccess
    public function offsetSet($offset, $value): void
    {
        if (is_null($offset)) {
            $this->_propMap[] = $value;
        } else {
            $this->_propMap[$offset] = $value;
        }
    }

    public function offsetExists($offset): bool
    {
        return isset($this->_propMap[$offset]);
    }

    public function offsetUnset($offset): void
    {
        unset($this->_propMap[$offset]);
    }

    public function offsetGet($offset)
    {
        return isset($this->_propMap[$offset]) ? $this->_propMap[$offset] : null;
    }
    // IteratorAggregate
    public function getIterator(): ArrayIterator
    {
        return new \ArrayIterator($this->_propMap);
    }
    // Countable
    public function count(): int
    {
        return \count($this->_propMap);
    }
}

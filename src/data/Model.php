<?php
/*
 * @Author: He.Bin 
 * @Date: 2022-05-23 18:30:17 
 * @Last Modified by: He.Bin
 * @Last Modified time: 2022-05-24 17:37:39
 */

declare(strict_types=1);

namespace Netflying\Payment\data;

use Exception;

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
 *  @method _toArray() 对象可转为数据,对象可作为数据形式循环遍历
 *  @method _toJson() 对象可转为json
 *  @method count() 获取公开成员数量
 *  对象 echo $Model 可直接打印输入为字符串
 */
class Model implements \JsonSerializable, \ArrayAccess, \IteratorAggregate, \Countable
{
    /**
     * 类型强制转换的有效类型
     * 允许强制有: int,bool,float,double,real,string,array,object。注意array及object类型转换会去请求getter,setter并返回具体array或某object结构体，以此类推...
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
    protected $fieldsNull = 1;
    /**
     * 定义公开成员字段模型
     * 结构示例
     * ['key' => '类型强制转换‘ ... ]
     */
    protected $fields = [];
    // Data Structure details 公开成员字段数据
    protected $_propMap = [];
    // fields default value 成员字段默认值,不能为null，默认设为null，说明调用者需要必填值覆盖
    protected $defaults = [];
    //模型字段不满足要求(异常)提示
    protected $_propInit = [];
    //Array of attributes to setter functions
    protected $setters = [];
    //Array of attributes to getter functions
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
        $mode = $this->setterMode($this->fields, $data, $this->defaults);
        $this->_propMap = $mode;
        return $this;
    }
    /**
     * 根据数据模型设置模型值
     *
     * @param array $mode ['key'=>'可转类型']
     * @param array $data ['key'=>'值']
     * @param array $defaults ['key'=>'默认值,当$data中未定义时']
     * @return void
     */
    protected function setterMode(array $mode, array $data = [], array $defaults = [])
    {
        $output = [];
        foreach ($mode as $k => $type) {
            if (!in_array($type, $this->_propType)) {
                continue;
            }
            if (in_array($type, ['array', 'object'])) {
                $method = 'get' . self::convertToCamelCase($k);
                $val = $this->$method();
            } else {
                $val = isset($data[$k]) ? $data[$k] : (isset($defaults[$k]) ? $defaults[$k] : null);
            }
            if (is_null($val)) {
                if ($this->fieldsNull!=0) {
                    $class = get_class($this);
                    throw new \Exception("{$class} fields can't be null:" . $k);
                }
                continue;
            }
            $output[$k] = $val;
        }
        return $output;
    }
    protected function setter($key, $value)
    {
        if (isset($this->fields[$key])) {
            $this->_propMap[$key] = $value;
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
                throw new \Exception("method[callable] not exists:" . $method);
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
                throw new \Exception("method[callable] not exists:" . $method);
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
            throw new Exception("method[fields] not exists:" . $method);
        }
        foreach ($this->fields as $k => $v) {
            if (is_string($k) && !is_numeric($k)) {
                $getter = !empty($this->getters[$k]) ? $this->getters[$k] : 'get' . self::convertToCamelCase($k);
                if ($method == $getter) {
                    return $this->getter($k);
                }
                $setter = !empty($this->setter[$k]) ? $this->setter[$k] : 'set' . self::convertToCamelCase($k);
                if ($method == $setter) {
                    $value = isset($args[0]) ? $args[0] : null;
                    return $this->setter($k, $value);
                }
            }
        }
        throw new Exception("method not exists:" . $method);
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
    public function offsetSet($offset, $value)
    {
        if (is_null($offset)) {
            $this->_propMap[] = $value;
        } else {
            $this->_propMap[$offset] = $value;
        }
    }

    public function offsetExists($offset)
    {
        return isset($this->_propMap[$offset]);
    }

    public function offsetUnset($offset)
    {
        unset($this->_propMap[$offset]);
    }

    public function offsetGet($offset)
    {
        return isset($this->_propMap[$offset]) ? $this->_propMap[$offset] : null;
    }
    // IteratorAggregate
    public function getIterator()
    {
        return new \ArrayIterator($this->_propMap);
    }
    // Countable
    public function count()
    {
        return \count($this->_propMap);
    }
}

<?php
/**
 * Created by PhpStorm.
 * User: hhtjim
 * Date: 2017/6/1 0001
 * Time: 11:16
 *
 * MongoDb Cache Driver
 */

namespace app\driver\cache;

use think\cache\Driver;

class Mongodb extends Driver
{
    protected $options = [
        'socket_type' => 'mongodb',
        'host' => '127.0.0.1',
        'username' => false,
        'password' => false,
        'port' => 27017,
        'timeout' => 0,
        'database' => 'test',
    ];

    protected $_db;//当前操作的集合Collections对象
    protected $_id;
    protected $error;//错误消息
    private $_default_cache_collection = '__dco';//用于has,set，get这些方法的操作的集合Collections名称

    /**
     * 构造函数
     *
     * Mongodb constructor.
     * @param array $options 缓存参数
     * @access public
     * @throws \MongoConnectionException
     */
    public function __construct($options = [])
    {
        if (!extension_loaded('mongo')) {
            throw new \BadFunctionCallException('not support: mongo');
        }
        if (!empty($options)) {
            $this->options = array_merge($this->options, $options);
        }
        $uri = null;
        if (!empty($this->options['socket'])) {
            $uri = "mongodb://" . $this->options['socket'];
        } else {
            $uri = "mongodb://" . $this->options['host'] . ":" . $this->options['port'];
        }

        $targetOptions = array();
        if (!empty($this->options['username']) && !empty($this->options['password'])) {
            $targetOptions["username"] = $this->options['username'];
            $targetOptions["password"] = $this->options['password'];
        }

        if (class_exists("MongoClient")) {
            $this->handler = new \MongoClient($uri, $targetOptions);
        } else {
            $this->handler = new \Mongo($uri, $targetOptions);
        }

        $this->_db = $this->handler->selectDB($this->options['database']);

        try {
            if ($this->options['socket_type'] === 'unix') {
                $success = $this->handler->connect($this->options['socket']);
            } else // tcp socket
            {
                $success = $this->handler->connect($this->options['host'], $this->options['port'], $this->options['timeout']);
            }

            if (!$success) {
                throw new \MongoConnectionException('Cache: MongoDb connection failed. Check your configuration.');
            }
        } catch (\MongoException $e) {
            throw new \MongoConnectionException('Cache: MongoDb connection refused (' . $e->getMessage() . ')');
        }
    }

    /**
     * 判断缓存是否存在
     * @access public
     * @param string $name 缓存变量名
     * @return bool
     */
    public function has($name)
    {
        return $this->get($name) ? true : false;
    }

    /**
     * 读取缓存
     * @access public
     * @param string $name 缓存变量名
     * @param mixed $default 默认值
     * @return mixed
     */
    public function get($name, $default = false)
    {
        $key = $this->getCacheKey($name);
        $re = $this->_db->{$this->_default_cache_collection}->findOne(array('_id' => $key));
        if (!$re) return $default;
        $expire = $re['expire'];
        if (0 != $expire && $_SERVER['REQUEST_TIME'] > $expire) {
            //缓存过期删除缓存文件
            $this->rm($name);
            return $default;
        }
        $content = $re['value'];
        try {
            $content = unserialize($content);
        } catch (\Exception $e) {
            //反序列化异常 删除然后返回默认值
            $this->rm($name);
            return $default;
        }
        return $content;
    }

    /**
     * 写入缓存
     * 若存在会更新
     *
     * @access public
     * @param string $name 缓存变量名
     * @param mixed $value 存储数据
     * @param int $expire 有效时间 0为永久
     * @return boolean
     */
    public function set($name, $value, $expire = null)
    {
        if (is_null($expire)) {
            $expire = 0;
        } else {
            $expire = intval($expire);
        }
        if ($expire > 0) $expire = time() + $expire;//设置到期的时间戳
        $key = $this->getCacheKey($name);
        $db = $this->_db->{$this->_default_cache_collection};
        if ($this->tag && !$this->has($name)) {
            $first = true;
        }
        try {
            $data = array(
                '_id' => $key,
                'value' => serialize($value),
                'expire' => $expire,//到期的时间戳
            );
            $rel = $db->save($data);
            if ($rel) {
                isset($first) && $this->setTagItem($name);
                return true;
            }
            return false;
        } catch (\MongoCursorException $e) {
            $this->error = $e->getMessage();
            return false;
        }
    }

    /**
     * 自增缓存（针对数值缓存）
     * @access public
     * @param string $name 缓存变量名
     * @param int $step 步长
     * @return false|int
     */
    public function inc($name, $step = 1)
    {
        if ($this->has($name)) {
            $value = $this->get($name,0) + $step;
        } else {
            $value = $step;
        }
        return $this->set($name, $value, 0) ? $value : false;
    }

    /**
     * 自减缓存（针对数值缓存）
     * @access public
     * @param string $name 缓存变量名
     * @param int $step 步长
     * @return false|int
     */
    public function dec($name, $step = 1)
    {
        if ($this->has($name)) {
            $value = $this->get($name,0) - $step;
        } else {
            $value = $step;
        }
        return $this->set($name, $value, 0) ? $value : false;
    }

    /**
     * 删除缓存
     * @access public
     * @param string $name 缓存变量名
     * @return boolean
     */
    public function rm($name)
    {
        $key = $this->getCacheKey($name);
        try {
            $where ['_id'] = $key;
            $rel = $this->_db->{$this->_default_cache_collection}->remove($where);
            return $rel;
        } catch (\MongoCursorException $e) {
            $this->error = $e->getMessage();
            return false;
        }
    }

    /**
     * 清除缓存
     * @access public
     * @param string $tag 标签名
     * @return boolean
     */
    public function clear($tag = null)
    {
        if ($tag) {
            // 指定标签清除
            $keys = $this->getTagItem($tag);
            foreach ($keys as $key) {
                $this->rm($key);
            }
            $this->rm('tag_' . md5($tag));
            return true;
        }
        $this->_db->{$this->_default_cache_collection}->remove();
        return true;
    }

    /**
     * 取得变量的存储文件名
     * @access protected
     * @param string $name 缓存变量名
     * @return string
     */
    protected function getCacheKey($name)
    {
        return md5($name);
    }



    /**
     * 获取默认缓存的剩余时间 /秒
     * @param $name
     * @return int
     *
     * 参照redis：当 key 不存在时，返回 -2 。 当 key 存在但没有设置剩余生存时间时，返回 -1
     */
    public function ttl($name)
    {
        $key = $this->getCacheKey($name);

        $re = $this->_db->{$this->_default_cache_collection}->findOne(array('_id' => $key));
        if (!$re) return -2;//不存在时，返回 -2
        $expire = $re['expire'];
        if($expire === 0) return -1;//存在但没有设置剩余生存时间时，返回 -1

        if ($_SERVER['REQUEST_TIME'] > $expire) {
            //缓存过期删除缓存文件
            $this->rm($name);
            return -2;
        }
        return $expire - $_SERVER['REQUEST_TIME'];
    }




    private function _id($id)
    {
        $this->_id = new \MongoId($id);
        return $this->_id;
    }

    /**
     * 插入文档  直接插入新文档 主键重复不做任何操作
     *
     * @param $collection
     * @param $data
     * @return bool
     */
    public function insert($collection, $data)
    {
        try {
            if (isset($data['_id'])) {
                $data['_id'] = $this->_id($data['_id']);
            }
            $rel = $this->_db->$collection->insert($data);
            return $rel;
        } catch (\MongoCursorException $e) {
            $this->error = $e->getMessage();
            return false;
        }
    }

    /**
     * 插入文档  新增数据时，如果主键重复，则更新该文档
     *
     * @param $collection
     * @param $data
     * @return bool
     */
    public function save($collection, $data)
    {
        try {
            if (isset($data['_id'])) {
                $data['_id'] = $this->_id($data['_id']);
            }
            $rel = $this->_db->$collection->save($data);
            return $rel;
        } catch (\MongoCursorException $e) {
            $this->error = $e->getMessage();
            return false;
        }
    }

    /**
     * 更新文档
     *
     * @param $collection
     * @param $data
     * @param $where
     * @return bool
     */
    public function update($collection, $data, $where)
    {
        try {
            if (empty($where)) return false;
            if (isset($where['_id'])) {
                $where['_id'] = $this->_id($where['_id']);
            }
            $rel = $this->_db->$collection->update($where, $data);
            return $rel;
        } catch (\MongoCursorException $e) {
            $this->error = $e->getMessage();
            return false;
        }
    }

    /**
     * 删除文档
     *
     * @param $collection
     * @param $where
     * @return array|bool
     */
    public function delete($collection, $where)
    {
        try {
            if (empty($where)) return false;
            if (isset($where ['_id'])) {
                $where ['_id'] = $this->_id($where ['_id']);
            }
            $rel = $this->_db->$collection->remove($where);
            return $rel;
        } catch (\MongoCursorException $e) {
            $this->error = $e->getMessage();
            return false;
        }
    }

    /**
     * 查找一条文档
     *
     * @param $collection
     * @param $where
     * @param array $filed
     * @return array|bool|null
     */
    public function findOne($collection, $where, $filed = array())
    {
        try {
            if (empty($where)) return false;
            if (isset($where ['_id'])) {
                $where['_id'] = $this->_id($where['_id']);
            }
            if ($filed) {
                $rel = $this->_db->$collection->findOne($where, $filed);
            } else {
                $rel = $this->_db->$collection->findOne($where);
            }
            return $rel;
        } catch (\MongoCursorException $e) {
            $this->error = $e->getMessage();
            return false;
        }
    }

    /**
     * 查找多条文档记录
     *
     * @param $collection
     * @param array $where
     * @param array $filed
     * @return array|bool|\MongoCursor
     */
    public function find($collection, $where = array(), $filed = array())
    {
        try {
            if (isset($where ['_id'])) {
                $where ['_id'] = $this->_id($where ['_id']);
            }
            if ($filed) {
                $rel = $this->_db->$collection->find($where, $filed);
            } else {
                $rel = $this->_db->$collection->find($where);
            }
            return iterator_to_array($rel);
        } catch (\MongoCursorException $e) {
            $this->error = $e->getMessage();
            return false;
        }
    }
    
    
    /**
     * 获取操作的集合 增加弹性
     *
     * @param $collection
     * @return \MongoCollection
     */
    public function getCollection($collection){
        return $this->_db->{$collection};
    }
}

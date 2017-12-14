<?php

/**
 * Created by PhpStorm.
 * User: hhtjim
 * Date: 2017/6/1 0001
 * Time: 10:45
 *
 * 自定义Redis缓存驱动
 * 继承think\cache\driver\Redis实现重写
 *
 * redis配置: application/config.php cache数组
 * 其他和操作方法请参考父类
 *
 */
namespace app\driver\cache;
use think\cache\driver\Redis as R;

class Redis extends R
{

    /**
     * 删除缓存
     *
     * @param $key
     * @return bool
     */
    public function delete($key){
        return $this->rm($key);
    }

    /**
     * 自增缓存（针对数值缓存）
     * @access public
     * @param string    $name 缓存变量名
     * @param int       $step 步长
     * @return false|int
     */
    public function increment($name, $step = 1)
    {
        return $this->inc($name, $step);
    }


    /**
     * 自减缓存（针对数值缓存）
     * @access public
     * @param string    $name 缓存变量名
     * @param int       $step 步长
     * @return false|int
     */
    public function decrement($name, $step = 1)
    {
        return $this->dec($name, $step);
    }

    /**
     * 清除缓存
     * @access public
     * @param string $tag 标签名
     * @return boolean
     */
    public function clean($tag = null)
    {
        return $this->clear($tag);
    }

    /**
     * Get cache driver info
     *
     * @return	array
     * @see		Redis::info()
     */
    public function cache_info()
    {
        return $this->handler->info();
    }

    /**
     * Get cache metadata
     *
     * @param	string	$key	Cache key
     * @return	array
     */
    public function get_metadata($key)
    {
        $value = $this->get($key);

        if ($value !== FALSE)
        {
            return array(
                'expire' => time() + $this->_redis->ttl($key),
                'data' => $value
            );
        }

        return FALSE;
    }

    /**
     * hset
     */
    public function hset($key,$name,$value){
        return $this->handler->hSet($key,$name,$value);
    }

    /**
     * hget
     */
    public function hget($key,$name){
        return $this->handler->hGet($key,$name);
    }
    /**
     * hgetall
     */
    public function hgetall($key){
        return $this->handler->hGetall($key);
    }
    /**
     * hkeys
     */
    public function hkeys($name){
        return $this->handler->hkeys($name);
    }
    /**
     * EXISTS
     */
    public function exists($name){
        return $this->handler->exists($name);
    }
    /**
     * HEXISTS
     */
    public function hexists($name,$filed){
        return $this->handler->hexists($name,$filed);
    }
    /**
     * HDEL
     */
    public function hdel($name,$filed){
        return $this->handler->hdel($name,$filed);
    }

}
## TP5.0 Redis MongoDB 缓存类

之前TP5项目使用Redis,MongoDB作为缓存组件使用，但是没有找到有相关代码，遂按照CI框架中的操作搬到TP5中。

### 配置CONFIG

TP5全局config 或者下级config都是可以的
如`application/config.php`
```

    // +----------------------------------------------------------------------
    // | 缓存设置
    // +----------------------------------------------------------------------

    'cache'                  => [
        // 使用复合缓存类型
        'type'  =>  'complex',
        // 默认使用的缓存
        'default'   =>  [
            // 驱动方式
            'type'   => 'File',
            // 缓存保存目录
            'path'   => CACHE_PATH,
        ],
        // 文件缓存
        'file'   =>  [
            // 驱动方式
            'type'   => 'file',
            // 设置不同的缓存保存目录
            'path'   => RUNTIME_PATH . 'file/',
        ],
        // redis缓存
        'redis'   =>  [
            // 驱动方式
            'type'   => '\app\driver\cache\Redis',
            // 服务器地址
            'host'       => '127.0.0.1',
        ],
        //mongodb
        'mongodb'=>[
            // 驱动方式
            'type'   => '\app\driver\cache\Mongodb',
            // 服务器地址
            'host'       => '127.0.0.1',
            'database'=>'bsc',
        ]
    ],

```
`type`为驱动擦欧哦类的命名空间路径

## 使用USING

```php
#Redis
//       $r =  Cache::store('redis')->set('name','value');
//       $rr =  Cache::store('redis')->get('name');
//        $hset = Cache::store('redis')->hset('hh','k',time());
//        $hget = Cache::store('redis')->hget('hh','k');

#MongoDB 同上
//        $hget = Cache::store('mongodb')->insert('ceshi','hhhhhhh');
//        $hget = Cache::store('mongodb')->clear();

```


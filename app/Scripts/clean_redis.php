<?php
require_once dirname(__FILE__) . '/../../vendor/autoload.php';

use App\Lib\RedisClient;

$redis = RedisClient::getInstance()->getRedis();
$keys = $redis->keys('request:*');
foreach ($keys as $key) {
    if ($redis->ttl($key) < 0) {
        $redis->del($key);
    }
}
$keys = $redis->keys('refresh_token:*');
foreach ($keys as $key) {
    if ($redis->ttl($key) < 0) {
        $redis->del($key);
    }
}
echo "Redis 清理完成\n";
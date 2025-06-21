<?php
namespace App\Lib;

class RedisClient {
    private static $instance = null;
    private $redis;

    private function __construct() {
        $this->redis = new \Redis();
        $host = $_ENV['REDIS_HOST'] ?? '127.0.0.1';
        $port = $_ENV['REDIS_PORT'] ?? 6379;
        $password = $_ENV['REDIS_PASSWORD'] ?? null;
        $this->redis->connect($host, $port);
        if ($password) {
            $this->redis->auth($password);
        }
    }

    public static function getInstance(): RedisClient {
        if (self::$instance === null) {
            self::$instance = new RedisClient();
        }
        return self::$instance;
    }

    public function getRedis(): \Redis {
        return $this->redis;
    }
}
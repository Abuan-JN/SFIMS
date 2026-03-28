<?php

namespace App\Services;

class Cache
{
    private static ?self $instance = null;
    private string $cacheDir;
    private int $defaultTtl;

    private function __construct()
    {
        $this->cacheDir = dirname(__DIR__, 2) . '/storage/cache';
        $this->defaultTtl = 3600; // 1 hour

        if (!is_dir($this->cacheDir)) {
            mkdir($this->cacheDir, 0755, true);
        }
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $file = $this->getCacheFile($key);

        if (!file_exists($file)) {
            return $default;
        }

        $data = unserialize(file_get_contents($file));

        if ($data['expires_at'] < time()) {
            $this->delete($key);
            return $default;
        }

        return $data['value'];
    }

    public function set(string $key, mixed $value, int $ttl = null): bool
    {
        $ttl = $ttl ?? $this->defaultTtl;
        $file = $this->getCacheFile($key);

        $data = [
            'value' => $value,
            'expires_at' => time() + $ttl,
            'created_at' => time()
        ];

        return file_put_contents($file, serialize($data), LOCK_EX) !== false;
    }

    public function delete(string $key): bool
    {
        $file = $this->getCacheFile($key);

        if (file_exists($file)) {
            return unlink($file);
        }

        return false;
    }

    public function has(string $key): bool
    {
        $file = $this->getCacheFile($key);

        if (!file_exists($file)) {
            return false;
        }

        $data = unserialize(file_get_contents($file));
        return $data['expires_at'] >= time();
    }

    public function remember(string $key, callable $callback, int $ttl = null): mixed
    {
        $value = $this->get($key);

        if ($value !== null) {
            return $value;
        }

        $value = $callback();
        $this->set($key, $value, $ttl);

        return $value;
    }

    public function flush(): bool
    {
        $files = glob($this->cacheDir . '/*.cache');

        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }

        return true;
    }

    public function clearExpired(): int
    {
        $files = glob($this->cacheDir . '/*.cache');
        $cleared = 0;

        foreach ($files as $file) {
            if (is_file($file)) {
                $data = unserialize(file_get_contents($file));
                if ($data['expires_at'] < time()) {
                    unlink($file);
                    $cleared++;
                }
            }
        }

        return $cleared;
    }

    private function getCacheFile(string $key): string
    {
        $hash = md5($key);
        return $this->cacheDir . '/' . $hash . '.cache';
    }

    public function getCacheDir(): string
    {
        return $this->cacheDir;
    }

    public function getDefaultTtl(): int
    {
        return $this->defaultTtl;
    }

    public function setDefaultTtl(int $ttl): void
    {
        $this->defaultTtl = $ttl;
    }
}

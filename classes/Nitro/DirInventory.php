<?php

namespace Bnomei\Nitro;

use Kirby\Cms\App;
use Kirby\Filesystem\Dir;
use Kirby\Filesystem\F;
use Kirby\Toolkit\A;
use ReflectionClass;

class DirInventory
{
    private ?array $data;

    private bool $isDirty;

    private array $options;

    public function __construct(array $options = [])
    {
        // can not use option() as it might run before Kirby is loaded
        $this->options = $options;
        $this->isDirty = false;
        $this->data = [];

        $this->load();
    }

    public function __destruct()
    {
        $this->write();
    }

    public function file(): string
    {
        return $this->cacheDir().'/dir-inventory.json';
    }

    private function load(): void
    {
        if (! file_exists($this->file())) {
            return;
        }

        $data = file_get_contents($this->file());
        $data = $data ? json_decode($data, true) : [];
        if (is_array($data) || is_null($data)) {
            $this->data = $data;
        }
    }

    public function get(string|array $key): ?array
    {
        $key = $this->key($key);

        return A::get($this->data, $key);
    }

    public function set(string|array $key, ?array $input = null): void
    {
        $this->isDirty = true;
        $key = $this->key($key);
        $this->data[$key] = $input;
    }

    public function flush(): void
    {
        $file = $this->file();
        if (file_exists($file)) {
            unlink($file);
        }

        $this->data = [];
        $this->isDirty = true;
    }

    private function key(string|array $key): string
    {
        return is_array($key) ? hash('xxh3', print_r($key, true)) : $key;
    }

    public function cacheDir(): string
    {
        return $this->options['cacheDir'];
    }

    public function patchDirClass(): bool
    {
        $patch = $this->cacheDir().'/dir-inventory.'.App::versionHash().'.patch';
        if (file_exists($patch)) {
            return false;
        }

        $reflection = new ReflectionClass(Dir::class);
        $file = $reflection->getFileName();

        $content = $file ? file_get_contents($file) : null;

        if (! $file || ! $content) {
            return false;
        }

        $head = <<<'CODE'
$items = static::read($dir, $contentIgnore);
CODE;

        $head_new = <<<'CODE'
$cacheKey = func_get_args();
        if ($cache = \Bnomei\Nitro::singleton()->dir()->get($cacheKey)) {
            return $cache;
        }
        $items = static::read($dir, $contentIgnore);
CODE;
        $foot = <<<'CODE'
return $inventory;
	}
CODE;
        $foot_new = <<<'CODE'
\Bnomei\Nitro::singleton()->dir()->set($cacheKey, $inventory);

		return $inventory;
	}
CODE;
        if (strpos($content, $head_new) === false) {
            $content = str_replace($head, $head_new, $content);
            $content = str_replace($foot, $foot_new, $content);
            F::write($file, $content);

            if (function_exists('opcache_invalidate')) {
                opcache_invalidate($file); // @codeCoverageIgnore
            }
        }

        return F::write($patch, date('c'));
    }

    public function write(): bool
    {
        if (! $this->isDirty) {
            return false;
        }

        $file = $this->file();
        $this->isDirty = false;

        return F::write($file, json_encode($this->data, $this->options['json-encode-flags']));
    }
}

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

    private array $options = [];

    public function __construct(array $options = [])
    {
        $this->options = $options;
        $this->isDirty = false;
        $this->data = [];

        $this->load();
    }

    public function __destruct()
    {
        if (! $this->isDirty || ! $this->enabled()) {
            return;
        }

        $file = $this->file();

        if ($this->cacheType() === 'php') {
            F::write($file, '<?php'.PHP_EOL.' return '.var_export($this->data, true).';');
            if (function_exists('opcache_invalidate')) {
                opcache_invalidate($file);
            }
        } else {
            F::write($file, json_encode($this->data));
        }
    }

    public function file(): string
    {
        if ($this->cacheType() === 'php') {
            return $this->cacheDir().'/dir-inventory.php';
        }

        return $this->cacheDir().'/dir-inventory.json';
    }

    private function load(): void
    {
        if (! $this->enabled() || ! file_exists($this->file())) {
            return;
        }

        if ($this->cacheType() === 'php') {
            $this->data = include $this->file();
        } else {
            $this->data = json_decode(file_get_contents($this->file()), true);
        }
    }

    public function get($key): ?array
    {
        if (! $this->enabled()) {
            return null;
        }

        $key = $this->key($key);

        return A::get($this->data, $key);
    }

    public function set($key, ?array $input = null): void
    {
        if (! $this->enabled()) {
            return;
        }

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

    private function key($key): string
    {
        return is_array($key) ? hash('xxh3', print_r($key, true)) : $key;
    }

    private function enabled(): bool
    {
        return $this->options['enabled'];
    }

    private function cacheDir(): string
    {
        return $this->options['cacheDir'];
    }

    private function cacheType(): string
    {
        return $this->options['cacheType'];
    }

    public function patchDirClass(): void
    {

        if (! $this->enabled()) {
            return;
        }

        $patch = $this->cacheDir().'/dir-inventory.'.App::versionHash().'.patch';
        if (file_exists($patch)) {
            return;
        }

        $reflection = new ReflectionClass(Dir::class);
        $file = $reflection->getFileName();

        $content = file_get_contents($file);
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
                opcache_invalidate($file);
            }
        }

        F::write($patch, date('c'));
    }
}

<?php

namespace Bnomei\Nitro;

use Kirby\Cache\Cache;
use Kirby\Cache\Value;
use Kirby\Filesystem\F;
use Kirby\Toolkit\A;

class SingleFileCache extends Cache
{
    private int $isDirty = 0;

    protected array $options = [];

    private array $data = [];

    public function __construct(array $options = [])
    {
        parent::__construct();

        $this->options = array_merge([
            'auto-clean-cache' => option('bnomei.nitro.auto-clean-cache'),
            'max-dirty-cache' => (int) option('bnomei.nitro.max-dirty-cache'),
            'debug' => option('debug'),
        ], $options);

        $this->data = F::exists($this->file()) ? json_decode(F::read($this->file()), true) : [];

        if ($this->options['auto-clean-cache']) {
            $this->clean();
        }
    }

    public function __destruct()
    {
        $this->write();
    }

    public function key(string $key): string
    {
        $key = parent::key($key);

        return hash('xxh3', $key);
    }

    /**
     * {@inheritDoc}
     */
    public function set(string $key, $value, int $minutes = 0): bool
    {
        /* SHOULD SET EVEN IN DEBUG
        if ($this->option('debug')) {
            return true;
        }
        */

        $key = $this->key($key);
        $this->data[$key] = (new Value($value, $minutes))->toArray();
        $this->isDirty++;
        if ($this->isDirty > $this->options['max-dirty-cache']) {
            $this->write();
        }

        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function retrieve(string $key): ?Value
    {
        $value = A::get($this->data, $this->key($key));

        if (! $value) {
            return null;
        }

        return is_array($value) ? Value::fromArray($value) : $value;
    }

    public function get(string $key, $default = null)
    {
        if ($this->options['debug']) {
            return $default;
        }

        return parent::get($key, $default);
    }

    /**
     * {@inheritDoc}
     */
    public function remove(string $key): bool
    {
        $key = $this->key($key);
        if (array_key_exists($key, $this->data)) {
            unset($this->data[$key]);
            $this->isDirty++;
            if ($this->isDirty > $this->options['max-dirty-cache']) {
                $this->write();
            }
        }

        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function flush(): bool
    {
        if (count($this->data) === 0) {
            $this->isDirty++;
        }
        $this->data = [];
        $this->write();

        return true;
    }

    private static ?self $singleton = null;

    public static function singleton(array $options = []): SingleFileCache
    {
        if (is_null(self::$singleton)) {
            self::$singleton = new SingleFileCache($options);
        }

        return self::$singleton;
    }

    private function clean(): void
    {
        foreach ($this->data as $key => $value) {
            $this->get($key); // will remove if expired
        }
    }

    private function file()
    {
        return kirby()->cache('bnomei.nitro.sfc')->root().'/single-file-cache.json';
    }

    private function write(): bool
    {
        if ($this->isDirty === 0) {
            return false;
        }
        F::write($this->file(), json_encode($this->data));
        $this->isDirty = 0;

        return true;
    }
}

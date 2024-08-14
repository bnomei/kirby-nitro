<?php

namespace Bnomei\Nitro;

use Kirby\Cache\Cache;
use Kirby\Cache\FileCache;
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
        parent::__construct($options);

        $this->options = array_merge([
            'global' => option('bnomei.nitro.global'),
            'atomic' => option('bnomei.nitro.atomic'),
            'sleep' => option('bnomei.nitro.sleep'),
            'auto-clean-cache' => option('bnomei.nitro.auto-clean-cache'),
            'json-encode-flags' => option('bnomei.nitro.json-encode-flags'),
            'cacheDir' => realpath(__DIR__.'/../').'/cache', // must be here as well for when used without nitro like as uuid cache
            'max-dirty-cache' => intval(option('bnomei.nitro.max-dirty-cache')), // @phpstan-ignore-line
            'debug' => option('debug'),
        ], $options);

        $this->atomic();

        $data = F::exists($this->file()) ? F::read($this->file()) : null;
        $data = $data ? json_decode($data, true) : null;
        if (is_array($data)) {
            $this->data = $data;
        }

        if ($this->options['auto-clean-cache']) {
            $this->clean();
        }
    }

    public function __destruct()
    {
        $this->write(lock: false);
    }

    public function key(string|array $key): string
    {
        if (is_array($key)) {
            $key = print_r($key, true);
        }
        $key = parent::key($key);

        return hash('xxh3', $key);
    }

    /**
     * {@inheritDoc}
     */
    public function set(string|array $key, mixed $value, int $minutes = 0): bool
    {
        /* SHOULD SET EVEN IN DEBUG
        if ($this->option('debug')) {
            return true;
        }
        */

        $key = $this->key($key);

        // flatten kirby fields
        try {
            $value = $this->serialize($value);
        } catch (AbortCachingExeption $e) {
            return false;
        }

        // make sure the value can be stored as json
        // if not fail here so a trace is more helpful
        $json_encode = json_encode($value, $this->options['json-encode-flags']);
        $value = $json_encode ? json_decode($json_encode, true) : null;

        $this->data[$key] = (new Value($value, $minutes))->toArray();
        $this->isDirty++;
        if ($this->isDirty >= $this->options['max-dirty-cache']) {
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

    public function get(array|string $key, mixed $default = null): mixed
    {
        if ($this->options['debug']) {
            return $default;
        }

        if (is_array($key)) {
            $key = print_r($key, true);
        }

        return parent::get($key, $default);
    }

    /**
     * {@inheritDoc}
     */
    public function remove(string|array $key): bool
    {
        $key = $this->key($key);
        if (array_key_exists($key, $this->data)) {
            unset($this->data[$key]);
            $this->isDirty++;
            if ($this->isDirty >= $this->options['max-dirty-cache']) {
                $this->write();
            }
        }

        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function flush(bool $write = true): bool
    {
        if (count($this->data) === 0) {
            $this->isDirty++;
        }
        $this->data = [];
        if ($write) {
            $this->write();
        }

        return true;
    }

    private function clean(): void
    {
        foreach ($this->data as $key => $value) {
            $this->get($key); // will remove if expired
        }
    }

    protected function file(?string $key = null): string
    {
        /** @var FileCache $cache */
        if ($this->options['global']) {
            $cache = $this->options['cacheDir'];
        } else {
            $cache = kirby()->cache('bnomei.nitro.sfc')->root();
        }

        return $cache.'/single-file-cache.json';
    }

    public function write(bool $lock = true): bool
    {
        // if is atomic but has no file, don't write
        if ($this->options['atomic'] && ! F::exists($this->file().'.lock')) {
            return false;
        }

        $this->unlock();

        if ($this->isDirty === 0) {
            if ($lock) {
                $this->unlock();
            }

            return false;
        }
        $this->isDirty = 0;

        $success = F::write($this->file(), json_encode($this->data, $this->options['json-encode-flags']));
        if ($lock) {
            $this->lock();
        }

        return $success;
    }

    private static function isCallable(mixed $value): bool
    {
        // do not call global helpers just methods or closures
        return ! is_string($value) && is_callable($value);
    }

    public function serialize(mixed $value): mixed
    {
        if (! $value) {
            return null;
        }
        $value = self::isCallable($value) ? $value() : $value; // @phpstan-ignore-line

        if (is_array($value)) {
            $items = [];
            foreach ($value as $key => $item) {
                $items[$key] = $this->serialize($item);
            }

            return $items;
        }

        if (is_a($value, 'Kirby\Content\Field')) {
            return $value->value();
        }

        return $value;
    }

    public function count(): int
    {
        return count($this->data);
    }

    private function isLocked()
    {
        if (! $this->options['atomic']) {
            return false;
        }

        return F::exists($this->file().'.lock');
    }

    public function lock(): bool
    {
        if (! $this->options['atomic']) {
            return false;
        }

        return F::write($this->file().'.lock', date('c'));
    }

    public function unlock(): bool
    {
        if (! $this->options['atomic']) {
            return false;
        }

        return F::remove($this->file().'.lock');
    }

    private function atomic(): bool
    {
        if (! $this->options['atomic']) {
            return false;
        }

        // this is what makes it atomic
        // get php max execution time
        $maxExecutionTime = (int) ini_get('max_execution_time');
        if ($maxExecutionTime === 0) {
            $maxExecutionTime = 30; // default, might happen in xdebug mode
        }
        $maxCycles = $maxExecutionTime * 1000 * 1000; // seconds to microseconds
        $sleep = $this->options['sleep'];

        while ($this->isLocked()) {
            $maxCycles -= $sleep;
            if ($maxCycles <= 0) {
                throw new \Exception('Something is very wrong. SingleFileCache could not get lock within '.$maxExecutionTime.' seconds! Are using xdebug breakpoints or maybe you need to forcibly `kirby nitro:unlock`?');
            }

            usleep($sleep);
        }

        return $this->lock();
    }
}

<?php

namespace Bnomei;

use Bnomei\Nitro\DirInventory;
use Bnomei\Nitro\SingleFileCache;
use Closure;
use Kirby\Cache\FileCache;
use Kirby\Cms\App;
use Kirby\Cms\Files;
use Kirby\Filesystem\Dir;
use Kirby\Filesystem\F;
use Kirby\Toolkit\Str;
use ReflectionClass;

class Nitro
{
    private array $options;

    private ?DirInventory $dirInventory = null;

    private ?SingleFileCache $singleFileCache = null;

    private bool $_ready = false;

    public function __construct(array $options = [])
    {
        $this->options = array_merge([
            'cacheDir' => realpath(__DIR__.'/../').'/cache',
            'json-encode-flags' => JSON_THROW_ON_ERROR,
        ], $options);

        foreach ($this->options as $key => $value) {
            if ($value instanceof Closure) {
                $this->options[$key] = $value();
            }
        }

        if (! Dir::exists($this->options['cacheDir'])) {
            Dir::make($this->options['cacheDir']);
        }
    }

    public function option(string $key): mixed
    {
        return $this->options[$key] ?? null;
    }

    public function dir(): DirInventory
    {
        if (! $this->dirInventory) {
            $this->dirInventory = new DirInventory($this->options);
        }

        return $this->dirInventory;
    }

    public function cache(): SingleFileCache
    {
        if (! $this->singleFileCache) {
            $this->singleFileCache = new SingleFileCache($this->options);
        }

        return $this->singleFileCache;
    }

    public function ready(): void
    {
        $this->replaceCacheFolderWithSymlink();
        $this->patchFilesClass();
        $this->dir()->patchDirClass();

        $this->_ready = true;
    }

    public function isReady(): bool
    {
        return $this->_ready;
    }

    /*
     * map the current kirby cache plugin folder (depending on $_HOST) to the cache folder inside the plugin itself.
     */
    private function replaceCacheFolderWithSymlink(): bool
    {
        $internalDir = $this->options['cacheDir'];
        /** @var FileCache $cache */
        $cache = kirby()->cache('bnomei.nitro.dir');
        $kirbyDir = $cache->root();

        if (! file_exists($internalDir)) {
            Dir::make($internalDir);
        }

        if (file_exists($kirbyDir) && ! is_link($kirbyDir)) {
            Dir::remove($kirbyDir);
        }

        if (! file_exists($kirbyDir)) {
            return symlink($internalDir, $kirbyDir);
        }

        return true;
    }

    public function patchFilesClass(): bool
    {
        if (option('bnomei.nitro.patch-files-class') !== true) {
            return false;
        }

        $patch = $this->options['cacheDir'].'/files.'.App::versionHash().'.patch';
        if (file_exists($patch)) {
            return false;
        }

        $filesClass = (new ReflectionClass(Files::class))->getFileName();
        if ($filesClass && F::exists($filesClass) && F::isWritable($filesClass)) {
            $code = F::read($filesClass);
            if ($code && Str::contains($code, '\Bnomei\NitroFile::factory') === false) {
                $code = str_replace('File::factory(', '\Bnomei\NitroFile::factory(', $code);
                F::write($filesClass, $code);

                if (function_exists('opcache_invalidate')) {
                    opcache_invalidate($filesClass); // @codeCoverageIgnore
                }
            }

            return F::write($patch, date('c'));
        }

        return false;
    }

    public function modelIndex(): int
    {
        $count = 0;
        foreach (site()->index(true) as $page) {
            /** @var NitroPage $page */
            if ($page->hasNitro() !== true) {
                continue;
            }
            $page->readContent();
            $count++;
            foreach ($page->files() as $file) {
                /** @var NitroFile $file */
                if ($file->hasNitro() !== true) {
                    continue;
                }
                $file->readContent();
                $count++;
            }
        }
        foreach (kirby()->users() as $user) {
            /** @var NitroUser $user */
            if ($user->hasNitro() !== true) {
                continue;
            }
            $user->readContent();
            $count++;
        }

        return $count;
    }

    public function flush(): void
    {
        // reset in memory cache as it will be written on destruct
        // and thus would survive the flushing of the directories
        $this->cache()->flush();

        $internalDir = $this->options['cacheDir'];
        if (Dir::exists($internalDir)) {
            // preserve inode of dir in removing files/dirs within not root folder itself
            foreach (Dir::files($internalDir, absolute: true) as $file) {
                unlink($file);
            }
            foreach (Dir::dirs($internalDir, absolute: true) as $dir) {
                Dir::remove($dir);
            }
        }

        $this->dir()->flush();
    }

    public static ?self $singleton = null;

    public static function singleton(array $options = []): self
    {
        if (is_null(self::$singleton)) {
            self::$singleton = new self($options);
        }

        return self::$singleton;
    }
}

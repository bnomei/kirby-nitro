<?php

namespace Bnomei;

use Bnomei\Nitro\DirInventory;
use Bnomei\Nitro\SingleFileCache;
use Kirby\Cms\App;
use Kirby\Filesystem\Dir;
use Kirby\Filesystem\F;
use Kirby\Toolkit\Str;

class Nitro
{
    private array $options;

    private ?DirInventory $dirInventory = null;

    private ?SingleFileCache $singleFileCache = null;

    public function __construct(array $options = [])
    {
        $this->options = array_merge([
            'enabled' => true,
            'cacheDir' => realpath(__DIR__.'/../').'/cache',
            'cacheType' => 'json',
        ], $options);

        foreach ($this->options as $key => $value) {
            if ($value instanceof \Closure) {
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
            $this->singleFileCache = SingleFileCache::singleton($this->options);
        }

        return $this->singleFileCache;
    }

    public function ready(): void
    {
        $this->replaceCacheFolderWithSymlink();
        $this->patchFilesClass();
        $this->dir()->patchDirClass();
    }

    /*
     * map the current kirby cache plugin folder (depending on $_HOST) to the cache folder inside the plugin itself.
     */
    private function replaceCacheFolderWithSymlink(): bool
    {
        if (! $this->options['enabled']) {
            return false;
        }

        $internalDir = $this->options['cacheDir'];
        $kirbyDir = kirby()->cache('bnomei.nitro.dir')->root();

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

    private function patchFilesClass()
    {
        if (option('bnomei.nitro.patch-files-class') !== true) {
            return;
        }

        $patch = $this->options['cacheDir'].'/files.'.App::versionHash().'.patch';
        if (file_exists($patch)) {
            return;
        }

        $filesClass = kirby()->roots()->kirby().'/src/Cms/Files.php';
        if (F::exists($filesClass) && F::isWritable($filesClass)) {
            $code = F::read($filesClass);
            if (Str::contains($code, '\Bnomei\NitroFile::factory') === false) {
                $code = str_replace('File::factory(', '\Bnomei\NitroFile::factory(', $code);
                F::write($filesClass, $code);

                if (function_exists('opcache_invalidate')) {
                    opcache_invalidate($filesClass);
                }
            }
            F::write($patch, date('c'));
        }
    }

    public function modelIndex(): int
    {
        $count = 0;
        foreach (site()->index(true) as $page) {
            if ($page->hasNitro() === true) {
                $page->readContent();
                $count++;
                foreach ($page->files() as $file) {
                    if ($file->hasNitro() === true) {
                        $file->readContent();
                        $count++;
                    }
                }
            }
        }
        foreach (kirby()->users() as $user) {
            if ($user->hasNitro() === true) {
                $user->readContent();
                $count++;
            }
        }

        return $count;
    }

    public function flush(): void
    {
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
        if (is_null(static::$singleton)) {
            static::$singleton = new static($options);
        }

        return static::$singleton;
    }
}
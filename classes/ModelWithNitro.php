<?php

declare(strict_types=1);

namespace Bnomei;

use Kirby\Cms\File;

trait ModelWithNitro
{
    private bool $nitroCacheWillBeDeleted = false;

    public function hasNitro(): bool
    {
        if ($this instanceof File) {
            return $this->parent()->hasNitro() === true;
        }

        return true;
    }

    public function setNitroCacheWillBeDeleted(bool $value): void
    {
        $this->nitroCacheWillBeDeleted = $value;
    }

    public function keyNitro(?string $languageCode = null): string
    {
        $key = $this->id(); // can not use UUID since content not loaded yet
        if (! $languageCode) {
            $languageCode = kirby()->languages()->count() ? kirby()->language()->code() : null;
        }
        if ($languageCode) {
            $key = $key.'-'.$languageCode;
        }

        return hash('xxh3', $key);
    }

    public function readContentCache(?string $languageCode = null): ?array
    {
        return nitro()->cache()->get($this->keyNitro($languageCode));
    }

    public function readContent(?string $languageCode = null): array
    {
        // read from boostedCache if exists
        $data = option('bnomei.nitro.model.read') === false || option('debug') ? null : $this->readContentCache($languageCode);

        // read from file and update
        if (! $data) {
            $data = parent::readContent($languageCode);

            if ($data && $this->nitroCacheWillBeDeleted !== true) {
                $this->writeNitro($data, $languageCode);
            }
        }

        return $data;
    }

    public function writeNitro(?array $data = null, ?string $languageCode = null): bool
    {
        if (option('bnomei.nitro.model.write') === false) {
            return true;
        }

        return nitro()->cache()->set($this->keyNitro($languageCode), $data);
    }

    public function writeContent(array $data, ?string $languageCode = null): bool
    {
        // write to file and cache
        return parent::writeContent($data, $languageCode) &&
            $this->writeNitro($data, $languageCode);
    }

    public function deleteNitro(): bool
    {
        $this->setNitroCacheWillBeDeleted(true);

        if (kirby()->multilang()) {
            foreach (kirby()->languages() as $language) {
                nitro()->cache()->remove($this->keyNitro($language->code()));
            }
        } else {
            nitro()->cache()->remove($this->keyNitro());
        }

        return true;
    }

    public function delete(bool $force = false): bool
    {
        $success = parent::delete($force);
        $this->deleteNitro();

        return $success;
    }
}

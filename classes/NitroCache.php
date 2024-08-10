<?php

namespace Bnomei;

use Kirby\Cache\FileCache;

class NitroCache extends FileCache
{
    // make a magic call to all methods of the cache
    public function __call($method, $args)
    {
        return call_user_func_array([Nitro::singleton()->cache(), $method], $args);
    }
}

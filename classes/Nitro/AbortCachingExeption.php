<?php

namespace Bnomei\Nitro;

class AbortCachingExeption extends \Exception
{
    public function __construct($message = 'Abort Caching', $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}

<?php

@include_once __DIR__.'/vendor/autoload.php';

if (! function_exists('nitro')) {
    function nitro(): \Bnomei\Nitro
    {
        return \Bnomei\Nitro::singleton();
    }
}

Kirby::plugin('bnomei/nitro', [
    'options' => [
        'cache' => [
            'sfc' => true,
            'dir' => true,
        ],
        'patch-files-class' => true,
        'auto-clean-cache' => true,
        'max-dirty-cache' => 512, // write every N changes or on destruct
        'model' => [
            'read' => true,
            'write' => true,
        ],
    ],
    'cacheTypes' => [
        'nitro' => \Bnomei\Nitro\SingleFileCache::class,
    ],
    'hooks' => [
        'system.loadPlugins:after' => function () {
            \Bnomei\Nitro::singleton()->ready();
        },
        'page.*:after' => function ($event, $page) {
            if ($event->action() !== 'render') {
                \Bnomei\Nitro::singleton()->flush();
            }
        },
    ],
]);

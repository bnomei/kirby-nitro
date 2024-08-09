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
        'json-encode-flags' => JSON_THROW_ON_ERROR, // | JSON_INVALID_UTF8_IGNORE,
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
                \Bnomei\Nitro::singleton()->dir()->flush();
            }
        },
        'file.*:after' => function ($event, $file) {
            if ($event->action() !== 'render') {
                \Bnomei\Nitro::singleton()->dir()->flush();
            }
        },
    ],
    'commands' => [
        'nitro:index' => [
            'description' => 'Run Nitro Index',
            'args' => [],
            'command' => static function ($cli): void {

                $cli->out('Flushing...');
                nitro()->flush();

                $cli->out('Indexing...');
                $count = nitro()->modelIndex();
                $cli->out($count.' models indexed.');

                $cli->success('Done.');

                if (function_exists('janitor')) {
                    janitor()->data($cli->arg('command'), [
                        'status' => 200,
                        'message' => $count.' models indexed.',
                    ]);
                }
            },
        ],
        'nitro:flush' => [
            'description' => 'Flush Nitro Cache',
            'args' => [],
            'command' => static function ($cli): void {

                $cli->out('Flushing...');
                nitro()->flush();

                $cli->success('Done.');

                if (function_exists('janitor')) {
                    janitor()->data($cli->arg('command'), [
                        'status' => 200,
                        'message' => 'Nitro Cache flushed.',
                    ]);
                }
            },
        ],
    ],
]);

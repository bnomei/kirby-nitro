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
        'global' => true,
        'atomic' => true,
        'sleep' => 1_000, // MICROSECONDS with usleep, 1ms
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
        'nitro' => \Bnomei\NitroCache::class,
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
        'system.exception' => function (Throwable $exception) {
            // flush and unlock nitro if an exception occurs
            \Bnomei\Nitro::singleton()->dir()->flush();
            \Bnomei\Nitro::singleton()->cache()->flush();
            \Bnomei\Nitro::singleton()->cache()->unlock();
        },
    ],
    'commands' => [
        'nitro:flush' => [
            'description' => 'Flush Nitro Cache',
            'args' => [],
            'command' => static function ($cli): void {

                $cli->out('🚽 Flushing...');
                nitro()->flush();

                $cli->success('✅ Done.');

                if (function_exists('janitor')) {
                    janitor()->data($cli->arg('command'), [
                        'status' => 200,
                        'message' => 'Nitro Cache flushed.',
                    ]);
                }
            },
        ],
        'nitro:unlock' => [
            'description' => 'Forcibly removes the lock of a Nitro Cache',
            'args' => [],
            'command' => static function ($cli): void {

                $cli->out('🛼 Unlocking...');
                $success = nitro()->cache(['atomic' => false, 'auto-clean-cache' => false])->unlock();
                $success ? $cli->success('🔓 Unlocked.') : $cli->error('❌ Failed.');

                // the flush is necessary as the current instance might not have valid data anymore
                $cli->out('🚽 Flushing...');
                nitro()->flush();

                $cli->success('✅ Done.');

                if (function_exists('janitor')) {
                    janitor()->data($cli->arg('command'), [
                        'status' => 200,
                        'message' => $success ? 'Unlocked' : 'Failed',
                    ]);
                }
            },
        ],
        'nitro:index' => [
            'description' => 'Run Nitro Index',
            'args' => [],
            'command' => static function ($cli): void {

                $cli->out('🚽 Flushing...');
                nitro()->flush();

                $cli->out('🔎 Indexing...');
                $count = nitro()->modelIndex();
                $cli->out($count.' models indexed.');

                $cli->success('✅ Done.');

                if (function_exists('janitor')) {
                    janitor()->data($cli->arg('command'), [
                        'status' => 200,
                        'message' => $count.' models indexed.',
                    ]);
                }
            },
        ],
    ],
]);

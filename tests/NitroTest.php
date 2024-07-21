<?php

use Bnomei\Nitro;
use Kirby\Filesystem\Dir;
use Kirby\Filesystem\F;

beforeEach(function () {
    nitro()->flush(); // cleanup
    nitro()->cache()->flush(); // cleanup
});

it('can has an singleton', function () {
    $nitro = Nitro::singleton();
    expect($nitro)->toBeInstanceOf(Nitro::class);
});

it('can use the cache', function () {
    Nitro::$singleton = null;

    // force instant writes for this test
    Nitro::singleton([
        'max-dirty-cache' => 1,
    ]);

    $cache = nitro()->cache();
    $cache->set('test', 'value');
    $value = $cache->get('test');

    expect($value)->toBe('value');

    $cache->set(['test'], 'value');
    $value = $cache->get(['test']);

    expect($value)->toBe('value');

    $cache->remove('test');

    expect($cache->get('test'))->toBeNull();
});

it('will serialize kirby\content\fields to their value', function () {
    $cache = nitro()->cache();
    $cache->set('home.title', page('home')->title());
    $value = $cache->get('home.title');

    expect($value)->toBe('Home');
});

it('will serialize objects if they support it', function () {
    $cache = nitro()->cache();
    $cache->set('object', new \Kirby\Toolkit\Obj([
        'hello' => 'world',
    ]));
    $value = $cache->get('object');

    expect($value)->toBe([
        'hello' => 'world',
    ]);
});

it('will not use the cache in debug mode', function () {
    Nitro::$singleton = null;

    Nitro::singleton([
        'debug' => true,
    ]);

    expect(nitro()->option('debug'))->toBeTrue();

    nitro()->cache()->set('test', 'value');
    expect(nitro()->cache()->get('test'))->toBeNull();

    Nitro::$singleton = null;
});

it('can abort caching a value', function () {
    $cache = nitro()->cache();
    $cache->set('test', 'value');
    $cache->set('test', function () {
        throw new \Bnomei\Nitro\AbortCachingExeption();
    });

    expect($cache->get('test'))->toBe('value');
});

it('can have a closure as option', function () {
    Nitro::$singleton = null;
    Nitro::singleton(['test' => function () {
        return 'value';
    }]);

    expect(nitro()->option('test'))->toBe('value');
});

it('will create the local cache dir if it is missing', function () {
    Nitro::$singleton = null;

    expect(Dir::remove(__DIR__.'/../cache'))->toBeTrue();
    expect(Dir::exists(__DIR__.'/../cache'))->toBeFalse();

    nitro();

    expect(Dir::exists(nitro()->option('cacheDir')))->toBeTrue();
});

it('has a ready method to apply the monkey patches', function () {
    Nitro::$singleton = null;
    nitro()->ready();

    expect(nitro()->isReady())->toBeTrue();
});

it('will remove files and folder when flushing but not break symlinks', function () {
    $dir = __DIR__.'/../cache';
    $inode = fileinode($dir);
    F::write($dir.'/test.txt', 'test');
    Dir::make($dir.'/test');

    nitro()->flush();

    expect(F::exists($dir.'/test.txt'))->toBeFalse()
        ->and(Dir::exists($dir.'/test'))->toBeFalse()
        ->and(fileinode($dir))->toBe($inode);
});

it('can update its index', function () {
    $count = nitro()->modelIndex();

    expect($count)->toBeGreaterThan(0);
});

it('will symlink the kirby cache folder to the local cache folder so the dir index is the same', function () {

    Nitro::$singleton = null;

    $internalDir = nitro()->option('cacheDir');
    $cache = kirby()->cache('bnomei.nitro.dir');
    $kirbyDir = $cache->root();

    Dir::remove($internalDir); // should be created if missing
    @unlink($kirbyDir); // is file but should be symlink
    Dir::make($kirbyDir); // is dir but should be symlink

    nitro()->ready();

    expect(is_link($kirbyDir))->toBeTrue()
        ->and(readlink($kirbyDir))->toBe($internalDir);
});

it('can patch the files class', function () {
    Nitro::$singleton = null;

    $patch = nitro()->option('cacheDir').'/files.'.kirby()->versionHash().'.patch';
    F::remove($patch);

    $success = nitro()->patchFilesClass();

    expect(F::exists($patch))->toBeTrue()
        ->and($success)->toBeTrue()
        ->and(nitro()->patchFilesClass())->toBeFalse();
});

it('can patch the dir class', function () {
    Nitro::$singleton = null;

    $patch = nitro()->option('cacheDir').'/dir-inventory.'.kirby()->versionHash().'.patch';
    F::remove($patch);

    $success = nitro()->dir()->patchDirClass();

    expect(F::exists($patch))->toBeTrue()
        ->and($success)->toBeTrue()
        ->and(nitro()->dir()->patchDirClass())->toBeFalse();
});

it('can flush the dir cache', function () {
    nitro()->modelIndex();
    $di = nitro()->dir();
    $di->write(); // force now, not on destruct

    expect(Dir::files($di->cacheDir()))->toHaveCount(1)
        ->and($di->write())->toBeFalse();

    $di->flush();

    expect(Dir::files($di->cacheDir()))->toHaveCount(0);
});

it('can serialize even null values', function () {
    $cache = nitro()->cache();
    $cache->set('null', null);

    $value = $cache->get('null');

    expect($value)->toBeNull()
        ->and($cache->count())->toBe(1);
});

it('will update the model cache if the model is updated', function () {
    nitro()->modelIndex();
    $cache = nitro()->cache();

    $home = page('home');
    kirby()->impersonate('kirby');
    $home = $home->update([
        'title' => 'new title',
    ]);

    $value = $cache->get($home->keyNitro());
    expect($value)->toHaveKey('title', 'new title');

    // reset
    $home->update([
        'title' => 'Home',
    ]);
});

it('will update the model cache if the model is deleted', function () {
    $home = page('home');
    kirby()->impersonate('kirby');
    $page = $home->createChild([
        'slug' => 'test',
    ]);

    nitro()->modelIndex();
    $cache = nitro()->cache();
    $count = $cache->count();

    $page->delete(true);

    expect($cache->count())->toBe($count - 1);
});

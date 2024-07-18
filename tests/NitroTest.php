<?php

use Bnomei\Nitro;

beforeEach(function () {
    nitro()->flush(); // cleanup
});

it('can has an singleton', function () {
    $nitro = Nitro::singleton();
    expect($nitro)->toBeInstanceOf(Nitro::class);
});

it('can use the cache', function () {
    $cache = nitro()->cache();
    $cache->set('test', 'value');
    $value = $cache->get('test');

    expect($value)->toBe('value');
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

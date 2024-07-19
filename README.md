# ⛽️ Kirby Nitro

![Release](https://flat.badgen.net/packagist/v/bnomei/kirby-nitro?color=ae81ff)
![Downloads](https://flat.badgen.net/packagist/dt/bnomei/kirby-nitro?color=272822)
[![Build Status](https://flat.badgen.net/travis/bnomei/kirby-nitro)](https://travis-ci.com/bnomei/kirby-nitro)
[![Maintainability](https://flat.badgen.net/codeclimate/maintainability/bnomei/kirby-nitro)](https://codeclimate.com/github/bnomei/kirby-nitro)
[![Twitter](https://flat.badgen.net/badge/twitter/bnomei?color=66d9ef)](https://twitter.com/bnomei)

Nitro speeds up the loading of content in your Kirby project.

## Commercial Usage

> <br>
> <b>Support open source!</b><br><br>
> This plugin is free but if you use it in a commercial project please consider to sponsor me or make a donation.<br>
> If my work helped you to make some cash it seems fair to me that I might get a little reward as well, right?<br><br>
> Be kind. Share a little. Thanks.<br><br>
> &dash; Bruno<br>
> &nbsp; 

| M                                                    | O                                     | N                                               | E                                                   | Y                                            |
|------------------------------------------------------|---------------------------------------|-------------------------------------------------|-----------------------------------------------------|----------------------------------------------|
| [Github sponsor](https://github.com/sponsors/bnomei) | [Patreon](https://patreon.com/bnomei) | [Buy Me a Coffee](https://buymeacoff.ee/bnomei) | [Paypal dontation](https://www.paypal.me/bnomei/15) | [Hire me](mailto:b@bnomei.com?subject=Kirby) |

## Installation

- unzip [master.zip](https://github.com/bnomei/kirby-nitro/archive/master.zip) as folder `site/plugins/kirby-nitro`
  or
- `git submodule add https://github.com/bnomei/kirby-nitro.git site/plugins/kirby-nitro` or
- `composer require bnomei/kirby-nitro`

## Checklist: When to use this plugin?

- You load more than 100 but less than 2000 models (pages/files/users) in a **single request**?
- You have less than 4000 models or 2 MB combined TXT files in your project?
- If you load less, you do not need any performance plugins apart from maybe
  a [key-value caching helper](https://github.com/bnomei/kirby3-lapse).
- If you load more, you should consider [Boost](https://github.com/bnomei/kirby3-boost)
  or [Khulan](https://github.com/bnomei/kirby-mongodb) instead.

## Setup

For each template you want to be cached you need to use a model to add the content cache logic using a trait.

**site/models/default.php**

```php
class DefaultPage extends \Kirby\Cms\Page
{
    use \Bnomei\ModelWithNitro;
}
```

> [!NOTE]
> You can also use the trait for user models. File models are patched automatically.

## Using the Cache

You can use the single-file-based cache of Nitro to store your own key-value pairs, just like with a regular cache in
Kirby.

```php
nitro()->cache()->set('mykey', 'value');
nitro()->cache()->set('mykey', 'value', 1);

$value = nitro()->cache()->get('mykey');
$value = nitro()->cache()->getOrSet('mykey', fn() => 'value');
```

> [!WARNING]
> Since the Nitro cache is fully loaded with every request I would not advise to store too many big chunks of
> data (like HTML output or when having too many models in total).

## Using the Cache Driver in Kirby

You can also use the singe-file-based cache of Nitro as a **cache driver** for Kirby. This will allow you to use it for
caching of other extensions in Kirby.

> [!NOTE]
> I would highly recommend to use the Nitro cache for Kirby's UUID cache.

**site/config/config.php**

```php
return [
    // ... other options
    
    // use nitro as cache driver for storing uuids
    // instead of the default file-based cache
    'cache' => [
        'uuid' => [
            'type' => 'nitro',
        ],
    ],
    
    // example: in Lapse plugin
    'bnomei.lapse.cache' => [
        'type' => 'nitro',
    ],
];
```

## Settings

| bnomei.nitro.     | Default               | Description                                                                  |            
|-------------------|-----------------------|------------------------------------------------------------------------------|
| auto-clean-cache  | `true`                | will clean the cache once before the first get()                             |
| patch-dir-class   | always on             | monkey-patch the \Kirby\Filesystem\Dir class to use Nitro for caching        |
| patch-files-class | `true`                | monkey-patch the \Kirby\CMS\Files class to use Nitro for caching its content |
| max-dirty-cache   | `512`                 | write every N changes or on destruct                                         |
| json-encode-flags | `JSON_THROW_ON_ERROR` |                                                                              |
| model.read        | `true`                | read from cache for all models that use the ModelWithNitro trait             |
| model.write       | `true`                | write to cache for all models that use the ModelWithNitro trait              |

## Disclaimer

This plugin is provided "as is" with no guarantee. Use it at your own risk and always test it yourself before using it
in a production environment. If you find any issues,
please [create a new issue](https://github.com/bnomei/kirby-nitro/issues/new).

## License

[MIT](https://opensource.org/licenses/MIT)

It is discouraged to use this plugin in any project that promotes racism, sexism, homophobia, animal abuse, violence or
any other form of hate speech.

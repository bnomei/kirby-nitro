# ⛽️ Kirby Nitro

![Release](https://flat.badgen.net/packagist/v/bnomei/kirby-nitro?color=ae81ff&icon=github&label)
[![Discord](https://flat.badgen.net/badge/discord/bnomei?color=7289da&icon=discord&label)](https://discordapp.com/users/bnomei)
[![Buymecoffee](https://flat.badgen.net/badge/icon/donate?icon=buymeacoffee&color=FF813F&label)](https://www.buymeacoffee.com/bnomei)

Nitro speeds up the loading of content in your Kirby project.

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
- If you need to process multiple requests fully concurrently you should not use this plugin. But from my experience
  most Kirby projects do not need that.

## Global & Atomic Cache

The Nitro cache is a global cache. This means that the cache is shared between all HTTP_HOST environments. This will
make it behave like a single database connection.

The Nitro cache is by default an atomic cache. This means that the cache will block the cache file for the full duration
of your request to maintain data consistency. This will make it behave like a database with locks.

> [!WARNING]
> No matter how many php-fpm workers you have, only one will be running at a time when Nitro is in atomic mode! You have
> been warned! But this is the only way to guarantee data consistency, and it will still be wicked fast.

## Usecase

The plugin will speed up Kirby setups, loading 100-2000 page models in a single request by providing a special
cache. It solves the three major performance bottlenecks in Kirby that I know of and links the cache between CLI and
HTTP requests.

It does this by:

- Providing a regular file cache you can use yourself. But the cache is always fully loaded in every request and only
  uses a single file, which makes it wicked fast.
- Optionally you can use that cache for storing the UUID to page ID relations. So instead of loading a single file for
  every page model UUID resolution, the cache will have them ready instantly.
- It allows you to store the TXT content of selected page/file/user models in the cache to speed up the loading time of
  content.
- I uses a second cache, which will cache `Dir::index` results. This will skip crawling the file structure step in
  populating the full index from the cache until you update any page/file.

## Setup

For each template you want to be cached you need to use a model to add the content cache logic using a trait.

**site/models/default.php**

```php
class DefaultPage extends \Kirby\Cms\Page
{
    use \Bnomei\ModelWithNitro;
}
```

or

**site/models/article.php**

```php
class ArticlePage extends \Kirby\Cms\Page
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

The Nitro cache is a bit smarter than the default cache in Kirby. It allows you optionally provide keys as arrays, it
will serialize values automatically (like Kirby fields to their `->value()`) and storing a value can be canceled.

```php
nitro()->cache()->set(['articles', $page->slug()], $page->title());

nitro()->cache()->set('test', function () {
    // ... some logic
    if($cancel) {
        throw new \Bnomei\Nitro\AbortCachingExeption();
    }
});
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
| global            | `true`                | all HTTP_HOSTs will share the same cache                                     |
| atomic            | `true`                | will lock the cache while a request is processed to achieve data consistency |
| sleep             | `1000`                | duration in MICRO seconds before checking the lock again                     |
| auto-unlock-cache | `true`                | will forcibly unlock the cache if it could not get a lock within set time    |
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

<?php

// autoload_static.php @generated by Composer

namespace Composer\Autoload;

class ComposerStaticInit0eeb079738a6a917b8e643d59e53f8d6
{
    public static $prefixLengthsPsr4 = array (
        'K' => 
        array (
            'Kirby\\' => 6,
        ),
        'B' => 
        array (
            'Bnomei\\' => 7,
        ),
    );

    public static $prefixDirsPsr4 = array (
        'Kirby\\' => 
        array (
            0 => __DIR__ . '/..' . '/getkirby/composer-installer/src',
        ),
        'Bnomei\\' => 
        array (
            0 => __DIR__ . '/../..' . '/classes',
        ),
    );

    public static $classMap = array (
        'Bnomei\\ModelWithNitro' => __DIR__ . '/../..' . '/classes/ModelWithNitro.php',
        'Bnomei\\Nitro' => __DIR__ . '/../..' . '/classes/Nitro.php',
        'Bnomei\\NitroCache' => __DIR__ . '/../..' . '/classes/NitroCache.php',
        'Bnomei\\NitroFile' => __DIR__ . '/../..' . '/classes/NitroFile.php',
        'Bnomei\\NitroPage' => __DIR__ . '/../..' . '/classes/NitroPage.php',
        'Bnomei\\NitroUser' => __DIR__ . '/../..' . '/classes/NitroUser.php',
        'Bnomei\\Nitro\\AbortCachingExeption' => __DIR__ . '/../..' . '/classes/Nitro/AbortCachingExeption.php',
        'Bnomei\\Nitro\\DirInventory' => __DIR__ . '/../..' . '/classes/Nitro/DirInventory.php',
        'Bnomei\\Nitro\\SingleFileCache' => __DIR__ . '/../..' . '/classes/Nitro/SingleFileCache.php',
        'Composer\\InstalledVersions' => __DIR__ . '/..' . '/composer/InstalledVersions.php',
        'Kirby\\ComposerInstaller\\CmsInstaller' => __DIR__ . '/..' . '/getkirby/composer-installer/src/ComposerInstaller/CmsInstaller.php',
        'Kirby\\ComposerInstaller\\Installer' => __DIR__ . '/..' . '/getkirby/composer-installer/src/ComposerInstaller/Installer.php',
        'Kirby\\ComposerInstaller\\Plugin' => __DIR__ . '/..' . '/getkirby/composer-installer/src/ComposerInstaller/Plugin.php',
        'Kirby\\ComposerInstaller\\PluginInstaller' => __DIR__ . '/..' . '/getkirby/composer-installer/src/ComposerInstaller/PluginInstaller.php',
    );

    public static function getInitializer(ClassLoader $loader)
    {
        return \Closure::bind(function () use ($loader) {
            $loader->prefixLengthsPsr4 = ComposerStaticInit0eeb079738a6a917b8e643d59e53f8d6::$prefixLengthsPsr4;
            $loader->prefixDirsPsr4 = ComposerStaticInit0eeb079738a6a917b8e643d59e53f8d6::$prefixDirsPsr4;
            $loader->classMap = ComposerStaticInit0eeb079738a6a917b8e643d59e53f8d6::$classMap;

        }, null, ClassLoader::class);
    }
}

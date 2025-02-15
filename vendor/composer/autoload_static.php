<?php

// autoload_static.php @generated by Composer

namespace Composer\Autoload;

class ComposerStaticInit4838cf8352863bb7d9e96d1fbeaaebcc
{
    public static $prefixLengthsPsr4 = array (
        'V' => 
        array (
            'Voucherify\\Wordpress\\' => 21,
            'Voucherify\\Test\\Helpers\\' => 24,
            'Voucherify\\' => 11,
        ),
    );

    public static $prefixDirsPsr4 = array (
        'Voucherify\\Wordpress\\' => 
        array (
            0 => __DIR__ . '/../..' . '/src/Voucherify/Wordpress',
        ),
        'Voucherify\\Test\\Helpers\\' => 
        array (
            0 => __DIR__ . '/..' . '/rspective/voucherify/test/helpers',
        ),
        'Voucherify\\' => 
        array (
            0 => __DIR__ . '/../..' . '/src/Voucherify',
            1 => __DIR__ . '/..' . '/rspective/voucherify/src',
        ),
    );

    public static $classMap = array (
        'Composer\\InstalledVersions' => __DIR__ . '/..' . '/composer/InstalledVersions.php',
    );

    public static function getInitializer(ClassLoader $loader)
    {
        return \Closure::bind(function () use ($loader) {
            $loader->prefixLengthsPsr4 = ComposerStaticInit4838cf8352863bb7d9e96d1fbeaaebcc::$prefixLengthsPsr4;
            $loader->prefixDirsPsr4 = ComposerStaticInit4838cf8352863bb7d9e96d1fbeaaebcc::$prefixDirsPsr4;
            $loader->classMap = ComposerStaticInit4838cf8352863bb7d9e96d1fbeaaebcc::$classMap;

        }, null, ClassLoader::class);
    }
}

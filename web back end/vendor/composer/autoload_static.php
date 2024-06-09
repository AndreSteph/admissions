<?php

// autoload_static.php @generated by Composer

namespace Composer\Autoload;

class ComposerStaticInitc2c5dde4a73d2a3be986f3e48dd85da4
{
    public static $prefixLengthsPsr4 = array (
        'L' => 
        array (
            'Lenovo\\CourseSubjects\\' => 22,
        ),
    );

    public static $prefixDirsPsr4 = array (
        'Lenovo\\CourseSubjects\\' => 
        array (
            0 => __DIR__ . '/../..' . '/src',
        ),
    );

    public static $classMap = array (
        'Composer\\InstalledVersions' => __DIR__ . '/..' . '/composer/InstalledVersions.php',
    );

    public static function getInitializer(ClassLoader $loader)
    {
        return \Closure::bind(function () use ($loader) {
            $loader->prefixLengthsPsr4 = ComposerStaticInitc2c5dde4a73d2a3be986f3e48dd85da4::$prefixLengthsPsr4;
            $loader->prefixDirsPsr4 = ComposerStaticInitc2c5dde4a73d2a3be986f3e48dd85da4::$prefixDirsPsr4;
            $loader->classMap = ComposerStaticInitc2c5dde4a73d2a3be986f3e48dd85da4::$classMap;

        }, null, ClassLoader::class);
    }
}

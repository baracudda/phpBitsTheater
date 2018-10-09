<?php

// autoload_static.php @generated by Composer

namespace Composer\Autoload;

class ComposerStaticInitb39f3cd7b67f8cf7d36df4f9e1849813
{
    public static $prefixLengthsPsr4 = array (
        'P' => 
        array (
            'PHPMailer\\PHPMailer\\' => 20,
        ),
    );

    public static $prefixDirsPsr4 = array (
        'PHPMailer\\PHPMailer\\' => 
        array (
            0 => __DIR__ . '/..' . '/phpmailer/phpmailer/src',
        ),
    );

    public static function getInitializer(ClassLoader $loader)
    {
        return \Closure::bind(function () use ($loader) {
            $loader->prefixLengthsPsr4 = ComposerStaticInitb39f3cd7b67f8cf7d36df4f9e1849813::$prefixLengthsPsr4;
            $loader->prefixDirsPsr4 = ComposerStaticInitb39f3cd7b67f8cf7d36df4f9e1849813::$prefixDirsPsr4;

        }, null, ClassLoader::class);
    }
}

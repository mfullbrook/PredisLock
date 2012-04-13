<?php

/*
 * This file is part of the PredisLock package.
 *
 * (c) Mark Fullbrook <mark.fullbrook@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

include __DIR__ . '/../autoload.php';

$classLoader->registerNamespaces(array(
    'PredisLock\\Tests' => __DIR__
));

// enable Debug loader
require __DIR__.'/../vendor/symfony/src/Symfony/Component/ClassLoader/DebugUniversalClassLoader.php';
Symfony\Component\ClassLoader\DebugUniversalClassLoader::enable();

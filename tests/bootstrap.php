<?php

if (file_exists($file = __DIR__.'/../vendor/autoload.php')) {
    require_once $file;
} elseif (file_exists($file = __DIR__.'/../../../autoload.php')) {
    require_once $file;
} else {
    throw new \RuntimeException('Install dependencies using Composer to run the test suite.');
}

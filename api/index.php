<?php

use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

require __DIR__.'/../vendor/autoload.php';

// Only disposable compiled templates and PDF scratch files live here.
$storage = sys_get_temp_dir().'/attendify';
foreach (['framework/views', 'framework/cache', 'framework/sessions', 'logs'] as $directory) {
    $path = $storage.'/'.$directory;
    if (! is_dir($path) && ! mkdir($path, 0700, true) && ! is_dir($path)) {
        throw new RuntimeException('Unable to initialize temporary Laravel storage.');
    }
}

putenv('LARAVEL_STORAGE_PATH='.$storage);
putenv('VIEW_COMPILED_PATH='.$storage.'/framework/views');
$_ENV['LARAVEL_STORAGE_PATH'] = $_SERVER['LARAVEL_STORAGE_PATH'] = $storage;
$_ENV['VIEW_COMPILED_PATH'] = $_SERVER['VIEW_COMPILED_PATH'] = $storage.'/framework/views';

$app = require __DIR__.'/../bootstrap/app.php';
$app->useStoragePath($storage);
$app->handleRequest(Request::capture());

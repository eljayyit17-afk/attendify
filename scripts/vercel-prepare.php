<?php

// The runtime skips Composer's normal scripts. Initialize package discovery.
foreach (['bootstrap/cache', 'storage/framework/views', 'storage/framework/cache', 'storage/logs'] as $directory) {
    if (! is_dir(__DIR__.'/../'.$directory)) {
        mkdir(__DIR__.'/../'.$directory, 0700, true);
    }
}

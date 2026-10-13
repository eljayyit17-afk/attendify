<?php

use Symfony\Component\Process\Process;

it('boots serverless requests with temporary compiled views and built assets', function (string $uri, int $status) {
    $root = dirname(__DIR__, 2);
    $script = <<<'PHP'
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_SERVER['REQUEST_URI'] = $argv[1];
    $_SERVER['HTTP_HOST'] = 'attendify.example';
    $_SERVER['SERVER_PORT'] = '80';
    $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
    $_SERVER['HTTP_X_FORWARDED_PROTO'] = 'https';
    $_SERVER['HTTP_X_FORWARDED_PORT'] = '443';
    $_SERVER['SCRIPT_NAME'] = '/api/index.php';
    require 'api/index.php';
    echo '\nSTATUS:'.http_response_code();
    echo '\nSTORAGE:'.$app->storagePath();
    PHP;

    $process = new Process([PHP_BINARY, '-r', $script, $uri], $root, [
        'APP_ENV' => 'testing',
        'VERCEL' => '1',
        'APP_DEBUG' => 'false',
        'APP_KEY' => 'base64:'.base64_encode(random_bytes(32)),
        'APP_URL' => 'https://attendify.example',
        'SESSION_DRIVER' => 'array',
        'CACHE_STORE' => 'array',
        'LOG_CHANNEL' => 'stderr',
        'DB_CONNECTION' => 'sqlite',
        'DB_DATABASE' => ':memory:',
        'DB_URL' => '',
    ]);
    $process->mustRun();
    expect($process->getOutput())
        ->toContain('STATUS:'.$status)
        ->toContain('STORAGE:'.sys_get_temp_dir().'/attendify');

    if ($uri === '/login') {
        expect($process->getOutput())
            ->toContain('https://attendify.example/build/assets/')
            ->toContain('livewire');
    }
})->with([
    'health' => ['/up', 200],
    'login' => ['/login', 200],
    'protected admin' => ['/admin/dashboard', 302],
]);

import { spawnSync } from 'node:child_process';
import { chmodSync } from 'node:fs';
import { createRequire } from 'node:module';
import { dirname, join, delimiter } from 'node:path';

// Use the community runtime's PHP/Composer, not an assumed system installation.
const require = createRequire(import.meta.url);
const runtime = dirname(require.resolve('vercel-php/package.json'));
const runtimeRequire = createRequire(join(runtime, 'package.json'));
const libphp = dirname(runtimeRequire.resolve('@libphp/almalinux-9-v85/package.json'));
const phpDirectory = join(libphp, 'native/php');
const composer = join(phpDirectory, 'composer');
const linux = process.platform === 'linux';
const php = linux ? join(phpDirectory, 'php') : (process.env.PHP_BINARY || 'php');
const phpArguments = linux ? ['-c', join(phpDirectory, 'php.ini')] : [];
const env = linux ? {
    ...process.env,
    COMPOSER_HOME: '/tmp',
    PATH: `${phpDirectory}${delimiter}${process.env.PATH || ''}`,
    PHP_INI_EXTENSION_DIR: join(phpDirectory, 'modules'),
    PHP_INI_SCAN_DIR: join(runtime, 'conf'),
    LD_LIBRARY_PATH: `${join(libphp, 'native/lib')}:/usr/lib64:/lib64`,
} : process.env;

if (linux) chmodSync(php, 0o755);

for (const args of [
    ['install', '--no-dev', '--prefer-dist', '--optimize-autoloader', '--no-interaction', '--no-progress', '--no-scripts'],
    ['run', 'vercel'],
]) {
    const result = spawnSync(php, [...phpArguments, composer, ...args], { env, stdio: 'inherit' });
    if (result.error) throw result.error;
    if (result.status !== 0) process.exit(result.status ?? 1);
}

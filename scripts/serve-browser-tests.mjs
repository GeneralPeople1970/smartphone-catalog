import { spawn, spawnSync } from 'node:child_process';
import { mkdirSync } from 'node:fs';
import { isAbsolute, join, resolve } from 'node:path';

const runtime = process.env.BROWSER_TEST_RUNTIME;
if (!runtime || !isAbsolute(runtime)) throw new Error('A dedicated BROWSER_TEST_RUNTIME directory is required.');
const port = process.env.BROWSER_TEST_PORT || '8765';
const php = process.env.PHP_BINARY || 'php';
mkdirSync(join(runtime, 'views'), { recursive: true });
const env = {
    ...process.env,
    APP_ENV: 'browser-testing',
    APP_KEY: 'base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=',
    APP_URL: `http://127.0.0.1:${port}`,
    APP_DEBUG: 'false',
    APP_CONFIG_CACHE: join(runtime, 'config.php'),
    DB_CONNECTION: 'sqlite',
    DB_DATABASE: join(runtime, 'browser.sqlite'),
    DB_URL: '',
    CACHE_STORE: 'array',
    SESSION_DRIVER: 'file',
    SESSION_COOKIE: 'catalog_browser_test_session',
    SESSION_SECURE_COOKIE: 'false',
    SESSION_DOMAIN: '',
    VIEW_COMPILED_PATH: join(runtime, 'views'),
    MAIL_MAILER: 'array',
    LOG_CHANNEL: 'stderr',
    QUEUE_CONNECTION: 'sync',
    CATALOG_SEARCH_DRIVER: 'like',
    BCRYPT_ROUNDS: '4',
};
const seed = spawnSync(php, ['tests/Browser/seed.php'], { env, stdio: 'inherit' });
if (seed.error) throw seed.error;
if (seed.status !== 0) process.exit(seed.status || 1);

const server = spawn(php, ['-S', `127.0.0.1:${port}`, '-t', '.', resolve('vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php')], {
    cwd: resolve('public'),
    env,
    stdio: ['ignore', 'pipe', 'pipe'],
    windowsHide: true,
});
server.stdout.on('data', chunk => {
    const lines = chunk.toString().split(/\r?\n/).filter(line => line && !/ \[(?:GET|POST|PUT|PATCH|DELETE|HEAD)\] URI: /.test(line));
    if (lines.length) process.stdout.write(`${lines.join('\n')}\n`);
});
// PHP's development server logs every request on stderr; keep successful test
// output readable while still showing startup errors and application failures.
server.stderr.on('data', chunk => {
    const lines = chunk.toString().split(/\r?\n/).filter(line => line && !/ (?:Accepted|Closing)$| \[\d{3}\]: /.test(line));
    if (lines.length) process.stderr.write(`${lines.join('\n')}\n`);
});
server.on('error', error => { throw error; });
server.on('exit', code => process.exit(code || 0));
for (const signal of ['SIGINT', 'SIGTERM']) {
    process.on(signal, () => server.kill());
}

/**
 * The E2E web server: reseed a throwaway SQLite database, then run PHP's built-in
 * server on :8100, restarting it if it dies.
 *
 * Why not plain `php artisan serve`: in CI the built-in server occasionally died
 * mid-suite with no log line at all (2026-09-14 and 2026-09-15, both at the first
 * `/shop/search-index` request), and `artisan serve` then exits without saying how
 * its child ended. Every later test failed with ERR_CONNECTION_REFUSED, so one
 * dev-server hiccup read as fifteen broken search features.
 *
 * Running `php -S` directly lets us print HOW it exited (a signal name such as
 * SIGPIPE, or an exit code), which is the piece the CI log was missing, and
 * restart it so an unrelated test is not failed by a crash it did not cause. The
 * restart is loud on purpose: if a real crash creeps in, the log says so.
 *
 * Mirrors what `artisan serve` runs: `php -S host:port <router>` from inside
 * `public/`, with Laravel's own router script. DB_* overrides arrive through the
 * environment from playwright.config.ts, and a real env var beats `.env`.
 */
import { spawn, spawnSync } from 'node:child_process';
import { existsSync } from 'node:fs';
import path from 'node:path';

const HOST = '127.0.0.1:8100';
const MAX_RESTARTS = 5;

const root = process.cwd();
const router = existsSync(path.join(root, 'server.php'))
    ? path.join(root, 'server.php')
    : path.join(root, 'vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php');

const seeded = spawnSync('php', ['artisan', 'migrate:fresh', '--seed', '--force'], { stdio: 'inherit' });
if (seeded.status !== 0) {
    console.error(`[e2e-server] seeding failed (exit ${seeded.status})`);
    process.exit(seeded.status ?? 1);
}

let child = null;
let restarts = 0;
let stopping = false;

/**
 * Forward the server's output, minus the per-connection "Accepted" / "Closing"
 * lines that `artisan serve` used to hide. Request lines and errors still show.
 */
function forward(stream, target) {
    let buffer = '';
    stream.on('data', (chunk) => {
        buffer += chunk;
        const lines = buffer.split('\n');
        buffer = lines.pop() ?? '';
        for (const line of lines) {
            if (!/ (Accepted|Closing)\r?$/.test(line)) target.write(`${line}\n`);
        }
    });
}

function start() {
    child = spawn('php', ['-S', HOST, router], { cwd: path.join(root, 'public'), stdio: ['ignore', 'pipe', 'pipe'] });
    forward(child.stdout, process.stdout);
    forward(child.stderr, process.stderr);

    child.on('exit', (code, signal) => {
        if (stopping) return;

        console.error(`[e2e-server] PHP server exited unexpectedly (code ${code}, signal ${signal ?? 'none'}).`);

        if (restarts >= MAX_RESTARTS) {
            console.error(`[e2e-server] giving up after ${MAX_RESTARTS} restarts.`);
            process.exit(1);
        }

        restarts++;
        console.error(`[e2e-server] restarting (${restarts}/${MAX_RESTARTS}).`);
        setTimeout(start, 200);
    });
}

function stop() {
    stopping = true;
    child?.kill();
    process.exit(0);
}

for (const sig of ['SIGINT', 'SIGTERM', 'SIGHUP']) {
    process.on(sig, stop);
}

start();

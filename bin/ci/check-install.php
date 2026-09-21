#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Scaffold this plugin onto a real, installed Pollora site and check it works.
 *
 * Run from the skeleton root, with WordPress already installed:
 *
 *   php /path/to/plugin-default/bin/ci/check-install.php \
 *       --url=https://pollora-plugin-ci.ddev.site [--name=ci-plugin] [--source=/path/to/plugin-default]
 *
 * The contract tests next door are properties of the files. This is the part
 * they cannot see: that a generated plugin activates without a fatal on the
 * framework the site actually runs, that the scaffolder's `.stub` renaming
 * produced loadable classes, and that the plugin registers with Pollora.
 *
 * The case worth the whole script is a site on framework 13.4, where
 * `pollora_register()` does not exist. Calling it unconditionally was a fatal
 * on every stable site; the fix went out in 0.8 with nothing written that
 * would catch it coming back.
 *
 * --source overlays the working copy onto the generated plugin, so CI measures
 * the commit under test rather than the tag the scaffolder downloaded. The
 * scaffolder can only fetch tags, which is why the overlay exists at all.
 */

require_once dirname(__DIR__).'/replacements.php';

$options = getopt('', ['url:', 'name::', 'source::']);

if (! isset($options['url'])) {
    fwrite(STDERR, "\nUsage: php bin/ci/check-install.php --url=<site url> [--name=ci-plugin] [--source=<plugin repo>]\n\n");
    exit(2);
}

$baseUrl = rtrim((string) $options['url'], '/');
$name = (string) ($options['name'] ?? 'ci-plugin');
$source = isset($options['source']) ? rtrim((string) $options['source'], '/') : null;

$host = parse_url($baseUrl, PHP_URL_HOST) ?: '';
$disposable = str_ends_with($host, '.ddev.site')
    || str_ends_with($host, '.test')
    || str_ends_with($host, '.localhost')
    || in_array($host, ['localhost', '127.0.0.1'], true);

// Activating a plugin changes what the site runs. Two independent gates, the
// same shape as the skeleton's install scenarios.
if (! $disposable) {
    fwrite(STDERR, "\n\033[31mRefusing to run against {$host}.\033[0m\nThis script activates a plugin; it only runs against a local or CI host.\n\n");
    exit(2);
}

// ─────────────────────────────────────────────────────────────────────────────
// Harness
// ─────────────────────────────────────────────────────────────────────────────

$passed = 0;
$failed = 0;

function section(string $title): void
{
    echo "\n\033[1m── {$title} ──\033[0m\n";
}

/** @param callable():(true|string) $fn */
function test(string $name, callable $fn): void
{
    global $passed, $failed;

    try {
        $result = $fn();
    } catch (\Throwable $e) {
        echo "  \033[31m✗\033[0m  {$name} — {$e->getMessage()}\n";
        $failed++;

        return;
    }

    if ($result === true) {
        echo "  \033[32m✓\033[0m  {$name}\n";
        $passed++;

        return;
    }

    $reason = is_string($result) && $result !== '' ? " — {$result}" : '';
    echo "  \033[31m✗\033[0m  {$name}{$reason}\n";
    $failed++;
}

/** @return array{code: int, out: string} */
function run(string $command): array
{
    $descriptors = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
    $process = proc_open($command.' 2>&1', $descriptors, $pipes);

    if (! is_resource($process)) {
        throw new \RuntimeException("could not run: {$command}");
    }

    $out = stream_get_contents($pipes[1]) ?: '';
    fclose($pipes[1]);
    fclose($pipes[2]);

    return ['code' => proc_close($process), 'out' => trim($out)];
}

function wpEval(string $php): string
{
    $result = run('wp eval '.escapeshellarg($php));

    if ($result['code'] !== 0) {
        throw new \RuntimeException("wp eval failed: {$result['out']}");
    }

    return $result['out'];
}

/** @return array{status: int, body: string} */
function http(string $url): array
{
    $context = stream_context_create([
        'http' => ['ignore_errors' => true, 'timeout' => 30, 'follow_location' => 1],
        'ssl' => ['verify_peer' => false, 'verify_peer_name' => false],
    ]);

    $body = @file_get_contents($url, false, $context);
    $status = 0;

    foreach ($http_response_header ?? [] as $header) {
        if (preg_match('#^HTTP/\S+\s+(\d{3})#', $header, $m) === 1) {
            $status = (int) $m[1];
        }
    }

    return ['status' => $status, 'body' => is_string($body) ? $body : ''];
}

/**
 * The command changed name in 13.32; both spellings have to be tried because
 * the point of this script is to run on the old framework too.
 */
function makePluginCommand(): string
{
    $list = run('php artisan list --raw');

    return str_contains($list['out'], 'pollora:make:plugin') ? 'pollora:make:plugin' : 'pollora:make-plugin';
}

/**
 * Make sure the site has an active theme before the plugin is exercised.
 *
 * A plugin cannot be judged on a site that cannot render: every page would
 * answer with the missing-theme notice and the checks below would pass or fail
 * for reasons that have nothing to do with the plugin. On framework 13.4 the
 * install leaves no theme at all, because `pollora:install` dies before its
 * theme step.
 *
 * This lived in the workflow as a shell one-liner and was the first thing to
 * break in CI. Here it can be read, and it runs identically by hand.
 */
function ensureActiveTheme(): void
{
    $installed = array_values(array_filter(explode("\n", run('wp theme list --field=name')['out'])));

    if ($installed === []) {
        $list = run('php artisan list --raw');
        $command = str_contains($list['out'], 'pollora:make:theme') ? 'pollora:make:theme' : 'pollora:make-theme';

        echo "  \033[2m→ no theme on the site; scaffolding one with {$command}\033[0m\n";

        $scaffold = run('php artisan '.$command.' default'
            .' --theme-author=Pollora --theme-description='.escapeshellarg('Theme for the plugin check')
            .' --theme-version=1.0.0 --no-interaction');

        if ($scaffold['code'] !== 0) {
            throw new \RuntimeException("could not scaffold a theme: {$scaffold['out']}");
        }

        $installed = array_values(array_filter(explode("\n", run('wp theme list --field=name')['out'])));
    }

    if ($installed === []) {
        throw new \RuntimeException('the site still has no theme after scaffolding one');
    }

    $active = run('wp theme list --status=active --field=name')['out'];

    if (trim($active) !== '') {
        return;
    }

    $activated = run('wp theme activate '.escapeshellarg($installed[0]));

    if ($activated['code'] !== 0) {
        throw new \RuntimeException("could not activate {$installed[0]}: {$activated['out']}");
    }
}

/**
 * Where the scaffolder put the plugin.
 *
 * The plugins directory moved between skeleton generations, so it is asked for
 * rather than assumed.
 */
function pluginsDirectory(): string
{
    $path = wpEval('echo WP_PLUGIN_DIR;');

    if ($path === '' || ! is_dir($path)) {
        throw new \RuntimeException("WP_PLUGIN_DIR is {$path}, which does not exist");
    }

    return $path;
}

/**
 * Copy the commit under test over the generated plugin.
 *
 * The scaffolder downloads a tag — it has no way to fetch a branch — so
 * without this, CI on a pull request measures the last release and reports it
 * as the branch being green. The `.stub` renaming and the placeholder
 * substitution are reproduced here because that is what the scaffolder did to
 * the files being replaced.
 */
function overlaySource(string $source, string $pluginDir, string $name): void
{
    $replacements = scaffolderReplacements($name);
    $skip = ['.git', 'node_modules', 'bin', '.github', 'package-lock.json', 'yarn.lock', 'README.md'];

    $iterator = new RecursiveIteratorIterator(
        new RecursiveCallbackFilterIterator(
            new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS),
            fn (SplFileInfo $file): bool => ! in_array($file->getFilename(), $skip, true)
        ),
        RecursiveIteratorIterator::SELF_FIRST
    );

    $copied = 0;

    foreach ($iterator as $file) {
        $relative = substr($file->getPathname(), strlen($source) + 1);

        // Filenames carry placeholders too — the main file is literally named
        // %plugin_name%.php — and app/ ships as .stub.
        $relative = str_replace(array_keys($replacements), array_values($replacements), $relative);
        $relative = preg_replace('/\.stub$/', '.php', $relative) ?? $relative;

        $target = $pluginDir.'/'.$relative;

        if ($file->isDir()) {
            is_dir($target) || mkdir($target, 0755, true);

            continue;
        }

        $contents = (string) file_get_contents($file->getPathname());

        // Binary files carry no placeholders and must not be rewritten.
        if (preg_match('//u', $contents) === 1) {
            $contents = str_replace(array_keys($replacements), array_values($replacements), $contents);
        }

        is_dir(dirname($target)) || mkdir(dirname($target), 0755, true);
        file_put_contents($target, $contents);
        $copied++;
    }

    echo "  \033[2m→ overlaid {$copied} files from the commit under test\033[0m\n";
}

echo "\n\033[1m=== plugin-default — install check ===\033[0m\n";
echo "\033[2m{$baseUrl} · plugin {$name}\033[0m\n";

ensureActiveTheme();

$command = makePluginCommand();
echo "  \033[2m→ scaffolding with {$command}\033[0m\n";

// The scaffolder fetches the starter from GitHub, so this step can fail
// for reasons that have nothing to do with the commit under test — a rate
// limit, an archive that times out. One retry, then the failure stands:
// retrying forever would turn a broken starter into a slow green build.
$scaffold = ['code' => 1, 'out' => ''];

foreach ([1, 2] as $attempt) {
    $scaffold = run('php artisan '.$command.' '.escapeshellarg($name)
        .' --plugin-author=Pollora --plugin-description='.escapeshellarg('Plugin under test')
        .' --plugin-version=1.0.0 --force --no-interaction');

    if ($scaffold['code'] === 0) {
        break;
    }

    if ($attempt === 1) {
        echo "  \033[33m→ scaffolding failed, retrying once\033[0m\n";
        sleep(10);
    }
}

if ($scaffold['code'] !== 0) {
    fwrite(STDERR, "\n\033[31mScaffolding failed twice:\033[0m\n{$scaffold['out']}\n\n");
    exit(1);
}

$pluginDir = pluginsDirectory().'/'.$name;

if (! is_dir($pluginDir)) {
    fwrite(STDERR, "\n\033[31mThe scaffolder reported success but {$pluginDir} does not exist.\033[0m\n\n");
    exit(1);
}

if ($source !== null) {
    overlaySource($source, $pluginDir, $name);

    if (is_file($pluginDir.'/package.json')) {
        echo "  \033[2m→ rebuilding assets\033[0m\n";
        run('cd '.escapeshellarg($pluginDir).' && npm install --no-audit --no-fund && npm run build');
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// Checks
// ─────────────────────────────────────────────────────────────────────────────

section('Activation — the guard in the main file, on the framework this site runs');

test('WordPress lists the generated plugin', function () use ($name) {
    $listed = run('wp plugin list --field=name');

    return in_array($name, explode("\n", $listed['out']), true)
        ? true
        : "wp plugin list does not show {$name}: {$listed['out']}";
});

$activation = run('wp plugin activate '.escapeshellarg($name));

test('Activating the plugin does not fatal', function () use ($activation) {
    if ($activation['code'] !== 0) {
        return "activation failed — this is the shape the 13.4 regression took: {$activation['out']}";
    }

    foreach (['Fatal error', 'Uncaught', 'Parse error'] as $leak) {
        if (str_contains($activation['out'], $leak)) {
            return "activation printed a {$leak}: {$activation['out']}";
        }
    }

    return true;
});

test('WordPress still boots with the plugin active', function () {
    $result = run('wp eval '.escapeshellarg('echo "loaded";'));

    if ($result['code'] !== 0) {
        return "WordPress could not boot with the plugin active: {$result['out']}";
    }

    return str_contains($result['out'], 'loaded')
        ? true
        : "unexpected output from a booted site: {$result['out']}";
});

test('The plugin reports itself active', function () use ($name) {
    return wpEval('echo is_plugin_active('.var_export($name.'/'.$name.'.php', true).') ? "yes" : "no";') === 'yes'
        ? true
        : "is_plugin_active() says no — the guard left {$name} inert";
});

test('The constants the plugin defines are available', function () use ($name) {
    $constant = strtoupper(str_replace('-', '_', $name)).'_VERSION';

    return wpEval('echo defined('.var_export($constant, true).') ? "yes" : "no";') === 'yes'
        ? true
        : "{$constant} is undefined — the main file exited before defining it";
});

section('Scaffolding — what the .stub renaming produced');

test('The main plugin class file is a .php, not a .stub', function () use ($pluginDir) {
    $leftover = glob($pluginDir.'/app/*.stub') ?: [];

    if ($leftover !== []) {
        return 'the scaffolder left '.implode(', ', array_map('basename', $leftover)).' unrenamed';
    }

    $classes = glob($pluginDir.'/app/*.php') ?: [];

    return $classes !== []
        ? true
        : 'no class file reached app/ — the scaffolder produced nothing there';
});

test('No placeholder survived into the generated plugin', function () use ($pluginDir) {
    $offenders = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($pluginDir, FilesystemIterator::SKIP_DOTS)
    );

    foreach ($iterator as $file) {
        $path = $file->getPathname();

        if (! $file->isFile() || str_contains($path, '/node_modules/') || str_contains($path, '/vendor/')) {
            continue;
        }

        $contents = (string) file_get_contents($path);

        if (preg_match('/%[a-zA-Z_]+%/', $contents) === 1 || preg_match('/%[a-zA-Z_]+%/', basename($path)) === 1) {
            $offenders[] = substr($path, strlen($pluginDir) + 1);
        }
    }

    return $offenders === []
        ? true
        : 'these reached the user with placeholders intact: '.implode(', ', $offenders);
});

section('Rendering — the site is unharmed');

test('The homepage still answers without a Laravel error page', function () use ($baseUrl) {
    $response = http($baseUrl.'/');

    if ($response['status'] >= 500) {
        return "the homepage answered {$response['status']} with the plugin active";
    }

    foreach (['Whoops', 'ViewException', 'Stack trace'] as $leak) {
        if (str_contains($response['body'], $leak)) {
            return "the homepage leaked a Laravel error page: {$leak}";
        }
    }

    return true;
});

// ─────────────────────────────────────────────────────────────────────────────

$total = $passed + $failed;
echo "\n";
echo $failed === 0
    ? "\033[32m{$total} checks, all passed.\033[0m\n\n"
    : "\033[31m{$total} checks, {$failed} failed.\033[0m\n\n";

exit($failed === 0 ? 0 : 1);

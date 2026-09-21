<?php

declare(strict_types=1);

/**
 * The registration guard in the main plugin file, exercised against every
 * Pollora version this template can land on.
 *
 * Why this suite exists: `make:plugin` downloads this repository's latest tag
 * whatever framework the site is running, so one file has to work on all of
 * them. It did not: calling `pollora_register()` unconditionally took every
 * site still on framework 13.4 down with a fatal — the same defect
 * theme-default carried, found only because the theme was looked at. It was
 * fixed and tagged 0.8 without anything being written that would catch it
 * coming back.
 *
 * Each case runs in its own interpreter with a stubbed environment, because
 * what the guard branches on — `function_exists('pollora_register')`, a
 * loadable class — is global state that cannot be reset inside one process.
 *
 * The stubs stand in for the framework; they do not prove the real enum case
 * or the real service still carry these names. That is what the install job
 * in .github/workflows/tests.yml measures, against a real site.
 */

require_once dirname(__DIR__).'/replacements.php';

const TEST_SLUG = 'my-plugin';

/**
 * The template's main file, with the scaffolder's substitutions applied.
 *
 * The file is named `%plugin_name%.php` and its body defines constants built
 * from `%PLUGIN_NAME%`: nothing about it is valid PHP until it is scaffolded,
 * so the thing under test is the substituted result.
 */
function scaffoldedPluginFile(): string
{
    $source = pluginPath('%plugin_name%.php');

    if (! is_file($source)) {
        throw new \RuntimeException('the template has no %plugin_name%.php — the main file was renamed');
    }

    $replacements = scaffolderReplacements(TEST_SLUG);

    return str_replace(
        array_keys($replacements),
        array_values($replacements),
        (string) file_get_contents($source)
    );
}

/**
 * Compose one registration scenario and run it.
 *
 * @param  bool  $helper  whether `pollora_register()` exists (framework >= 13.32)
 * @param  bool  $registrar  whether the 13.4 PluginRegistrar service is loadable
 * @param  bool  $laravel  whether `app()` exists at all
 * @param  bool  $wordpress  whether ABSPATH is defined (false = direct access)
 * @return array{code: int, out: string}
 */
function registrationCase(bool $helper, bool $registrar, bool $laravel = true, bool $wordpress = true): array
{
    $plugin = tempnam(sys_get_temp_dir(), 'plugin-main-').'.php';
    file_put_contents($plugin, scaffoldedPluginFile());

    $code = "namespace Pollora\\Modules\\Domain\\Enums {\n"
        ."    enum ModuleType: string { case Theme = 'theme'; case Plugin = 'plugin'; }\n"
        ."}\n\n";

    if ($registrar) {
        $code .= "namespace Pollora\\Plugin\\Application\\Services {\n"
            ."    final class PluginRegistrar\n"
            ."    {\n"
            ."        public function register(string \$slug, string \$dir): void\n"
            ."        {\n"
            ."            \$GLOBALS['trace'][] = 'registrar::register:'.\$slug;\n"
            ."        }\n"
            ."    }\n"
            ."}\n\n";
    }

    $code .= "namespace {\n";
    $code .= "    \$GLOBALS['trace'] = [];\n";

    // Stand-ins for the WordPress functions the main file calls before it
    // reaches the registration guard. They exist whenever ABSPATH does, so
    // their absence here would be an artefact of the harness, not a finding.
    if ($wordpress) {
        $code .= "    define('ABSPATH', sys_get_temp_dir().'/');\n"
            ."    function plugin_dir_path(string \$file): string { return dirname(\$file).'/'; }\n"
            ."    function plugin_dir_url(string \$file): string { return 'https://example.test/plugins/'.basename(dirname(\$file)).'/'; }\n";
    }

    if ($laravel) {
        $code .= "    function app(?string \$abstract = null): object\n"
            ."    {\n"
            ."        return new \$abstract();\n"
            ."    }\n";
    }

    if ($helper) {
        $code .= "    function pollora_register(\\Pollora\\Modules\\Domain\\Enums\\ModuleType \$type, ?string \$slug = null, ?string \$dir = null): void\n"
            ."    {\n"
            ."        \$GLOBALS['trace'][] = 'pollora_register:'.\$type->value.':'.(\$slug ?? '-');\n"
            ."    }\n";
    }

    $code .= '    require '.var_export($plugin, true).";\n";
    $code .= "    echo implode(',', \$GLOBALS['trace']);\n";
    $code .= "    echo '|'.(defined('".strtoupper(str_replace('-', '_', TEST_SLUG))."_VERSION') ? 'constants' : 'no-constants');\n";
    $code .= "}\n";

    $result = phpRun($code);
    unlink($plugin);

    return $result;
}

function checkRegistrationGuard(): void
{
    section('Registration guard — the main file on every framework it can meet');

    test('Framework >= 13.32: registers through pollora_register()', function () {
        $result = registrationCase(helper: true, registrar: true);

        if ($result['code'] !== 0) {
            return "fatal on the current framework: {$result['out']}";
        }

        return str_starts_with($result['out'], 'pollora_register:plugin:'.TEST_SLUG)
            ? true
            : "expected pollora_register:plugin:".TEST_SLUG.", got '{$result['out']}'";
    });

    test('Framework >= 13.32: does not also call the registrar', function () {
        $result = registrationCase(helper: true, registrar: true);

        return str_contains($result['out'], 'registrar::register')
            ? 'the plugin registered twice — pollora_register() and the registrar both ran'
            : true;
    });

    test('Framework 13.4: falls back to the registrar service', function () {
        $result = registrationCase(helper: false, registrar: true);

        if ($result['code'] !== 0) {
            return "fatal on framework 13.4 — this is the regression that took stable sites down: {$result['out']}";
        }

        return str_starts_with($result['out'], 'registrar::register:'.TEST_SLUG)
            ? true
            : "expected registrar::register:".TEST_SLUG.", got '{$result['out']}'";
    });

    test('Neither is available: leaves the plugin unregistered, without a fatal', function () {
        $result = registrationCase(helper: false, registrar: false);

        if ($result['code'] !== 0) {
            return "fatal when Pollora is absent — WordPress cannot even list the plugin: {$result['out']}";
        }

        return str_starts_with($result['out'], '|')
            ? true
            : "expected no registration attempt, got '{$result['out']}'";
    });

    test('Registrar present but no container: does not fatal', function () {
        $result = registrationCase(helper: false, registrar: true, laravel: false);

        if ($result['code'] !== 0) {
            return "fatal when app() is missing: {$result['out']}";
        }

        return str_starts_with($result['out'], '|')
            ? true
            : "expected no registration attempt, got '{$result['out']}'";
    });

    test('The plugin constants are defined before registration', function () {
        $result = registrationCase(helper: true, registrar: true);

        return str_ends_with($result['out'], '|constants')
            ? true
            : 'the *_VERSION, *_PLUGIN_FILE, *_PLUGIN_DIR and *_PLUGIN_URL constants were not defined';
    });

    // The one guard that has nothing to do with Pollora, and the one whose
    // absence is a disclosure bug rather than a fatal.
    test('Loaded outside WordPress: exits without running anything', function () {
        $result = registrationCase(helper: true, registrar: true, wordpress: false);

        return $result['out'] === ''
            ? true
            : "the ABSPATH guard let the file run: '{$result['out']}'";
    });
}

/**
 * Every PHP file the plugin ships has to parse once it is scaffolded.
 *
 * A parse error here is not a caught exception: WordPress serves a white page
 * and the admin cannot reach the screen that would deactivate the plugin.
 *
 * `.stub` files count: the scaffolder renames them to `.php` and they become
 * the plugin's classes.
 */
function checkSyntax(): void
{
    section('Syntax — every PHP file parses once scaffolded');

    $files = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(pluginPath(), FilesystemIterator::SKIP_DOTS)
    );

    foreach ($iterator as $file) {
        $path = $file->getPathname();

        // bin/ is stripped by the scaffolder, so it is not part of what ships —
        // and this very suite lives there, naming placeholders in its comments.
        if (str_contains($path, '/node_modules/') || str_contains($path, '/.git/') || str_contains($path, '/bin/')) {
            continue;
        }

        // Blade templates end in .php but are not PHP.
        if (str_ends_with($path, '.blade.php')) {
            continue;
        }

        if (in_array($file->getExtension(), ['php', 'stub'], true)) {
            $files[] = $path;
        }
    }

    sort($files);

    $replacements = scaffolderReplacements(TEST_SLUG);

    test(count($files).' PHP files parse', function () use ($files, $replacements) {
        if ($files === []) {
            return 'no PHP file was found — the walk is looking in the wrong place';
        }

        $broken = [];

        foreach ($files as $file) {
            $scaffolded = str_replace(
                array_keys($replacements),
                array_values($replacements),
                (string) file_get_contents($file)
            );

            $temp = tempnam(sys_get_temp_dir(), 'plugin-lint-').'.php';
            file_put_contents($temp, $scaffolded);
            $check = shell_exec(escapeshellarg(PHP_BINARY).' -l '.escapeshellarg($temp).' 2>&1');
            unlink($temp);

            if (! is_string($check) || ! str_contains($check, 'No syntax errors')) {
                $broken[] = basename($file).': '.trim(str_replace($temp, basename($file), (string) $check));
            }
        }

        return $broken === [] ? true : implode(' | ', $broken);
    });

    // A token the scaffolder does not know is substituted by nothing: it ships
    // to the user as a literal "%plugin_slug%" in the file that needed it.
    test('No file uses a placeholder the scaffolder does not substitute', function () use ($files, $replacements) {
        $known = array_keys($replacements);
        $unknown = [];

        foreach ($files as $file) {
            $candidates = [(string) file_get_contents($file), basename($file)];

            foreach ($candidates as $haystack) {
                if (preg_match_all('/%[a-zA-Z_]+%/', $haystack, $matches) === 0) {
                    continue;
                }

                foreach (array_unique($matches[0]) as $token) {
                    if (! in_array($token, $known, true)) {
                        $unknown[] = basename($file).": {$token}";
                    }
                }
            }
        }

        return $unknown === []
            ? true
            : implode(', ', array_unique($unknown)).' — the scaffolder leaves these in place verbatim';
    });
}

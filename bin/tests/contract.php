<?php

declare(strict_types=1);

/**
 * What this template owes the scaffolder and the sites it lands on.
 *
 * The plugin is developed as `pollora-demo-plugin` and turned into a template
 * by bin/package-plugin.sh: names become placeholders, and the classes under
 * app/ become `.stub` files the scaffolder renames back. Every one of those
 * conventions is load-bearing and none of them was checked.
 */

require_once dirname(__DIR__).'/replacements.php';

/**
 * The development names that must never survive packaging.
 *
 * theme-default shipped a tag where a whole new directory slipped past its
 * equivalent rules, so every generated theme registered its blocks under the
 * template's own name. The rules here are broader, which is a reason to check
 * rather than a reason not to.
 */
const CODE_NAMES = ['pollora-demo-plugin', 'PolloraDemoPlugin', 'pollora_demo_plugin', 'POLLORA_DEMO_PLUGIN'];

/**
 * @return array<int, string>  every shipped file, repository-relative
 */
function shippedFiles(): array
{
    $files = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(pluginPath(), FilesystemIterator::SKIP_DOTS)
    );

    foreach ($iterator as $file) {
        $path = $file->getPathname();

        // bin/ is where this suite and the packaging script live; the
        // scaffolder is asked to strip it, and neither belongs in a user's
        // plugin. README.md documents the packaging workflow.
        foreach (['/.git/', '/node_modules/', '/bin/', '/README.md'] as $exempt) {
            if (str_contains($path, $exempt)) {
                continue 2;
            }
        }

        if ($file->isFile()) {
            $files[] = substr($path, strlen(pluginPath()) + 1);
        }
    }

    sort($files);

    return $files;
}

function checkPackaging(): void
{
    section('Packaging — no development code name survives');

    $files = shippedFiles();

    test('No file keeps a development code name', function () use ($files) {
        $offenders = [];

        foreach ($files as $relative) {
            $contents = (string) file_get_contents(pluginPath($relative));

            foreach (CODE_NAMES as $name) {
                if (str_contains($contents, $name) || str_contains($relative, $name)) {
                    $offenders[] = "{$relative} ({$name})";

                    break;
                }
            }
        }

        return $offenders === []
            ? true
            : 'the code name survived packaging in '.implode(', ', $offenders);
    });

    test('The main file is still named after the placeholder', function () {
        return is_file(pluginPath('%plugin_name%.php'))
            ? true
            : 'there is no %plugin_name%.php — the scaffolder has nothing to rename into the plugin file';
    });

    test('The WordPress plugin header carries placeholders, not literals', function () {
        $header = (string) file_get_contents(pluginPath('%plugin_name%.php'));

        $expected = [
            'Plugin Name: %plugin_name%',
            'Plugin URI: %plugin_uri%',
            'Description: %plugin_description%',
            'Version: %plugin_version%',
            'Author: %plugin_author%',
            'Author URI: %plugin_author_uri%',
        ];

        $missing = array_values(array_filter($expected, fn (string $line): bool => ! str_contains($header, $line)));

        return $missing === []
            ? true
            : 'the header lost '.implode(', ', $missing)."— generated plugins will carry this template's own identity";
    });

    // WordPress reads the header of every file in the plugins directory. A
    // class file that still says "Plugin Name:" would be listed as a second,
    // broken plugin.
    test('Only the main file declares a plugin header', function () use ($files) {
        $extra = [];

        foreach ($files as $relative) {
            if ($relative === '%plugin_name%.php') {
                continue;
            }

            if (str_contains((string) file_get_contents(pluginPath($relative)), 'Plugin Name:')) {
                $extra[] = $relative;
            }
        }

        return $extra === []
            ? true
            : implode(', ', $extra).' declare a plugin header and would be listed as separate plugins';
    });
}

/**
 * The classes under app/ are shipped as `.stub` and renamed to `.php` by the
 * scaffolder. A `.php` committed there is copied through verbatim, which puts
 * a file holding `namespace %plugin_namespace%;` into a live plugin — a parse
 * error WordPress answers with a white page.
 */
function checkStubConvention(): void
{
    section('Stub convention — the classes the scaffolder renames');

    test('Every file under app/ is a .stub', function () {
        $wrong = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(pluginPath('app'), FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() !== 'stub') {
                $wrong[] = substr($file->getPathname(), strlen(pluginPath()) + 1);
            }
        }

        return $wrong === []
            ? true
            : implode(', ', $wrong).' would ship unrenamed, placeholders and all';
    });

    test('The main plugin class is where the template says it is', function () {
        return is_file(pluginPath('app/%plugin_namespace%Plugin.stub'))
            ? true
            : 'app/%plugin_namespace%Plugin.stub is missing — the scaffolder has no class to generate';
    });

    test('Every stub under app/ declares the placeholder namespace', function () {
        $wrong = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(pluginPath('app'), FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'stub') {
                continue;
            }

            $relative = substr($file->getPathname(), strlen(pluginPath()) + 1);
            $source = (string) file_get_contents($file->getPathname());

            // The declaration line itself, not merely the token somewhere in
            // the file: a stub that keeps `use Plugin\%plugin_namespace%\X;`
            // while hardcoding its own namespace would pass a looser check
            // and still land in the wrong namespace once generated.
            if (preg_match('/^namespace\s+([^;]+);/m', $source, $matches) !== 1) {
                $wrong[] = "{$relative} declares no namespace";

                continue;
            }

            if (! str_contains($matches[1], '%plugin_namespace%')) {
                $wrong[] = "{$relative} declares {$matches[1]}";
            }
        }

        return $wrong === []
            ? true
            : implode(', ', $wrong).' — a hardcoded namespace will not match the generated plugin';
    });
}

/**
 * Blade resolves an @extends or @include at render time, so a moved partial is
 * a 500 on the page that uses it and nowhere else.
 */
function checkViewReferences(): void
{
    section('View references — every @extends and @include resolves');

    $views = [];

    if (is_dir(pluginPath('resources/views'))) {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(pluginPath('resources/views'), FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if (str_ends_with($file->getPathname(), '.blade.php')) {
                $views[] = $file->getPathname();
            }
        }
    }

    sort($views);

    test(count($views).' Blade files reference only views that exist', function () use ($views) {
        if ($views === []) {
            return 'no Blade file was found — the walk is looking in the wrong place';
        }

        $broken = [];

        foreach ($views as $view) {
            $source = (string) file_get_contents($view);

            if (preg_match_all('/@(?:extends|include|includeIf|includeWhen|includeFirst)\(\s*[\'"]([^\'"]+)[\'"]/', $source, $matches) === 0) {
                continue;
            }

            foreach ($matches[1] as $referenced) {
                // Views are addressed through the plugin's own namespace once
                // registered; only the local part can be checked from here.
                $local = str_contains($referenced, '::') ? explode('::', $referenced, 2)[1] : $referenced;
                $path = pluginPath('resources/views/'.str_replace('.', '/', $local).'.blade.php');

                if (! is_file($path)) {
                    $broken[] = basename($view)." → {$referenced}";
                }
            }
        }

        return $broken === [] ? true : implode(', ', $broken);
    });
}

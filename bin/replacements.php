<?php

declare(strict_types=1);

/**
 * The placeholders the scaffolder substitutes when it generates a plugin.
 *
 * Mirrors MakePluginCommand::getReplacements() in the framework. Two things
 * here need it, and neither can go and ask the framework:
 *
 *  - the contract tests, which lint what the user receives rather than the
 *    template — a .stub carrying `namespace %plugin_namespace%;` does not
 *    parse, and the main file is literally named `%plugin_name%.php`;
 *  - bin/ci/check-install.php, which overlays the commit under test onto a
 *    plugin the scaffolder generated from the published tag, so CI measures
 *    the branch and not what is already released.
 *
 * Drift against the framework is caught by the contract test "No file uses a
 * placeholder the scaffolder does not substitute": a key that disappears here
 * or a token that appears in the template without a key both fail it.
 *
 * @return array<string, string>  placeholder => a plausible substituted value
 */
function scaffolderReplacements(string $name = 'my-plugin'): array
{
    $studly = str_replace(' ', '', ucwords(str_replace(['-', '_'], ' ', $name)));
    $function = strtolower(str_replace('-', '_', $name));

    return [
        '%plugin_name%' => $name,
        '%plugin_function_name%' => $function,
        '%PLUGIN_FUNCTION_NAME%' => strtoupper($function),
        '%PLUGIN_NAME%' => strtoupper($function),
        '%plugin_author%' => 'Pollora',
        '%plugin_author_uri%' => 'https://pollora.dev',
        '%plugin_uri%' => 'https://pollora.dev',
        '%plugin_description%' => 'Plugin under test',
        '%plugin_version%' => '1.0.0',
        '%plugin_namespace%' => $studly,
        '%plugin_slug%' => $name,
        '%plugin_basename%' => $name.'/'.$name.'.php',
    ];
}

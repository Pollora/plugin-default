#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Contract tests for the plugin template.
 *
 * Usage:  php bin/tests/run.php
 *
 * No Composer install, no database, no site: everything here is a property of
 * the files in this repository, checked in a second or two so it can run on
 * every push. What it cannot see — that the plugin actually activates on a
 * real WordPress — is the install job's part, in .github/workflows/tests.yml.
 */

require __DIR__.'/lib.php';
require __DIR__.'/compat.php';
require __DIR__.'/contract.php';

echo "\n\033[1m=== plugin-default — contract tests ===\033[0m\n";
echo "\033[2m".pluginPath()."\033[0m\n";

checkRegistrationGuard();
checkPackaging();
checkStubConvention();
checkViewReferences();
checkSyntax();

exit(summary());

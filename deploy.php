<?php

namespace Deployer;

require 'recipe/laravel.php';

// Config
$token = strtok(file_get_contents(getcwd() . '/.token'), "\n");

set('bin/composer', '/usr/local/bin/composer');
set('composer_options', '--ignore-platform-reqs --verbose --prefer-dist --no-progress --no-interaction --no-dev --optimize-autoloader');
set('repository', "https://jcharika:$token@github.com/jcharika/magetsi-agents-wa.git");

add('shared_files', ['database/database.sqlite']);
add('shared_dirs', []);
add('writable_dirs', []);

// Hosts

host('agents-test.magetsi.co.zw')
    ->setHostname('ec2-54-152-213-126.compute-1.amazonaws.com')
    ->set('http_user', 'agents-test')
    ->set('remote_user', 'agents-test')
    ->set('deploy_path', '/var/www/agents-test.magetsi.co.zw');

//host('agents.magetsi.co.zw')
//    ->set('remote_user', 'deployer')
//    ->set('deploy_path', '~/magetsi-agents');

// Hooks

after('deploy:failed', 'deploy:unlock');
after('deploy:success', 'artisan:optimize');
after('push', 'artisan:optimize');

// ── Custom Tasks ────────────────────────────────────────────

desc('Update a .env variable on the remote server');
task('env:update', function () {
    $deployPath = get('deploy_path');
    $envPath = "$deployPath/shared/.env";

    // 1. Read the remote .env file
    $envContent = run("cat $envPath");

    if (empty(trim($envContent))) {
        warning('Remote .env file is empty or does not exist.');
        return;
    }

    // 2. Parse into key=value pairs (skip comments and blank lines)
    $lines = explode("\n", $envContent);
    $envVars = [];
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }
        if (str_contains($line, '=')) {
            [$key] = explode('=', $line, 2);
            $envVars[] = trim($key);
        }
    }

    if (empty($envVars)) {
        warning('No environment variables found in .env file.');
        return;
    }

    // 3. Display numbered list
    writeln('');
    writeln('<info>── Remote .env variables ──</info>');
    writeln('');
    foreach ($envVars as $i => $key) {
        writeln(sprintf('  <comment>[%d]</comment> %s', $i + 1, $key));
    }
    writeln(sprintf('  <comment>[%d]</comment> <fg=green>+ Add a new variable</>', count($envVars) + 1));
    writeln('');

    // 4. Ask user to select a variable
    $choice = ask('Select a variable number: ');
    $choice = (int) $choice;

    if ($choice < 1 || $choice > count($envVars) + 1) {
        warning('Invalid selection.');
        return;
    }

    if ($choice === count($envVars) + 1) {
        // Adding a new variable
        $key = ask('Enter the new variable name: ');
        $key = strtoupper(trim($key));
        if (empty($key)) {
            warning('Variable name cannot be empty.');
            return;
        }
    } else {
        $key = $envVars[$choice - 1];
    }

    // 5. Show current value (if exists)
    $currentValue = run("grep '^$key=' $envPath | head -1 | cut -d'=' -f2-") ?? '';
    if (!empty(trim($currentValue))) {
        writeln(sprintf('  Current value: <comment>%s</comment>', trim($currentValue)));
    }

    // 6. Ask for new value
    $newValue = ask("Enter new value for <info>$key</info>: ");

    // 7. Confirm
    $confirm = askConfirmation(sprintf(
        'Set <info>%s</info>=<comment>%s</comment> — proceed?',
        $key,
        $newValue
    ), true);

    if (!$confirm) {
        writeln('<comment>Aborted.</comment>');
        return;
    }

    // 8. Update or append the variable in the remote .env
    // Escape special characters for sed
    $escapedValue = str_replace(['/', '&', '\\'], ['\\/', '\\&', '\\\\'], $newValue);

    $exists = run("grep -c '^$key=' $envPath || true");
    if ((int) trim($exists) > 0) {
        run("sed -i 's/^$key=.*/$key=$escapedValue/' $envPath");
    } else {
        run("echo '$key=$newValue' >> $envPath");
    }

    writeln(sprintf('<info>✓</info> Updated <info>%s</info> in remote .env', $key));

    // 9. Clear optimizations then re-optimize
    writeln('');
    writeln('<info>Running optimize:clear...</info>');
    run("cd $deployPath/current && {{bin/php}} artisan optimize:clear");

    writeln('<info>Running optimize...</info>');
    run("cd $deployPath/current && {{bin/php}} artisan optimize");

    writeln('');
    writeln('<info>✓ Done.</info>');
});

<?php

namespace Deployer;

require 'recipe/laravel.php';

// Config
set('bin/composer', '/usr/local/bin/composer');
set('composer_options', '--ignore-platform-reqs --verbose --prefer-dist --no-progress --no-interaction --no-dev --optimize-autoloader');
set('repository', 'https://jcharika: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@github.com/jcharika/magetsi-agents-wa.git');

add('shared_files', ['database/database.sqlite']);
add('shared_dirs', []);
add('writable_dirs', []);

// Hosts

host('agents-test.magetsi.co.zw')
    ->setHostname('ec2-54-152-213-126.compute-1.amazonaws.com')
    ->set('http_user', 'agents-test')
    ->set('remote_user', 'agents-test')
    ->set('deploy_path', '/var/www/agents-test.magetsi.co.zw');

host('wa.magetsi.co.zw')
    ->setHostname('44.194.102.193')
    ->set('http_user', 'wa')
    ->set('remote_user', 'wa') // hf79DoTGOkfm4VxTBtFwJ2HCTf8QiUPZwt6oPPhp
    ->set('deploy_path', '/var/www/wa.magetsi.co.zw');

// Hooks

after('deploy:failed', 'deploy:unlock');
after('deploy:success', 'artisan:optimize');

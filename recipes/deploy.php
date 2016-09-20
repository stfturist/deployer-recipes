<?php

/**
 * Common parameters.
 */
set('user', 'deploy');
set('group', 'www-data');

/**
 * Set right user and group on root files.
 */
task('deploy:groupify_root', function () {
    run("test -d {{deploy_path}}/ && sudo chown -R {{user}}:{{group}} {{deploy_path}}/");
})->desc('Set right permissions on root directory');

/**
 * Set right user and group on releases files.
 */
task('deploy:groupify_releases', function () {
    run("test -d {{deploy_path}}/releases/ && sudo chown -R {{user}}:{{group}} {{deploy_path}}/releases/");
})->desc('Set right permissions on releases directory');

/**
 * Set right user and group on shared files.
 */
task('deploy:groupify_shared', function () {
    run("test -d {{deploy_path}}/shared/ && sudo chown -R {{user}}:{{group}} {{deploy_path}}/shared/");
})->desc('Set right permissions on shared files');

/**
 * Update code.
 */
task('deploy:update_code', function () {
    $repository = trim(get('repository'));
    $branch = env('branch') ?: 'master';
    $git = env('bin/git');
    $ci = getenv('CI_BUILD_REF') ?: '';
    $verbose = '';
    $tarballPath = '/tmp/{{release_name}}.gz';

    if (isVerbose()) {
        $verbose = '-v';
    }

    // Extract from git to tarball.
    if (!empty($ci)) {
        runLocally("git archive --format=tar $verbose HEAD | bzip2 > $tarballPath");
    } else {
        runLocally("git archive --remote=$repository --format=tar $verbose $branch | bzip2 > $tarballPath");
    }

    // Upload tarball.
    upload($tarballPath, $tarballPath);

    // Extract tarball.
    run("mkdir -p {{deploy_path}}/tar/$branch");
    run("tar -xf $tarballPath -C {{deploy_path}}/tar/$branch");
    run("find {{deploy_path}}/tar/$branch/ -mindepth 1 -maxdepth 1 -exec mv -t {{release_path}}/ -- {} +");

    // Cleanup.
    run("rm -rf {{deploy_path}}/tar");
    run("rm $tarballPath");
})->desc('Updating code');

/**
 * Add before and after hooks.
 */
before('deploy:update_code', 'deploy:groupify_root');
before('rollback', 'deploy:groupify_releases');
after('deploy:update_code', 'deploy:groupify_releases');
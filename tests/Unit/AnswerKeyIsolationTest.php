<?php

/*
 * The handoff: test/ holds the answer key and the app must never read it.
 * Nothing outside tests/ may mention the fixture folder or its files.
 */
it('never reads the answer key from application code', function () {
    $root = dirname(__DIR__, 2);
    $forbidden = '/Fixtures|expected_system_findings|answer_key|workflow_trace|chat_tests/';
    $offenders = [];

    foreach (['app', 'config', 'database', 'routes', 'resources/views'] as $dir) {
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator("{$root}/{$dir}", FilesystemIterator::SKIP_DOTS));
        foreach ($files as $file) {
            if ($file->getExtension() === 'php' && preg_match($forbidden, file_get_contents($file->getPathname()))) {
                $offenders[] = substr($file->getPathname(), strlen($root) + 1);
            }
        }
    }

    expect($offenders)->toBe([]);
});

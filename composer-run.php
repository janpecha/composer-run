<?php

require __DIR__ . '/src/Runner.php';

$config = [];

if (is_file(__DIR__ . '/.config.php')) {
	$config = require __DIR__ . '/.config.php';
}

$config = array_merge([
	'composerExecutable' => 'composer',
	'tempDirectory' => __DIR__ . '/.tmp',
], $config);

$args = $_SERVER['argv'];
array_shift($args);

exit((new JP\ComposerRun\Runner(
	$config['composerExecutable'],
	$config['tempDirectory']
))->run(getcwd(), $args));

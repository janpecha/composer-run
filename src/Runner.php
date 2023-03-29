<?php

	namespace JP\PHPStanRunner;


	class Runner
	{
		/** @var string */
		private $composerExecutable;

		/** @var string */
		private $tempDirectory;


		public function __construct(
			string $composerExecutable,
			string $tempDirectory
		)
		{
			$this->composerExecutable = $composerExecutable;
			$this->tempDirectory = $tempDirectory;
		}


		/**
		 * @param  string[] $args
		 */
		public function run(
			string $cwd,
			array $args
		): int
		{
			$cwd = getcwd();
			$phpstanPackages = $this->fetchExtensions($cwd . '/composer.json');

			if (count($phpstanPackages) > 0) {
				$phpstanPackages[] = 'phpstan/extension-installer';
			}

			$phpstanPackages[] = 'phpstan/phpstan';

			$phpstanPackages = array_unique($phpstanPackages);
			sort($phpstanPackages, SORT_STRING);

			echo "[PACKAGES]\n";
			echo '- ' . implode("\n- ", $phpstanPackages), "\n";
			$phpstanBinary = $this->initInstallation($phpstanPackages);
			$exitCode = 0;

			echo "[RUN PHPSTAN]\n";
			array_unshift($args, $phpstanBinary);
			$this->passthruCommand(...$args);

			return $exitCode;
		}


		private function initInstallation(array $phpstanPackages)
		{
			$key = md5(serialize($phpstanPackages));
			$installationDirectory = $this->tempDirectory . '/' . $key;
			@mkdir($installationDirectory, 0777, TRUE);
			$lastUpdated = NULL;
			$lastUpdatedFile = $installationDirectory . '/.last-updated';

			if (is_file($lastUpdatedFile)) {
				$lastUpdated = \DateTimeImmutable::createFromFormat(
					'Y-m-d H:i:s',
					trim(file_get_contents($lastUpdatedFile)),
					new \DateTimeZone('UTC')
				);
			}

			$installPackages = TRUE;
			$currentDate = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));

			if ($lastUpdated !== NULL) {
				echo "Last updated: ", $lastUpdated->format('Y-m-d H:i:s'), "\n";
				$lastUpdatedDiff = (int) $lastUpdated->diff($currentDate)->format('%a');
				$installPackages = $lastUpdatedDiff > 1;
			}

			if ($installPackages) {
				echo "[INIT INSTALLATION]\n";
				file_put_contents($installationDirectory . '/composer.json', "{}\n");
				$this->execCommand(
					$this->composerExecutable,
					'config',
					'--no-interaction',
					'--working-dir',
					$installationDirectory,
					'allow-plugins',
					'true'
				);

				$exitCode = $this->passthruCommand(
					$this->composerExecutable,
					'require',
					'--dev',
					'--sort-packages',
					'--optimize-autoloader',
					'--no-interaction',
					'--working-dir',
					$installationDirectory,
					...$phpstanPackages
				);

				if ($exitCode !== 0) {
					throw new \RuntimeException('Installation of PHPStan packages failed.');
				}

				file_put_contents($lastUpdatedFile, $currentDate->format('Y-m-d H:i:s') . "\n");
			}

			return $installationDirectory . '/vendor/bin/phpstan';
		}


		/**
		 * @return string[]
		 */
		private function fetchExtensions(string $composerFile): array
		{
			$extensions = [];
			$result = $this->execCommand(
				$this->composerExecutable,
				'config',
				'--no-interaction',
				'--json',
				'-f',
				$composerFile,
				'extra.phpstan-extensions',
			);

			if ($result['exitCode'] === 0 && $result['output'] !== '') {
				return json_decode($result['output'], TRUE);
			}

			return [];
		}


		/**
		 * @param  string ...$arg
		 */
		private function passthruCommand(
			...$arg
		): int
		{
			$command = $this->processCommand($arg);

			$descriptors = [
				['file', '/dev/tty', 'r'],
				['file', '/dev/tty', 'w'],
				['file', '/dev/tty', 'w'],
			];

			$process = proc_open($command, $descriptors, $pipes);
			return proc_close($process);
		}


		/**
		 * @param  string ...$arg
		 * @return array{exitCode: int, output: string}
		 */
		private function execCommand(
			...$arg
		): array
		{
			$command = $this->processCommand($arg) . ' 2>&1';

			$exitCode = 0;
			$output = [];
			exec($command, $output, $exitCode);

			return [
				'exitCode' => $exitCode,
				'output' => implode("\n", $output),
			];
		}


		private function processCommand(array $args): string
		{
			$res = [];

			foreach ($args as $arg) {
				$res[] = escapeshellarg($arg);
			}

			return implode(' ', $args);
		}
	}

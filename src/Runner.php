<?php

	declare(strict_types=1);

	namespace JP\ComposerRun;

	use Nette\Utils\FileSystem;


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
		 * @param array<string> $args
		 */
		public function run(
			string $cwd,
			array $args
		): int
		{
			$args = array_values($args);

			if (count($args) === 0) {
				return $this->commandHelp();
			}

			if ($args[0] === 'help') {
				return $this->commandHelp();

			} elseif ($args[0] === 'clean') {
				return $this->commandClean(array_slice($args, 1));

			} elseif ($args[0] === 'phpstan') {
				$args = array_merge(
					[
						'phpstan/extension-installer',
						'phpstan/phpstan',
						'extra:phpstan-extensions',
						'phpstan',
					],
					array_slice($args, 1)
				);
			}

			return $this->commandRun($cwd, $args);
		}


		private function commandHelp(): int
		{
			echo "Composer-Run\n\n";
			echo "Run commands from Composer packages locally, without global installation.\n\n";
			echo "Usage:\n";

			echo "\tcomposer-run <command>\n";
			echo "\tcomposer-run <package> <binary-name> <arguments>\n\n";

			return 0;
		}


		/**
		 * @param  list<string> $args
		 */
		private function commandClean(
			array $args
		): int
		{
			$days = 30;

			foreach ($args as $arg) {
				if ($arg !== '' && \Nette\Utils\Validators::isNumericInt($arg)) {
					$arg = (int) $arg;

					if ($arg > 0) {
						$days = $arg;
						break;
					}
				}

				echo "[ERROR] Invalid number of days.\n";
				return 1;
			}

			$this->cleanInstallations(days: $days);
			return 0;
		}


		/**
		 * @param  list<string> $args
		 */
		private function commandRun(
			string $cwd,
			array $args
		): int
		{
			$packages = [];
			$binaryName = NULL;
			$binaryArgs = [];
			$state = 'packages';
			$composerFile = $cwd . '/composer.json';

			foreach ($args as $arg) {
				if ($arg === '') {
					continue;
				}

				if ($state === 'packages') {
					if (str_starts_with($arg, 'extra:') && strlen($arg) > 6) {
						$packages = array_merge(
							$packages,
							$this->fetchExtensions($composerFile, substr($arg, 6))
						);

					} elseif (substr_count($arg, '/') === 1) {
						$packages[] = $arg;

					} else {
						$state = 'binaryName';
					}
				}

				if ($state === 'binaryName') {
					$binaryName = $arg;
					$state = 'binaryArgs';

				} elseif ($state === 'binaryArgs') {
					$binaryArgs[] = $arg;
				}
			}

			$packages = array_unique($packages);
			sort($packages, SORT_STRING);

			if (count($packages) === 0) {
				echo "[ERROR] No packages specified\n";
				return 1;
			}

			if ($binaryName === NULL) {
				echo "[ERROR] No binary name specified\n";
				return 1;
			}

			echo "[PACKAGES]\n";
			echo '- ' . implode("\n- ", $packages), "\n";
			$binaryFile = $this->initInstallation($packages, $binaryName);

			$this->cleanInstallations(days: 30);

			echo "[RUN]\n";
			array_unshift($binaryArgs, $binaryFile);
			return $this->passthruCommand(...$binaryArgs);
		}


		/**
		 * @param  list<non-empty-string> $packages
		 * @param  non-empty-string $binaryName
		 * @return non-empty-string
		 */
		private function initInstallation(array $packages, string $binaryName): string
		{
			$key = md5(serialize($packages));
			$installationDirectory = $this->tempDirectory . '/' . $key;
			@mkdir($installationDirectory, 0777, TRUE);
			$lastUpdated = NULL;
			$lastUpdatedFile = $installationDirectory . '/.last-updated';

			if (is_file($lastUpdatedFile)) {
				$lastUpdated = \DateTimeImmutable::createFromFormat(
					'Y-m-d H:i:s',
					trim((string) file_get_contents($lastUpdatedFile)),
					new \DateTimeZone('UTC')
				);

				if ($lastUpdated === FALSE) {
					$lastUpdated = NULL;
				}
			}

			$installPackages = TRUE;
			$currentDate = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));

			if ($lastUpdated !== NULL) {
				echo "Last updated: ", $lastUpdated->format('Y-m-d H:i:s'), "\n";
				$lastUpdatedDiff = (int) $lastUpdated->diff($currentDate)->format('%a');
				$installPackages = $lastUpdatedDiff >= 1;
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
					...$packages
				);

				if ($exitCode !== 0) {
					throw new \RuntimeException('Installation of packages failed.');
				}

				file_put_contents($lastUpdatedFile, $currentDate->format('Y-m-d H:i:s') . "\n");
			}

			return $installationDirectory . '/vendor/bin/' . $binaryName;
		}


		private function cleanInstallations(int $days): void
		{
			$items = scandir($this->tempDirectory);

			if (!is_array($items)) {
				return;
			}

			$currentDate = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
			$toDelete = [];

			foreach ($items as $item) {
				if ($item === '.' || $item === '..') {
					continue;
				}

				$lastUpdated = NULL;
				$lastUpdatedFile = $this->tempDirectory . '/' . $item . '/.last-updated';

				if (is_file($lastUpdatedFile)) {
					$lastUpdated = \DateTimeImmutable::createFromFormat(
						'Y-m-d H:i:s',
						trim((string) file_get_contents($lastUpdatedFile)),
						new \DateTimeZone('UTC')
					);

					if ($lastUpdated === FALSE) {
						$lastUpdated = NULL;
					}
				}

				if ($lastUpdated !== NULL) {
					$lastUpdatedDiff = (int) $lastUpdated->diff($currentDate)->format('%a');

					if ($lastUpdatedDiff >= $days) {
						$toDelete[$item] = $lastUpdated;
					}
				}
			}

			if (count($toDelete) > 0) {
				echo "[CLEAN OLD INSTALLATIONS]\n";

				foreach ($toDelete as $item => $lastUpdated) {
					echo '- ', $item, ' [', $lastUpdated->format('Y-m-d H:i:s'), " UTC]\n";
					FileSystem::delete($this->tempDirectory . '/' . $item);
				}
			}
		}


		/**
		 * @return non-empty-string[]
		 */
		private function fetchExtensions(string $composerFile, string $sectionName): array
		{
			if (!is_file($composerFile)) {
				return [];
			}

			$result = $this->execCommand(
				$this->composerExecutable,
				'config',
				'--no-interaction',
				'--json',
				'-f',
				$composerFile,
				'extra.' . $sectionName,
			);

			if ($result['exitCode'] === 0 && $result['output'] !== '') {
				$packages = [];
				$data = json_decode($result['output'], TRUE);

				if (!is_array($data)) {
					$data = [];
				}

				foreach ($data as $row) {
					if (is_string($row) && $row !== '') {
						$packages[] = $row;
					}
				}

				return $packages;
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
				STDIN,
				STDOUT,
				STDERR,
			];

			$process = proc_open($command, $descriptors, $pipes);

			if ($process === FALSE) {
				return 99;
			}

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
			$command = $this->processCommand($arg) . ' 2>/dev/null';

			$exitCode = 0;
			$output = [];
			exec($command, $output, $exitCode);

			return [
				'exitCode' => $exitCode,
				'output' => implode("\n", $output),
			];
		}


		/**
		 * @param  array<string> $args
		 */
		private function processCommand(array $args): string
		{
			$res = [];

			foreach ($args as $arg) {
				$res[] = escapeshellarg($arg);
			}

			return implode(' ', $args);
		}
	}

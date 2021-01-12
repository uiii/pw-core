<?php
namespace PW\Composer;

use Composer\Installer\LibraryInstaller;
use Composer\Package\PackageInterface;
use Composer\Repository\InstalledRepositoryInterface;

class Installer extends LibraryInstaller
{
	/**
	 * {@inheritDoc}
	 */
	public function getInstallPath(PackageInterface $package)
	{
		$extra = $this->composer->getPackage()->getExtra();

		if (isset($extra['pw-install-path'])) {
			return $extra['pw-install-path'];
		}

		return '.'; // project's root
	}

	public function getDownloadPath(PackageInterface $package)
	{
		return $this->getInstallPath($package) . '/.pw-core';
	}

	/**
	 * {@inheritDoc}
	 */
	public function getPackageBasePath(PackageInterface $package)
	{
		// project's root
		return '.';
	}

	/**
	 * {@inheritDoc}
	 */
	public function supports($packageType)
	{
		return $packageType === 'pw-core';
	}

	/**
	 * {@inheritDoc}
	 */
	public function isInstalled(InstalledRepositoryInterface $repo, PackageInterface $package)
	{
		return $repo->hasPackage($package) && is_readable($this->getInstallPath($package) . '/wire');
	}

	/**
	 * {@inheritDoc}
	 */
	public function installCode(PackageInterface $package)
	{
		$installPath = $this->getInstallPath($package);
		$downloadPath = $this->getDownloadPath($package);
		$processwireDownloadPath = $downloadPath . "/processwire-{$package->getPrettyVersion()}";

		$this->downloadProcesswire($package, $downloadPath);

		if (! file_exists($installPath . '/site/assets/installed.php')) {
			// site profile not created - creating new PW project
			// copy files needed for installation
			foreach(glob($processwireDownloadPath . '/site-*') as $dir) {
				$this->copy($dir, $installPath . '/' . basename($dir), false);
			}

			$this->copy($processwireDownloadPath . '/install.php', $installPath . '/install.php', false);
		}

		$this->copy($processwireDownloadPath . '/wire', $installPath . '/wire', false);
		$this->copy($processwireDownloadPath . '/index.php', $installPath . '/index.php');
		$this->copy($processwireDownloadPath . '/htaccess.txt', $installPath . '/.htaccess');

		$this->filesystem->remove($downloadPath);
	}

	/**
	 * {@inheritDoc}
	 */
	public function updateCode(PackageInterface $initial, PackageInterface $target)
	{
		return $this->installCode($target);
	}

	/**
	 * {@inheritDoc}
	 */
	public function removeCode(PackageInterface $package)
	{
		$installPath = $this->getInstallPath($package);

		foreach(glob($installPath . '/site-*') as $dir) {
			$this->filesystem->remove($dir);
		}

		$this->filesystem->remove($installPath . '/wire');
		$this->filesystem->remove($installPath . '/install.php');

		$this->filesystem->rename($installPath . '/index.php', $installPath . '/index.php.bak');
		$this->filesystem->rename($installPath . '/.htaccess', $installPath . '/.htaccess.bak');
	}

	private function downloadProcesswire(PackageInterface $package, $downloadPath) {
		$archiveName = str_replace('dev-', '', $package->getPrettyVersion()) . '.zip';
		$archiveUrl = "https://github.com/processwire/processwire/archive/{$archiveName}";
		$archiveFile = $downloadPath . "/pw-{$package->getPrettyVersion()}.zip";

		$this->filesystem->ensureDirectoryExists($downloadPath);
		$this->filesystem->copy($archiveUrl, $archiveFile);

		$zip = new \ZipArchive;
		if ($zip->open($archiveFile) === TRUE) {
			$zip->extractTo($downloadPath);
			$zip->close();
		} else {
			throw new \RuntimeException("Processwire download filed: Cannot opent zip archive.");
		}
	}

	private function copy($source, $destination, $backupDestination = true) {
		if (file_exists($destination) && $backupDestination) {
			$this->io->write("{$destination} already exists, renaming to {$destination}.bak");
			$this->filesystem->rename($destination, $destination . '.bak');
		}

		$this->filesystem->copyThenRemove($source, $destination);
	}
}

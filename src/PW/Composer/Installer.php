<?php
namespace PW\Composer;

use Composer\Installer\LibraryInstaller;
use Composer\Package\PackageInterface;
use Composer\Package\Package;
use Composer\Repository\InstalledRepositoryInterface;
use Composer\Util\Filesystem;

class Installer extends LibraryInstaller
{
	/**
	 * {@inheritDoc}
	 */
	public function getInstallPath(PackageInterface $package)
	{
		// project's root
		return '.';
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
		$downloadPath = $this->download($package);

		$installPath = $this->getInstallPath($package);

		if (! file_exists($installPath . '/site/assets/installed.php')) {
			// site profile not created - creating new PW project
			// copy files needed for installation
			foreach(glob($downloadPath . '/site-*') as $dir) {
				$this->copy($dir, $installPath . '/' . basename($dir), false);
			}

			$this->copy($downloadPath . '/install.php', $installPath . '/install.php', false);
		}

		$this->copy($downloadPath . '/wire', $installPath . '/wire', false);
		$this->copy($downloadPath . '/index.php', $installPath . '/index.php');
		$this->copy($downloadPath . '/htaccess.txt', $installPath . '/.htaccess');

		$this->filesystem->remove($downloadPath);
	}

	/**
	 * {@inheritDoc}
	 */
	public function updateCode(PackageInterface $initial, PackageInterface $target)
	{
		$this->installCode($target);
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

	private function download(PackageInterface $package) {
		$downloadPath = $this->getInstallPath($package) . '/.pw-core';

		$archiveName = str_replace('dev-', '', $package->getPrettyVersion()) . '.zip';
		$archiveUrl = "https://github.com/processwire/processwire/archive/{$archiveName}";

		$pwPackage = new Package('processwire/processwire', $package->getVersion(), $package->getPrettyVersion());
		$pwPackage->setDistType('zip');
		$pwPackage->setDistUrl($archiveUrl);

		$this->downloadManager->download($pwPackage, $downloadPath);

		return $downloadPath;
	}

	private function copy($source, $destination, $backupDestination = true) {
		if (file_exists($destination) && $backupDestination) {
			$this->io->write("{$destination} already exists, renaming to {$destination}.bak");
			$this->filesystem->rename($destination, $destination . '.bak');
		}

		$this->filesystem->copyThenRemove($source, $destination);
	}
}
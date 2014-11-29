<?php
namespace Rocketeer\Satellite\Services\Applications;

use DateTime;
use Illuminate\Filesystem\FileNotFoundException;
use Illuminate\Filesystem\Filesystem;
use Rocketeer\Satellite\Services\Pathfinder;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

class ApplicationsManager
{
	/**
	 * @type Pathfinder
	 */
	protected $pathfinder;

	/**
	 * @type Filesystem
	 */
	protected $files;

	/**
	 * @param Pathfinder $pathfinder
	 * @param Filesystem $files
	 */
	public function __construct(Pathfinder $pathfinder, Filesystem $files)
	{
		$this->pathfinder = $pathfinder;
		$this->files      = $files;
	}

	/**
	 * List available applications
	 *
	 * @return Application[]
	 */
	public function getApplications()
	{
		$folder = $this->pathfinder->getApplicationsFolder();
		$apps   = $this->files->directories($folder);
		$apps   = array_map('basename', $apps);

		foreach ($apps as $key => $app) {
			$apps[$key] = $this->getApplication($app);
		}

		return $apps;
	}


	/**
	 * Get an application
	 *
	 * @param string $app
	 *
	 * @return Application
	 */
	public function getApplication($app)
	{
		$app = new Application(array(
			'name' => $app,
			'path' => $this->pathfinder->getApplicationFolder($app),
		));

		// Set extra informations
		$app->paths = array(
			'current'  => $app->path.DS.'current',
			'releases' => $app->path.DS.'releases',
			'shared'   => $app->path.DS.'shared',
		);

		$app->current       = $this->getCurrentRelease($app);
		$app->configuration = $this->getApplicationConfiguration($app);

		return $app;
	}

	/**
	 * Get the current release of an application
	 *
	 * @param Application $app
	 *
	 * @return DateTime
	 */
	public function getCurrentRelease(Application $app)
	{
		$releases = $this->files->directories($app->paths['releases']);
		$current  = end($releases);
		$current  = basename($current);

		return DateTime::createFromFormat('YmdHis', $current);
	}

	/**
	 * Get the configuration of an application
	 *
	 * @param Application $app
	 *
	 * @return array
	 * @throws FileNotFoundException
	 */
	public function getApplicationConfiguration(Application $app)
	{
		$folder = $app->paths['current'].DS.'.rocketeer';
		if (!file_exists($folder)) {
			throw new FileNotFoundException('No configuration found for '.$app->name);
		}

		/** @type SplFileInfo[] $files */
		$files         = (new Finder())->files()->in($folder);
		$configuration = [];
		foreach ($files as $file) {
			if ($file->getExtension() !== 'php' || $file->getBasename() == 'tasks.php') {
				continue;
			}

			// Get configuration
			$key      = $file->getBasename('.php');
			$contents = include $file->getPathname();

			$configuration[$key] = $contents;
		}

		return $configuration;
	}
}

<?php declare(strict_types = 1);

namespace Pd\Monitoring\Router;

class RouterFactory
{

	use \Nette\SmartObject;

	/**
	 * @var \Nette\Caching\IStorage
	 */
	private $storage;


	public function __construct(
		\Nette\Caching\IStorage $storage
	) {
		$this->storage = $storage;
	}


	public function createRouter(): \Nette\Application\IRouter
	{
		$router = new \Nette\Application\Routers\RouteList();

		$metadata = [
			'module' => 'DashBoard',
			'presenter' => 'Octocats',
			'action' => 'default',
			NULL => [
				\Nette\Application\Routers\Route::FILTER_OUT => function (array $parameters) {
					if ($parameters['presenter'] === 'Octocats' && $parameters['action'] === 'random') {

						$cache = new \Nette\Caching\Cache($this->storage);

						$fb = static function (&$dp): array {
							$octodexFeedContent = \file_get_contents('https://feeds.feedburner.com/Octocats');
							$octodexFeed = new \SimpleXMLElement($octodexFeedContent);
							$octocats = [];
							foreach ($octodexFeed->entry as $entry) {
								$imageUrl = (string) $entry->content->div->a->img['src'];
								if (\Nette\Utils\Validators::isUrl($imageUrl)) {
									$octocat = \substr($imageUrl, \strrpos($imageUrl, '/') + 1);
									$octocats[] = $octocat;
								}
							}

							$dp[\Nette\Caching\Cache::EXPIRE] = '+24 hours';

							return $octocats;
						};

						$octocats = $cache->load('octocats', $fb);

						\shuffle($octocats);
						$octocat = \reset($octocats);

						$parameters = [];
						$parameters['octocat'] = $octocat;
					}

					return $parameters;
				},
			],
		];
		$router[] = new \Nette\Application\Routers\Route('https://octodex.github.com/images/<octocat [a-z0-9\.\-]+>', $metadata);

		$metadata = [
			'module' => 'DashBoard',
			'presenter' => 'HomePage',
			'action' => 'default',
		];
		$router[] = new \Nette\Application\Routers\Route('<module>/<presenter>/<action>[/<id>]', $metadata);

		return $router;
	}

}

<?php

namespace Pd\Monitoring\Check\Consumers;

class AliveCheck implements \Kdyby\RabbitMq\IConsumer
{

	/**
	 * @var \Pd\Monitoring\Check\ChecksRepository
	 */
	private $checksRepository;


	public function __construct(
		\Pd\Monitoring\Check\ChecksRepository $checksRepository
	) {
		$this->checksRepository = $checksRepository;
	}


	public function process(\PhpAmqpLib\Message\AMQPMessage $message): int
	{
		$checkId = $message->getBody();

		/** @var \Pd\Monitoring\Check\AliveCheck $check */
		$check = $this->checksRepository->getById($checkId);

		if ( ! $check || ! $check instanceof \Pd\Monitoring\Check\AliveCheck) {
			return self::MSG_REJECT;
		}

		$start = microtime(TRUE);

		$client = new \GuzzleHttp\Client();
		$response = $client->request('GET', $check->url);

		$duration = (microtime(TRUE) - $start) * 1000;

		$check->lastCheck = new \DateTime();

		if ($response->getStatusCode() !== 200) {
			$check->lastTimeout = NULL;
		} else {
			$check->lastTimeout = $duration;
		}

		$this->checksRepository->persistAndFlush($check);

		return self::MSG_ACK;
	}
}
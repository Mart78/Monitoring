<?php declare(strict_types = 1);

namespace Pd\Monitoring\Check\Consumers;

abstract class Check implements \Kdyby\RabbitMq\IConsumer
{

	/**
	 * @var \Pd\Monitoring\Check\ChecksRepository
	 */
	private $checksRepository;

	/**
	 * @var \Kdyby\Clock\IDateTimeProvider
	 */
	private $dateTimeProvider;

	/**
	 * @var \Pd\Monitoring\Orm\Orm
	 */
	private $orm;

	/**
	 * @var \Monolog\Logger
	 */
	private $logger;


	public function __construct(
		\Pd\Monitoring\Check\ChecksRepository $checksRepository,
		\Kdyby\Clock\IDateTimeProvider $dateTimeProvider,
		\Pd\Monitoring\Orm\Orm $orm,
		\Monolog\Logger $logger
	) {
		$this->checksRepository = $checksRepository;
		$this->dateTimeProvider = $dateTimeProvider;
		$this->orm = $orm;
		$this->logger = $logger;
	}


	public function process(\PhpAmqpLib\Message\AMQPMessage $message): int
	{
		$checkId = (int) $message->getBody();

		$this->orm->clearIdentityMapAndCaches(\Nextras\Orm\Model\IModel::I_KNOW_WHAT_I_AM_DOING);

		/** @var \Pd\Monitoring\Check\AliveCheck $check */
		$check = $this->checksRepository->getById($checkId);

		if ( ! $check || $check->type !== $this->getCheckType()) {
			return self::MSG_REJECT;
		}

		$maxAttempts = $this->getMaxAttempts();
		$attempts = 0;

		do {
			$this->logInfo($check, sprintf('Pokus číslo %u', $attempts));
			$check->lastCheck = $this->dateTimeProvider->getDateTime();

			$result = $this->doHardJob($check);
		} while ( ! $result && ++$attempts < $maxAttempts && sleep(3) === 0);

		$this->checksRepository->persistAndFlush($check);

		return self::MSG_ACK;
	}


	/**
	 * @param \Pd\Monitoring\Check\Check $check
	 * @return bool TRUE, pokud se podařilo úspěšně provést kontrolu, jinak FALSE. Po FALSE může následovat opětovné spuštění kontroly
	 */
	abstract protected function doHardJob(\Pd\Monitoring\Check\Check $check): bool;


	abstract protected function getCheckType(): int;


	protected function getMaxAttempts(): int
	{
		return 2;
	}


	protected function logInfo(\Pd\Monitoring\Check\Check $check, string $message): void
	{
		$this->logger->addInfo($message, ['check' => $check->id, 'checkType' => $check->type]);
	}


	protected function logError(\Pd\Monitoring\Check\Check $check, string $message): void
	{
		$this->logger->addError($message, ['check' => $check->id, 'checkType' => $check->type]);
	}

}

<?php declare(strict_types = 1);

namespace Pd\Monitoring\Check\Consumers;

class CertificateCheck extends Check
{

	/**
	 * @var \Kdyby\Clock\IDateTimeProvider
	 */
	private $dateTimeProvider;


	public function __construct(
		\Pd\Monitoring\Check\ChecksRepository $checksRepository,
		\Kdyby\Clock\IDateTimeProvider $dateTimeProvider,
		\Pd\Monitoring\Orm\Orm $orm,
		\Monolog\Logger $logger
	) {
		parent::__construct($checksRepository, $dateTimeProvider, $orm, $logger);

		$this->dateTimeProvider = $dateTimeProvider;
	}


	/**
	 * @param \Pd\Monitoring\Check\Check|\Pd\Monitoring\Check\CertificateCheck $check
	 * @return bool
	 */
	protected function doHardJob(\Pd\Monitoring\Check\Check $check): bool
	{
		set_error_handler(function ($code, $message) {
			restore_error_handler();
			throw new \Pd\Monitoring\Exception($message, $code);
		}, E_ALL);

		try {
			try {
				$get = stream_context_create(["ssl" => ["capture_peer_cert" => TRUE]]);
				$read = stream_socket_client("ssl://" . $check->url . ":443", $errno, $errstr, 30, STREAM_CLIENT_CONNECT, $get);
				restore_error_handler();
				$cert = stream_context_get_params($read);
				$certinfo = openssl_x509_parse($cert['options']['ssl']['peer_certificate'], TRUE);

				if (empty($certinfo['validTo_time_t'])) {
					throw new \InvalidArgumentException('No certificate data');
				}

				$date = $this->dateTimeProvider->getDateTime();
				$date = $date->setTimestamp($certinfo['validTo_time_t']);

				$check->lastValiddate = $date;
			} catch (\Exception $e) {
				$check->lastValiddate = NULL;

				throw $e;
			}

			try {
				$curl = new \GuzzleHttp\Client();
				$gradeResponse = \Nette\Utils\Json::decode($curl->get('https://api.ssllabs.com/api/v3/analyze?fromCache=1&maxAge=26&host=' . $check->url)->getBody(), \Nette\Utils\Json::FORCE_ARRAY);

				if ($gradeResponse['status'] === 'READY') {
					$check->lastGrade = NULL;
					foreach ($gradeResponse['endpoints'] as $endpoint) {
						if (
							$check->lastGrade
							&&
							(
								! in_array($endpoint['grade'], \Pd\Monitoring\Check\CertificateCheck::GRADES)
								||
								array_search($endpoint['grade'], \Pd\Monitoring\Check\CertificateCheck::GRADES) < array_search($check->lastGrade, \Pd\Monitoring\Check\CertificateCheck::GRADES)
							)

						) {
							continue;
						}

						$check->lastGrade = $endpoint['grade'];
					}
				}
			} catch (\Exception $e) {
				$check->lastGrade = NULL;

				throw $e;
			}
		} catch (\Exception $e) {
			return FALSE;
		}

		return TRUE;
	}


	protected function getCheckType(): int
	{
		return \Pd\Monitoring\Check\ICheck::TYPE_CERTIFICATE;
	}
}

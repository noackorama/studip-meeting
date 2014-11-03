<?php

namespace ElanEv\Driver;

use Guzzle\Http\Client;

/**
 * Creates driver instances based on the application configuration.
 *
 * @author Christian Flothmann <christian.flothmann@uos.de>
 */
class DriverFactory
{
    const DEFAULT_DRIVER_CONFIG_ID = '3c6bfcf5dd3157f53ab1143af1acc899';

    /**
     * @var \Config
     */
    private $config;

    /**
     * @param \Config $config The application configuration
     */
    public function __construct(\Config $config)
    {
        $this->config = $config;
    }

    /**
     * Returns the application's default driver (according to its
     * configuration).
     *
     * @throws \LogicException when the configured default driver is missing
     *                         or when the default driver is not configured
     *                         properly
     */
    public function getDefaultDriver()
    {
        return $this->getDriver($this->config->getValue('VC_DRIVER'));
    }

    /**
     * Returns the driver for the current application configuration.
     *
     * @param string $driver The name of the driver to use (one of the
     *                       DRIVER_ constants)
     *
     * @return DriverInterface The driver instance
     *
     * @throws \InvalidArgumentException if the requested driver doesn't exist
     * @throws \LogicException           if a required configuration option
     *                                   is missing
     */
    public function getDriver($driver)
    {
        switch ($driver) {
            case BigBlueButtonDriver::NAME:
                $config = $this->resolveConfiguration(array('BBB_URL', 'BBB_SALT'));
                $client = $this->createHttpClient($config['BBB_URL']);

                return new BigBlueButtonDriver($client, $config['BBB_SALT']);
            case DfnVcDriver::NAME:
                $config = $this->resolveConfiguration(array('DFN_VC_URL', 'DFN_VC_LOGIN', 'DFN_VC_PASSWORD'));
                $client = $this->createHttpClient($config['DFN_VC_URL']);

                return new DfnVcDriver($client, $config['DFN_VC_LOGIN'], $config['DFN_VC_PASSWORD']);
            default:
                throw new \InvalidArgumentException(sprintf('The driver "%s" does not exist.', $driver));
        }
    }

    private function createHttpClient($apiUrl)
    {
        return new Client($apiUrl);
    }

    private function resolveConfiguration(array $expectedOptions)
    {
        $config = array();

        foreach ($expectedOptions as $option) {
            $value = $this->config->getValue($option);

            if (!$value) {
                throw new \LogicException(sprintf('The config option "%s" is not configured.', $option));
            }

            $config[$option] = $value;
        }

        return $config;
    }
}
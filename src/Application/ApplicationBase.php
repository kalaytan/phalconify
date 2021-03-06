<?php

namespace Phalconify\Application;

abstract class ApplicationBase extends \Phalcon\Mvc\Micro
{
    /**
     * Create and set a factory default di.
     *
     * @param string $configFilePath
     */
    public function __construct($configFilePath = null)
    {
        // Create the dependency injection container
        $this->setDI(new \Phalcon\Di\FactoryDefault());

        // Set self inside the di container
        $this->getDI()->setShared('phalconify-application', $this);

        // Load the config
        $this->loadConfigFromFile($configFilePath);

        // Load services
        $this->loadServices();

        // Load filters
        $this->loadFilters();

        // Set not found handler
        $this->setNotFoundHandler();
    }

    /**
     * Loads config data from an ini file and stores in di container.
     */
    public function loadConfigFromFile($configFilePath)
    {
        try {
            // Ensure file exists
            if (!file_exists($configFilePath)) {
                throw new \Exception('Config file not found');
            }

            // Load file using ini adapter
            $config = new \Phalcon\Config\Adapter\Json($configFilePath);

            // Set config inside the DI
            $this->getDI()->setShared('phalconify-config', $config);
        } catch (\Exception $e) {
            echo $e->getMessage();
            exit;
        }
    }

    private function _getDatabaseAdapter($adapter)
    {
        // Use correct adapter
        switch ($adapter) {
            case 'mysql':
                break;
            case 'mongodb':
                return new \Phalconify\Database\Mongo();
                break;
        }

        return null;
    }

    public function setDatabaseConnection()
    {
        try {
            // Get config from di
            $config = $this->getDI()->getShared('phalconify-config');

            $databaseConfig = (array)$config->database;
            $singleConnection = false;
            if (isset($databaseConfig['adapter'])) {
                $singleConnection = true;
            }

            if ($singleConnection) {
                // Get the correct adapter
                $adapter = $this->_getDatabaseAdapter($config->database->adapter);

                // Set credentials
                $adapter->setCredentials($config->database)
                    ->setDI($this->getDI());
            } else {
                foreach ($databaseConfig as $connection) {
                    // Get the correct adapter
                    $adapter = $this->_getDatabaseAdapter($connection->adapter);

                    // Set credentials
                    $adapter->setCredentials($connection)
                        ->setDI($this->getDI());
                }
            }
        } catch (\Exception $e) {
            echo $e->getMessage();
            exit;
        }
    }

    public function loadServices($filePath = null)
    {
        // Initialise services
        $services = [];

        // If filePath given, load those, else load from config
        if ($filePath !== null) {
            $services = new \Phalcon\Config\Adapter\Json($filePath);
        } else {
            if (isset($this->getDI()->getShared('phalconify-config')->services)) {
                $services = $this->getDI()->getShared('phalconify-config')->services;
            }
        }

        // Loop through services and inject them inside the container
        foreach ($services as $service) {
            $this->getDI()->set($service->name, $service->definition, $service->shared);
        }
    }

    public function loadFilters()
    {
        $filters = new \Phalcon\Config\Adapter\Json(__DIR__ . '/../Filters/filters.json');
        foreach ($filters as $filter) {
            $this->filter->add($filter->name, new $filter->definition());
        }
    }
}

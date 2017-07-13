<?php

namespace Phalconify\Utils;

/**
 * Implements utilities related to logs.
 */
class Logger extends \Phalcon\Di\Injectable
{
    function logDeniedAccess($message)
    {
        $config = \Phalcon\Di::getDefault()->get('phalconify-config');
        $logger = new \Phalcon\Logger\Adapter\File($config->log->path.$config->log->access);
        
        return ($logger->notice($message)) ? true : false;
    }
}

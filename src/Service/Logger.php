<?php
/**
 *
 * User: richardgoldstein
 * Date: 11/9/18
 * Time: 2:48 PM
 */

namespace App\Service;


use Psr\Log\LoggerInterface;

/**
 * Class Logger
 *
 * @package App\Service
 */
class Logger
{

    /**
     * @var LoggerInterface
     */
    private static $loggerInstance = null;

    /**
     * @return LoggerInterface
     */
    public static function instance()
    {
        if (!self::$loggerInstance) {
            self::$loggerInstance = new \Monolog\Logger('error_log');
            self::$loggerInstance->pushHandler(new \Monolog\Handler\ErrorLogHandler());
            if (!\App\Bootstrap\Bootstrap::isProduction()) {
                self::$loggerInstance->pushHandler(new \Monolog\Handler\ChromePHPHandler());
            }
        }
        return self::$loggerInstance;
    }

}

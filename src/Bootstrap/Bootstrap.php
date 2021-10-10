<?php
/**
 *
 * User: richardgoldstein
 * Date: 11/9/18
 * Time: 2:02 PM
 */

namespace App\Bootstrap;

/**
 * Class Bootstrap
 * Performs overall app initialization
 *
 * @package App\Bootstrap
 */
class Bootstrap
{

    /**
     * @var bool Is the application in production mode?
     */
    private static $production = true;

    /**
     * Inititalize the application
     */
    public static function init()
    {
        self::initEnvironment();
        self::initFatFree();

        // Load the routes
        Routes::init();
    }

    /**
     * @return bool Is the application in production mode?
     */
    public static function isProduction()
    {
        return self::$production;
    }

    /**
     * Initialize the environment variables. Test for correct setup...
     */
    private static function initEnvironment()
    {
        if (false === getenv('KUFA_ENV') || trim(getenv('KUFA_ENV') == '')) {
            header('X-Environment: DEV');
            self::$production = false;
            $dotenv = new \Dotenv\Dotenv(__DIR__ . '/../..');
            $dotenv->load();
        //} else {
        //    header('X-Environment: PROD');
        //
        }

    }

    /**
     * Inititalize the framework
     */
    private static function initFatFree()
    {
        $f3 = \Base::instance();

         //CORS to enable APIs from clients
        //$f3->set('CORS.origin', '*'); // Should this be explicit in the production mode?
        ////$f3->copy('HEADERS.Origin','CORS.origin');
        //$f3->set('CORS.headers', ['Accept', 'Content-Type', 'Authorization']);
        //$f3->set('CORS.expose', ['Authorization']);

        // We use the composer auto loader so we're not setting thr AUTOLOAD value here

        $f3->set('UI', __DIR__ . '/../Views/');
    }


}

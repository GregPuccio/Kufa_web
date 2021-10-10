<?php
/**
 *
 * User: richardgoldstein
 * Date: 11/9/18
 * Time: 2:07 PM
 */

namespace App\Bootstrap;


/**
 * Class Routes
 *
 * @package App\Bootstrap
 */
class Routes
{
    /** @var \Base */
    protected static $f3;

    /**
     * Initializs the routes
     */
    public static function init()
    {
        self::$f3 = \Base::instance();
        //self::$f3->route('GET /dbtest', function() {
        //    $db = \App\Service\DbConnectionService::instance();
        //    $rs = $db->exec("SELECT * FROM test");
        //
        //    echo "In /<br /><pre>" . print_r($rs, true) ."</pre>";
        //});

//        self::routeToClass('GET /testx', \App\Controller\TestController::class,  'test');
//        self::routeToClass('POST /wcreg', \App\Controller\WinterCampRegController::class, 'saveReg');
//        self::routeToClass('POST /wcreg2', \App\Controller\WinterCampRegController::class, 'saveReg2');
//        self::routeToClass('GET /wcreg2', \App\Controller\WinterCampRegController::class, 'getAvailableDays');
//        self::routeToClass('POST /ccreg', \App\Controller\CaregiversClashRegController::class, 'saveReg');

        self::routeToClass('GET /session', \App\Controller\SessionTestController::class, 'read');
        self::routeToClass('POST /session', \App\Controller\SessionTestController::class, 'set');
        // self::routeToClass('DEL /session', \App\Controller\SessionTestController::class, 'clear');

        self::routeToClass('GET /', \App\Controller\Web\HomeController::class, 'home');

        self::routeToClass('GET @content: /content/@slug', \App\Controller\Web\StaticContentController::class, 'show');

        self::routeToClass('GET @regform: /register/@slug', \App\Controller\Web\RegistrationController::class, 'registrationForm');
        self::routeToClass('POST /register/@slug', \App\Controller\Web\RegistrationController::class, 'postReg');

        self::routeToClass('GET @login: /login', \App\Controller\Web\LoginController::class, 'login');
        self::routeToClass('POST /login', \App\Controller\Web\LoginController::class, 'loginPost');
        self::routeToClass('GET @logout: /logout', \App\Controller\Web\LoginController::class, 'logout');

        self::routeToClass('GET @events: /events', \App\Controller\Web\EventController::class, 'listEvents');
        self::routeToClass('GET @event: /events/@id', \App\Controller\Web\EventController::class, 'eventDetail');
    }

    /**
     * Helper method for routing to a class method.
     * @param string $route     The route
     * @param string $class     Use the MyClass::class format to avoid typos
     * @param string $method    Name of the handler method
     */
    protected static function routeToClass($route, $class, $method)
    {
        self::$f3->route($route, "{$class}->{$method}");

    }

    /**
     * Map matching routes to class methods named for the HTTP verb (get/post/put/delete)
     * @param string $route    The route
     * @param string $class    Use the MyClass::class format to avoid typos
     */
    protected static function mapToClass($route, $class)
    {
        self::$f3->map($route, $class);
    }

}

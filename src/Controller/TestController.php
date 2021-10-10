<?php
/**
 *
 * User: richardgoldstein
 * Date: 11/9/18
 * Time: 2:23 PM
 */

namespace App\Controller;


class TestController
{
    public function beforeRoute(\Base $f3, $args) {
        echo "In Before Route<br />";
    }

    public function afterRoute(\Base $f3, $args) {
        echo "In After Route<br />";
    }

    public function test(\Base $f3, $args)
    {
        echo "In Route<br />";
        \App\Service\Logger::instance()->err("Some error in the logger");
    }

}

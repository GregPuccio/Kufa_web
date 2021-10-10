<?php
/**
 *
 * User: richardgoldstein
 * Date: 11/20/18
 * Time: 6:10 AM
 */

namespace App\Controller;


class SessionTestController
{

    public function beforeRoute(\Base $f3, $args)
    {
        // \App\Service\Logger::instance()->error($f3->get('VERB'));
    }
    public function set(\Base $f3)
    {
        $data = json_decode($f3->get('BODY'), true);
        \App\Service\Logger::instance()->error(print_r($data, true));
        $f3->set('SESSION.value', $data['data']['value']);
        (new \App\Utility\ApiResponse(200, ['orig'=>$data['data']['value'], 'rev'=>strrev($data['data']['value'])]))->emit();
    }
    public function read(\Base $f3)
    {
        $val = $f3->get('SESSION.value');
        (new \App\Utility\ApiResponse(200, ['orig'=>$val, 'rev'=>strrev($val)]))->emit();

    }
    public function clear(\Base $f3)
    {
        $f3->clear('SESSION.value');
        $this->read($f3);

    }
}

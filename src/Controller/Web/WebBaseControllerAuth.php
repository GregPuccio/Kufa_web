<?php
/**
 *
 * User: richardgoldstein
 * Date: 2019-03-18
 * Time: 06:07
 */

namespace App\Controller\Web;


use App\Utility\Flash;

class WebBaseControllerAuth extends WebBaseController
{

    /** @var array User record-array */
    protected $user;

    public function beforeRoute(\Base $f3, $args) {
        if (!$f3->exists('SESSION.user', $user) || !$user['u_id']) {
            Flash::instance()->addMessage('Not Authorized', 'is-danger', true);
            $f3->reroute('/');
        }
        $this->user = $user;
    }
}

<?php
/**
 *
 * User: richardgoldstein
 * Date: 2019-03-17
 * Time: 17:26
 */

namespace App\Controller\Web;


use App\Service\DbConnectionService;
use App\Utility\Flash;

class LoginController extends WebBaseController
{
    public function login(\Base $f3, $args)
    {
        $this->render('Login/login_form.html');
    }

    public function loginPost(\Base $f3, $args)
    {
        $f = $_POST['form'];
        $user = DbConnectionService::instance()->getAR('users');
        $user->load(['u_email = ?', trim($f['email'])]);
        if ($user->dry() || !password_verify(trim($f['password']), $user->u_password)) {
            $f3->clear('SESSION.user');
            Flash::instance()->addMessage('Invalid Login', 'is-danger');
            $f3->reroute($f3->get('PATH'));
        } else {
            // We have a match!
            $f3->set('SESSION.user', $user->cast());
            $f3->reroute('/');
        }
    }

    public function logout(\Base $f3, $args)
    {
        $f3->clear('SESSION.user');
        $f3->reroute('/');
    }
}


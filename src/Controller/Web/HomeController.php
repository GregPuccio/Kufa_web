<?php
/**
 *
 * User: richardgoldstein
 * Date: 2019-03-17
 * Time: 09:11
 */

namespace App\Controller\Web;

class HomeController extends WebBaseController
{

    public function home(\Base $f3) {
        $f3->set('TEMPLATE.slug', 'home');
        $this->render('StaticContent/home.html');
    }
}

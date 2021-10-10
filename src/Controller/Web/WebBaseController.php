<?php
/**
 *
 * User: richardgoldstein
 * Date: 2019-03-17
 * Time: 09:04
 */

namespace App\Controller\Web;

class WebBaseController
{

    const VIEW_BASE = 'Web/';

    public function render($template) {
        \Base::instance()->set('TEMPLATE.content', self::VIEW_BASE . $template);
        echo \Template::instance()->render(self::VIEW_BASE . 'scaffold.html');
    }


}

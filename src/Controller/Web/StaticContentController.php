<?php
/**
 *
 * User: richardgoldstein
 * Date: 2019-03-17
 * Time: 09:55
 */

namespace App\Controller\Web;


class StaticContentController extends WebBaseController
{
    // Static content. Requires that there is a template in StaticContent with the lowercase
    // name provided in the args

    public function show(\Base $f3, $args) {
        $slug = trim(strtolower($args['slug']));
        if (file_exists($f3->get('UI') . "Web/StaticContent/{$slug}.html")) {
            $f3->set('TEMPLATE.slug', $slug);
            $this->render("StaticContent/{$slug}.html");
        } else {
            $f3->error(404);
        }
    }
}

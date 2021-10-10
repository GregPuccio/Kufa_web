<?php
/**
 *
 * User: richardgoldstein
 * Date: 11/13/18
 * Time: 6:50 AM
 */

namespace App\Controller;


use App\Service\Logger;

class CaregiversClashRegController
{
    public function saveReg(\Base $f3)
    {
        $data = json_decode($f3->get('BODY'), true);
        Logger::instance()->debug(print_r($data, true));

        try {
            $f3->set('RS', $data['data']);
            $email = new \SendGrid\Mail\Mail();
            $email->setFrom("service@kapricaunited.com", "KapricaUnited Web");
            $email->setSubject("Caregivers Clash Registration [" . time() . "]");
            $notify = explode(',', getenv('KUFA_NOTIFY'));
            foreach ($notify as $recip) {
                $email->addTo($recip);
            }
            $email->addContent(
                "text/html",
                \Template::instance()->render('CaregiversClashRegController/notify_email.html')
            );
            $sendgrid = new \SendGrid(getenv('SENDGRID_KEY'));
            $response = $sendgrid->send($email);
            $f3->status($response->statusCode());
        } catch (\Exception $e) {
            Logger::instance()->error($e);
            $f3->status(501);
        }

        //$f3->status(201);
    }
}

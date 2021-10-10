<?php
/**
 *
 * User: richardgoldstein
 * Date: 11/13/18
 * Time: 6:50 AM
 */

namespace App\Controller;


use App\Service\DbConnectionService;
use App\Service\Logger;

class WinterCampRegController
{
    public function saveReg(\Base $f3)
    {
        $data = json_decode($f3->get('BODY'), true);

        try {
            $f3->set('RS', $data['data']);
            $email = new \SendGrid\Mail\Mail();
            $email->setFrom("service@kapricaunited.com", "KapricaUnited Web");
            $email->setSubject("Winter Camp Registration [" . time() . "]");
            $notify = explode(',', getenv('KUFA_NOTIFY'));
            foreach ($notify as $recip) {
                $email->addTo($recip);
            }
            $email->addContent(
                "text/html",
                \Template::instance()->render('WinterCampRegController/notify_email.html')
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

    /**
     * Revised with saving to database
     * @param \Base $f3
     */
    public function saveReg2(\Base $f3)
    {
        $data = json_decode($f3->get('BODY'), true);

        /*
        firstname: '',
        lastname: '',
        parentFirst: '',
        parentLast: '',
        email: '',
        phone: '',
        days: [],
        comments: ''

         */
        try {
            $f = $data['data'];
            // Save to database...
            $db = DbConnectionService::instance();
            try {
                $db->begin();
                $ar = $db->getAR('winter_camp_reg');
                $ar->wcr_first = $f['firstname'];
                $ar->wcr_last = $f['lastname'];
                $ar->wcr_parent_first = $f['parentFirst'];
                $ar->wcr_parent_last = $f['parentLast'];
                $ar->wcr_comments = $f['comments'];
                $ar->wcr_phone = $f['phone'];
                $ar->wcr_email = $f['email'];
                $ar->wcr_added = strftime('%Y/%m/%d %H:%M:%S');
                $ar->save();
                $id= $ar->get('_id');
                $days = $db->getAR('winter_camp_reg_days');
                foreach ($f['days'] as $d) {
                    $days->reset();
                    $days->wcrd_wcr_id = $id;
                    $days->wcrd_day_num = $d;
                    $days->save();
                    $db->exec("UPDATE reg_counts SET rc_count=rc_count+1 WHERE rc_event=:event AND rc_day=:day",
                        [':event'=> 'wcreg', ':day'=>$d]);
                }
                $db->commit();
            } catch (\Exception $e) {
                echo $e->getMessage();
                $db->rollback();
            }

            //$f3->set('RS', $data['data']);
            //$email = new \SendGrid\Mail\Mail();
            //$email->setFrom("service@kapricaunited.com", "KapricaUnited Web");
            //$email->setSubject("Winter Camp Registration [" . time() . "]");
            //$notify = explode(',', getenv('KUFA_NOTIFY'));
            //foreach ($notify as $recip) {
            //    $email->addTo($recip);
            //}
            //$email->addContent(
            //    "text/html",
            //    \Template::instance()->render('WinterCampRegController/notify_email.html')
            //);
            //$sendgrid = new \SendGrid(getenv('SENDGRID_KEY'));
            //$response = $sendgrid->send($email);
            //$f3->status($response->statusCode());
        } catch (\Exception $e) {
            Logger::instance()->error($e);
            $f3->status(501);
        }

        //$f3->status(201);
    }

    public function getAvailableDays(\Base $f3)
    {
        $db = DbConnectionService::instance();

        $rs = $db->exec('SELECT rc_day FROM reg_counts WHERE rc_count < rc_limit and rc_event = :event ORDER BY rc_day ASC',
            [':event'=>'wcreg']);
        echo json_encode(array_map(function($x) { return (int)$x; }, array_column($rs, 'rc_day')));
    }

}

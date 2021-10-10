<?php
/**
 *
 * User: richardgoldstein
 * Date: 2019-03-18
 * Time: 06:10
 */

namespace App\Controller\Web;


use App\Service\DbConnectionService;
use App\Utility\Flash;

class EventController extends WebBaseControllerAuth
{
    public function listEvents(\Base $f3, $args) {
        // Load all events, with summary data
        $q = "SELECT ev.*, count(er_id) as registrants from `events` ev
            LEFT OUTER JOIN event_reg ON er_event_id=event_id
            WHERE event_active=1
            GROUP BY event_id
            ORDER BY event_pub_start";
        $rs = DbConnectionService::instance()->exec($q);
        // Load all the days
        $q1 = "SELECT ed.*, count(erd_id) as registrants from event_days ed
            JOIN events ON event_id=ed_event_id and event_active=1
          LEFT OUTER JOIN event_reg_days ON erd_ed_id=ed_id
          GROUP BY ed_id
          ORDER BY ed_date ASC";
        $days_rs = DbConnectionService::instance()->exec($q1);

        foreach ($rs as &$r) {
            // Get the list of days and counts
            $r['days'] = [];
            if ($r['event_has_dates']) {
                foreach ($days_rs as $d) {
                    if ($d['ed_event_id'] == $r['event_id']) {
                        $r['days'][] = $d;
                    }
                }
            }
        }
        $f3->set('RS.events', $rs);
        $this->render('Events/event_list.html');
    }

    public function eventDetail(\Base $f3, $args) {
        $q = "SELECT ev.*, count(er_id) as registrants from `events` ev
            LEFT OUTER JOIN event_reg ON er_event_id=event_id
            WHERE event_id=:id GROUP BY event_id";
        $rs = DbConnectionService::instance()->getOne($q, [':id'=>$args['id']]);
        if ($rs) {
            if ($rs['event_has_dates']) {
                $q = "SELECT ed.* from event_days ed
                      LEFT OUTER JOIN event_reg_days ON erd_ed_id=ed_id
                      WHERE ed_event_id=:id
                      GROUP BY ed_id
                      ORDER BY ed_date";
                $days = DbConnectionService::instance()->exec($q, [':id'=>$args['id']]);
            } else {
                $days = [];
            }
            // Get the attendees and all of their days
            $q = "SELECT er.*, ed_date
                FROM event_reg er
                LEFT OUTER JOIN event_reg_days erd ON erd.erd_er_id=er_id
                LEFT OUTER JOIN event_days ed on ed_id=erd.erd_ed_id
                WHERE er_event_id=:id
                ORDER BY er_last, er_first, ed_date";
            $reg = DbConnectionService::instance()->exec($q, [':id'=>$args['id']]);
            // Consolidate reg records
            $regList = [];
            foreach ($reg as $er) {
                if (!isset($regList[$er['er_id']])) {
                    $regList[$er['er_id']] = $er;
                    $regList[$er['er_id']]['days'] = [$er['ed_date']];
                } else {
                    $regList[$er['er_id']]['days'][] = $er['ed_date'];
                }
            }
            $f3->set('RS', [
               'event'=>$rs,
               'days'=>$days,
               'reg'=>$regList
            ]);
            $this->render('Events/event_detail.html');
            // echo "<pre>" . print_r($rs, true) . print_r($regList, true) . "</pre>";

        } else {
            Flash::instance()->addMessage('Event not found!', 'is-danger', true);
            $f3->reroute('@events');
        }

    }
}

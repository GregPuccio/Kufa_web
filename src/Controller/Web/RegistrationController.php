<?php
/**
 *
 * User: richardgoldstein
 * Date: 2019-03-17
 * Time: 14:37
 */

namespace App\Controller\Web;


use App\Service\DbConnectionService;
use App\Service\GeoIp;
use App\Service\Logger;
use App\Utility\Flash;
use Base;
use DB\SQL\Mapper;
use Exception;
use SendGrid;
use SendGrid\Mail\Mail;
use Template;

class RegistrationController extends WebBaseController
{

    /** @var Mapper */
    protected $event;
    protected $event_days;
    protected $slug;

    public function beforeRoute(Base $f3, $args): void
    {
        if (!isset($args['slug']) || trim($args['slug']) === '') {
            $f3->error(404);
            return;
        }
        $this->slug = trim($args['slug']);
        $event = DbConnectionService::instance()->getAR('events');
        $event->load(['event_slug=? and event_active=1', $this->slug]);
        if ($event->dry()) {
            $f3->error(404);
        }
        $this->event = $event;
        // Are there days?
        if ($event['event_has_dates']) {
            $days = DbConnectionService::instance()->exec(
                'SELECT * FROM event_days WHERE ed_event_id=:eid ORDER BY ed_date',
                [':eid' => $event->event_id]
            );
            $this->event_days = $days && is_array($days) && count($days) ? $days : false;
        } else {
            $this->event_days = false;
        }

        $orig_host = $f3->get('HEADERS.X-Original-Host');
        if (strpos($orig_host, 'ngrok.io') !== FALSE) {
            $jar = $f3->get('JAR');
            unset($jar['expire']);
            $jar['domain'] = $orig_host;
            $f3->set('JAR', $jar);
            session_set_cookie_params(...array_values($jar));
        }

    }

    public function registrationForm(Base $f3, $args): void
    {
        $f3->set('EVENT', $this->event->cast());
        $f3->set('EVENT_DAYS', $this->event_days);
        $f3->set('TEMPLATE.slug', "reg/{$args['slug']}");

        $this->setupCsrf();
        $this->render('Registration/regform.html');
        // echo "<pre>" . print_r($this->event->cast(), true) . "</pre>";
    }

    public function postReg(Base $f3): void
    {
        if (!isset($_POST['form'])) {
            $f3->reroute($f3->get('PATH'));
        }
        $this->testCsrfPost();
        // Validate values against form...
        try {
            $f = $_POST['form'];

            if ($this->event_days && (!is_array($f['days']) || !count($f['days']))) {
                Flash::instance()->addMessage(
                    'You must specify at least one day.' . print_r($f['days'], true),
                    'is-danger'
                );
                $f3->reroute($f3->get('PATH'));
            }

            DbConnectionService::instance()->begin();
            $er = DbConnectionService::instance()->getAR('event_reg');
            $er->er_event_id = $this->event->event_id;
            $er->er_first = $f['firstname'];
            $er->er_last = $f['last'];
            if ($this->event->event_has_parents) {
                $er->er_parent_first = $f['parentfirst'];
                $er->er_parent_last = $f['parentlast'];
            }
            $er->er_comments = $f['comments'];
            $er->er_phone = $f['phone'];
            $er->er_email = $f['email'];
            $er->er_added = strftime('%Y/%m/%d %H:%M:%S');
            $er->save();
            $db = DbConnectionService::instance();
            $days = $db->getAR('event_reg_days');
            if ($this->event_days && $f['days'] && is_array($f['days']) && count($f['days'])) {
                foreach ($f['days'] as $d) {
                    $days->reset();
                    $days->erd_er_id = $er->get('_id');
                    $days->erd_ed_id = $d;
                    $days->save();
                    $db->exec(
                        'UPDATE event_days SET ed_count=ed_count+1 WHERE ed_id=:day',
                        [':day' => $d]
                    );
                }
            }
            //
            DbConnectionService::instance()->commit();
            Flash::instance()->addMessage('Your registration has been successfully recorded.');
            if ($this->event->event_notify) {
                $this->notifyEmail($f3, $er->get('_id'));
            }
        } catch (Exception $e) {
            DbConnectionService::instance()->rollback();
            Flash::instance()->addMessage('An error occurred while recording your registration.', 'is-danger');
        }
        $f3->reroute($f3->get('PATH'));

    }

    private function notifyEmail(Base $f3, $er_id): void
    {
        try {
            $rs = DbConnectionService::instance()->getOne(
                'SELECT * FROM event_reg 
                  JOIN `events` ev on ev.event_id=er_event_id
                  WHERE er_id=:id', [':id' => $er_id]);

            if (!$rs) {
                Logger::instance()->error("Event reg id={$er_id} was not found.");
                return;
            }

            // Get the days if applicable
            if ($this->event->event_has_dates) {
                $days_rs = DbConnectionService::instance()->exec(
                    'SELECT ed_date FROM event_reg_days 
                        JOIN event_days on ed_id=erd_ed_id
                        WHERE erd_er_id=:id
                        ORDER BY ed_date', [':id' => $er_id]);
                $rs['days'] = array_column($days_rs, 'ed_date');
            } else {
                $rs['days'] = [];
            }
            $f3->set('REG', $rs);
            $f3->set('EVENT', $this->event);
            $email = new Mail();
            $email->setFrom('service@kapricaunited.com', 'KapricaUnited Web');
            $email->setSubject("Event Registration  {$this->event->event_name} [" . time() . ']');
            $notify = explode(',', getenv('KUFA_NOTIFY'));
            foreach ($notify as $recip) {
                $email->addTo($recip);
            }
            $email->addContent(
                'text/html',
                Template::instance()->render('Web/Registration/notify_email.html')
            );
            $sendgrid = new SendGrid(getenv('SENDGRID_KEY'));
            $sendgrid->send($email);
        } catch (Exception $e) {
            Logger::instance()->error($e);
        }

    }

    /**
     * Setup CSRF parameters before emitting a form
     */
    public function setupCsrf(): void
    {
        // We can do this without altering the session management, which causes problems (Which should be worked out...)
        // For now just handle CSRF directly

        $fw = Base::instance();
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $token = $fw->get('SEED') . '.' . $fw->hash(mt_rand());
        $key = $fw->hash(mt_rand() . uniqid('xxx', false));
        $fw->set('SESSION.csrf', $token);
        $fw->set('SESSION.csrf_var', $key);

        // Create a var that wraps the whole thing up

        $fw->set(
            'CSRF_TAG',
            "<input type=\"hidden\" name=\"{$key}\" value=\"{$token}\" />" .
            "<div style=\"display:none\"><input name=\"form[lastname]\" type=\"text\"><textarea name=\"notes_{$key}\"></textarea></div>"
        );

        /*
        if ($this->session) {
            $this->session->setupCsrf();
        }
        */
    }

    public function testCsrfPost(): void
    {
        $fw = Base::instance();
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // Geo ip - must be in us
        $country = GeoIp::currentCountry();
        if (GeoIp::currentCountry() !== 'US') {
            Logger::instance()->error("Attempt to access form from disallowed country: $country");
            Base::instance()->reroute(Base::instance()->get('PATH'));
            return; // For IDE
        }
        if (
            !isset($_SESSION['csrf_var'], $_SESSION['csrf']) ||
            $fw->get('POST.' . $fw->get('SESSION.csrf_var')) !== $fw->get('SESSION.csrf')
        ) {

            Logger::instance()->error(
                'Cross Site Request Forgery check failed at ' . $fw->get('PATH') . ' from ' .
                $fw->get('IP') . ' : ' . PHP_EOL . print_r($_POST, true) .
                $_SESSION['csrf_var'] . ' / ' . $_SESSION['csrf'] . ' - ' .
                $fw->get('SESSION.csrf_var') . ' ' . $fw->get('SESSION.csrf')
            );
            $fw->clear('COOKIE.' . session_name());
            session_destroy();
            $fw->error(403);

        }
        $hp_field = 'notes_' . $fw->get('SESSION.csrf_var');
        if (!isset($_POST[$hp_field], $_POST['form']['lastname']) || trim($_POST[$hp_field]) !== '' || trim($_POST['form']['lastname']) !== '') {
            Logger::instance()->error(
                'Formbot Honeypot check failed at ' . $fw->get('PATH') . ' from ' .
                $fw->get('IP') . ' : ' . PHP_EOL . print_r($_POST, true)
            );
            $fw->clear('COOKIE.' . session_name());
            session_destroy();
            $fw->error(403);
        }
        Logger::instance()->error(
            'Form Checks succeeded with  ' . $fw->get('PATH') . ' from ' .
            $fw->get('IP') . " $country : " . PHP_EOL . print_r($_POST, true)
        );
        /*
        if ($this->session) {
            $this->session->testCsrfPost();
        }
        */
    }

}

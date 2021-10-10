<?php
namespace App\Controller;

/**
 * Class ApiController
 * Base class for api controllers - handles auth etc
 * @package App\Controller
 *
 *
 */
class ApiController
{

    /**
     * The object representing current user
     *
     * @var array
     */
    protected $user = null;
    /**
     * Current user's effective permissions.
     *
     * @var array
     */
    protected $scopes = array();

    /**
     * Hash string to generate/validate JWT tokens
     */
    const HMAC = 'My PsuedoRandomly Generated Hash Key for decoding the results we need for JWT.';

    /**
     * Default session duration in minutes.
     */
    const SESSION_DURATION = 30;


    protected static $nextToken = '';

    // Required from BaseInterface
    public  function hasScope($scope)
    {
        return in_array($scope, $this->scopes);
    }


    public  function isLoggedIn()
    {
        return $this->user !== null;
    }


    public function getCurrentUser($field = null)
    {
        if (!$this->isLoggedIn()) {
            return null;
        }
        return $field === null ? $this->user : (isset($this->user[$field]) ? $this->user[$field] : null);
    }


    /**
     * Generate and emit success API response
     *
     * @param mixed $data
     */
    protected function success($code, $data = null)
    {
        //\Service\LoggerService::err("Next token: " . self::$nextToken);
        (new \App\Utility\ApiResponse($code, $data))->emit();
    }

    protected function error($code, $error, $error_code)
    {
        (new \App\Utility\ApiErrorResponse($code, $error, $error_code))->emit();
    }


    protected static function checkLoggedInJWT($token)
    {
        try {
            $dec = \Firebase\JWT\JWT::decode($token, self::HMAC, array('HS256'));
        } catch (Exception $e) {
            throw new ApiException('Invalid Token', self::API_E_AUTHORIZATION_FAILED);
        }
        $dec = (array)$dec;
        if (!isset($dec['uid'])) {
            throw new ApiException('Invalid User', self::API_E_INVALID_USER);
        }
        $user = new DB\User();
        if (!$user->checkLoginById($dec['uid'])) {
            throw new ApiException('User not found', self::API_E_USER_NOT_FOUND);
        }
        $session_user = \Business\UserAccounts::instance()->getSessionUser($user->id);

        self::$user = $session_user;
        self::$perms = BaseController::userEffectivePerms(self::$user);
        \Model\IcanModel::setUser(self::$user);

        // Keep session alive
        self::$nextToken = self::makeToken();

    }
    /**
     * Test the headers for a JWT token and validate it.
     * If valid, set the current user accordingly.
     * On failure, emits a failure response.
     * The return value need not be checked. Returns gracefully on success, and emits failure otherwise.
     * This should be a special case of failure to indicate that the token is bad
     */
    protected static function checkLoggedIn()
    {
        // Check for JWT Token
        $h = \Base::instance()->get('HEADERS');
        if (isset($h['Authorization']) && trim($h['Authorization']) != '') {
            try {
                $parts = preg_split('/\s+/', trim($h['Authorization']));
                if (count($parts) < 2 || $parts[0] != 'Bearer') {
                    throw new ApiException('Invalid Authorization Header', self::API_E_INVALID_AUTH_HEADER);
                }
                self::checkLoggedInJWT($parts[1]);
                /*
                try {
                    $dec = \Firebase\JWT\JWT::decode($parts[1], self::HMAC, array('HS256'));
                } catch (Exception $e) {
                    throw new ApiException('Invalid Token', self::API_E_AUTHORIZATION_FAILED);
                }
                $dec = (array)$dec;
                if (!isset($dec['uid'])) {
                    throw new ApiException('Invalid User', self::API_E_INVALID_USER);
                }
                $user = new DB\User();
                if (!$user->checkLoginById($dec['uid'])) {
                    throw new ApiException('User not found', self::API_E_USER_NOT_FOUND);
                }
                $session_user = Model\User::instance()->getSessionUser($user->id);

                self::$user = $session_user;
                self::$perms = BaseController::userEffectivePerms(self::$user);
                \Model\IcanModel::setUser(self::$user);

                // Keep session alive
                self::$nextToken = self::makeToken();
                */
            } catch (ApiException $e) {
                self::failure($e);
            } catch (Exception $e) {
                self::failure('An internal error occurred.', self::API_E_GENERAL_ERROR);
            }
        } else {
            // TODO Allow browser calls which will not have an Authorization header
            // Check for the cookie foirst so we dont let the framework start a session if its not needed
            if (isset($_COOKIE[session_name()])) {
                \Framework::instance()->installSessionHandler(true);
                if (AuthController::isLoggedIn()) {
                    // Copy login data
                    self::$user = \Base::instance()->get(BaseController::USER_KEY);
                    self::$perms = BaseController::userEffectivePerms(self::$user);
                    \Model\IcanModel::setUser(self::$user);
                    // return from here which is success by default
                    return;
                }
            }
            self::failure("No Authorization Header", self::API_E_INVALID_AUTH_HEADER);
        }
    }

    /**
     * Create a new JWT token with the given expiration time in minutes
     *
     * @param int $expires
     *
     * @return string The stringified token
     */
    protected static function makeToken($expires = self::SESSION_DURATION)
    {
        $expire_time = $expires * 60;
        $token = array(
            'iat' => time(),
            'exp' => time() + $expire_time, // 30 minutes
            'uid' => self::$user['id'],
            'lvl' => self::$user['access_level'],
            'perms' => implode(',', self::$perms)
        );
        return \Firebase\JWT\JWT::encode($token, self::HMAC, 'HS256');

    }



    /*
     * { email: 'email',  password: 'password' }
     * }
     */
    /**
     * Handle a login call by generating a JWT token for the session.
     * Expects json payload: { email: 'email',  password: 'password' }
     * A successful login returns enough info to identify the user and user type to the app
     * So access_level, effectve_perms, name, subscription status, sponsorship status
     * Handles pin logins as well.
     *
     * @param Base $f3
     */
    public static function login(\Base $f3)
    {
        try {
            // Should get username, password
            $p = self::request();
            $user = new DB\User();
            if (isset($p['pin']) && isset($p['token'])) {
                $uid = Model\PinModel::instance()->checkPinWithToken($p['pin'], $p['token']);
                if (!$user->loadByid($uid)) {
                    self::failure('Login Failed', self::API_E_LOGIN_FAILED);
                }
            } elseif (isset($p['email']) && isset($p['password'])) {
                if (!$user->checkLogin($p['email'], $p['password'])) {
                    self::failure('Login Failed', self::API_E_LOGIN_FAILED);
                }
            } else {
                self::failure('Invalid Payload', self::API_E_INVALID_PAYLOAD);
            }

            $old_ip = $user->login_ip;
            $user->login_ip = \Base::instance()->get('IP');
            if ($user->login_ip != $old_ip) {
                if ($user->login_ip == $user->registration_ip) {
                    $user->login_geo = $user->registration_geo;
                } else {
                    if ($user->login_ip == $user->activation_ip) {
                        $user->login_geo = $user->activation_geo;
                    } else {
                        $user->login_geo = Service\GeoLocationService::getGeoJson($user->login_ip);
                    }
                }
            }
            $user->save();

            $session_user = \Business\UserAccounts::instance()->getSessionUser($user->id);

            self::$user = $session_user;
            \Model\IcanModel::setUser($session_user);

            // Save perms
            self::$perms = BaseController::userEffectivePerms($session_user);
            // Create and return JWT token
            $expire_time = self::SESSION_DURATION * 60;
            $jwt = self::makeToken();

            $rv = array('token' => $jwt, 'expires_in' => $expire_time);
            $rv['FirstName'] = $user->firstname;
            $rv['LastName'] = $user->lastname;
            $rv['DisplayName'] = Model\User::getDisplayName($user);
                // trim($user->display_name) == '' ? ($user->firstname . ' ' . $user->lastname) : $user->display_name;
            $rv['Permissions'] = array_keys(self::$perms);
            $rv['AccessLevel'] = $user->access_level;
            // Subscription and Sponsirship
            $sub = Model\Subscription::instance()->loadCurrentSubscription($user->id);
            $rc['Subscription'] = array('Name' => $sub['name'], 'Type' => $sub['type'], 'Status' => $sub['status']);
            // Sponsorship
            // This is used by mobile clients. None presently so deprecate this call.
            // $rv['Sponsor'] = Model\Subscription::instance()->getSponsorship();


            self::success($rv);
        } catch (Exception $e) {
            self::failure($e);
        }

    }


    public static function resetPassword(\Base $f3)
    {
        $params = self::request();
        $email = trim($params['email']);

        if ($email == '') {
            self::failure('Email address not specified', self::API_E_GENERAL_ERROR);
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            self::failure('Invalid email address', self::API_E_GENERAL_ERROR);
        }

        $user = new \DB\User();
        if (!$user->loadByEmail($email)) {
            self::failure('Email address not found', self::API_E_GENERAL_ERROR);
        } else {
            $key = \Model\User::getActivationKey();
            // Save it
            $user->activate_key = $key;
            $user->save();
            $user->copyto('email_recip');

            $f3->set('root', $f3->get('email.linkRoot'));


            if (Service\SendMailService::getSenderService()->send(
                $user->email,
                "{$user->firstname} {$user->lastname}",
                'Cancer Expert Now Password Reset',
                Template::instance()->render('AuthController/resetPwdEmail.html', 'text/html')
            )
            ) {
                self::success();
            } else {
                self::failure('An error occurred sending the password recovery email.', self::API_E_GENERAL_ERROR);
            }
        }

    }

    /**
     * Test the request object for a speficied key and type cast.
     *
     * @param string $n    Key name
     * @param mixed $def   Default value if not present in Request
     * @param string $type Type to case to, default int
     *
     * @return mixed
     */
    protected static function reqParam($n, $def = 0, $type = 'int')
    {
        $a = isset($_REQUEST[$n]) ? $_REQUEST[$n] : $def;
        settype($a, $type);
        return $a;
    }


    /**
     * Get a collection of requerst parameters based on a defining array
     *
     * @param array $p Array of either key names or array(keyname, default, type)
     *
     * @return array Key->value pairs, typecase as requested and with default values if needed
     */
    protected static function getParams(array $p)
    {
        $r = array();
        foreach ($p as $v) {
            if (!is_array($v)) {
                $v = array($v);
            }
            $def = isset($v[1]) ? $v[1] : 0;
            $type = isset($v[2]) ? $v[2] : 'int';
            $r[$v[0]] = self::reqParam($v[0], $def, $type);
        }
        return $r;
    }

    /**
     * Get paging params. defaults to page=1, pagesize=10
     * Calculates start and limit
     *
     * @param array|null $a
     *
     * @return array
     */
    protected static function paginationParams(array $a = null)
    {
        $dd = array(
            array('pagesize', 10, 'int'),
            array('page', 1, 'int')
        );
        if (is_array($a)) {
            $dd = array_merge($dd, $a);
        }
        $p = self::getParams($dd);
        $p['pagesize'] = max(1, min(100, $p['pagesize']));
        $p['start'] = max(0, ($p['page'] - 1) * $p['pagesize']);
        $p['limit'] = $p['pagesize'];
        return $p;
    }

    protected static function successPaging($rows, $count, $pageSize, $params = null)
    {
        // There's always at least one page
        $pages = max(1, floor($count / $pageSize) + ($count % $pageSize ? 1 : 0));
        $rv = ['total' => (int)$count, 'pages' => (int)$pages, 'rows' => $rows, 'pageSize' => (int)$pageSize];

        if (is_array($params)) {
            $rv = array_merge($params, $rv);
        }
        self::success($rv);
    }


    public static function testPassword(\Base $f3) {
        // This is jquery validator specific
        //$params = $_POST;
        // \Service\LoggerService::err(print_r($_POST, true));
        $pwdSvc = \Service\PasswordValidatorService::instance();
        $valid = true;
        // Walk the array - only one element will be sent but we don't know what it is called
        // Or how deep it is.
        array_walk_recursive($_POST, function($item, $key) use (&$valid, $pwdSvc) {
            if (!$pwdSvc->attempt(trim($item))) {
                $valid = false;
            }
        });
        echo json_encode($valid ? true : [$pwdSvc->getDescription()]);
    }
}

<?php namespace Zizaco\ConfideMongo;

use Illuminate\Contracts\Auth\Authenticatable;
use Mongolid\ActiveRecord;

class ConfideMongoUser extends ActiveRecord implements Authenticatable
{

    /**
     * The database collection used by the model.
     *
     * @var string
     */
    public $collection = 'users';

    /**
     * The attributes excluded from the model's JSON form.
     *
     * @var array
     */
    protected $hidden = ['password'];

    /**
     * List of attribute names which should be hashed on save.
     *
     * @var array
     */
    protected $hashedAttributes = ['password'];

    /**
     * Ardent validation rules
     *
     * @var array
     */
    public static $rules = [
        'username'          => 'required|alpha_dash',
        'email'             => 'required|email',
        'password'          => 'required|between:4,11|confirmed',
        'confirmation_code' => 'required',
    ];

    /**
     * Create a new ConfideMongoUser instance.
     */
    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $this->collection = app('config')->get('auth.table');
    }

    /**
     * Get the unique identifier for the user.
     *
     * @return mixed
     */
    public function getAuthIdentifier()
    {
        return $this->_id;
    }

    /**
     * Get the password for the user.
     *
     * @return string
     */
    public function getAuthPassword()
    {
        return $this->password;
    }

    /**
     * Confirm the user (usually means that the user)
     * email is valid.
     *
     * @return bool
     */
    public function confirm()
    {
        $this->confirmed = 1;

        // ConfideRepository will update the database
        app('confide.repository')
            ->confirmUser($this);

        return true;
    }

    /**
     * Send email with information about password reset
     *
     * @return string
     */
    public function forgotPassword()
    {
        // ConfideRepository will generate token (and save it into database)
        $token = app('confide.repository')
            ->forgotPassword($this);

        $view = app('config')->get('confide::email_reset_password');

        $this->sendEmail('confide::confide.email.password_reset.subject', $view, ['user' => $this, 'token' => $token]);

        return true;
    }

    /**
     * Change user password
     *
     * @param $params
     *
     * @return string
     */
    public function resetPassword($params)
    {
        $password             = array_get($params, 'password', '');
        $passwordConfirmation = array_get($params, 'password_confirmation', '');

        if ($password == $passwordConfirmation) {
            return app('confide.repository')
                ->changePassword($this, app('hash')->make($password));
        } else {
            return false;
        }
    }

    /**
     * Overwrites MongoLid isValid method in order to check for duplicates
     *
     * @return bool Is Valid?
     */
    public function isValid()
    {
        if (parent::isValid()) {
            $duplicated = false;

            if (! $this->_id) {
                $duplicated = app('confide.repository')->userExists($this);
            }

            if (! $duplicated) {
                return true;
            } else {
                $this->errors()->add(
                    'duplicated',
                    app('translator')->get('confide::confide.alerts.duplicated_credentials')
                );

                return false;
            }
        }
    }

    /**
     * Save the model to the database if it's valid. Run beforeSave() and
     * afterSave() methods.
     *
     * @param $force Force save even if the object is invalid
     *
     * @return bool
     */
    public function save($force = false)
    {
        $this->beforeSave($force);

        $result = parent::save($force);

        $this->afterSave($result, $force);

        return $result;
    }

    /**
     * Before save the user. Generate a confirmation
     * code if is a new user.
     *
     * @param bool $forced Indicates whether the user is being saved forcefully
     *
     * @return bool
     */
    public function beforeSave($forced = false)
    {
        /**
         * Generates confirmation code
         */
        if (empty($this->_id)) {
            $this->confirmation_code = md5(uniqid(mt_rand(), true));
        }

        return true;
    }

    /**
     * After save, delivers the confirmation link email.
     * code if is a new user.
     *
     * @param bool $success
     * @param bool $forced Indicates whether the user is being saved forcefully
     *
     * @return bool
     */
    public function afterSave($success, $forced = false)
    {
        if ($success and ! $this->confirmed) {
            $view = app('config')->get('confide::email_account_confirmation');

            $this->sendEmail('confide::confide.email.account_confirmation.subject', $view, ['user' => $this]);
        }

        return true;
    }

    /**
     * Add the namespace 'confide::' to view hints.
     * this makes possible to send emails using package views from
     * the command line.
     *
     * @return void
     */
    protected static function fixViewHint()
    {
        if ($viewFinder = app('view.finder')) {
            $viewFinder->addNamespace('confide', __DIR__ . '/../../views');
        }
    }

    /**
     * Send email using the lang sentence as subject and the viewname
     *
     * @param mixed $subject_translation
     * @param mixed $view_name
     * @param array $params
     *
     * @return voi.
     */
    protected function sendEmail($subject_translation, $view_name, $params = [])
    {
        if (app('config')->getEnvironment() == 'testing') {
            return;
        }

        static::fixViewHint();

        $user = $this;

        app('mailer')->send(
            $view_name, $params, function ($m) use ($subject_translation, $user) {
            $m->to($user->email)
                ->subject(app('translator')->get($subject_translation));
        }
        );
    }

    /**
     * Get the token value for the "remember me" session.
     *
     * @see \Illuminate\Auth\UserInterface
     * @return string
     */
    public function getRememberToken()
    {
        return $this->remember_token;
    }

    /**
     * Set the token value for the "remember me" session.
     *
     * @see \Illuminate\Auth\UserInterface
     *
     * @param  string $value
     *
     * @return void
     */
    public function setRememberToken($value)
    {
        $this->remember_token = $value;
    }

    /**
     * Get the column name for the "remember me" token.
     *
     * @see \Illuminate\Auth\UserInterface
     * @return string
     */
    public function getRememberTokenName()
    {
        return 'remember_token';
    }

    /*
    |--------------------------------------------------------------------------
    | Deprecated methods
    |--------------------------------------------------------------------------
    |
    */

    /**
     * [Deprecated] Checks if an user exists by it's credentials. Perform a 'where' within
     * the fields contained in the $identityColumns.
     *
     * @deprecated Use ConfideRepository getUserByIdentity instead.
     *
     * @param  array $credentials     An array containing the attributes to search for
     * @param  mixed $identityColumns Array of attribute names or string (for one atribute)
     *
     * @return boolean                 Exists?
     */
    public function checkUserExists($credentials, $identity_columns = ['username', 'email'])
    {
        $user = app('confide.repository')->getUserByIdentity($credentials, $identity_columns);

        if ($user) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * [Deprecated] Checks if an user is confirmed by it's credentials. Perform a 'where' within
     * the fields contained in the $identityColumns.
     *
     * @deprecated Use ConfideRepository getUserByIdentity instead.
     *
     * @param  array $credentials     An array containing the attributes to search for
     * @param  mixed $identityColumns Array of attribute names or string (for one atribute)
     *
     * @return boolean                 Is confirmed?
     */
    public function isConfirmed($credentials, $identity_columns = ['username', 'email'])
    {
        $user = app('confide.repository')->getUserByIdentity($credentials, $identity_columns);

        if (! is_null($user) and $user->confirmed) {
            return true;
        } else {
            return false;
        }
    }
}

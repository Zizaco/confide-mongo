<?php namespace Zizaco\ConfideMongo;

use Exception;
use Mongolid\Connection\Pool;
use MongoDB\BSON\UTCDateTime;
use Zizaco\Confide\ConfideUser;
use Zizaco\Confide\RepositoryInterface;

/**
 * A layer that abstracts all database interactions that happens
 * in Confide
 */
class ConfideMongoRepository implements RepositoryInterface
{
    /**
     * Name of the model that should be used to retrieve your users.
     * You may specify an specific object. Then that object will be
     * returned when calling `model()` method.
     *
     * @var string
     */
    public $model;

    /**
     * Collection name used for password reminders functionality.
     *
     * @var string
     */
    protected $reminderCollection = 'password_reminders';

    /**
     * Returns the model set in auth config
     *
     * @return ConfideMongoUser Instantiated object of the 'auth.model' class
     * @throws Exception
     */
    public function model(): ConfideMongoUser
    {
        if (null === $this->model) {
            $this->model = app('config')->get('auth.model');
        }

        if (is_object($this->model)) {
            return $this->model;
        } elseif (is_string($this->model)) {
            return new $this->model;
        }

        throw new Exception("Model not specified in config/auth.php", 639);
    }

    /**
     * Set the user confirmation to true.
     *
     * @param string $code
     *
     * @return bool
     */
    public function confirmByCode($code)
    {
        $user = $this->model()->first(['confirmation_code' => $code]);

        if ($user) {
            return $user->confirm();
        }

        return false;
    }

    /**
     * Find a user by the given email
     *
     * @param  string $email The email to be used in the query
     *
     * @return ConfideMongoUser User object
     */
    public function getUserByEmail($email)
    {
        return $this->model()->first(['email' => $email]);
    }

    /**
     * Find a user by the given email
     *
     * @param  string $emailOrUsername The email to be used in the query
     *
     * @return ConfideUser   User object
     */
    public function getUserByEmailOrUsername($emailOrUsername)
    {
        return $this->getUserByEmail($emailOrUsername);
    }

    /**
     * Find a user by it's credentials. Perform a 'find' within
     * the fields contained in the $identityColumns.
     *
     * @param  array $credentials     An array containing the attributes to search for
     * @param  mixed $identityColumns Array of attribute names or string (for one attribute)
     *
     * @return ConfideUser             User object
     */
    public function getUserByIdentity($credentials, $identityColumns = ['email'])
    {
        $identityColumns = (array) $identityColumns;

        $user = $this->model();

        $query = ['$or' => []];

        foreach ($identityColumns as $attribute) {
            if (isset($credentials[$attribute])) {
                $query['$or'][] = [$attribute => $credentials[$attribute]];
            }
        }

        return $user->first($query);
    }

    /**
     * Checks if an non saved user has duplicated credentials
     * (email and/or username)
     *
     * @param  ConfideMongoUser $user The non-saved user to be checked
     *
     * @return int The number of entries founds. Probably 0 or 1.
     */
    public function userExists(ConfideMongoUser $user)
    {
        if ($user->username) {
            $query = [
                '$or' => [
                    ['username' => $user->username],
                    ['email' => $user->email],
                ],
            ];
        } else {
            $query = ['email' => $user->email];
        }

        return $user->where($query)->count();
    }

    /**
     * Get password reminders count by the given token
     *
     * @param  string $token
     *
     * @return int    Password reminders count
     */
    public function getPasswordRemindersCount($token)
    {
        $count = $this->database()
            ->{$this->reminderCollection}
            ->findOne(['token' => $token])->count();

        return $count;
    }

    /**
     * Get email of password reminder by the given token
     *
     * @param  string $token
     *
     * @return string Email
     */
    public function getEmailByReminderToken($token)
    {
        $email = $this->database()
            ->{$this->reminderCollection}
            ->findOne(['token' => $token], ['email']);

        return $email->email ?? '';
    }

    /**
     * Remove password reminder from database by the given token
     *
     * @param  string $token
     *
     * @return void
     */
    public function deleteEmailByReminderToken($token)
    {
        $this->database()
            ->{$this->reminderCollection}
            ->deleteOne(['token' => $token]);
    }

    /**
     * Generate a token for password change and saves it in
     * the 'password_reminders' table with the email of the
     * user.
     *
     * @param  ConfideMongoUser $user An existent user
     *
     * @return string Password reset token
     */
    public function forgotPassword($user)
    {
        $token = md5(uniqid(mt_rand(), true));

        $values = [
            'email'      => $user->email,
            'token'      => $token,
            'created_at' => new UTCDateTime(time()),
        ];

        $this->database()
            ->{$this->reminderCollection}
            ->insertOne($values);

        return $token;
    }

    /**
     * Validate the user by a given custom rule.
     *
     * @param       $user
     * @param array $rules
     * @param array $customMessages
     *
     * @return mixed
     */
    public function validate($user, array $rules, array $customMessages)
    {
        return $user->validate($rules, $customMessages);
    }

    /**
     * Returns the MongoDB database object (using the database provided
     * in the config)
     *
     * @return \MongoDB\Client
     */
    protected function database()
    {
        $connection = app(Pool::class)->getConnection();
        $database   = $connection->defaultDatabase;

        return $connection->getRawConnection()->$database;
    }

    /* Deprecated Methods */

    /**
     * Change the password of the given user. Make sure to hash
     * the $password before calling this method.
     *
     * @deprecated use ConfideMongoUser resetPassword method instead.
     *
     * @param  ConfideMongoUser $user     An existent user
     * @param  string           $password The password hash to be used
     *
     * @return boolean Success
     */
    public function changePassword($user, $password)
    {
        $user->password = $password;

        return $user->save();
    }

    /**
     * Set the 'confirmed' column of the given user to 1
     *
     * @deprecated use ConfideMongoUser resetPassword method instead.
     *
     * @param  ConfideMongoUser $user An existent user
     *
     * @return boolean Success
     */
    public function confirmUser($user)
    {
        return $user->confirm();
    }
}

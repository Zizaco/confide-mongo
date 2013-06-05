<?php namespace Zizaco\ConfideMongo;

use Zizaco\Confide\ConfideRepository;

/**
 * A layer that abstracts all database interactions that happens
 * in Confide
 */
class ConfideMongoRepository implements ConfideRepository
{
    /**
     * Laravel application
     * 
     * @var Illuminate\Foundation\Application
     */
    public $app;

    /**
     * Name of the model that should be used to retrieve your users.
     * You may specify an specific object. Then that object will be
     * returned when calling `model()` method.
     * 
     * @var string
     */
    public $model;

    /**
     * Create a new ConfideRepository
     *
     * @return void
     */
    public function __construct()
    {
        $this->app = app();
    }

    /**
     * Returns the model set in auth config
     *
     * @return mixed Instantiated object of the 'auth.model' class
     */
    public function model()
    {
        if (! $this->model)
        {               
            $this->model = $this->app['config']->get('auth.model');
        }

        if(is_object($this->model))
        {
            return $this->model;
        }
        elseif(is_string($this->model))
        {
            return new $this->model;
        }

        throw new \Exception("Model not specified in config/auth.php", 639);
    }

    /**
     * Set the user confirmation to true.
     *
     * @param string $code
     * @return bool
     */
    public function confirm( $code )
    {
        $user = $this->model()->first(array('confirmation_code'=>$code));
        
        if( $user )
        {
            return $user->confirm();
        }
        else
        {
            return false;
        }
    }

    /**
     * Find a user by the given email
     * 
     * @param  string $email The email to be used in the query
     * @return ConfideUser   User object
     */
    public function getUserByMail( $email )
    {
        $user = $this->model()->first(array('email'=>$email));

        return $user;
    }

    /**
     * Find a user by it's credentials. Perform a 'find' within
     * the fields contained in the $identityColumns.
     * 
     * @param  array $credentials      An array containing the attributes to search for
     * @param  mixed $identityColumns  Array of attribute names or string (for one atribute)
     * @return ConfideUser             User object
     */
    public function getUserByIdentity( $credentials, $identityColumns = array('email') )
    {
        $identityColumns = (array)$identityColumns;

        $user = $this->model();

        $query = array('$or'=>array());

        foreach ($identityColumns as $attribute) {
            
            if(isset($credentials[$attribute]))
            {
                $query['$or'][] = array($attribute => $credentials[$attribute]);
            }
        }

        return $user->first($query);
    }

    /**
     * Get password reminders count by the given token
     * 
     * @param  string $token
     * @return int    Password reminders count
     */
    public function getPasswordRemindersCount( $token )
    {
        $count = $this->database()->password_reminders
            ->find(array('token'=>$token))->count();

        return $count;
    }

    /**
     * Get email of password reminder by the given token
     * 
     * @param  string $token
     * @return string Email
     */
    public function getEmailByReminderToken( $token )
    {
        $email = $this->database()->password_reminders
            ->findOne(array('token'=>$token), array('email'));

        if ($email && is_object($email))
        {
            $email = $email->email;
        }
        elseif ($email && is_array($email))
        {
            $email = $email['email'];
        }

        return $email;
    }

    /**
     * Remove password reminder from database by the given token
     * 
     * @param  string $token
     * @return void
     */
    public function deleteEmailByReminderToken( $token )
    {
        $this->database()->password_reminders
            ->remove(array('token'=>$token));
    }

    /**
     * Change the password of the given user. Make sure to hash
     * the $password before calling this method.
     * 
     * @param  ConfideUser $user     An existent user
     * @param  string      $password The password hash to be used
     * @return boolean Success
     */
    public function changePassword( $user, $password )
    {
        $usersCollection = $user->getCollectionName();
        $id = $user->_id;

        $this->database()->$usersCollection
            ->update(array('_id'=>$id), array('$set'=>array('password'=>$password)));
        
        return true;
    }

    /**
     * Generate a token for password change and saves it in
     * the 'password_reminders' table with the email of the
     * user.
     * 
     * @param  ConfideUser $user     An existent user
     * @return string Password reset token
     */
    public function forgotPassword( $user )
    {
        $token = md5( uniqid(mt_rand(), true) );

        $values = array(
            'email'=> $user->email,
            'token'=> $token,
            'created_at'=> new \DateTime
        );

        $this->database()->password_reminders
            ->insert( $values );
        
        return $token;
    }

    /**
     * Checks if an non saved user has duplicated credentials
     * (email and/or username)
     * 
     * @param  ConfideUser  $user The non-saved user to be checked
     * @return int          The number of duplicated entry founds. Probably 0 or 1.
     */
    public function userExists( $user )
    {
        $usersCollection = $user->getCollectionName();

        if($user->username)
        {
            $query = array(
                '$or' => array(
                    array('username' => $user->username),
                    array('email' => $user->email)
                )
            );
        }
        else
        {
            $query = array('email' => $user->email);
        }

        $users = $this->database()->$usersCollection
            ->find($query);

        $count = $users->count();
        
        return $count;
    }

    /**
     * Set the 'confirmed' column of the given user to 1
     * 
     * @param  ConfideUser $user     An existent user
     * @return boolean Success
     */
    public function confirmUser( $user )
    {
        $usersCollection = $user->getCollectionName();
        $id = $user->_id;

        $this->database()->$usersCollection
            ->update(array('_id'=>$id), array('$set'=>array('confirmed'=>1)));
        
        return true;
    }

    /**
     * Returns the MongoDB database object (using the database provided
     * in the config)
     * 
     * @return MongoDatabase
     */
    protected function database()
    {
        $name = $this->app['config']->get('database.mongodb.default.database');

        $database = $this->app['MongoLidConnector']->getConnection()->$name;

        return $database;
    }
}

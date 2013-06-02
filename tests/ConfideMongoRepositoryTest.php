<?php

use Zizaco\Confide\Confide;
use Zizaco\ConfideMongo\ConfideMongoRepository;
use Mockery as m;

class ConfideRepositoryTest extends PHPUnit_Framework_TestCase {

    /**
     * ConfideRepository instance
     *
     * @var Zizaco\Confide\ConfideRepository
     */
    protected $repo;

    public function setUp()
    {
        $app = $this->mockApp();
        $this->repo = new ConfideMongoRepository();

        // Set the app attribute with mock
        $this->repo->app = $app;
    }

    public function tearDown()
    {
        m::close();
    }

    public function testGetModel()
    {
        // Make sure to return the wanted value from config
        $config = m::mock('Illuminate\Config\Repository');
        $config->shouldReceive('get')
            ->with('auth.model')
            ->andReturn( '_mockedUser' )
            ->once();
        $this->repo->app['config'] = $config;

        // Mocks an user
        $confide_user = $this->mockConfideUser();

        // Runs the `model()` method
        $user = $this->repo->model();

        // Assert the result
        $this->assertInstanceOf('_mockedUser', $user);
    }

    public function testShouldConfirm()
    {
        // Make sure that our user will recieve confirm
        $confide_user = m::mock(new _mockedUser);
        $confide_user->shouldReceive('confirm') // Should receive confirm
            ->andReturn( true )
            ->once()
            
            ->getMock()->shouldReceive('first') // Should query for the model
            ->with(array('confirmation_code'=>'123123'))
            ->andReturn( $confide_user )
            ->once();

        // This will make sure that the mocked user will be returned
        // when calling `model()` (that will occur inside `repo->confirm()`)
        $this->repo->model = $confide_user;

        $this->assertTrue( $this->repo->confirm( '123123' ) );
    }

    public function testShouldGetByEmail()
    {
        // Make sure that our user will recieve confirm
        $confide_user = m::mock(new _mockedUser);
        $confide_user->shouldReceive('first') // Should query for the model
            ->with(array('email'=>'lol@sample.com'))
            ->andReturn( $confide_user )
            ->once();

        // This will make sure that the mocked user will be returned
        // when calling `model()` (that will occur inside `repo->confirm()`)
        $this->repo->model = $confide_user;

        $this->assertEquals( $confide_user, $this->repo->getUserByMail( 'lol@sample.com' ) );
    }

    public function testShouldGetByIdentity()
    {
        // Make sure that our user will be returned when querying
        $confide_user = m::mock(new _mockedUser);

        $confide_user->email = 'lol@sample.com';
        $confide_user->username = 'LoL';

        $confide_user->shouldReceive('first') // Should query for the model
            ->with(array('email'=>'lol@sample.com', 'username'=>'LoL'))
            ->andReturn( $confide_user )
            ->atLeast(1)

            ->getMock()->shouldReceive('first') // Should query for the model
            ->with(array('email'=>'lol@sample.com'))
            ->andReturn( $confide_user )
            ->atLeast(1)
            
            ->getMock()->shouldReceive('first') // Should query for the model
            ->with(array('username'=>'LoL'))
            ->andReturn( $confide_user )
            ->atLeast(1);

        // This will make sure that the mocked user will be returned
        // when calling `model()` (that will occur inside `repo->confirm()`)
        $this->repo->model = $confide_user;

        // Parameters to search for
        $values = array(
            'email' => 'lol@sample.com',
            'username' => 'LoL',
        );

        // Identity
        $identity = array( 'email','username' );

        // Using array
        $this->assertEquals(
            $confide_user, $this->repo->getUserByIdentity( $values, $identity )
        );

        // Using string
        $this->assertEquals(
            $confide_user, $this->repo->getUserByIdentity( $values, 'email' )
        );

        // Using string for username
        $this->assertEquals(
            $confide_user, $this->repo->getUserByIdentity( $values, 'username' )
        );
    }

    public function testShouldGetPasswordRemindersCountByToken()
    {
        // Make sure that the password reminders table will receive a first
        $database = $this->repo->app['MongoLidConnector'];

        $database->password_reminders = $database; // The collection that should be accessed

        $database->shouldReceive('find') // Should query for the password reminders with the given token
            ->with(array('token'=>'456456'))
            ->andReturn( $database )
            ->once()

            ->getMock()->shouldReceive('count')
            ->andReturn( 1 )
            ->once();

        $this->repo->app['MongoLidConnector'] = $database;

        $this->assertEquals( 1, $this->repo->getPasswordRemindersCount( '456456' ) );
    }

    public function testShouldGetPasswordReminderEmailByToken()
    {
        // Make sure that the password reminders collection will receive a first
        $database = $this->repo->app['MongoLidConnector'];

        $database->password_reminders = $database; // The collection that should be accessed

        $database->shouldReceive('first') // Should query for the password reminders collection by the given token
            ->with(array('token'=>'456456'), array('email'))
            ->andReturn( 'lol@sample.com' )
            ->once();

        $this->repo->app['MongoLidConnector'] = $database;

        $this->assertEquals( 'lol@sample.com', $this->repo->getEmailByReminderToken( '456456' ) );
    }

    public function testShouldDeletePasswordReminderEmailByToken()
    {
        // Make sure that the password reminders collection will receive a remove
        $database = $this->repo->app['MongoLidConnector'];

        $database->password_reminders = $database; // The collection that should be accessed

        $database->shouldReceive('remove') // Should remove by the given token
            ->with(array('token'=>'456456'))
            ->andReturn( null )
            ->once();

        $this->repo->app['MongoLidConnector'] = $database;

        $this->assertNull( $this->repo->deleteEmailByReminderToken( '456456' ) );
    }

    public function testShouldChangePassword()
    {
        // Make sure that the mock will have an _id
        $confide_user = m::mock(new _mockedUser);
        $confide_user->_id = '123123';

        // Make sure that the password reminders collection will receive a update
        $database = $this->repo->app['MongoLidConnector'];

        $database->users = $database; // The collection that should be accessed

        $database->shouldReceive('update') // Should update the password of the user
            ->with(array('_id'=>'123123'), array('password'=>'secret'))
            ->andReturn( true )
            ->once();

        $this->repo->app['MongoLidConnector'] = $database;

        // Actually change the user password
        $this->assertTrue(
            $this->repo->changePassword($confide_user, 'secret')
        );
    }

    public function testShouldForgotPassword()
    {
        // Make sure that the mock will have an email
        $confide_user = m::mock(new _mockedUser);

        $confide_user->email = 'bob@sample.com';

        $timeStamp = new \DateTime;

        // Make sure that the password reminders collection will receive an insert
        $database = $this->repo->app['MongoLidConnector'];

        $database->password_reminders = $database; // The collection that should be accessed

        $database->shouldReceive('insert') // Should query for the password reminders with the given token
            ->andReturn( true )
            ->once();

        $this->repo->app['MongoLidConnector'] = $database;

        // Actually checks if the user exists
        $this->assertTrue(
            $this->repo->forgotPassword($confide_user)
        );
    }

    public function testUserExists()
    {
        // Make sure that the mock will have it's attributes
        $confide_user = m::mock(new _mockedUser);

        $confide_user->username = 'Bob';
        $confide_user->email =    'bob@sample.com';

        // The query should be
        $query = array(
            '$or' => array(
                array('username' => $confide_user->username),
                array('email' => $confide_user->email)
            )
        );

        // Make sure that the password reminders collection will receive a find and count
        $database = $this->repo->app['MongoLidConnector'];

        $database->users = $database; // The collection that should be accessed

        $database->shouldReceive('find') // Should query for the password reminders with the given token
            ->with($query)
            ->andReturn( $database )
            ->once()

            ->getMock()->shouldReceive('count')
            ->andReturn( 1 )
            ->once();

        $this->repo->app['MongoLidConnector'] = $database;

        // Actually checks if the user exists
        $this->assertEquals(
            1,
            $this->repo->userExists($confide_user)
        );
    }

    public function testShouldConfirmUser()
    {
        // Make sure that the mock will return an id
        $confide_user = m::mock(new _mockedUser);
        $confide_user->_id = '123123';

        // Make sure that the password reminders collection will receive a update
        $database = $this->repo->app['MongoLidConnector'];

        $database->users = $database; // The collection that should be accessed

        $database->shouldReceive('update') // Should query for the password reminders with the given token
            ->with(array('_id'=>'123123'), array('confirmed'=>1))
            ->andReturn( true )
            ->once();

        $this->repo->app['MongoLidConnector'] = $database;

        // Actually change the user password
        $this->assertTrue(
            $this->repo->confirmUser($confide_user)
        );
    }

    /**
     * Returns a mocked ConfideUser object for testing purposes
     * only
     * 
     * @return Illuminate\Auth\UserInterface A mocked confide user
     */
    private function mockConfideUser()
    {
        $confide_user = m::mock( 'Illuminate\Auth\UserInterface' );
        $confide_user->username = 'uname';
        $confide_user->password = '123123';
        $confide_user->confirmed = 0;
        $confide_user->shouldReceive('find','get', 'first', 'all','getUserFromCredsIdentity')
            ->andReturn( $confide_user );

        return $confide_user;
    }

    /**
     * Mocks the application components that
     * are not Confide's responsibility
     * 
     * @return object Mocked laravel application
     */
    private function mockApp()
    {
        // Mocks the application components that
        // are not Confide's responsibility
        $app = array();

        $app['config'] = m::mock( 'Config' );
        $app['config']->shouldReceive( 'get' )
            ->with( 'auth.table' )
            ->andReturn( 'users' );

        $app['config']->shouldReceive( 'get' )
            ->with( 'auth.model' )
            ->andReturn( '_mockedUser' );

        $app['config']->shouldReceive( 'get' )
            ->with( 'app.key' )
            ->andReturn( '123' );

        $app['config']->shouldReceive( 'get' )
            ->with( 'confide::throttle_limit' )
            ->andReturn( 9 );

        $app['config']->shouldReceive( 'get' )
            ->with( 'database.mongodb.default.database' )
            ->andReturn( 'mongolid' );

        $app['config']->shouldReceive( 'get' )
            ->with( 'database.mongodb.default.database', m::any() )
            ->andReturn( 'mongolid' );

        $app['config']->shouldReceive( 'get' )
            ->andReturn( 'confide::login' );

        $app['mail'] = m::mock( 'Mail' );
        $app['mail']->shouldReceive('send')
            ->andReturn( null );

        $app['hash'] = m::mock( 'Hash' );
        $app['hash']->shouldReceive('make')
            ->andReturn( 'aRandomHash' );

        $app['cache'] = m::mock( 'Cache' );
        $app['cache']->shouldReceive('get')
            ->andReturn( 0 );
        $app['cache']->shouldReceive('put');

        $app['auth'] = m::mock( 'Auth' );
        $app['auth']->shouldReceive('login')
            ->andReturn( true );

        $app['request'] = m::mock( 'Request' );
        $app['request']->shouldReceive('server')
            ->andReturn( null );

        $app['MongoLidConnector'] = m::mock( 'MongoLidConnector' );
        $app['MongoLidConnector']->shouldReceive('getConnection')
            ->andReturn( $app['MongoLidConnector'] );
        $app['MongoLidConnector']->mongolid = $app['MongoLidConnector'];
        $app['MongoLidConnector']->users = $app['MongoLidConnector'];

        return $app;
    }

}

class _mockedUser {

    public function getCollectionName()
    {
        return 'users';
    }
}

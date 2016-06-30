<?php
namespace Zizaco\ConfideMongo;

use Illuminate\Contracts\Config\Repository as Config;
use Mockery as m;

class ConfideRepositoryTest extends TestCase
{
    /**
     * {@inheritdoc}
     */
    public function tearDown()
    {
        m::close();

        parent::tearDown();
    }

    public function testGetModel()
    {
        // Set
        $repository = new ConfideMongoRepository;
        $config     = m::mock(Config::class);

        // Assertions
        $config->shouldReceive('get')
            ->once()
            ->with('auth.model')
            ->andReturn(ConfideMongoUser::class);

        /* ConfideMongoUser constructor use this config */
        $config->shouldReceive('get')
            ->once()
            ->with('auth.table')
            ->andReturn('users');

        $this->setInstance('config', $config);

        // Actions
        $user = $repository->model();

        // Assertions
        $this->assertInstanceOf(ConfideMongoUser::class, $user);
    }

    public function testShouldConfirmByCode()
    {
        // Set
        $repository  = new ConfideMongoRepository;
        $confideUser = $this->getConfideMongoUser();

        // Expectations
        $confideUser->shouldReceive('confirm')
            ->andReturn(true)
            ->once();

        $confideUser->shouldReceive('first')
            ->with(['confirmation_code' => '123123'])
            ->andReturn($confideUser)
            ->once();

        $this->setProtected($repository, 'model', $confideUser);

        // Actions
        $result = $repository->confirmByCode('123123');

        // Assertions
        $this->assertTrue($result);
    }

    public function testShouldGetByEmail()
    {
        // Set
        $repository  = new ConfideMongoRepository;
        $confideUser = $this->getConfideMongoUser();

        // Expectations
        $confideUser->shouldReceive('first')
            ->with(['email' => 'lol@sample.com'])
            ->andReturn($confideUser)
            ->once();

        $this->setProtected($repository, 'model', $confideUser);

        // Actions
        $result = $repository->getUserByEmail('lol@sample.com');

        // Assertions
        $this->assertEquals($confideUser, $result);
    }

    public function testShouldGetByIdentity()
    {
        // Set
        $repository  = new ConfideMongoRepository;
        $confideUser = $this->getConfideMongoUser();

        $identity = ['email', 'username'];

        $values = [
            'email'    => 'lol@sample.com',
            'username' => 'LoL',
        ];

        $confideUser->email    = $values['email'];
        $confideUser->username = $values['username'];

        // Expectations

        $confideUser->shouldReceive('first')
            ->with(
                [
                    '$or' => [
                        ['email' => 'lol@sample.com'],
                        ['username' => 'LoL'],
                    ],
                ]
            )
            ->andReturn($confideUser);

        $confideUser->shouldReceive('first')
            ->with(['$or' => [['email' => 'lol@sample.com']]])
            ->andReturn($confideUser);

        $confideUser->shouldReceive('first')
            ->with(['$or' => [['username' => 'LoL']]])
            ->andReturn($confideUser);

        $this->setProtected($repository, 'model', $confideUser);

        // Assertions
        $this->assertEquals($confideUser, $repository->getUserByIdentity($values, $identity));
        $this->assertEquals($confideUser, $repository->getUserByIdentity($values, 'email'));
        $this->assertEquals($confideUser, $repository->getUserByIdentity($values, 'username'));
    }

    public function testShouldGetPasswordRemindersCountByToken_legacy()
    {
        // Make sure that the password reminders table will receive a first
        $database = $this->repo->app['MongoLidConnector'];

        $database->password_reminders = $database; // The collection that should be accessed

        $database->shouldReceive('find')// Should query for the password reminders with the given token
        ->with(['token' => '456456'])
            ->andReturn($database)
            ->once()
            ->getMock()->shouldReceive('count')
            ->andReturn(1)
            ->once();

        $this->repo->app['MongoLidConnector'] = $database;

        $this->assertEquals(1, $this->repo->getPasswordRemindersCount('456456'));
    }

    public function testShouldGetPasswordReminderEmailByToken_legacy()
    {
        // Make sure that the password reminders collection will receive a first
        $database = $this->repo->app['MongoLidConnector'];

        $database->password_reminders = $database; // The collection that should be accessed

        $database->shouldReceive('findOne')// Should query for the password reminders collection by the given token
        ->with(['token' => '456456'], ['email'])
            ->andReturn('lol@sample.com')
            ->once();

        $this->repo->app['MongoLidConnector'] = $database;

        $this->assertEquals('lol@sample.com', $this->repo->getEmailByReminderToken('456456'));
    }

    public function testShouldDeletePasswordReminderEmailByToken_legacy()
    {
        // Make sure that the password reminders collection will receive a remove
        $database = $this->repo->app['MongoLidConnector'];

        $database->password_reminders = $database; // The collection that should be accessed

        $database->shouldReceive('remove')// Should remove by the given token
        ->with(['token' => '456456'])
            ->andReturn(null)
            ->once();

        $this->repo->app['MongoLidConnector'] = $database;

        $this->assertNull($this->repo->deleteEmailByReminderToken('456456'));
    }

    public function testShouldChangePassword_legacy()
    {
        // Make sure that the mock will have an _id
        $confideUser      = m::mock(new _mockedUser);
        $confideUser->_id = '123123';

        // Make sure that the password reminders collection will receive a update
        $database = $this->repo->app['MongoLidConnector'];

        $database->users = $database; // The collection that should be accessed

        $database->shouldReceive('update')// Should update the password of the user
        ->with(['_id' => '123123'], ['$set' => ['password' => 'secret']])
            ->andReturn(true)
            ->once();

        $this->repo->app['MongoLidConnector'] = $database;

        // Actually change the user password
        $this->assertTrue(
            $this->repo->changePassword($confideUser, 'secret')
        );
    }

    public function testShouldForgotPassword_legacy()
    {
        // Make sure that the mock will have an email
        $confideUser = m::mock(new _mockedUser);

        $confideUser->email = 'bob@sample.com';

        $timeStamp = new \DateTime;

        // Make sure that the password reminders collection will receive an insert
        $database = $this->repo->app['MongoLidConnector'];

        $database->password_reminders = $database; // The collection that should be accessed

        $database->shouldReceive('insert')// Should query for the password reminders with the given token
        ->andReturn(true)
            ->once();

        $this->repo->app['MongoLidConnector'] = $database;

        // Actually checks if the token is returned
        $this->assertNotNull(
            $this->repo->forgotPassword($confideUser)
        );
    }

    public function testUserExists_legacy()
    {
        // Make sure that the mock will have it's attributes
        $confideUser = m::mock(new _mockedUser);

        $confideUser->username = 'Bob';
        $confideUser->email    = 'bob@sample.com';

        // The query should be
        $query = [
            '$or' => [
                ['username' => $confideUser->username],
                ['email' => $confideUser->email],
            ],
        ];

        // Make sure that the password reminders collection will receive a find and count
        $database = $this->repo->app['MongoLidConnector'];

        $database->users = $database; // The collection that should be accessed

        $database->shouldReceive('find')// Should query for the password reminders with the given token
        ->with($query)
            ->andReturn($database)
            ->once()
            ->getMock()->shouldReceive('count')
            ->andReturn(1)
            ->once();

        $this->repo->app['MongoLidConnector'] = $database;

        // Actually checks if the user exists
        $this->assertEquals(
            1,
            $this->repo->userExists($confideUser)
        );
    }

    public function testShouldConfirmUser_legacy()
    {
        // Make sure that the mock will return an id
        $confideUser      = m::mock(new _mockedUser);
        $confideUser->_id = '123123';

        // Make sure that the password reminders collection will receive a update
        $database = $this->repo->app['MongoLidConnector'];

        $database->users = $database; // The collection that should be accessed

        $database->shouldReceive('update')// Should query for the password reminders with the given token
        ->with(['_id' => '123123'], ['$set' => ['confirmed' => 1]])
            ->andReturn(true)
            ->once();

        $this->repo->app['MongoLidConnector'] = $database;

        // Actually change the user password
        $this->assertTrue(
            $this->repo->confirmUser($confideUser)
        );
    }

    /**
     * Returns a mocked ConfideMongoUser object for testing purposes
     * only
     *
     * @param $configMock Config mock to be used
     *
     * @return ConfideMongoUser A mocked confide user
     */
    private function getConfideMongoUser()
    {
        $confideUser = m::mock(ConfideMongoUser::class)
            ->makePartial()
            ->shouldAllowMockingProtectedMethods();

        $confideUser->username  = 'uname';
        $confideUser->password  = '123123';
        $confideUser->confirmed = 0;

        return $confideUser;
    }
}


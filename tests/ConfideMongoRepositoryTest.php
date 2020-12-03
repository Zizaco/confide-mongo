<?php
namespace Zizaco\ConfideMongo;

use Illuminate\Contracts\Config\Repository as Config;
use Mockery as m;
use MongoDB\Client;
use MongoDB\Collection;

class ConfideMongoRepositoryTest extends TestCase
{
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

    public function testShouldGetPasswordRemindersCountByToken()
    {
        // Set
        $database   = m::mock(Client::class);
        $collection = m::mock(Collection::class);
        $repository = $this->getRepositoryWithDatabaseInteraction($database);

        $reminderCollection = 'password_reminders';

        $database->$reminderCollection = $collection;

        // Expectations
        $collection->shouldReceive('findOne')
            ->with(['token' => '456456'])
            ->andReturnSelf();

        $collection->shouldReceive('count')
            ->once()
            ->andReturn(1);

        // Actions
        $result = $repository->getPasswordRemindersCount('456456');

        // Assertions
        $this->assertEquals(1, $result);
    }

    public function testShouldGetPasswordReminderEmailByToken()
    {
        // Set
        $database    = m::mock(Client::class);
        $collection  = m::mock(Collection::class);
        $confideUser = $this->getConfideMongoUser();
        $repository  = $this->getRepositoryWithDatabaseInteraction($database);

        $confideUser->email = 'lol@sample.com';

        $reminderCollection = 'password_reminders';

        $database->$reminderCollection = $collection;

        // Expectations
        $collection->shouldReceive('findOne')
            ->once()
            ->with(['token' => '456456'], ['email'])
            ->andReturn($confideUser);

        // Actions
        $result = $repository->getEmailByReminderToken('456456');

        // Assertions
        $this->assertEquals('lol@sample.com', $result);
    }

    public function testShouldDeletePasswordReminderEmailByToken()
    {
        // Set
        $database   = m::mock(Client::class);
        $collection = m::mock(Collection::class);
        $repository = $this->getRepositoryWithDatabaseInteraction($database);

        $reminderCollection = 'password_reminders';

        $database->$reminderCollection = $collection;

        // Expectations
        $collection->shouldReceive('deleteOne')
            ->once()
            ->with(['token' => '456456']);

        // Actions
        $repository->deleteEmailByReminderToken('456456');
    }

    public function testShouldForgotPassword()
    {
        // Set
        $database    = m::mock(Client::class);
        $collection  = m::mock(Collection::class);
        $confideUser = $this->getConfideMongoUser();
        $repository  = $this->getRepositoryWithDatabaseInteraction($database);

        $confideUser->email = 'bob@sample.com';

        $reminderCollection = 'password_reminders';

        $database->$reminderCollection = $collection;

        // Expectations
        $collection->shouldReceive('insertOne')
            ->once()
            ->with(m::subset(['email' => 'bob@sample.com']))
            ->andReturn(true);

        // Actions
        $result = $repository->forgotPassword($confideUser);

        // Assertions
        $this->assertNotNull($result);
    }

    public function testUserExists()
    {
        // Set
        $confideUser = $this->getConfideMongoUser();
        $repository  = new ConfideMongoRepository;

        $confideUser->username = 'Bob';
        $confideUser->email    = 'bob@sample.com';

        $query = [
            '$or' => [
                ['username' => $confideUser->username],
                ['email' => $confideUser->email],
            ],
        ];

        // Expectations
        $confideUser->shouldReceive('where')
            ->once()
            ->with($query)
            ->andReturnSelf();

        $confideUser->shouldReceive('count')
            ->once()
            ->andReturn(1);

        // Actions
        $result = $repository->userExists($confideUser);

        // Assertions
        $this->assertEquals(1, $result);
    }

    /* Deprecated Functions Tests */

    public function testShouldChangePassword()
    {
        // Set
        $confideUser = $this->getConfideMongoUser();
        $repository  = new ConfideMongoRepository;

        // Expectations
        $confideUser->shouldReceive('save')
            ->once()
            ->andReturn(true);

        // Actions
        $result = $repository->changePassword($confideUser, 'secret');

        // Assertions
        $this->assertTrue($result);
    }

    public function testShouldConfirmUser()
    {
        // Set
        $repository  = new ConfideMongoRepository;
        $confideUser = $this->getConfideMongoUser();

        $confideUser->_id = '123123';

        // Expectations
        $confideUser->shouldReceive('confirm')
            ->once()
            ->andReturn(true);

        // Actions
        $result = $repository->confirmUser($confideUser);

        // Assertions
        $this->assertTrue($result);
    }

    /**
     * Make a partial mock that interacts with database.
     *
     * @param $mockMongoClient
     *
     * @return ConfideMongoRepository
     */
    protected function getRepositoryWithDatabaseInteraction($mockMongoClient)
    {
        $repository = m::mock(ConfideMongoRepository::class . '[database]')
            ->shouldAllowMockingProtectedMethods();

        $repository->shouldReceive('database')
            ->andReturn($mockMongoClient);

        return $repository;
    }

    /**
     * Returns a mocked ConfideMongoUser object for testing purposes
     * only.
     *
     * @return ConfideMongoUser A mocked confide user
     */
    protected function getConfideMongoUser()
    {
        $confideUser = m::mock(ConfideMongoUser::class)
            ->makePartial();

        return $confideUser;
    }
}


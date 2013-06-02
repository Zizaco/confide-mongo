<?php namespace Zizaco\ConfideMongo;

use Zizaco\Confide\ConfideServiceProvider;

class ConfideMongoServiceProvider extends ConfideServiceProvider {

    /**
     * Register the repository that will handle all the database interaction.
     *
     * @return void
     */
    protected function registerRepository()
    {
        $this->app->bind('confide.repository', function($app)
        {
            return new ConfideMongoRepository;
        });
    }
}

<?php namespace Zizaco\ConfideMongo;

use Zizaco\Confide\ServiceProvider as ConfideServiceProvider;

class ConfideMongoServiceProvider extends ConfideServiceProvider {

    /**
     * Bootstrap the service provider.
     *
     * @return void
     */
    public function boot()
    {
    }

    /**
     * Register the repository that will handle all the database interaction.
     *
     * @return void
     */
    public function register()
    {
        $this->app->bind('confide.repository', function($app)
        {
            return new ConfideMongoRepository;
        });
    }
}

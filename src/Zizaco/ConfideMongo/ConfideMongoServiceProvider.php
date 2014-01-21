<?php namespace Zizaco\ConfideMongo;

use Zizaco\Confide\ConfideServiceProvider;

class ConfideMongoServiceProvider extends ConfideServiceProvider {

    /**
     * Bootstrap the service provider.
     *
     * @return void
     */
    public function boot()
    {
        /*
            Explicitally define the confide's path, in order avoid
            wrong path resolutions.
         */
        $path = app_path() . "/../vendor/zizaco/confide/src";
        $this->package('zizaco/confide', 'confide', $path);
    }


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

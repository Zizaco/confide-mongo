<?php namespace Zizaco\ConfideMongo;

/**
 * A layer that abstracts all database interactions that happens
 * in Confide
 */
class ConfideMongoRepository
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
}

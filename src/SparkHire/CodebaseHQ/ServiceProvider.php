<?php

  namespace SparkHire\CodebaseHQ;

  use Airbrake;

  /**
   * CodebaseHQ Service Provider
   *
   * @package SparkHire\CodebaseHQ
   */
  class ServiceProvider extends \Illuminate\Support\ServiceProvider {


    /**
     * Register the service provider
     *
     * @return void
     */
    public function register() {
      // Build the airbrake object for posting exceptions to airbrake
      $this->app->singleton('codebasehq.airbrake', function($app) {

        // Settings
        $apiKey  = $app->make('config')->get('codebasehq::exceptions_key');
        $options = [
          'apiEndPoint'     => 'https://exceptions.codebasehq.com/notifier_api/v2/notices',
          'environmentName' => $app->environment(),
          'timeout'         => 10 // The default wasn't log enough in my tests
        ];

        // Instantiate airbrake
        $config = new Airbrake\Configuration($apiKey, $options);

        return new Airbrake\Client($config);
      });

      // Build the Request object which talks to codebase
      $this->app->singleton('codebasehq.request', function($app) {
        $config = $app->make('config');

        return new Request(
          $config->get('codebasehq::api.username'),
          $config->get('codebasehq::api.key'),
          $config->get('codebasehq::project')
        );
      });
    }


    /**
     * Boot it up
     */
    public function boot() {
      $app = $this->app;

      $this->publishes([
        __DIR__ . '/../../config/config.php' => config_path('codebasehq.php'),
      ]);

      // Listen for exception events and pass them to Codebase HQ.
      if ($app->make('config')->get('codebasehq::exception_logging')) {
        $app->error(function(\Exception $exception) use ($app) {

          // Exceptions to ignore
          foreach($app->make('config')->get('codebasehq::ignored_exceptions') as $class) {
            if (is_a($exception, $class)) {
              return;
            }
          }

          // Tell Codebase
          $app->make('codebasehq.airbrake')->notifyOnException($exception);
        });
      }

    }


    /**
     * Get the services provided by the provider
     *
     * @return array
     */
    public function provides() {
      return array(
        'codebasehq.airbrake',
        'codebasehq.request'
      );
    }


  }
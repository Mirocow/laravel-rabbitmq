<?php
namespace NeedleProject\LaravelRabbitMq\Providers;

use Illuminate\Support\ServiceProvider as LaravelServiceProvider;
use NeedleProject\LaravelRabbitMq\Builder\ContainerBuilder;
use NeedleProject\LaravelRabbitMq\Command\BaseConsumerCommand;
use NeedleProject\LaravelRabbitMq\Command\BasePublisherCommand;
use NeedleProject\LaravelRabbitMq\Command\DeleteAllCommand;
use NeedleProject\LaravelRabbitMq\Command\SetupCommand;
use NeedleProject\LaravelRabbitMq\Command\ListEntitiesCommand;
use NeedleProject\LaravelRabbitMq\ConfigHelper;
use NeedleProject\LaravelRabbitMq\ConsumerInterface;
use NeedleProject\LaravelRabbitMq\Container;
use NeedleProject\LaravelRabbitMq\Exception\LaravelRabbitMqException;
use NeedleProject\LaravelRabbitMq\PublisherInterface;
use Laravel\Lumen\Application as LumenApplication;
use Illuminate\Foundation\Application as LaravelApplication;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;

/**
 * Class ServiceProvider
 *
 * @package NeedleProject\LaravelRabbitMq\Providers
 * @author  Adrian Tilita <adrian@tilita.ro>
 */
class ServiceProvider extends LaravelServiceProvider
{
    /**
     * Indicates if loading of the provider is deferred.
     *
     * @var bool
     */
    protected $defer = false;

    /**
     * Perform post-registration booting of services.
     *
     * @return void
     * @throws LaravelRabbitMqException
     */
    public function boot()
    {
        $this->publishConfig();
        $this->registerContainer();
        $this->registerPublishers();
        $this->registerConsumers();
        $this->registerCommands();
    }

    /**
     * Publish Config
     */
    private function publishConfig()
    {
        $source = realpath(__DIR__.'/../../config/laravel_rabbitmq.php');

        if ($this->app instanceof LaravelApplication) {
            $this->publishes([$source => config_path('laravel_rabbitmq.php')]);
        } elseif ($this->app instanceof LumenApplication) {
            $this->app->configure('laravel_rabbitmq');
        }

        $this->mergeConfigFrom($source, 'laravel_rabbitmq');
    }

    /**
     * Create container and register binding
     */
    private function registerContainer()
    {
        $config = config('laravel_rabbitmq', []);
        if (!is_array($config)) {
            throw new \RuntimeException(
                "Invalid configuration provided for LaravelRabbitMQ!"
            );
        }
        $configHelper = new ConfigHelper();
        $config = $configHelper->addDefaults($config);

        $this->app->singleton(
            Container::class,
            function () use ($config) {
                $container = new ContainerBuilder();
                return $container->createContainer($config);
            }
        );
    }

    /**
     * Register publisher bindings
     */
    private function registerPublishers()
    {
        // Get "tagged" like Publisher
        $this->app->singleton(PublisherInterface::class, function ($application, $arguments) {
            /** @var Container $container */
            $container = $application->make(Container::class);
            if (empty($arguments)) {
                throw new \RuntimeException("Cannot make Publisher. No publisher identifier provided!");
            }
            $aliasName = $arguments[0];
            return $container->getPublisher($aliasName);
        });
    }

    /**
     * Register consumer bindings
     */
    private function registerConsumers()
    {
        // Get "tagged" like Consumers
        $this->app->singleton(ConsumerInterface::class, function ($application, $arguments) {
            /** @var Container $container */
            $container = $application->make(Container::class);
            if (empty($arguments)) {
                throw new \RuntimeException("Cannot make Consumer. No consumer identifier provided!");
            }
            $aliasName = $arguments[0];

            if (!$container->hasConsumer($aliasName)) {
                throw new \RuntimeException("Cannot make Consumer.\nNo consumer with alias name {$aliasName} found!");
            }
            /** @var LoggerAwareInterface $consumer */
            $consumer = $container->getConsumer($aliasName);
            $consumer->setLogger($application->make(LoggerInterface::class));
            return $consumer;
        });
    }

    /**
     * Register commands
     */
    private function registerCommands()
    {
        $this->commands([
            SetupCommand::class,
            ListEntitiesCommand::class,
            BaseConsumerCommand::class,
            DeleteAllCommand::class,
            BasePublisherCommand::class
        ]);
    }
}

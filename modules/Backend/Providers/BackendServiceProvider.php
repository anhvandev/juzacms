<?php

namespace Juzaweb\Backend\Providers;

use Illuminate\Foundation\AliasLoader;
use Illuminate\Routing\Router;
use Juzaweb\Backend\Actions\BackupAction;
use Juzaweb\Backend\Actions\EnqueueStyleAction;
use Juzaweb\Backend\Actions\MenuAction;
use Juzaweb\Backend\Actions\PermissionAction;
use Juzaweb\Backend\Actions\SeoAction;
use Juzaweb\Backend\Actions\SocialLoginAction;
use Juzaweb\Backend\Actions\ToolAction;
use Juzaweb\Backend\Commands\AutoSubmitCommand;
use Juzaweb\Backend\Commands\EmailTemplateGenerateCommand;
use Juzaweb\Backend\Commands\FindTransCommand;
use Juzaweb\Backend\Commands\PermissionGenerateCommand;
use Juzaweb\Backend\Commands\ThemePublishCommand;
use Juzaweb\Backend\Commands\TransFromEnglish;
use Juzaweb\Backend\Models\Comment;
use Juzaweb\Backend\Models\Menu;
use Juzaweb\Backend\Models\Post;
use Juzaweb\Backend\Models\Taxonomy;
use Juzaweb\Backend\Observers\CommentObserver;
use Juzaweb\Backend\Observers\MenuObserver;
use Juzaweb\Backend\Observers\PostObserver;
use Juzaweb\Backend\Observers\TaxonomyObserver;
use Juzaweb\Backend\Repositories\CommentRepository;
use Juzaweb\Backend\Repositories\CommentRepositoryEloquent;
use Juzaweb\Backend\Repositories\MediaFileRepository;
use Juzaweb\Backend\Repositories\MediaFileRepositoryEloquent;
use Juzaweb\Backend\Repositories\MediaFolderRepository;
use Juzaweb\Backend\Repositories\NotificationRepository;
use Juzaweb\Backend\Repositories\NotificationRepositoryEloquent;
use Juzaweb\Backend\Repositories\PostRepository;
use Juzaweb\Backend\Repositories\PostRepositoryEloquent;
use Juzaweb\Backend\Repositories\TaxonomyRepository;
use Juzaweb\Backend\Repositories\TaxonomyRepositoryEloquent;
use Juzaweb\Backend\Repositories\UserRepository;
use Juzaweb\Backend\Repositories\UserRepositoryEloquent;
use Juzaweb\CMS\Facades\ActionRegister;
use Juzaweb\CMS\Http\Middleware\Admin;
use Juzaweb\CMS\Support\Html\Field;
use Juzaweb\CMS\Support\Macros\RouterMacros;
use Juzaweb\CMS\Support\ServiceProvider;

class BackendServiceProvider extends ServiceProvider
{
    public array $bindings = [
        PostRepository::class => PostRepositoryEloquent::class,
        TaxonomyRepository::class => TaxonomyRepositoryEloquent::class,
        CommentRepository::class => CommentRepositoryEloquent::class,
        UserRepository::class => UserRepositoryEloquent::class,
        MediaFileRepository::class => MediaFileRepositoryEloquent::class,
        MediaFolderRepository::class => MediaFileRepositoryEloquent::class,
        NotificationRepository::class => NotificationRepositoryEloquent::class
    ];

    public function boot()
    {
        $this->bootMiddlewares();
        $this->bootPublishes();

        Taxonomy::observe(TaxonomyObserver::class);
        Post::observe(PostObserver::class);
        Menu::observe(MenuObserver::class);
        Comment::observe(CommentObserver::class);

        ActionRegister::register(
            [
                MenuAction::class,
                EnqueueStyleAction::class,
                PermissionAction::class,
                SocialLoginAction::class,
                ToolAction::class,
                SeoAction::class,
                BackupAction::class
            ]
        );

        $this->commands(
            [
                PermissionGenerateCommand::class,
                FindTransCommand::class,
                TransFromEnglish::class,
                EmailTemplateGenerateCommand::class,
                ThemePublishCommand::class,
                AutoSubmitCommand::class,
            ]
        );
    }

    public function register()
    {
        $this->app->register(RouteServiceProvider::class);
        $this->app->register(AuthServiceProvider::class);
        $this->registerRouteMacros();
        $this->app->booting(
            function () {
                $loader = AliasLoader::getInstance();
                $loader->alias('Field', Field::class);
            }
        );
    }

    protected function bootMiddlewares()
    {
        /**
         * @var Router $router
         */
        $router = $this->app['router'];
        $router->aliasMiddleware('admin', Admin::class);
    }

    protected function bootPublishes()
    {
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'cms');
        $this->loadTranslationsFrom(__DIR__ . '/../resources/lang', 'cms');

        $this->publishes(
            [
                __DIR__ . '/../resources/lang' => resource_path('lang/cms'),
            ],
            'cms_lang'
        );

        $this->publishes(
            [
                __DIR__ . '/../resources/assets/public' => public_path('jw-styles/juzaweb'),
            ],
            'cms_assets'
        );
    }

    protected function registerRouteMacros()
    {
        Router::mixin(new RouterMacros());
    }
}

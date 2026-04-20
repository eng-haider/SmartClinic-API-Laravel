<?php

namespace App\Providers;

use App\Events\CaseCompleted;
use App\Events\CaseCreated;
use App\Listeners\HandleCaseCompletedAutomation;
use App\Listeners\HandleCaseCreatedAutomation;
use App\Services\Messaging\AutomationEngine;
use App\Services\Messaging\ChannelManager;
use App\Services\Messaging\MessageService;
use App\Services\Messaging\TemplateEngine;
use App\Services\Messaging\WebhookService;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class MessagingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Register as singletons so they're shared within a request
        $this->app->singleton(ChannelManager::class);
        $this->app->singleton(TemplateEngine::class);
        $this->app->singleton(AutomationEngine::class);

        $this->app->singleton(MessageService::class, function ($app) {
            return new MessageService(
                $app->make(ChannelManager::class),
                $app->make(TemplateEngine::class),
            );
        });

        $this->app->singleton(WebhookService::class, function ($app) {
            return new WebhookService(
                $app->make(MessageService::class),
            );
        });
    }

    public function boot(): void
    {
        // Register event listeners for automation triggers
        Event::listen(CaseCreated::class, HandleCaseCreatedAutomation::class);
        Event::listen(CaseCompleted::class, HandleCaseCompletedAutomation::class);
    }
}

<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use App\Policies\UserPolicy;
use App\Policies\ThreadPolicy;
use App\Policies\ResponsePolicy;
use App\Policies\FriendRequestPolicy;
use App\Policies\ReportPolicy;
use App\Policies\AdminPolicy;
use App\Policies\AdminMessagePolicy;
use App\Models\User;
use App\Models\Thread;
use App\Models\Response;
use App\Models\FriendRequest;
use App\Models\Report;
use App\Models\AdminMessage;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        User::class => UserPolicy::class,
        Thread::class => ThreadPolicy::class,
        Response::class => ResponsePolicy::class,
        FriendRequest::class => FriendRequestPolicy::class,
        Report::class => ReportPolicy::class,
        AdminMessage::class => AdminMessagePolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        $this->registerPolicies();
    }
}

<?php

namespace App\Providers;

use Illuminate\Support\Facades\Gate;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use App\Models\User;
use App\Models\PurchaseRequest;
use App\Policies\PurchaseRequestPolicy;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        // 'App\Models\Model' => 'App\Policies\ModelPolicy',
        PurchaseRequest::class => PurchaseRequestPolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        $this->registerPolicies();

        // Gate để kiểm tra người dùng có phải là Quản trị viên (rank 0) không
        Gate::define('is-admin', function (User $user) {
            return $user->assignments()->whereHas('approvalRank', function ($query) {
                $query->where('rank_level', 0);
            })->exists();
        });

        // Gate để kiểm tra người dùng có phải là người tạo phiếu (Cấp 1)
        Gate::define('is-level-1', function (User $user) {
            return $user->assignments()->whereHas('approvalRank', function ($query) {
                $query->where('rank_level', 1);
            })->exists();
        });

        /**
         * Gate để kiểm tra người dùng có quyền duyệt phiếu (Cấp 2 trở lên)
         */
        Gate::define('can-approve', function (User $user) {
            // Kiểm tra xem người dùng có bất kỳ bản phân công nào với rank_level >= 2 không
            return $user->assignments()->whereHas('approvalRank', function ($query) {
                $query->where('rank_level', '>=', 2);
            })->exists();
        });
    }
}

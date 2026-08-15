<?php

namespace App\Http\Controllers;

use App\Models\HomepageFeaturedPhone;
use App\Models\HomepageSlide;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $currentUser = Auth::user();

        $earlierUsersCount = User::where('created_at', '<', $currentUser->created_at)->count();
        $sameTimeUsersCountBeforeCurrent = User::where('created_at', $currentUser->created_at)
            ->where('id', '<', $currentUser->id)
            ->count();

        $canAccessAdmin = $currentUser->canAccessAdmin();
        $productCounts = $canAccessAdmin
            ? Product::statusCounts()
            : ['total' => 0, 'published' => 0, 'draft' => 0];

        return view('dashboard', [
            'userRank' => $earlierUsersCount + $sameTimeUsersCountBeforeCurrent + 1,
            'totalUsers' => User::count(),
            'totalProducts' => $productCounts['total'],
            'publishedProducts' => $productCounts['published'],
            'draftProducts' => $productCounts['draft'],
            'activeFeaturedPhones' => $canAccessAdmin
                ? HomepageFeaturedPhone::where('is_active', true)->count()
                : 0,
            'activeHomepageSlides' => $canAccessAdmin
                ? HomepageSlide::where('is_active', true)->count()
                : 0,
        ]);
    }
}

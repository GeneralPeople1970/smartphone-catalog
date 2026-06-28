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

        $productCounts = Product::statusCounts();

        return view('dashboard', [
            'userRank' => $earlierUsersCount + $sameTimeUsersCountBeforeCurrent + 1,
            'totalUsers' => User::count(),
            'totalProducts' => $productCounts['total'],
            'publishedProducts' => $productCounts['published'],
            'draftProducts' => $productCounts['draft'],
            'activeFeaturedPhones' => HomepageFeaturedPhone::where('is_active', true)->count(),
            'activeHomepageSlides' => HomepageSlide::where('is_active', true)->count(),
        ]);
    }
}

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

        return view('dashboard', [
            'userRank' => $earlierUsersCount + $sameTimeUsersCountBeforeCurrent + 1,
            'totalUsers' => User::count(),
            'totalProducts' => Product::count(),
            'publishedProducts' => Product::where('status', 'published')->count(),
            'draftProducts' => Product::where('status', 'draft')->count(),
            'activeFeaturedPhones' => HomepageFeaturedPhone::where('is_active', true)->count(),
            'activeHomepageSlides' => HomepageSlide::where('is_active', true)->count(),
        ]);
    }
}

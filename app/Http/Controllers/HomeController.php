<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Contracts\View\View;

class HomeController extends Controller
{
    public function index(): View
    {
        $users = User::query()
            ->latest()
            ->paginate(10);

        return view('home.index', compact('users'));
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Auth;

class InertiaController extends Controller
{
    //
    public function test() {
        return Inertia::render('InertiaTest', ['username' => 'Jesus' ]);
    }
}

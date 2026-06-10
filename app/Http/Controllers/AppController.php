<?php

namespace App\Http\Controllers;

use App\Supports\TheOneResponse;
use Inertia\Inertia;
use Laravel\Fortify\Features;

class AppController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return Inertia::render('welcome', [
            'canRegister' => Features::enabled(Features::registration()),
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function dashboard()
    {
        return Inertia::render('dashboard');
    }

    public function ping()
    {
        return TheOneResponse::ok(['data' => 'pong']);
    }

    public function dev()
    {
        return Inertia::render('dev');
    }
}

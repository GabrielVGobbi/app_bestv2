<?php

namespace App\Http\Controllers;

use App\Actions\Dashboard\BuildOperationalDashboard;
use App\Models\User;
use App\Supports\TheOneResponse;
use Inertia\Inertia;
use Inertia\Response;
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
    public function dashboard(BuildOperationalDashboard $dashboard): Response
    {
        /** @var User $user */
        $user = request()->user();

        return Inertia::render('dashboard', [
            'operationalDashboard' => Inertia::defer(
                fn (): array => $dashboard->handle($user),
            ),
        ]);
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

<?php

namespace App\Http\Controllers;

use App\Supports\ActivityActions\ActivityActionRegistry;
use Inertia\Inertia;
use Inertia\Response;

class ActivityLogController extends Controller
{
    public function index(ActivityActionRegistry $activityActions): Response
    {
        return Inertia::render('app/activity-log/index', [
            'activityActionCatalog' => $activityActions->catalog(),
        ]);
    }
}

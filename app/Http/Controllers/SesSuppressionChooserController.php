<?php

namespace App\Http\Controllers;

use App\Services\ProjectAccessService;
use Illuminate\View\View;

class SesSuppressionChooserController extends Controller
{
    public function index(ProjectAccessService $projectService): View
    {
        $accessibleProjects = $projectService->getAccessibleProjects(auth()->user());

        return view('ses-suppression.chooser', compact('accessibleProjects'));
    }
}

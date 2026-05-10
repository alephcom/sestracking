<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Support\SesSuppressionHttpHandler;
use App\Models\Project;
use App\Services\SesSuppressedDestinationLister;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProjectSesSuppressionController extends Controller
{
    public function __construct(
        private readonly SesSuppressionHttpHandler $handler,
        private readonly SesSuppressedDestinationLister $lister
    ) {}

    public function index(Request $request, Project $project): View
    {
        $this->authorize('update', $project);

        $error = null;
        $destinations = null;
        if (! $project->canRunSesSuppressionApi()) {
            $error = SesSuppressionHttpHandler::USER_FACING_CONFIG_ERROR;
        } else {
            $destinations = $this->lister->paginate($project, $request);
        }

        return view('admin.projects.ses-suppression.index', [
            'project' => $project,
            'destinations' => $destinations,
            'error' => $error,
            'indexRoute' => 'admin.projects.ses-suppression.index',
            'storeRoute' => 'admin.projects.ses-suppression.store',
            'destroyRoute' => 'admin.projects.ses-suppression.destroy',
            'backUrl' => route('admin.projects.edit', $project),
            'backLabel' => 'Back to project',
        ]);
    }

    public function store(Request $request, Project $project): RedirectResponse
    {
        $this->authorize('update', $project);

        $data = $request->validate([
            'email' => ['required', 'email'],
            'reason' => ['required', 'in:BOUNCE,COMPLAINT'],
        ]);

        $result = $this->handler->store($project, $data['email'], $data['reason']);

        $redirect = redirect()->route(
            'admin.projects.ses-suppression.index',
            array_merge(['project' => $project], $this->handler->redirectListQuery($request))
        );

        return $result['type'] === 'success'
            ? $redirect->with('success', $result['message'])
            : $redirect->with('error', $result['message']);
    }

    public function destroy(Request $request, Project $project): RedirectResponse
    {
        $this->authorize('update', $project);

        $data = $request->validate([
            'email' => ['required', 'email'],
        ]);

        $result = $this->handler->destroy($project, $data['email']);

        $redirect = redirect()->route(
            'admin.projects.ses-suppression.index',
            array_merge(['project' => $project], $this->handler->redirectListQuery($request))
        );

        return $result['type'] === 'success'
            ? $redirect->with('success', $result['message'])
            : $redirect->with('error', $result['message']);
    }
}

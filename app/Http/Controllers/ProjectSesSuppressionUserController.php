<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Support\SesSuppressionHttpHandler;
use App\Models\Project;
use App\Services\SesSuppressedDestinationLister;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProjectSesSuppressionUserController extends Controller
{
    public function __construct(
        private readonly SesSuppressionHttpHandler $handler,
        private readonly SesSuppressedDestinationLister $lister
    ) {}

    public function index(Request $request, Project $project): View
    {
        $this->authorize('view', $project);

        $error = null;
        $destinations = null;
        if (! $project->canRunSesSuppressionApi()) {
            $error = SesSuppressionHttpHandler::USER_FACING_CONFIG_ERROR;
        } else {
            $destinations = $this->lister->paginate($project, $request);
        }

        return view('ses-suppression.index', [
            'project' => $project,
            'destinations' => $destinations,
            'error' => $error,
            'indexRoute' => 'ses-suppression.index',
            'storeRoute' => 'ses-suppression.store',
            'destroyRoute' => 'ses-suppression.destroy',
            'backUrl' => route('ses-suppression.chooser'),
            'backLabel' => 'All projects',
        ]);
    }

    public function store(Request $request, Project $project): RedirectResponse
    {
        $this->authorize('view', $project);

        $data = $request->validate([
            'email' => ['required', 'email'],
            'reason' => ['required', 'in:BOUNCE,COMPLAINT'],
        ]);

        $result = $this->handler->store($project, $data['email'], $data['reason']);

        $redirect = redirect()->route(
            'ses-suppression.index',
            array_merge(['project' => $project], $this->handler->redirectListQuery($request))
        );

        return $result['type'] === 'success'
            ? $redirect->with('success', $result['message'])
            : $redirect->with('error', $result['message']);
    }

    public function destroy(Request $request, Project $project): RedirectResponse
    {
        $this->authorize('view', $project);

        $data = $request->validate([
            'email' => ['required', 'email'],
        ]);

        $result = $this->handler->destroy($project, $data['email']);

        $redirect = redirect()->route(
            'ses-suppression.index',
            array_merge(['project' => $project], $this->handler->redirectListQuery($request))
        );

        return $result['type'] === 'success'
            ? $redirect->with('success', $result['message'])
            : $redirect->with('error', $result['message']);
    }
}

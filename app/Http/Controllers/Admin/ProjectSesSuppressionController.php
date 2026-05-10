<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Services\SesSuppressionService;
use Aws\Exception\AwsException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use InvalidArgumentException;

class ProjectSesSuppressionController extends Controller
{
    private const USER_FACING_CONFIG_ERROR = 'Per-project AWS Access Key ID, Secret Access Key, and a resolvable region are required for the suppression list (global .env keys are not used). Configure them on the project edit screen.';

    public function __construct(
        private readonly SesSuppressionService $suppression
    ) {}

    public function index(Request $request, Project $project): View
    {
        $this->authorize('update', $project);

        $summaries = [];
        $nextToken = null;
        $error = null;

        try {
            $page = $this->suppression->listSuppressedDestinations(
                $project,
                $request->query('next_token'),
                25
            );
            $summaries = $page['summaries'];
            $nextToken = $page['next_token'];
        } catch (InvalidArgumentException $e) {
            Log::warning('ProjectSesSuppressionController: index configuration error', [
                'project_id' => $project->id,
                'message' => $e->getMessage(),
            ]);
            $error = self::USER_FACING_CONFIG_ERROR;
        } catch (AwsException $e) {
            Log::warning('ProjectSesSuppressionController: index AWS error', [
                'project_id' => $project->id,
                'aws_error_code' => $e->getAwsErrorCode(),
                'message' => $e->getMessage(),
            ]);
            $error = $e->getMessage();
        }

        return view('admin.projects.ses-suppression.index', [
            'project' => $project,
            'summaries' => $summaries,
            'nextToken' => $nextToken,
            'error' => $error,
        ]);
    }

    public function store(Request $request, Project $project): RedirectResponse
    {
        $this->authorize('update', $project);

        $data = $request->validate([
            'email' => ['required', 'email'],
            'reason' => ['required', 'in:BOUNCE,COMPLAINT'],
        ]);

        try {
            $this->suppression->putSuppressedDestination(
                $project,
                strtolower($data['email']),
                $data['reason']
            );
        } catch (InvalidArgumentException $e) {
            Log::warning('ProjectSesSuppressionController: store configuration error', [
                'project_id' => $project->id,
                'message' => $e->getMessage(),
            ]);

            return redirect()
                ->route('admin.projects.ses-suppression.index', $project)
                ->with('error', self::USER_FACING_CONFIG_ERROR);
        } catch (AwsException $e) {
            return redirect()
                ->route('admin.projects.ses-suppression.index', $project)
                ->with('error', 'Could not add address: '.$e->getMessage());
        }

        return redirect()
            ->route('admin.projects.ses-suppression.index', $project)
            ->with('success', 'Address added to the suppression list.');
    }

    public function destroy(Request $request, Project $project): RedirectResponse
    {
        $this->authorize('update', $project);

        $data = $request->validate([
            'email' => ['required', 'email'],
        ]);

        try {
            $this->suppression->deleteSuppressedDestination($project, strtolower($data['email']));
        } catch (InvalidArgumentException $e) {
            Log::warning('ProjectSesSuppressionController: destroy configuration error', [
                'project_id' => $project->id,
                'message' => $e->getMessage(),
            ]);

            return redirect()
                ->route('admin.projects.ses-suppression.index', $project)
                ->with('error', self::USER_FACING_CONFIG_ERROR);
        } catch (AwsException $e) {
            if ($e->getAwsErrorCode() === 'NotFoundException') {
                return redirect()
                    ->route('admin.projects.ses-suppression.index', $project)
                    ->with('success', 'Address was not on the list (already removed).');
            }

            return redirect()
                ->route('admin.projects.ses-suppression.index', $project)
                ->with('error', 'Could not remove address: '.$e->getMessage());
        }

        return redirect()
            ->route('admin.projects.ses-suppression.index', $project)
            ->with('success', 'Address removed from the suppression list.');
    }
}

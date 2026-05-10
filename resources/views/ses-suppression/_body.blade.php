<div class="mb-3">
    <a href="{{ $backUrl }}" class="btn btn-outline-secondary btn-sm">
        <i class="fas fa-arrow-left"></i> {{ $backLabel }}
    </a>
</div>

@if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif
@if(session('error'))
    <div class="alert alert-danger">{{ session('error') }}</div>
@endif

<div class="row">
    <div class="col-lg-10">
        @include('admin.projects.partials.ses-suppression-iam-requirements')

        <div class="card mt-3">
            <div class="card-header">Add address</div>
            <div class="card-body">
                <form method="post" action="{{ route($storeRoute, $project) }}" class="row g-3 align-items-end">
                    @csrf
                    <div class="col-md-6">
                        <label class="form-label" for="add-email">Email</label>
                        <input type="email" class="form-control @error('email') is-invalid @enderror" id="add-email" name="email" value="{{ old('email') }}" required>
                        @error('email')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="col-md-3">
                        <label class="form-label" for="add-reason">Reason</label>
                        <select class="form-select @error('reason') is-invalid @enderror" id="add-reason" name="reason" required>
                            <option value="BOUNCE" @selected(old('reason', 'BOUNCE') === 'BOUNCE')>BOUNCE</option>
                            <option value="COMPLAINT" @selected(old('reason', 'BOUNCE') === 'COMPLAINT')>COMPLAINT</option>
                        </select>
                        @error('reason')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="col-md-3">
                        <button type="submit" class="btn btn-primary w-100">Add to list</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card mt-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span>Suppressed destinations</span>
                @if($nextToken)
                    <a class="btn btn-sm btn-outline-primary" href="{{ route($indexRoute, ['project' => $project, 'next_token' => $nextToken]) }}">Next page</a>
                @endif
            </div>
            <div class="card-body p-0">
                @if($error)
                    <div class="alert alert-warning m-3 mb-0">{{ $error }}</div>
                @elseif(count($summaries) === 0)
                    <p class="p-3 mb-0 text-muted">No entries returned (or list is empty).</p>
                @else
                    <div class="table-responsive">
                        <table class="table table-striped mb-0">
                            <thead>
                                <tr>
                                    <th>Email</th>
                                    <th>Reason</th>
                                    <th>Last update</th>
                                    <th class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($summaries as $row)
                                    <tr>
                                        <td>{{ $row['email'] }}</td>
                                        <td>{{ $row['reason'] }}</td>
                                        <td>{{ $row['last_update_time'] }}</td>
                                        <td class="text-end">
                                            <form method="post" action="{{ route($destroyRoute, $project) }}" class="d-inline" onsubmit="return confirm('Remove this address from the SES suppression list?');">
                                                @csrf
                                                @method('DELETE')
                                                <input type="hidden" name="email" value="{{ $row['email'] }}">
                                                <button type="submit" class="btn btn-sm btn-outline-danger">Remove</button>
                                            </form>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>

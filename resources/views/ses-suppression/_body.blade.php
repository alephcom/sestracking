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
                    @foreach(['q', 'sort', 'direction', 'page'] as $__rp)
                        @if(request()->filled($__rp))
                            <input type="hidden" name="{{ $__rp }}" value="{{ request($__rp) }}">
                        @endif
                    @endforeach
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
            <div class="card-header">
                <span>Suppressed destinations</span>
            </div>
            <div class="card-body border-bottom py-3">
                @if($error)
                    <div class="alert alert-warning mb-0">{{ $error }}</div>
                @else
                    <p class="small text-muted mb-2">
                        This table shows the last import from Amazon SES into this app
                        @if($project->ses_suppression_list_synced_at)
                            ({{ $project->ses_suppression_list_synced_at->timezone(config('app.timezone'))->format('Y-m-d H:i') }} {{ config('app.timezone') }}).
                        @else
                            (not synced yet; run the scheduled import or <code>php artisan ses:sync-suppression-list</code> on the server).
                        @endif
                        Add and remove actions apply immediately in AWS; new rows appear here after sync or after a successful add.
                    </p>
                    <form method="get" action="{{ route($indexRoute, $project) }}" class="row g-2 align-items-end">
                        <div class="col-md-8">
                            <label class="form-label visually-hidden" for="suppression-search-q">Search email</label>
                            <input type="text" class="form-control" id="suppression-search-q" name="q" value="{{ request('q') }}" placeholder="Search by email…">
                        </div>
                        <div class="col-md-2">
                            <input type="hidden" name="sort" value="{{ request('sort', 'last_update_time') }}">
                            <input type="hidden" name="direction" value="{{ request('direction', 'desc') }}">
                            <button type="submit" class="btn btn-outline-secondary w-100">Search</button>
                        </div>
                        @if(request()->filled('q'))
                            <div class="col-md-2">
                                <a href="{{ route($indexRoute, array_merge(['project' => $project], request()->only(['sort', 'direction']))) }}" class="btn btn-link w-100">Clear</a>
                            </div>
                        @endif
                    </form>
                @endif
            </div>
            <div class="card-body p-0">
                @if($error)
                @elseif($destinations->total() === 0 && ! request()->filled('q'))
                    <p class="p-3 mb-0 text-muted">No addresses in the synced list yet, or the list is empty in AWS.</p>
                @elseif($destinations->total() === 0)
                    <p class="p-3 mb-0 text-muted">No addresses match your search.</p>
                @else
                    <div class="table-responsive">
                        <table class="table table-striped mb-0">
                            <thead>
                                <tr>
                                    <th>
                                        <a href="{{ route($indexRoute, array_merge(['project' => $project], array_filter(request()->only('q', 'page')), \App\Services\SesSuppressedDestinationLister::nextSortQuery(request(), 'email'))) }}" class="text-decoration-none text-dark">Email</a>
                                    </th>
                                    <th>
                                        <a href="{{ route($indexRoute, array_merge(['project' => $project], array_filter(request()->only('q', 'page')), \App\Services\SesSuppressedDestinationLister::nextSortQuery(request(), 'reason'))) }}" class="text-decoration-none text-dark">Reason</a>
                                    </th>
                                    <th>
                                        <a href="{{ route($indexRoute, array_merge(['project' => $project], array_filter(request()->only('q', 'page')), \App\Services\SesSuppressedDestinationLister::nextSortQuery(request(), 'last_update_time'))) }}" class="text-decoration-none text-dark">Last update</a>
                                    </th>
                                    <th class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($destinations as $row)
                                    <tr>
                                        <td>{{ $row->email }}</td>
                                        <td>{{ $row->reason }}</td>
                                        <td>{{ $row->last_update_time?->timezone(config('app.timezone'))->format('Y-m-d H:i:s') ?? '—' }}</td>
                                        <td class="text-end">
                                            <form method="post" action="{{ route($destroyRoute, $project) }}" class="d-inline" onsubmit="return confirm('Remove this address from the SES suppression list?');">
                                                @csrf
                                                @method('DELETE')
                                                @foreach(['q', 'sort', 'direction', 'page'] as $__rp)
                                                    @if(request()->filled($__rp))
                                                        <input type="hidden" name="{{ $__rp }}" value="{{ request($__rp) }}">
                                                    @endif
                                                @endforeach
                                                <input type="hidden" name="email" value="{{ $row->email }}">
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
            @if(! $error && $destinations->hasPages())
                <div class="card-footer">
                    {{ $destinations->links() }}
                </div>
            @endif
        </div>
    </div>
</div>

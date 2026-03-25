@extends('layouts.master')

@section('site-title')
    Reports
@endsection

@section('h1')
    <h1 class="h2">Reports</h1>
@endsection

@section('page-content')

@if($accessibleProjects->isNotEmpty())
    <!-- Filters -->
    <div class="card mb-4 shadow-sm">
        <div class="card-header bg-white border-bottom">
            <h5 class="mb-0">
                <i class="fas fa-filter me-2"></i>Report Criteria
            </h5>
        </div>
        <div class="card-body p-4">
            <div class="row">
                <!-- Project Selection -->
                <div class="col-md-6 mb-3 mb-md-0">
                    <label for="report_project_selector" class="form-label fw-bold mb-2">
                        <i class="fas fa-project-diagram me-2"></i>Projects
                    </label>
                    <select id="report_project_selector" class="form-select" multiple size="8" style="min-height: 200px;">
                        <option value="all" selected>All Projects</option>
                        @foreach($accessibleProjects as $project)
                            <option value="{{ $project->id }}">{{ $project->name }}</option>
                        @endforeach
                    </select>
                    <small class="form-text text-muted mt-2 d-block">
                        <i class="fas fa-info-circle me-1"></i>
                        Hold <kbd>Ctrl</kbd> (or <kbd>Cmd</kbd> on Mac) to select multiple projects. "All Projects" will be automatically deselected when selecting specific projects.
                    </small>
                </div>
                
                <!-- Date Range -->
                <div class="col-md-6">
                    <label class="form-label fw-bold mb-2">
                        <i class="fas fa-calendar-alt me-2"></i>Date Range
                    </label>
                    <div class="d-flex flex-column gap-3">
                        <div>
                            <label for="date_from" class="form-label">From Date</label>
                            <input type="date" id="date_from" class="form-control" value="{{ $defaultStartDate }}">
                        </div>
                        <div>
                            <label for="date_to" class="form-label">To Date</label>
                            <input type="date" id="date_to" class="form-control" value="{{ $defaultEndDate }}">
                        </div>
                        <div class="mt-2">
                            <button type="button" id="apply_filters" class="btn btn-primary w-100">
                                <i class="fas fa-search me-2"></i>Generate Reports
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Report Tabs -->
    <ul class="nav nav-tabs mb-4" id="reportTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="emails-tab" data-bs-toggle="tab" data-bs-target="#emails-report" type="button" role="tab" aria-controls="emails-report" aria-selected="true">
                <i class="fas fa-envelope me-2"></i>Email Report
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="recipients-tab" data-bs-toggle="tab" data-bs-target="#recipients-report" type="button" role="tab" aria-controls="recipients-report" aria-selected="false">
                <i class="fas fa-users me-2"></i>Recipients Report
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="senders-tab" data-bs-toggle="tab" data-bs-target="#senders-report" type="button" role="tab" aria-controls="senders-report" aria-selected="false">
                <i class="fas fa-paper-plane me-2"></i>Senders Report
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="bounced-tab" data-bs-toggle="tab" data-bs-target="#bounced-report" type="button" role="tab" aria-controls="bounced-report" aria-selected="false">
                <i class="fas fa-exclamation-triangle me-2"></i>Bounced Recipients
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="unsubscribes-tab" data-bs-toggle="tab" data-bs-target="#unsubscribes-report" type="button" role="tab" aria-controls="unsubscribes-report" aria-selected="false">
                <i class="fas fa-user-slash me-2"></i>Unsubscribes
            </button>
        </li>
    </ul>

    <!-- Tab Content -->
    <div class="tab-content" id="reportTabContent">
        <!-- Emails Report -->
        <div class="tab-pane fade show active" id="emails-report" role="tabpanel" aria-labelledby="emails-tab">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Email Report</h5>
                    <button type="button" id="export-emails" class="btn btn-sm btn-outline-primary" disabled>
                        <i class="fas fa-download me-1"></i> Export
                    </button>
                </div>
                <div class="card-body">
                    <div id="emails-loading" class="text-center py-5" style="display: none;">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mt-2 text-muted">Loading email report...</p>
                    </div>
                    <div id="emails-error" class="alert alert-danger" style="display: none;"></div>
                    <div id="emails-content">
                        <div class="text-center py-5 text-muted">
                            <i class="fas fa-envelope fa-3x mb-3"></i>
                            <p>Select filters and click "Generate Reports" to view the email report</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recipients Report -->
        <div class="tab-pane fade" id="recipients-report" role="tabpanel" aria-labelledby="recipients-tab">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Recipients Report</h5>
                    <button type="button" id="export-recipients" class="btn btn-sm btn-outline-primary" disabled>
                        <i class="fas fa-download me-1"></i> Export
                    </button>
                </div>
                <div class="card-body">
                    <div id="recipients-loading" class="text-center py-5" style="display: none;">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mt-2 text-muted">Loading recipients report...</p>
                    </div>
                    <div id="recipients-error" class="alert alert-danger" style="display: none;"></div>
                    <div id="recipients-content">
                        <div class="text-center py-5 text-muted">
                            <i class="fas fa-users fa-3x mb-3"></i>
                            <p>Select filters and click "Generate Reports" to view the recipients report</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Senders Report -->
        <div class="tab-pane fade" id="senders-report" role="tabpanel" aria-labelledby="senders-tab">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Senders Report</h5>
                    <button type="button" id="export-senders" class="btn btn-sm btn-outline-primary" disabled>
                        <i class="fas fa-download me-1"></i> Export
                    </button>
                </div>
                <div class="card-body">
                    <div id="senders-loading" class="text-center py-5" style="display: none;">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mt-2 text-muted">Loading senders report...</p>
                    </div>
                    <div id="senders-error" class="alert alert-danger" style="display: none;"></div>
                    <div id="senders-content">
                        <div class="text-center py-5 text-muted">
                            <i class="fas fa-paper-plane fa-3x mb-3"></i>
                            <p>Select filters and click "Generate Reports" to view the senders report</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Bounced Recipients Report -->
        <div class="tab-pane fade" id="bounced-report" role="tabpanel" aria-labelledby="bounced-tab">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Bounced Recipients Report</h5>
                    <button type="button" id="export-bounced" class="btn btn-sm btn-outline-primary" disabled>
                        <i class="fas fa-download me-1"></i> Export
                    </button>
                </div>
                <div class="card-body">
                    <div id="bounced-loading" class="text-center py-5" style="display: none;">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mt-2 text-muted">Loading bounced recipients report...</p>
                    </div>
                    <div id="bounced-error" class="alert alert-danger" style="display: none;"></div>
                    <div id="bounced-content">
                        <div class="text-center py-5 text-muted">
                            <i class="fas fa-exclamation-triangle fa-3x mb-3"></i>
                            <p>Select filters and click "Generate Reports" to view the bounced recipients report</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Unsubscribes Report -->
        <div class="tab-pane fade" id="unsubscribes-report" role="tabpanel" aria-labelledby="unsubscribes-tab">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Unsubscribes</h5>
                    <button type="button" id="export-unsubscribes" class="btn btn-sm btn-outline-primary" disabled>
                        <i class="fas fa-download me-1"></i> Export
                    </button>
                </div>
                <div class="card-body">
                    <div id="unsubscribes-loading" class="text-center py-5" style="display: none;">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mt-2 text-muted">Loading unsubscribes report...</p>
                    </div>
                    <div id="unsubscribes-error" class="alert alert-danger" style="display: none;"></div>
                    <div id="unsubscribes-content">
                        <div class="text-center py-5 text-muted">
                            <i class="fas fa-user-slash fa-3x mb-3"></i>
                            <p>Select filters and click "Generate Reports" to view unsubscribes</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@else
    <div class="card">
        <div class="card-body text-center py-5">
            <i class="fas fa-folder-open fa-3x text-muted mb-3"></i>
            <h4 class="text-muted">No projects available</h4>
            <p class="text-muted">Contact an administrator to get access to projects.</p>
            @if(!auth()->user()->isSuperAdmin())
            <a href="{{ route('project-requests.create') }}" class="btn btn-primary mt-2">
                <i class="fas fa-hand-paper"></i> Request a Project
            </a>
            @endif
        </div>
    </div>
@endif

@endsection

@section('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const projectSelector = document.getElementById('report_project_selector');
    const dateFromInput = document.getElementById('date_from');
    const dateToInput = document.getElementById('date_to');
    const applyButton = document.getElementById('apply_filters');
    
    let currentProjectIds = 'all';
    let emailsData = [];
    let recipientsData = [];
    let sendersData = [];
    let bouncedData = [];
    let unsubscribesData = [];

    // Project selector handler
    if (projectSelector) {
        projectSelector.addEventListener('change', function() {
            const selectedOptions = Array.from(this.selectedOptions);
            let selectedValues = selectedOptions.map(option => option.value);
            
            if (selectedValues.length === 0) {
                const allOption = this.querySelector('option[value="all"]');
                if (allOption) allOption.selected = true;
                currentProjectIds = 'all';
            } else if (selectedValues.includes('all') && selectedValues.length > 1) {
                selectedValues = selectedValues.filter(val => val !== 'all');
                const allOption = this.querySelector('option[value="all"]');
                if (allOption) allOption.selected = false;
                currentProjectIds = selectedValues.join(',');
            } else if (selectedValues.length === 1 && selectedValues[0] === 'all') {
                currentProjectIds = 'all';
            } else {
                currentProjectIds = selectedValues.join(',');
            }
        });
    }

    // Apply filters
    if (applyButton) {
        applyButton.addEventListener('click', function() {
            loadReports();
        });
    }

    // Load reports function
    function loadReports() {
        const dateFrom = dateFromInput.value;
        const dateTo = dateToInput.value;

        if (!dateFrom || !dateTo) {
            alert('Please select both date from and date to');
            return;
        }

        loadEmailsReport();
        loadRecipientsReport();
        loadSendersReport();
        loadBouncedRecipientsReport();
        loadUnsubscribesReport();
    }

    // Load emails report
    function loadEmailsReport() {
        const dateFrom = dateFromInput.value;
        const dateTo = dateToInput.value;
        const loadingEl = document.getElementById('emails-loading');
        const errorEl = document.getElementById('emails-error');
        const contentEl = document.getElementById('emails-content');
        const exportBtn = document.getElementById('export-emails');

        if (!loadingEl || !errorEl || !contentEl || !exportBtn) return;

        loadingEl.style.display = 'block';
        errorEl.style.display = 'none';
        contentEl.innerHTML = '';

        fetch(`{{ route('reports.emails') }}?projectId=${encodeURIComponent(currentProjectIds)}&dateFrom=${encodeURIComponent(dateFrom)}&dateTo=${encodeURIComponent(dateTo)}`)
            .then(response => response.json())
            .then(data => {
                loadingEl.style.display = 'none';
                
                if (data.error) {
                    errorEl.textContent = data.error;
                    errorEl.style.display = 'block';
                    exportBtn.disabled = true;
                    return;
                }

                emailsData = data.data || [];
                displayEmailsReport(emailsData);
                exportBtn.disabled = false;
            })
            .catch(error => {
                loadingEl.style.display = 'none';
                errorEl.textContent = 'Error loading email report: ' + error.message;
                errorEl.style.display = 'block';
                exportBtn.disabled = true;
                console.error('Email report error:', error);
            });
    }

    // Display emails report
    function displayEmailsReport(data) {
        const contentEl = document.getElementById('emails-content');
        if (!contentEl) return;

        if (data.length === 0) {
            contentEl.innerHTML = '<div class="text-center py-5 text-muted">No emails found for the selected filters.</div>';
            return;
        }

        let html = `
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Project</th>
                            <th>Subject</th>
                            <th>From</th>
                            <th>Sent At</th>
                            <th>Status</th>
                            <th>Recipients</th>
                            <th>Opens</th>
                            <th>Clicks</th>
                            <th>Unsubscribes</th>
                        </tr>
                    </thead>
                    <tbody>
        `;

        data.forEach(email => {
            const statusBadge = getStatusBadge(email.status);
            html += `
                <tr>
                    <td>${email.id}</td>
                    <td>${escapeHtml(email.project_name || '')}</td>
                    <td>${escapeHtml(email.subject || '(No subject)')}</td>
                    <td>${escapeHtml(email.source || '')}</td>
                    <td>${email.sent_at || ''}</td>
                    <td>${statusBadge}</td>
                    <td><span class="badge bg-info">${email.recipient_count || 0}</span></td>
                    <td><span class="badge bg-success">${email.opens || 0}</span></td>
                    <td><span class="badge bg-warning">${email.clicks || 0}</span></td>
                    <td><span class="badge bg-secondary">${email.unsubscribes || 0}</span></td>
                </tr>
            `;
        });

        html += `
                    </tbody>
                </table>
            </div>
            <div class="mt-3 text-muted small">
                <strong>Total:</strong> ${data.length} email(s)
            </div>
        `;

        contentEl.innerHTML = html;
    }

    // Load recipients report
    function loadRecipientsReport() {
        const dateFrom = dateFromInput.value;
        const dateTo = dateToInput.value;
        const loadingEl = document.getElementById('recipients-loading');
        const errorEl = document.getElementById('recipients-error');
        const contentEl = document.getElementById('recipients-content');
        const exportBtn = document.getElementById('export-recipients');

        if (!loadingEl || !errorEl || !contentEl || !exportBtn) return;

        loadingEl.style.display = 'block';
        errorEl.style.display = 'none';
        contentEl.innerHTML = '';

        fetch(`{{ route('reports.recipients') }}?projectId=${encodeURIComponent(currentProjectIds)}&dateFrom=${encodeURIComponent(dateFrom)}&dateTo=${encodeURIComponent(dateTo)}`)
            .then(response => response.json())
            .then(data => {
                loadingEl.style.display = 'none';
                
                if (data.error) {
                    errorEl.textContent = data.error;
                    errorEl.style.display = 'block';
                    exportBtn.disabled = true;
                    return;
                }

                recipientsData = data.data || [];
                displayRecipientsReport(recipientsData);
                exportBtn.disabled = false;
            })
            .catch(error => {
                loadingEl.style.display = 'none';
                errorEl.textContent = 'Error loading recipients report: ' + error.message;
                errorEl.style.display = 'block';
                exportBtn.disabled = true;
                console.error('Recipients report error:', error);
            });
    }

    // Display recipients report
    function displayRecipientsReport(data) {
        const contentEl = document.getElementById('recipients-content');
        if (!contentEl) return;

        if (data.length === 0) {
            contentEl.innerHTML = '<div class="text-center py-5 text-muted">No recipients found for the selected filters.</div>';
            return;
        }

        let html = `
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Email Address</th>
                            <th>Total Emails</th>
                            <th>Total Opens</th>
                            <th>Total Clicks</th>
                            <th>Unsubscribes</th>
                        </tr>
                    </thead>
                    <tbody>
        `;

        data.forEach(recipient => {
            html += `
                <tr>
                    <td>${escapeHtml(recipient.address || '')}</td>
                    <td><span class="badge bg-primary">${recipient.total_emails || 0}</span></td>
                    <td><span class="badge bg-success">${recipient.total_opens || 0}</span></td>
                    <td><span class="badge bg-warning">${recipient.total_clicks || 0}</span></td>
                    <td><span class="badge bg-secondary">${recipient.total_unsubscribes || 0}</span></td>
                </tr>
            `;
        });

        html += `
                    </tbody>
                </table>
            </div>
            <div class="mt-3 text-muted small">
                <strong>Total:</strong> ${data.length} recipient(s)
            </div>
        `;

        contentEl.innerHTML = html;
    }

    // Load senders report
    function loadSendersReport() {
        const dateFrom = dateFromInput.value;
        const dateTo = dateToInput.value;
        const loadingEl = document.getElementById('senders-loading');
        const errorEl = document.getElementById('senders-error');
        const contentEl = document.getElementById('senders-content');
        const exportBtn = document.getElementById('export-senders');

        if (!loadingEl || !errorEl || !contentEl || !exportBtn) return;

        loadingEl.style.display = 'block';
        errorEl.style.display = 'none';
        contentEl.innerHTML = '';

        fetch(`{{ route('reports.senders') }}?projectId=${encodeURIComponent(currentProjectIds)}&dateFrom=${encodeURIComponent(dateFrom)}&dateTo=${encodeURIComponent(dateTo)}`)
            .then(response => response.json())
            .then(data => {
                loadingEl.style.display = 'none';
                
                if (data.error) {
                    errorEl.textContent = data.error;
                    errorEl.style.display = 'block';
                    exportBtn.disabled = true;
                    return;
                }

                sendersData = data.data || [];
                displaySendersReport(sendersData);
                exportBtn.disabled = false;
            })
            .catch(error => {
                loadingEl.style.display = 'none';
                errorEl.textContent = 'Error loading senders report: ' + error.message;
                errorEl.style.display = 'block';
                exportBtn.disabled = true;
                console.error('Senders report error:', error);
            });
    }

    // Display senders report
    function displaySendersReport(data) {
        const contentEl = document.getElementById('senders-content');
        if (!contentEl) return;

        if (data.length === 0) {
            contentEl.innerHTML = '<div class="text-center py-5 text-muted">No senders found for the selected filters.</div>';
            return;
        }

        let html = `
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Sender</th>
                            <th>Total Emails</th>
                            <th>Total Opens</th>
                            <th>Total Clicks</th>
                            <th>Delivered</th>
                            <th>Sent</th>
                            <th>Bounced</th>
                            <th>Complained</th>
                            <th>Other</th>
                        </tr>
                    </thead>
                    <tbody>
        `;

        data.forEach(sender => {
            html += `
                <tr>
                    <td><strong>${escapeHtml(sender.sender || '(Unknown)')}</strong></td>
                    <td><span class="badge bg-primary">${sender.total_emails || 0}</span></td>
                    <td><span class="badge bg-success">${sender.total_opens || 0}</span></td>
                    <td><span class="badge bg-warning">${sender.total_clicks || 0}</span></td>
                    <td><span class="badge bg-success">${sender.status_delivered || 0}</span></td>
                    <td><span class="badge bg-info">${sender.status_sent || 0}</span></td>
                    <td><span class="badge bg-danger">${sender.status_bounced || 0}</span></td>
                    <td><span class="badge bg-warning">${sender.status_complained || 0}</span></td>
                    <td><span class="badge bg-secondary">${sender.status_other || 0}</span></td>
                </tr>
            `;
        });

        html += `
                    </tbody>
                </table>
            </div>
            <div class="mt-3 text-muted small">
                <strong>Total:</strong> ${data.length} sender(s)
            </div>
        `;

        contentEl.innerHTML = html;
    }

    // Load bounced recipients report
    function loadBouncedRecipientsReport() {
        const dateFrom = dateFromInput.value;
        const dateTo = dateToInput.value;
        const loadingEl = document.getElementById('bounced-loading');
        const errorEl = document.getElementById('bounced-error');
        const contentEl = document.getElementById('bounced-content');
        const exportBtn = document.getElementById('export-bounced');

        if (!loadingEl || !errorEl || !contentEl || !exportBtn) return;

        loadingEl.style.display = 'block';
        errorEl.style.display = 'none';
        contentEl.innerHTML = '';

        fetch(`{{ route('reports.bounced-recipients') }}?projectId=${encodeURIComponent(currentProjectIds)}&dateFrom=${encodeURIComponent(dateFrom)}&dateTo=${encodeURIComponent(dateTo)}`)
            .then(response => response.json())
            .then(data => {
                loadingEl.style.display = 'none';
                
                if (data.error) {
                    errorEl.textContent = data.error;
                    errorEl.style.display = 'block';
                    exportBtn.disabled = true;
                    return;
                }

                bouncedData = data.data || [];
                displayBouncedRecipientsReport(bouncedData);
                exportBtn.disabled = false;
            })
            .catch(error => {
                loadingEl.style.display = 'none';
                errorEl.textContent = 'Error loading bounced recipients report: ' + error.message;
                errorEl.style.display = 'block';
                exportBtn.disabled = true;
                console.error('Bounced recipients report error:', error);
            });
    }

    // Display bounced recipients report
    function displayBouncedRecipientsReport(data) {
        const contentEl = document.getElementById('bounced-content');
        if (!contentEl) return;

        if (data.length === 0) {
            contentEl.innerHTML = '<div class="text-center py-5 text-muted">No bounced recipients found for the selected filters.</div>';
            return;
        }

        let html = `
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Recipient Email</th>
                            <th>Bounce Type</th>
                            <th>Bounce Subtype</th>
                            <th>Bounced At</th>
                            <th>Project</th>
                            <th>Email Subject</th>
                            <th>Email From</th>
                        </tr>
                    </thead>
                    <tbody>
        `;

        data.forEach(bounce => {
            const bounceTypeBadge = bounce.bounce_type === 'Permanent' 
                ? '<span class="badge bg-danger">Permanent</span>'
                : '<span class="badge bg-warning">Transient</span>';
            
            html += `
                <tr>
                    <td>${escapeHtml(bounce.recipient_address || '')}</td>
                    <td>${bounceTypeBadge}</td>
                    <td><span class="badge bg-secondary">${escapeHtml(bounce.bounce_subtype || 'Unknown')}</span></td>
                    <td>${bounce.bounced_at || ''}</td>
                    <td>${escapeHtml(bounce.project_name || '')}</td>
                    <td>${escapeHtml(bounce.email_subject || '(No subject)')}</td>
                    <td>${escapeHtml(bounce.email_source || '')}</td>
                </tr>
            `;
        });

        html += `
                    </tbody>
                </table>
            </div>
            <div class="mt-3 text-muted small">
                <strong>Total:</strong> ${data.length} bounced recipient(s)
            </div>
        `;

        contentEl.innerHTML = html;
    }

    // Load unsubscribes report
    function loadUnsubscribesReport() {
        const dateFrom = dateFromInput.value;
        const dateTo = dateToInput.value;
        const loadingEl = document.getElementById('unsubscribes-loading');
        const errorEl = document.getElementById('unsubscribes-error');
        const contentEl = document.getElementById('unsubscribes-content');
        const exportBtn = document.getElementById('export-unsubscribes');

        if (!loadingEl || !errorEl || !contentEl || !exportBtn) return;

        loadingEl.style.display = 'block';
        errorEl.style.display = 'none';
        contentEl.innerHTML = '';

        fetch(`{{ route('reports.unsubscribes') }}?projectId=${encodeURIComponent(currentProjectIds)}&dateFrom=${encodeURIComponent(dateFrom)}&dateTo=${encodeURIComponent(dateTo)}`)
            .then(response => response.json())
            .then(data => {
                loadingEl.style.display = 'none';

                if (data.error) {
                    errorEl.textContent = data.error;
                    errorEl.style.display = 'block';
                    exportBtn.disabled = true;
                    return;
                }

                unsubscribesData = data.data || [];
                displayUnsubscribesReport(unsubscribesData);
                exportBtn.disabled = false;
            })
            .catch(error => {
                loadingEl.style.display = 'none';
                errorEl.textContent = 'Error loading unsubscribes report: ' + error.message;
                errorEl.style.display = 'block';
                exportBtn.disabled = true;
                console.error('Unsubscribes report error:', error);
            });
    }

    function displayUnsubscribesReport(data) {
        const contentEl = document.getElementById('unsubscribes-content');
        if (!contentEl) return;

        if (data.length === 0) {
            contentEl.innerHTML = '<div class="text-center py-5 text-muted">No unsubscribe events found for the selected filters.</div>';
            return;
        }

        let html = `
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Recipient</th>
                            <th>Unsubscribed At</th>
                            <th>Subject</th>
                            <th>From</th>
                            <th>Project</th>
                            <th>Contact list</th>
                            <th>Subscription source</th>
                        </tr>
                    </thead>
                    <tbody>
        `;

        data.forEach(row => {
            html += `
                <tr>
                    <td>${escapeHtml(row.recipient_address || '')}</td>
                    <td>${row.unsubscribed_at || ''}</td>
                    <td>${escapeHtml(row.email_subject || '')}</td>
                    <td>${escapeHtml(row.email_source || '')}</td>
                    <td>${escapeHtml(row.project_name || '')}</td>
                    <td>${escapeHtml(row.contact_list || '')}</td>
                    <td><span class="badge bg-secondary">${escapeHtml(row.subscription_source || '')}</span></td>
                </tr>
            `;
        });

        html += `
                    </tbody>
                </table>
            </div>
            <div class="mt-3 text-muted small">
                <strong>Total:</strong> ${data.length} unsubscribe event(s)
            </div>
        `;

        contentEl.innerHTML = html;
    }

    // Helper functions
    function getStatusBadge(status) {
        const badges = {
            'delivered': '<span class="badge bg-success">Delivered</span>',
            'sent': '<span class="badge bg-info">Sent</span>',
            'bounced': '<span class="badge bg-danger">Bounced</span>',
            'complained': '<span class="badge bg-warning">Complained</span>',
        };
        return badges[status] || `<span class="badge bg-secondary">${status || 'Unknown'}</span>`;
    }

    function escapeHtml(text) {
        if (text === null || text === undefined) return '';
        const div = document.createElement('div');
        div.textContent = String(text);
        return div.innerHTML;
    }

    // Export functionality
    const exportEmailsBtn = document.getElementById('export-emails');
    const exportRecipientsBtn = document.getElementById('export-recipients');
    const exportSendersBtn = document.getElementById('export-senders');
    const exportBouncedBtn = document.getElementById('export-bounced');
    const exportUnsubscribesBtn = document.getElementById('export-unsubscribes');

    if (exportEmailsBtn) {
        exportEmailsBtn.addEventListener('click', function() {
            if (emailsData.length === 0) return;
            exportToCSV(emailsData, 'emails-report.csv', [
                'ID', 'Project', 'Subject', 'From', 'Sent At', 'Status', 'Recipients', 'Opens', 'Clicks', 'Unsubscribes'
            ]);
        });
    }

    if (exportRecipientsBtn) {
        exportRecipientsBtn.addEventListener('click', function() {
            if (recipientsData.length === 0) return;
            exportToCSV(recipientsData, 'recipients-report.csv', [
                'Email Address', 'Total Emails', 'Total Opens', 'Total Clicks', 'Total unsubscribes'
            ]);
        });
    }

    if (exportSendersBtn) {
        exportSendersBtn.addEventListener('click', function() {
            if (sendersData.length === 0) return;
            exportToCSV(sendersData, 'senders-report.csv', [
                'Sender', 'Total Emails', 'Total Opens', 'Total Clicks', 'Delivered', 'Sent', 'Bounced', 'Complained', 'Other'
            ]);
        });
    }

    if (exportBouncedBtn) {
        exportBouncedBtn.addEventListener('click', function() {
            if (bouncedData.length === 0) return;
            exportToCSV(bouncedData, 'bounced-recipients-report.csv', [
                'Recipient Email', 'Bounce Type', 'Bounce Subtype', 'Bounced At', 'Project', 'Email Subject', 'Email From'
            ]);
        });
    }

    if (exportUnsubscribesBtn) {
        exportUnsubscribesBtn.addEventListener('click', function() {
            if (unsubscribesData.length === 0) return;
            exportToCSV(unsubscribesData, 'unsubscribes-report.csv', [
                'Recipient Email', 'Unsubscribed At', 'Email Subject', 'Email From', 'Project', 'Contact list', 'Subscription source'
            ]);
        });
    }

    function exportToCSV(data, filename, headers) {
        // Map headers to data keys
        const headerMap = {
            'ID': 'id',
            'Project': 'project_name',
            'Subject': 'subject',
            'From': 'source',
            'Sent At': 'sent_at',
            'Status': 'status',
            'Recipients': 'recipient_count',
            'Opens': 'opens',
            'Clicks': 'clicks',
            'Unsubscribes': 'unsubscribes',
            'Email Address': 'address',
            'Total Emails': 'total_emails',
            'Total Opens': 'total_opens',
            'Total Clicks': 'total_clicks',
            'Total unsubscribes': 'total_unsubscribes',
            'Sender': 'sender',
            'Delivered': 'status_delivered',
            'Sent': 'status_sent',
            'Bounced': 'status_bounced',
            'Complained': 'status_complained',
            'Other': 'status_other',
            'Recipient Email': 'recipient_address',
            'Bounce Type': 'bounce_type',
            'Bounce Subtype': 'bounce_subtype',
            'Bounced At': 'bounced_at',
            'Email Subject': 'email_subject',
            'Email From': 'email_source',
            'Unsubscribed At': 'unsubscribed_at',
            'Contact list': 'contact_list',
            'Subscription source': 'subscription_source',
        };
        
        let csv = headers.join(',') + '\n';
        
        data.forEach(row => {
            const values = headers.map(header => {
                const key = headerMap[header] || header.toLowerCase().replace(/\s+/g, '_');
                let value = row[key] || '';
                
                // Convert to string and escape
                value = String(value);
                value = value.replace(/"/g, '""');
                if (value.includes(',') || value.includes('"') || value.includes('\n')) {
                    value = `"${value}"`;
                }
                return value;
            });
            csv += values.join(',') + '\n';
        });

        const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = filename;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        window.URL.revokeObjectURL(url);
    }
});
</script>
@endsection

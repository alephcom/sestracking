import axios from 'axios';
import moment from 'moment';
import { Modal, Dropdown } from 'bootstrap';
import './bootstrap';

class ActivityApp {
  constructor() {
    this.currentProjectId = window.dashboardProjectId || 'all';
    this.rows       = [];
    this.search     = '';
    this.dateFrom   = '';
    this.dateTo     = '';
    this.eventSelected = null;
    this.currentPage   = 1;
    this.perPage       = 10;
    this.totalRows     = 0;
    this.isBusy        = false;
    this.selectedId    = null;
    this.showDetails   = false;
    this.emailDetails  = null;
    this.detailsModal  = null;
    this.detailsLoading = false;

    this.eventOptions = [
      { value: null,        text: 'All Events' },
      { value: 'send',      text: 'Send' },
      { value: 'delivery',  text: 'Delivery' },
      { value: 'reject',    text: 'Reject' },
      { value: 'bounce',    text: 'Bounce' },
      { value: 'complaint', text: 'Complaint' },
      { value: 'failure',   text: 'Failure' },
      { value: 'open',      text: 'Open' },
      { value: 'click',     text: 'Click' },
    ];

    this.init();
  }

  init() {
    this.createDOM();
    this.setupFilters();
    this.setupModal();
    window.activityVueInstance = this;
    this.loadData();
  }

  createDOM() {
    const appContainer = document.getElementById('app');
    if (!appContainer) return;

    appContainer.innerHTML = `
      <!-- Row 1: search + date range + clear -->
      <div class="row g-2 mb-2 align-items-end">
        <div class="col-md-5">
          <label class="form-label small fw-semibold text-muted mb-1">Search</label>
          <input type="text" id="search-input" class="form-control" placeholder="Subject or email address" />
        </div>
        <div class="col-md-5">
          <label class="form-label small fw-semibold text-muted mb-1">Date Range</label>
          <div class="input-group">
            <input type="date" id="activity-date-from" class="form-control" />
            <span class="input-group-text">to</span>
            <input type="date" id="activity-date-to" class="form-control" />
          </div>
        </div>
        <div class="col-md-2 d-flex justify-content-end">
          <button type="button" class="btn btn-outline-secondary w-100" id="clear-btn" title="Clear filters">
            <i class="fas fa-times me-1"></i>Clear
          </button>
        </div>
      </div>

      <!-- Row 2: event filter + search + export -->
      <div class="row g-2 mb-3 align-items-end">
        <div class="col-md-4">
          <label class="form-label small fw-semibold text-muted mb-1">Event Type</label>
          <select id="event-select" class="form-control">
            ${this.eventOptions.map(opt => `<option value="${opt.value || ''}">${opt.text}</option>`).join('')}
          </select>
        </div>
        <div class="col-md-8 d-flex gap-2 justify-content-end align-items-end">
          <button type="button" class="btn btn-outline-primary" id="search-btn">
            <i class="fas fa-search me-1"></i>Search
          </button>
          <div class="btn-group">
            <button type="button" class="btn btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
              <i class="fas fa-download me-1"></i>Export
            </button>
            <ul class="dropdown-menu dropdown-menu-end">
              <li><a class="dropdown-item" href="#" id="export-excel"><i class="fas fa-file-excel me-2"></i>Excel</a></li>
              <li><a class="dropdown-item" href="#" id="export-csv"><i class="fas fa-file-csv me-2"></i>CSV</a></li>
            </ul>
          </div>
        </div>
      </div>

      <div id="table-container"></div>
      <div id="pagination-container"></div>

      <!-- Email Details Modal -->
      <div class="modal fade" id="emailDetailsModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
          <div class="modal-content">
            <div class="modal-header">
              <h5 class="modal-title"><i class="fas fa-envelope-open-text me-2 text-primary"></i>Email Details</h5>
              <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="modal-body">
              <div class="text-center" id="modal-loading">
                <div class="spinner-border text-primary" role="status">
                  <span class="visually-hidden">Loading...</span>
                </div>
              </div>
              <div id="modal-content" style="display: none;"></div>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-primary" data-bs-dismiss="modal">Close</button>
            </div>
          </div>
        </div>
      </div>
    `;
  }

  setupFilters() {
    setTimeout(() => this.initDropdowns(), 100);

    const searchInput   = document.getElementById('search-input');
    const dateFromInput = document.getElementById('activity-date-from');
    const dateToInput   = document.getElementById('activity-date-to');
    const eventSelect   = document.getElementById('event-select');
    const searchBtn     = document.getElementById('search-btn');
    const clearBtn      = document.getElementById('clear-btn');
    const exportExcel   = document.getElementById('export-excel');
    const exportCsv     = document.getElementById('export-csv');

    const defaultStart = moment().locale(window.navigator.language).startOf('week').utc().toDate();
    const defaultEnd   = moment().locale(window.navigator.language).endOf('week').utc().toDate();

    if (dateFromInput) {
      dateFromInput.value = moment(defaultStart).format('YYYY-MM-DD');
      this.dateFrom = defaultStart;
    }
    if (dateToInput) {
      dateToInput.value = moment(defaultEnd).format('YYYY-MM-DD');
      this.dateTo = defaultEnd;
    }

    if (searchInput) {
      searchInput.addEventListener('input', (e) => { this.search = e.target.value; });
      searchInput.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') { this.currentPage = 1; this.loadData(); }
      });
    }

    if (dateFromInput) {
      dateFromInput.addEventListener('change', (e) => {
        this.dateFrom = moment(e.target.value).startOf('day').toDate();
        this.currentPage = 1;
        this.loadData();
      });
    }

    if (dateToInput) {
      dateToInput.addEventListener('change', (e) => {
        this.dateTo = moment(e.target.value).endOf('day').toDate();
        this.currentPage = 1;
        this.loadData();
      });
    }

    if (eventSelect) {
      eventSelect.addEventListener('change', (e) => {
        this.eventSelected = e.target.value || null;
        this.currentPage = 1;
        this.loadData();
      });
    }

    if (searchBtn) {
      searchBtn.addEventListener('click', () => { this.currentPage = 1; this.loadData(); });
    }

    if (clearBtn) {
      clearBtn.addEventListener('click', () => {
        this.search        = '';
        this.dateFrom      = defaultStart;
        this.dateTo        = defaultEnd;
        this.eventSelected = null;
        if (searchInput)   searchInput.value   = '';
        if (dateFromInput) dateFromInput.value  = moment(defaultStart).format('YYYY-MM-DD');
        if (dateToInput)   dateToInput.value    = moment(defaultEnd).format('YYYY-MM-DD');
        if (eventSelect)   eventSelect.value    = '';
        this.currentPage = 1;
        this.loadData();
      });
    }

    if (exportExcel) {
      exportExcel.addEventListener('click', (e) => { e.preventDefault(); window.location.href = this.getExportUrl('excel'); });
    }
    if (exportCsv) {
      exportCsv.addEventListener('click', (e) => { e.preventDefault(); window.location.href = this.getExportUrl('csv'); });
    }
  }

  setupModal() {
    const modalElement = document.getElementById('emailDetailsModal');
    if (modalElement) {
      this.detailsModal = new Modal(modalElement);
      modalElement.addEventListener('hidden.bs.modal', () => { this.showDetails = false; });
    }
  }

  getExportUrl(format) {
    const params = {
      dateFrom:   moment(this.dateFrom).startOf('day').utc().toISOString(),
      dateTo:     moment(this.dateTo).endOf('day').utc().toISOString(),
      project_id: this.currentProjectId || 'all',
      format
    };
    if (this.search.length)    params['search']    = this.search;
    if (this.eventSelected)    params['eventType'] = this.eventSelected;
    return window.APP_EXPORT_URL + '?' + new URLSearchParams(params).toString();
  }

  loadData() {
    this.isBusy = true;
    this.renderTable();

    axios.get('/activity/list/api', {
      params: {
        page:       this.currentPage,
        limit:      this.perPage,
        search:     this.search,
        dateFrom:   moment(this.dateFrom).startOf('day').utc().toDate(),
        dateTo:     moment(this.dateTo).endOf('day').utc().toDate(),
        eventType:  this.eventSelected,
        project_id: this.currentProjectId
      }
    })
      .then(response => {
        this.rows      = response.data.rows;
        this.totalRows = response.data.totalRows;
        this.isBusy    = false;
        this.renderTable();
        this.renderPagination();
      })
      .catch(error => {
        this.isBusy = false;
        this.renderTable();
        console.error(error);
      });
  }

  getStatusBadge(status) {
    const map = {
      delivered:  'bg-success',
      bounced:    'bg-danger',
      complained: 'bg-danger',
      sent:       'bg-secondary',
      rejected:   'bg-warning',
      failed:     'bg-warning',
    };
    const cls = map[status] || 'bg-secondary';
    const textCls = (status === 'rejected' || status === 'failed') ? ' text-dark' : '';
    const label = status.charAt(0).toUpperCase() + status.slice(1);
    return `<span class="badge ${cls}${textCls}">${label}</span>`;
  }

  renderTable() {
    const container = document.getElementById('table-container');
    if (!container) return;

    if (this.isBusy) {
      container.innerHTML = `
        <div class="text-center text-primary my-4">
          <div class="spinner-border" role="status">
            <span class="visually-hidden">Loading...</span>
          </div>
        </div>
      `;
      return;
    }

    if (this.rows.length === 0) {
      container.innerHTML = `
        <div class="text-center py-5 text-muted">
          <i class="fas fa-inbox fa-3x mb-3 d-block"></i>
          <div class="lead">No emails to display</div>
          <small>Try adjusting your filters or date range.</small>
        </div>
      `;
      return;
    }

    const formatDate = (value) => {
      if (!value) return '';
      return moment(value).locale(window.navigator.language).local().format('LLL');
    };

    let tableHTML = `
      <table class="table table-hover">
        <thead>
          <tr>
            <th style="width: 130px;">Status</th>
            <th>Message</th>
            <th style="width: 190px;">Sent At</th>
            <th style="width: 70px;">Opens</th>
            <th style="width: 70px;">Clicks</th>
          </tr>
        </thead>
        <tbody>
    `;

    this.rows.forEach(row => {
      tableHTML += `
        <tr style="cursor: pointer;" data-id="${row.id}">
          <td>${this.getStatusBadge(row.status)}</td>
          <td>
            <div class="fw-semibold">${row.subject || '<em class="text-muted">(no subject)</em>'}</div>
            <small class="text-muted">${(row.destination || []).join(', ')}</small>
          </td>
          <td>
            <span title="${row.timestamp} UTC">${formatDate(row.timestamp)}</span>
          </td>
          <td>${row.opens || 0}</td>
          <td>${row.clicks || 0}</td>
        </tr>
      `;
    });

    tableHTML += `</tbody></table>`;
    container.innerHTML = tableHTML;

    container.querySelectorAll('tbody tr').forEach(tr => {
      tr.addEventListener('click', () => {
        const id = tr.getAttribute('data-id');
        if (id) this.rowClicked(id);
      });
    });
  }

  initDropdowns() {
    document.querySelectorAll('.dropdown-toggle').forEach(element => new Dropdown(element));
  }

  renderPagination() {
    const container = document.getElementById('pagination-container');
    if (!container) return;

    const start = (this.currentPage - 1) * this.perPage + 1;
    const end   = Math.min(this.currentPage * this.perPage, this.totalRows);

    const countHTML = this.totalRows > 0
      ? `<div class="text-muted small mb-2">Showing ${start}–${end} of ${this.totalRows.toLocaleString()} results</div>`
      : '';

    if (this.totalRows <= this.perPage) {
      container.innerHTML = countHTML;
      return;
    }

    const totalPages = Math.ceil(this.totalRows / this.perPage);
    let paginationHTML = '<nav><ul class="pagination mb-0">';

    paginationHTML += `
      <li class="page-item ${this.currentPage === 1 ? 'disabled' : ''}">
        <a class="page-link" href="#" data-page="${this.currentPage - 1}">Previous</a>
      </li>
    `;

    for (let i = 1; i <= totalPages; i++) {
      if (i === 1 || i === totalPages || (i >= this.currentPage - 2 && i <= this.currentPage + 2)) {
        paginationHTML += `
          <li class="page-item ${this.currentPage === i ? 'active' : ''}">
            <a class="page-link" href="#" data-page="${i}">${i}</a>
          </li>
        `;
      } else if (i === this.currentPage - 3 || i === this.currentPage + 3) {
        paginationHTML += '<li class="page-item disabled"><span class="page-link">…</span></li>';
      }
    }

    paginationHTML += `
      <li class="page-item ${this.currentPage === totalPages ? 'disabled' : ''}">
        <a class="page-link" href="#" data-page="${this.currentPage + 1}">Next</a>
      </li>
    `;
    paginationHTML += '</ul></nav>';

    container.innerHTML = countHTML + paginationHTML;

    container.querySelectorAll('a.page-link').forEach(link => {
      link.addEventListener('click', (e) => {
        e.preventDefault();
        const page = parseInt(link.getAttribute('data-page'));
        if (page && page !== this.currentPage) {
          this.currentPage = page;
          this.loadData();
        }
      });
    });
  }

  rowClicked(id) {
    this.selectedId  = id;
    this.showDetails = true;
    this.loadDetails();
    if (this.detailsModal) this.detailsModal.show();
  }

  loadDetails() {
    this.detailsLoading = true;
    this.emailDetails   = null;

    const modalLoading = document.getElementById('modal-loading');
    const modalContent = document.getElementById('modal-content');
    if (modalLoading) modalLoading.style.display = 'block';
    if (modalContent) modalContent.style.display = 'none';

    axios.get('/activity/details/api', { params: { id: this.selectedId } })
      .then(response => {
        this.emailDetails = response.data;
        this.renderDetails();
      })
      .catch(error => {
        console.error(error);
        this.detailsLoading = false;
        if (modalLoading) modalLoading.style.display = 'none';
      });
  }

  renderDetails() {
    const modalLoading = document.getElementById('modal-loading');
    const modalContent = document.getElementById('modal-content');
    if (!this.emailDetails || !modalContent) return;

    this.detailsLoading = false;
    if (modalLoading) modalLoading.style.display = 'none';
    modalContent.style.display = 'block';

    const formatDate = (value) => {
      if (!value) return '';
      return moment(value).locale(window.navigator.language).local().format('LLL');
    };

    let detailsHTML = `
      <div class="table-responsive mb-4">
        <table class="table">
          <tbody>
            <tr>
              <th style="width: 130px;">Subject</th>
              <td>${this.emailDetails.subject || ''}</td>
            </tr>
            <tr>
              <th>Message ID</th>
              <td><code class="user-select-all">${this.emailDetails.messageId || ''}</code></td>
            </tr>
            <tr>
              <th>Destination</th>
              <td>${(this.emailDetails.destination || []).join(', ')}</td>
            </tr>
            <tr>
              <th>Source</th>
              <td>${this.emailDetails.source || ''}</td>
            </tr>
            <tr>
              <th>Sent</th>
              <td title="${this.emailDetails.timestamp} UTC">${formatDate(this.emailDetails.timestamp)}</td>
            </tr>
          </tbody>
        </table>
      </div>

      <h5 class="mb-3"><i class="fas fa-stream me-2 text-primary"></i>Events Log</h5>
      <ul class="list-group list-group-flush">
    `;

    (this.emailDetails.emailEvents || []).forEach((emailEvent, index) => {
      const collapseId = `collapse-${emailEvent.id || index}`;
      detailsHTML += `
        <li class="list-group-item px-0">
          <div class="d-flex align-items-center justify-content-between">
            <button class="btn btn-link p-0 text-start text-decoration-none" type="button" data-bs-toggle="collapse" data-bs-target="#${collapseId}">
              <i class="far fa-dot-circle text-primary me-2"></i>
              <span class="fw-semibold text-capitalize">${emailEvent.event}</span>
              <small class="text-muted ms-2" title="${emailEvent.timestamp} UTC">${formatDate(emailEvent.timestamp)}</small>
            </button>
            <i class="fas fa-chevron-down text-muted small"></i>
          </div>
          <div class="collapse mt-2" id="${collapseId}">
            <pre class="bg-light rounded p-3 small mb-0"><code>${emailEvent.eventData || ''}</code></pre>
          </div>
        </li>
      `;
    });

    detailsHTML += '</ul>';
    modalContent.innerHTML = detailsHTML;
  }

  updateProjectId(projectId) {
    this.currentProjectId = projectId;
    this.currentPage = 1;
    this.loadData();
  }
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', () => new ActivityApp());
} else {
  new ActivityApp();
}

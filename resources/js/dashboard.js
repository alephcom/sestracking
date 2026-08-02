import axios from 'axios';
import moment from 'moment';
import { Chart, registerables } from 'chart.js';
Chart.register(...registerables);
import './bootstrap';

class DashboardApp {
  constructor() {
    this.projectId = window.dashboardProjectId || 'all';
    this.dateRange = {
      startDate: moment().locale(window.navigator.language).startOf('week').utc().toDate(),
      endDate: moment().locale(window.navigator.language).endOf('week').utc().toDate()
    };
    this.counters = {};
    this.bounceRate = 0;
    this.complaintRate = 0;
    this.chart = null;
    this.chartCanvas = null;
    this.chartColors = {
      Send:      '#6c757d',
      Delivery:  '#28a745',
      Reject:    '#db5b67',
      Bounce:    '#f59e0b',
      Complaint: '#dc3545',
      Failure:   '#e59aa2',
      Open:      '#007bff',
      Click:     '#8b5cf6'
    };

    this.init();
  }

  init() {
    this.createDOM();
    this.setupDateRangePicker();
    window.dashboardVueInstance = this;
    if (this.projectId) {
      this.loadData();
    }
  }

  createDOM() {
    const appContainer = document.getElementById('app');
    if (!appContainer) return;

    appContainer.innerHTML = `
      <div class="mb-4">
        <label class="form-label fw-semibold text-muted small text-uppercase letter-spacing-1 mb-2">
          <i class="fas fa-calendar-alt me-1"></i>Date Range
        </label>
        <div class="input-group" style="max-width: 360px;">
          <input type="date" id="date-from" class="form-control" />
          <span class="input-group-text">to</span>
          <input type="date" id="date-to" class="form-control" />
        </div>
      </div>

      <div id="error-container"></div>
      <div id="counters-cards"></div>

      <div class="small mb-5" style="position: relative; height: 300px;">
        <canvas id="line-chart"></canvas>
      </div>
    `;

    this.chartCanvas = document.getElementById('line-chart');
  }

  setupDateRangePicker() {
    const dateFromInput = document.getElementById('date-from');
    const dateToInput   = document.getElementById('date-to');

    if (dateFromInput && dateToInput) {
      dateFromInput.value = moment(this.dateRange.startDate).format('YYYY-MM-DD');
      dateToInput.value   = moment(this.dateRange.endDate).format('YYYY-MM-DD');

      dateFromInput.addEventListener('change', () => {
        this.dateRange.startDate = moment(dateFromInput.value).toDate();
        this.loadData();
      });

      dateToInput.addEventListener('change', () => {
        this.dateRange.endDate = moment(dateToInput.value).toDate();
        this.loadData();
      });
    }
  }

  renderLoading() {
    const countersContainer = document.getElementById('counters-cards');
    if (countersContainer) {
      countersContainer.innerHTML = `
        <div class="text-center my-4">
          <div class="spinner-border text-primary" role="status">
            <span class="visually-hidden">Loading...</span>
          </div>
        </div>
      `;
    }
  }

  renderError(message) {
    const errorContainer = document.getElementById('error-container');
    if (errorContainer) {
      errorContainer.innerHTML = `
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
          <i class="fas fa-exclamation-triangle me-2"></i>${message}
          <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
      `;
    }
  }

  clearError() {
    const errorContainer = document.getElementById('error-container');
    if (errorContainer) errorContainer.innerHTML = '';
  }

  loadData() {
    this.clearError();
    this.renderLoading();

    axios.get(window.dashboardEndpoint, {
      params: {
        projectId: this.projectId,
        dateFrom:  moment(this.dateRange.startDate).startOf('day').utc().toDate(),
        dateTo:    moment(this.dateRange.endDate).endOf('day').utc().toDate(),
        tzOffset:  moment().utcOffset()
      }
    })
      .then(response => {
        this.counters = response.data.counters;
        this.bounceRate = response.data.bounceRate;
        this.complaintRate = response.data.complaintRate;
        this.renderCounters();
        this.fillChartData(response.data.chartData);
      })
      .catch(error => {
        const message = error.response?.data?.error || 'An error occurred loading dashboard data. Please try again.';
        this.renderError(message);
        const countersContainer = document.getElementById('counters-cards');
        if (countersContainer) countersContainer.innerHTML = '';
      });
  }

  getBounceRateBadge() {
    const sent = this.counters.sent || 0;
    const rate = this.bounceRate;
    if (sent === 0) return '<span class="badge bg-secondary">—</span>';
    let bg = 'bg-success';
    if (rate >= 5) bg = 'bg-danger';
    else if (rate >= 2) bg = 'bg-warning text-dark';
    const label = rate >= 5 ? `${rate}% — Above AWS limit` : `${rate}%`;
    return `<span class="badge ${bg}">${label}</span>`;
  }

  getComplaintRateBadge() {
    const sent = this.counters.sent || 0;
    const rate = this.complaintRate;
    if (sent === 0) return '<span class="badge bg-secondary">—</span>';
    let bg = 'bg-success';
    if (rate > 0.1) bg = 'bg-danger';
    else if (rate >= 0.08) bg = 'bg-warning text-dark';
    const label = rate > 0.1 ? `${rate}% — Above AWS limit` : `${rate}%`;
    return `<span class="badge ${bg}">${label}</span>`;
  }

  renderCounters() {
    const countersContainer = document.getElementById('counters-cards');
    if (!countersContainer) return;

    const sent         = this.counters.sent         || 0;
    const delivered    = this.counters.delivered    || 0;
    const opens        = this.counters.opens        || 0;
    const clicks       = this.counters.clicks       || 0;
    const notDelivered = this.counters.notDelivered || 0;
    const bounce       = this.counters.bounce       || 0;
    const complaint    = this.counters.complaint    || 0;

    const deliveredPercent    = sent ? ((delivered    / sent) * 100).toFixed(1) : 0;
    const notDeliveredPercent = sent ? ((notDelivered / sent) * 100).toFixed(1) : 0;

    const fmt = (num) => new Intl.NumberFormat([], { maximumFractionDigits: 2 }).format(num);

    countersContainer.innerHTML = `
      <div class="row g-3 mb-5">
        <div class="col-6 col-md">
          <div class="card text-center h-100">
            <div class="card-body py-4">
              <i class="fas fa-envelope fa-2x text-secondary mb-3"></i>
              <div class="text-uppercase text-muted small fw-semibold mb-1">Sent</div>
              <div class="fs-3 fw-bold text-dark">${fmt(sent)}</div>
            </div>
          </div>
        </div>

        <div class="col-6 col-md">
          <div class="card text-center h-100">
            <div class="card-body py-4">
              <i class="fas fa-check-circle fa-2x text-success mb-3"></i>
              <div class="text-uppercase text-muted small fw-semibold mb-1">Delivered</div>
              <div class="fs-3 fw-bold text-success">${fmt(delivered)}</div>
              <div class="text-muted small">${fmt(deliveredPercent)}%</div>
            </div>
          </div>
        </div>

        <div class="col-6 col-md">
          <div class="card text-center h-100">
            <div class="card-body py-4">
              <i class="fas fa-eye fa-2x text-primary mb-3"></i>
              <div class="text-uppercase text-muted small fw-semibold mb-1">Opens</div>
              <div class="fs-3 fw-bold text-primary">${fmt(opens)}</div>
            </div>
          </div>
        </div>

        <div class="col-6 col-md">
          <div class="card text-center h-100">
            <div class="card-body py-4">
              <i class="fas fa-mouse-pointer fa-2x mb-3" style="color: #8b5cf6;"></i>
              <div class="text-uppercase text-muted small fw-semibold mb-1">Clicks</div>
              <div class="fs-3 fw-bold" style="color: #8b5cf6;">${fmt(clicks)}</div>
            </div>
          </div>
        </div>

        <div class="col-6 col-md">
          <div class="card text-center h-100">
            <div class="card-body py-4">
              <i class="fas fa-exclamation-circle fa-2x text-danger mb-3"></i>
              <div class="text-uppercase text-muted small fw-semibold mb-1">Not Delivered</div>
              <div class="fs-3 fw-bold text-danger">${fmt(notDelivered)}</div>
              <div class="text-muted small">${fmt(notDeliveredPercent)}%</div>
            </div>
          </div>
        </div>

        <div class="col-6 col-md">
          <div class="card text-center h-100">
            <div class="card-body py-4">
              <i class="fas fa-undo fa-2x text-warning mb-3"></i>
              <div class="text-uppercase text-muted small fw-semibold mb-1">Bounced</div>
              <div class="fs-3 fw-bold text-warning">${fmt(bounce)}</div>
              <div class="mt-2">${this.getBounceRateBadge()}</div>
            </div>
          </div>
        </div>

        <div class="col-6 col-md">
          <div class="card text-center h-100">
            <div class="card-body py-4">
              <i class="fas fa-flag fa-2x text-danger mb-3"></i>
              <div class="text-uppercase text-muted small fw-semibold mb-1">Complaint</div>
              <div class="fs-3 fw-bold text-danger">${fmt(complaint)}</div>
              <div class="mt-2">${this.getComplaintRateBadge()}</div>
            </div>
          </div>
        </div>
      </div>
    `;
  }

  formatChartLabel(label, granularity) {
    const m = moment(label);
    if (granularity === '30m' || granularity === '1h') {
      return m.format('MMM D, HH:mm');
    }
    return m.format('L');
  }

  fillChartData(data) {
    const granularity = data.granularity || '1d';
    const dense = granularity === '30m' || granularity === '1h';

    const datasets = data.datasets.map(element => ({
      label:           element.label,
      data:            element.data,
      backgroundColor: this.chartColors[element.label],
      borderColor:     this.chartColors[element.label],
      fill:            false,
      tension:         0.3,
      pointRadius:     dense ? 2 : 3,
      pointHoverRadius: 5
    }));

    const labels = data.labels.map(label => this.formatChartLabel(label, granularity));

    if (this.chart) {
      this.chart.destroy();
    }

    if (this.chartCanvas) {
      this.chart = new Chart(this.chartCanvas, {
        type: 'line',
        data: { labels, datasets },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: {
            tooltip: {
              mode: 'index',
              intersect: false,
            }
          },
          interaction: {
            mode: 'nearest',
            intersect: true
          },
          scales: {
            x: {
              ticks: {
                maxTicksLimit: dense ? 12 : 16,
                autoSkip: true,
              }
            }
          }
        }
      });
    }
  }
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', () => new DashboardApp());
} else {
  new DashboardApp();
}

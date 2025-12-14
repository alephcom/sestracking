import axios from 'axios';
import moment from 'moment';
import { Chart, registerables } from 'chart.js';
Chart.register(...registerables);
import './bootstrap';

// Dashboard Application
class DashboardApp {
  constructor() {
    this.projectId = window.dashboardProjectId || 'all';
    this.dateRange = {
      startDate: moment().locale(window.navigator.language).startOf('week').utc().toDate(),
      endDate: moment().locale(window.navigator.language).endOf('week').utc().toDate()
    };
    this.counters = {};
    this.chart = null;
    this.chartCanvas = null;
    this.chartColors = {
      Send: '#6c757d',
      Delivery: '#28a745',
      Reject: '#db5b67',
      Bounce: '#c8c8c8',
      Complaint: '#dc3545',
      Failure: '#e59aa2',
      Open: '#007bff',
      Click: '#ffc107'
    };
    
    this.init();
  }

  init() {
    // Create DOM structure
    this.createDOM();
    
    // Setup date range picker
    this.setupDateRangePicker();
    
    // Expose to window for external control
    window.dashboardVueInstance = this;
    
    // Load initial data
    if (this.projectId) {
      this.loadData();
    }
  }

  createDOM() {
    const appContainer = document.getElementById('app');
    if (!appContainer) return;

    appContainer.innerHTML = `
      <div class="mb-4 w-25">
        <div class="input-group">
          <input type="date" id="date-from" class="form-control" />
          <span class="input-group-text">to</span>
          <input type="date" id="date-to" class="form-control" />
        </div>
      </div>

      <div id="counters-cards"></div>

      <div class="small mb-5" style="position: relative; height: 300px;">
        <canvas id="line-chart"></canvas>
      </div>
    `;

    this.chartCanvas = document.getElementById('line-chart');
  }

  setupDateRangePicker() {
    const dateFromInput = document.getElementById('date-from');
    const dateToInput = document.getElementById('date-to');

    if (dateFromInput && dateToInput) {
      dateFromInput.value = moment(this.dateRange.startDate).format('YYYY-MM-DD');
      dateToInput.value = moment(this.dateRange.endDate).format('YYYY-MM-DD');

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

  loadData() {
    axios.get(window.dashboardEndpoint, {
      params: {
        projectId: this.projectId,
        dateFrom: moment(this.dateRange.startDate).startOf('day').utc().toDate(),
        dateTo: moment(this.dateRange.endDate).endOf('day').utc().toDate(),
        tzOffset: moment().utcOffset()
      }
    })
      .then(response => {
        this.counters = response.data.counters;
        this.renderCounters();
        this.fillChartData(response.data.chartData);
      })
      .catch(error => {
        if (error.response) {
          alert(error.response.data.error);
        } else {
          alert(error);
        }
      });
  }

  renderCounters() {
    const countersContainer = document.getElementById('counters-cards');
    if (!countersContainer) return;

    const sent = this.counters.sent || 0;
    const delivered = this.counters.delivered || 0;
    const opens = this.counters.opens || 0;
    const clicks = this.counters.clicks || 0;
    const notDelivered = this.counters.notDelivered || 0;
    
    const deliveredPercent = sent ? ((delivered / sent) * 100).toFixed(2) : 0;
    const notDeliveredPercent = sent ? ((notDelivered / sent) * 100).toFixed(2) : 0;

    const formatNumber = (num) => {
      return new Intl.NumberFormat([], {maximumFractionDigits: 2}).format(num);
    };

    countersContainer.innerHTML = `
      <div class="card-deck mb-5 d-flex justify-content-center">
        <div class="card bg-light text-center col-md-2 col-sm-6 m-2">
          <div class="card-body">
            <h6 class="text-uppercase">Sent</h6>
            <h3 class="text-muted">${formatNumber(sent)}</h3>
          </div>
        </div>

        <div class="card bg-light text-center col-md-2 col-sm-6 m-2">
          <div class="card-body">
            <h6 class="text-uppercase">Delivered</h6>
            <h4 class="text-success mb-0">${formatNumber(delivered)}</h4>
            <div class="text-muted">${formatNumber(deliveredPercent)}%</div>
          </div>
        </div>

        <div class="card bg-light text-center col-md-2 col-sm-6 m-2">
          <div class="card-body">
            <h6 class="text-uppercase">Opens</h6>
            <h4 class="text-primary">${formatNumber(opens)}</h4>
          </div>
        </div>

        <div class="card bg-light text-center col-md-2 col-sm-6 m-2">
          <div class="card-body">
            <h6 class="text-uppercase">Clicks</h6>
            <h4 class="text-warning">${formatNumber(clicks)}</h4>
          </div>
        </div>

        <div class="card bg-light text-center col-md-2 col-sm-6 m-2">
          <div class="card-body">
            <h6 class="text-uppercase">Not Delivered</h6>
            <h4 class="text-danger mb-0">${formatNumber(notDelivered)}</h4>
            <div class="text-muted">${formatNumber(notDeliveredPercent)}%</div>
          </div>
        </div>
      </div>
    `;
  }

  fillChartData(data) {
    const datasets = [];
    data.datasets.forEach(element => {
      datasets.push({
        label: element.label,
        data: element.data,
        backgroundColor: this.chartColors[element.label],
        borderColor: this.chartColors[element.label],
        fill: false
      });
    });

    const labels = data.labels.map(label => moment(label).format('L'));

    const chartData = {
      labels: labels,
      datasets: datasets
    };

    const options = {
      responsive: true,
      maintainAspectRatio: false,
      tooltips: {
        mode: 'index',
        intersect: false,
      },
      hover: {
        mode: 'nearest',
        intersect: true
      }
    };

    if (this.chart) {
      this.chart.destroy();
    }

    if (this.chartCanvas) {
      this.chart = new Chart(this.chartCanvas, {
        type: 'line',
        data: chartData,
        options: options
      });
    }
  }
}

// Initialize when DOM is ready
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', () => {
    new DashboardApp();
  });
} else {
  new DashboardApp();
}

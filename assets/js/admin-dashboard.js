document.addEventListener('DOMContentLoaded', function () {
  var periodSelect = document.getElementById('revenue-period');
  var filterForm = document.getElementById('admin-revenue-filter');

  function syncRevenueFilterFields() {
    if (!periodSelect) {
      return;
    }

    var period = periodSelect.value;
    var fields = document.querySelectorAll('[data-revenue-field]');

    fields.forEach(function (field) {
      var key = field.getAttribute('data-revenue-field');
      var show = false;

      if (period === 'month' && key === 'month') {
        show = true;
      } else if (period === 'day' && key === 'day') {
        show = true;
      } else if (period === 'year' && key === 'year') {
        show = true;
      } else if (period === 'range' && (key === 'range-from' || key === 'range-to')) {
        show = true;
      }

      field.hidden = !show;
      field.querySelectorAll('input, select').forEach(function (input) {
        input.disabled = !show;
      });
    });
  }

  if (periodSelect) {
    periodSelect.addEventListener('change', syncRevenueFilterFields);
    syncRevenueFilterFields();
  }

  if (filterForm && periodSelect) {
    filterForm.addEventListener('submit', function () {
      var period = periodSelect.value;
      filterForm.querySelectorAll('[data-revenue-field] input, [data-revenue-field] select').forEach(function (input) {
        var field = input.closest('[data-revenue-field]');
        if (!field || field.hidden) {
          input.disabled = true;
        }
      });
    });
  }

  var chartCanvas = document.getElementById('admin-revenue-chart');
  var chartDataEl = document.getElementById('admin-revenue-chart-data');

  if (!chartCanvas || !chartDataEl || typeof Chart === 'undefined') {
    return;
  }

  var chartData;
  try {
    chartData = JSON.parse(chartDataEl.textContent || '{}');
  } catch (err) {
    return;
  }

  var formatCurrency = function (value) {
    return new Intl.NumberFormat('vi-VN').format(Math.round(value)) + 'đ';
  };

  var formatAxisCurrency = function (value) {
    var n = Math.round(value);
    if (n >= 1000000000) {
      return (n / 1000000000).toLocaleString('vi-VN', { maximumFractionDigits: 1 }) + ' tỷ';
    }
    if (n >= 1000000) {
      return (n / 1000000).toLocaleString('vi-VN', { maximumFractionDigits: n >= 10000000 ? 0 : 1 }) + ' tr';
    }
    if (n >= 1000) {
      return (n / 1000).toLocaleString('vi-VN', { maximumFractionDigits: 0 }) + ' n';
    }
    return formatCurrency(n);
  };

  function niceStep(value) {
    if (value <= 0) {
      return 1;
    }
    var exponent = Math.floor(Math.log10(value));
    var fraction = value / Math.pow(10, exponent);
    var niceFraction;
    if (fraction <= 1) {
      niceFraction = 1;
    } else if (fraction <= 2) {
      niceFraction = 2;
    } else if (fraction <= 2.5) {
      niceFraction = 2.5;
    } else if (fraction <= 5) {
      niceFraction = 5;
    } else {
      niceFraction = 10;
    }
    return niceFraction * Math.pow(10, exponent);
  }

  function computeYScale(chartData) {
    var allValues = []
      .concat(chartData.net || [])
      .concat(chartData.refunded || [])
      .map(function (v) {
        return Number(v) || 0;
      });

    var maxVal = 0;
    allValues.forEach(function (v) {
      if (v > maxVal) {
        maxVal = v;
      }
    });

    if (maxVal <= 0) {
      return { max: 10000000, step: 2000000 };
    }

    var tickCount = 10;
    var step = niceStep(maxVal / (tickCount - 2));
    var max = Math.ceil((maxVal * 1.18) / step) * step;

    if (max / step < 6) {
      step = niceStep(maxVal / 6);
      max = Math.ceil((maxVal * 1.18) / step) * step;
    }

    return { max: max, step: step };
  }

  var yScale = computeYScale(chartData);

  new Chart(chartCanvas, {
    type: 'line',
    data: {
      labels: chartData.labels || [],
      datasets: [
        {
          label: 'Doanh thu thuần',
          data: chartData.net || [],
          borderColor: 'rgba(47, 93, 63, 1)',
          backgroundColor: 'rgba(47, 93, 63, 0.12)',
          borderWidth: 2.5,
          fill: true,
          tension: 0.35,
          pointRadius: 3,
          pointHoverRadius: 5,
          pointBackgroundColor: 'rgba(47, 93, 63, 1)',
        },
        {
          label: 'Hoàn trả',
          data: chartData.refunded || [],
          borderColor: 'rgba(239, 68, 68, 1)',
          backgroundColor: 'transparent',
          borderWidth: 2,
          borderDash: [6, 4],
          fill: false,
          tension: 0.35,
          pointRadius: 2,
          pointHoverRadius: 5,
          pointBackgroundColor: 'rgba(239, 68, 68, 1)',
        },
      ],
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      interaction: {
        mode: 'index',
        intersect: false,
      },
      plugins: {
        legend: {
          position: 'bottom',
          labels: {
            boxWidth: 12,
            boxHeight: 12,
            padding: 16,
            usePointStyle: true,
          },
        },
        tooltip: {
          callbacks: {
            label: function (context) {
              return context.dataset.label + ': ' + formatCurrency(context.parsed.y || 0);
            },
          },
        },
      },
      scales: {
        x: {
          grid: {
            color: 'rgba(0, 0, 0, 0.04)',
          },
          ticks: {
            maxRotation: 0,
            autoSkip: true,
            maxTicksLimit: 16,
          },
        },
        y: {
          beginAtZero: true,
          min: 0,
          max: yScale.max,
          grid: {
            color: 'rgba(0, 0, 0, 0.06)',
          },
          ticks: {
            stepSize: yScale.step,
            autoSkip: false,
            maxTicksLimit: 12,
            callback: function (value) {
              return formatAxisCurrency(value);
            },
          },
        },
      },
    },
  });
});

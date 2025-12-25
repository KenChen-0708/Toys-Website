// Analytics Page JavaScript

// Chart instances
let revenueChart = null;
let orderStatusChart = null;
let categoryChart = null;
let paymentChart = null;

// Initialize charts when DOM is loaded
document.addEventListener('DOMContentLoaded', function () {
    initializeCharts();
    setupChartControls();
});

// Initialize all charts
function initializeCharts() {
    if (typeof analyticsData === 'undefined') {
        console.error('Analytics data not found');
        return;
    }

    initRevenueChart();
    initOrderStatusChart();
    initCategoryChart();
    initPaymentChart();
}

// Revenue Trend Chart
function initRevenueChart() {
    const ctx = document.getElementById('revenueChart');
    if (!ctx) return;

    const dates = analyticsData.dailyRevenue.map(item => {
        const date = new Date(item.date);
        return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
    });
    const revenues = analyticsData.dailyRevenue.map(item => parseFloat(item.revenue));
    const orders = analyticsData.dailyRevenue.map(item => parseInt(item.orders));

    revenueChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: dates,
            datasets: [{
                label: 'Revenue (RM)',
                data: revenues,
                borderColor: '#3b82f6',
                backgroundColor: 'rgba(59, 130, 246, 0.1)',
                borderWidth: 3,
                fill: true,
                tension: 0.4,
                pointRadius: 5,
                pointHoverRadius: 7,
                pointBackgroundColor: '#3b82f6',
                pointBorderColor: '#fff',
                pointBorderWidth: 2
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: true,
                    position: 'top',
                    labels: {
                        font: {
                            size: 14,
                            weight: '600'
                        },
                        padding: 15,
                        usePointStyle: true
                    }
                },
                tooltip: {
                    backgroundColor: 'rgba(0, 0, 0, 0.8)',
                    padding: 12,
                    titleFont: {
                        size: 14,
                        weight: 'bold'
                    },
                    bodyFont: {
                        size: 13
                    },
                    callbacks: {
                        label: function (context) {
                            return 'Revenue: RM ' + context.parsed.y.toFixed(2);
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function (value) {
                            return 'RM ' + value.toLocaleString();
                        },
                        font: {
                            size: 12
                        }
                    },
                    grid: {
                        color: 'rgba(0, 0, 0, 0.05)'
                    }
                },
                x: {
                    ticks: {
                        font: {
                            size: 12
                        }
                    },
                    grid: {
                        display: false
                    }
                }
            }
        }
    });

    // Store orders data for switching
    revenueChart.ordersData = orders;
}

// Order Status Chart
function initOrderStatusChart() {
    const ctx = document.getElementById('orderStatusChart');
    if (!ctx) return;

    const labels = analyticsData.orderStatus.map(item => item.status);
    const data = analyticsData.orderStatus.map(item => parseInt(item.count));

    const colors = {
        'Pending': '#f59e0b',
        'Shipped': '#3b82f6',
        'Delivered': '#10b981',
        'Cancelled': '#ef4444',
        'Processing': '#8b5cf6'
    };

    const backgroundColors = labels.map(label => colors[label] || '#6b7280');

    orderStatusChart = new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: labels,
            datasets: [{
                data: data,
                backgroundColor: backgroundColors,
                borderWidth: 3,
                borderColor: '#fff',
                hoverOffset: 10
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        padding: 15,
                        font: {
                            size: 13,
                            weight: '600'
                        },
                        usePointStyle: true
                    }
                },
                tooltip: {
                    backgroundColor: 'rgba(0, 0, 0, 0.8)',
                    padding: 12,
                    titleFont: {
                        size: 14,
                        weight: 'bold'
                    },
                    bodyFont: {
                        size: 13
                    },
                    callbacks: {
                        label: function (context) {
                            const total = context.dataset.data.reduce((a, b) => a + b, 0);
                            const percentage = ((context.parsed / total) * 100).toFixed(1);
                            return context.label + ': ' + context.parsed + ' (' + percentage + '%)';
                        }
                    }
                }
            }
        }
    });
}

// Category Revenue Chart
function initCategoryChart() {
    const ctx = document.getElementById('categoryChart');
    if (!ctx) return;

    const labels = analyticsData.categoryRevenue.map(item => {
        const category = item.category || 'Uncategorized';
        return category.charAt(0).toUpperCase() + category.slice(1).replace(/_/g, ' ');
    });
    const data = analyticsData.categoryRevenue.map(item => parseFloat(item.revenue));

    const gradient = ctx.getContext('2d').createLinearGradient(0, 0, 0, 300);
    gradient.addColorStop(0, 'rgba(139, 92, 246, 0.8)');
    gradient.addColorStop(1, 'rgba(139, 92, 246, 0.2)');

    categoryChart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [{
                label: 'Revenue (RM)',
                data: data,
                backgroundColor: gradient,
                borderColor: '#8b5cf6',
                borderWidth: 2,
                borderRadius: 8,
                barThickness: 40
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    backgroundColor: 'rgba(0, 0, 0, 0.8)',
                    padding: 12,
                    titleFont: {
                        size: 14,
                        weight: 'bold'
                    },
                    bodyFont: {
                        size: 13
                    },
                    callbacks: {
                        label: function (context) {
                            return 'Revenue: RM ' + context.parsed.y.toFixed(2);
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function (value) {
                            return 'RM ' + value.toLocaleString();
                        },
                        font: {
                            size: 12
                        }
                    },
                    grid: {
                        color: 'rgba(0, 0, 0, 0.05)'
                    }
                },
                x: {
                    ticks: {
                        font: {
                            size: 11
                        }
                    },
                    grid: {
                        display: false
                    }
                }
            }
        }
    });
}

// Payment Methods Chart
function initPaymentChart() {
    const ctx = document.getElementById('paymentChart');
    if (!ctx) return;

    const labels = analyticsData.paymentMethods.map(item => item.payment_method);
    const data = analyticsData.paymentMethods.map(item => parseInt(item.count));

    const colors = [
        '#3b82f6',
        '#10b981',
        '#f59e0b',
        '#ef4444',
        '#8b5cf6',
        '#ec4899',
        '#14b8a6'
    ];

    paymentChart = new Chart(ctx, {
        type: 'pie',
        data: {
            labels: labels,
            datasets: [{
                data: data,
                backgroundColor: colors,
                borderWidth: 3,
                borderColor: '#fff',
                hoverOffset: 10
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        padding: 15,
                        font: {
                            size: 12,
                            weight: '600'
                        },
                        usePointStyle: true
                    }
                },
                tooltip: {
                    backgroundColor: 'rgba(0, 0, 0, 0.8)',
                    padding: 12,
                    titleFont: {
                        size: 14,
                        weight: 'bold'
                    },
                    bodyFont: {
                        size: 13
                    },
                    callbacks: {
                        label: function (context) {
                            const total = context.dataset.data.reduce((a, b) => a + b, 0);
                            const percentage = ((context.parsed / total) * 100).toFixed(1);
                            return context.label + ': ' + context.parsed + ' (' + percentage + '%)';
                        }
                    }
                }
            }
        }
    });
}

// Setup chart control buttons
function setupChartControls() {
    const chartBtns = document.querySelectorAll('.chart-btn');

    chartBtns.forEach(btn => {
        btn.addEventListener('click', function () {
            const chartType = this.getAttribute('data-chart');

            // Update active state
            chartBtns.forEach(b => b.classList.remove('active'));
            this.classList.add('active');

            // Switch chart data
            if (chartType === 'revenue') {
                switchToRevenue();
            } else if (chartType === 'orders') {
                switchToOrders();
            }
        });
    });
}

// Switch to revenue view
function switchToRevenue() {
    if (!revenueChart) return;

    const revenues = analyticsData.dailyRevenue.map(item => parseFloat(item.revenue));

    revenueChart.data.datasets[0].label = 'Revenue (RM)';
    revenueChart.data.datasets[0].data = revenues;
    revenueChart.data.datasets[0].borderColor = '#3b82f6';
    revenueChart.data.datasets[0].backgroundColor = 'rgba(59, 130, 246, 0.1)';
    revenueChart.data.datasets[0].pointBackgroundColor = '#3b82f6';

    revenueChart.options.plugins.tooltip.callbacks.label = function (context) {
        return 'Revenue: RM ' + context.parsed.y.toFixed(2);
    };

    revenueChart.options.scales.y.ticks.callback = function (value) {
        return 'RM ' + value.toLocaleString();
    };

    revenueChart.update();
}

// Switch to orders view
function switchToOrders() {
    if (!revenueChart || !revenueChart.ordersData) return;

    revenueChart.data.datasets[0].label = 'Orders';
    revenueChart.data.datasets[0].data = revenueChart.ordersData;
    revenueChart.data.datasets[0].borderColor = '#10b981';
    revenueChart.data.datasets[0].backgroundColor = 'rgba(16, 185, 129, 0.1)';
    revenueChart.data.datasets[0].pointBackgroundColor = '#10b981';

    revenueChart.options.plugins.tooltip.callbacks.label = function (context) {
        return 'Orders: ' + context.parsed.y;
    };

    revenueChart.options.scales.y.ticks.callback = function (value) {
        return value;
    };

    revenueChart.update();
}

// Date range quick filters
function setDateRange(range) {
    const today = new Date();
    let startDate, endDate;

    endDate = today.toISOString().split('T')[0];

    switch (range) {
        case 'today':
            startDate = endDate;
            break;
        case 'week':
            const weekAgo = new Date(today);
            weekAgo.setDate(today.getDate() - 7);
            startDate = weekAgo.toISOString().split('T')[0];
            break;
        case 'month':
            startDate = new Date(today.getFullYear(), today.getMonth(), 1).toISOString().split('T')[0];
            break;
        case 'year':
            startDate = new Date(today.getFullYear(), 0, 1).toISOString().split('T')[0];
            break;
        default:
            return;
    }

    document.getElementById('start_date').value = startDate;
    document.getElementById('end_date').value = endDate;
    document.querySelector('.date-filter-form').submit();
}

// Reset date filter
function resetDateFilter() {
    const today = new Date();
    const firstDayOfMonth = new Date(today.getFullYear(), today.getMonth(), 1);

    document.getElementById('start_date').value = firstDayOfMonth.toISOString().split('T')[0];
    document.getElementById('end_date').value = today.toISOString().split('T')[0];
    document.querySelector('.date-filter-form').submit();
}

// Export report functionality
function exportReport() {
    // Prepare data for export
    const reportData = {
        dateRange: {
            start: document.getElementById('start_date').value,
            end: document.getElementById('end_date').value
        },
        metrics: extractMetrics(),
        dailyRevenue: analyticsData.dailyRevenue,
        orderStatus: analyticsData.orderStatus,
        categoryRevenue: analyticsData.categoryRevenue,
        paymentMethods: analyticsData.paymentMethods
    };

    // Convert to CSV
    const csv = convertToCSV(reportData);

    // Download CSV file
    downloadCSV(csv, 'analytics_report_' + new Date().toISOString().split('T')[0] + '.csv');
}

// Extract metrics from the page
function extractMetrics() {
    const metrics = {};
    const metricCards = document.querySelectorAll('.metric-card');

    metricCards.forEach(card => {
        const title = card.querySelector('h3').textContent;
        const value = card.querySelector('.metric-value').textContent;
        metrics[title] = value;
    });

    return metrics;
}

// Convert data to CSV format
function convertToCSV(data) {
    let csv = 'Analytics Report\n\n';

    // Date Range
    csv += 'Date Range\n';
    csv += 'Start Date,' + data.dateRange.start + '\n';
    csv += 'End Date,' + data.dateRange.end + '\n\n';

    // Metrics
    csv += 'Key Metrics\n';
    for (const [key, value] of Object.entries(data.metrics)) {
        csv += key + ',' + value.replace(/,/g, '') + '\n';
    }
    csv += '\n';

    // Daily Revenue
    csv += 'Daily Revenue\n';
    csv += 'Date,Revenue,Orders\n';
    data.dailyRevenue.forEach(item => {
        csv += item.date + ',' + item.revenue + ',' + item.orders + '\n';
    });
    csv += '\n';

    // Order Status
    csv += 'Order Status\n';
    csv += 'Status,Count\n';
    data.orderStatus.forEach(item => {
        csv += item.status + ',' + item.count + '\n';
    });
    csv += '\n';

    // Category Revenue
    csv += 'Revenue by Category\n';
    csv += 'Category,Revenue,Items Sold\n';
    data.categoryRevenue.forEach(item => {
        csv += item.category + ',' + item.revenue + ',' + item.items_sold + '\n';
    });
    csv += '\n';

    // Payment Methods
    csv += 'Payment Methods\n';
    csv += 'Method,Count,Total Amount\n';
    data.paymentMethods.forEach(item => {
        csv += item.payment_method + ',' + item.count + ',' + item.total_amount + '\n';
    });

    return csv;
}

// Download CSV file
function downloadCSV(csv, filename) {
    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');

    if (navigator.msSaveBlob) { // IE 10+
        navigator.msSaveBlob(blob, filename);
    } else {
        link.href = URL.createObjectURL(blob);
        link.download = filename;
        link.style.display = 'none';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }
}

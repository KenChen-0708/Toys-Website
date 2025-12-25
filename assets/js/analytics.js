// Analytics Page JavaScript for Deep Insights - Custom Implementation (No Framework)

document.addEventListener('DOMContentLoaded', function () {
    if (typeof analyticsData === 'undefined') return;

    renderRevenueChart();
    renderCustomerSegmentChart();
    renderCategoryDistributionChart();
    renderOrderPipelineChart();
    setupTooltipEvents();
});

function setupTooltipEvents() {
    const tooltip = document.getElementById('chartTooltip');
    if (!tooltip) return;

    window.showTooltip = (e, title, value) => {
        tooltip.style.display = 'flex';
        // Force reflow for transition
        tooltip.offsetHeight;

        tooltip.querySelector('.tooltip-title').textContent = title;
        tooltip.querySelector('.tooltip-value').textContent = value;

        const x = e.clientX + 15;
        const y = e.clientY + 15;

        // Overflow check
        const width = tooltip.offsetWidth;
        const height = tooltip.offsetHeight;
        const finalX = (x + width > window.innerWidth) ? x - width - 30 : x;
        const finalY = (y + height > window.innerHeight) ? y - height - 30 : y;

        tooltip.style.left = finalX + 'px';
        tooltip.style.top = finalY + 'px';
        tooltip.style.opacity = '1';
        tooltip.style.transform = 'translateY(0) scale(1)';
    };

    window.hideTooltip = () => {
        tooltip.style.opacity = '0';
        tooltip.style.transform = 'translateY(10px) scale(0.95)';
        setTimeout(() => {
            if (tooltip.style.opacity === '0') {
                tooltip.style.display = 'none';
            }
        }, 300);
    };
}

function renderRevenueChart() {
    const container = document.getElementById('revenueChartContainer');
    if (!container || !analyticsData.dailyRevenue.length) return;

    const data = analyticsData.dailyRevenue.map(d => parseFloat(d.revenue));
    const labels = analyticsData.dailyRevenue.map(d => d.date);
    const max = Math.max(...data, 1);

    const width = 1000;
    const height = 400; // Increased height to room for labels
    const paddingLeft = 80; // More room for Y labels
    const paddingRight = 40;
    const paddingTop = 40;
    const paddingBottom = 60; // Room for X labels

    const innerWidth = width - paddingLeft - paddingRight;
    const innerHeight = height - paddingTop - paddingBottom;

    let points = "";
    let markers = "";
    let xLabels = "";
    const step = innerWidth / (Math.max(data.length - 1, 1));

    // Determine label frequency (don't show every date if there are many)
    const labelStep = Math.ceil(data.length / 10);

    data.forEach((val, i) => {
        const x = paddingLeft + (i * step);
        const y = paddingTop + innerHeight - (val / max * innerHeight);
        points += `${x},${y} `;

        markers += `
            <circle cx="${x}" cy="${y}" r="6" fill="white" stroke="var(--primary)" stroke-width="3" 
                    class="chart-point" 
                    onmouseover="showTooltip(event, '${labels[i]}', 'RM ${val.toLocaleString(undefined, { minimumFractionDigits: 2 })}')"
                    onmouseout="hideTooltip()"
                    style="animation: popIn 0.5s ease-out forwards ${i * 0.05}s; opacity: 0; cursor: pointer;">
            </circle>`;

        // Add X-axis label
        if (i % labelStep === 0 || i === data.length - 1) {
            const date = new Date(labels[i]);
            const formattedDate = date.toLocaleDateString(undefined, { month: 'short', day: 'numeric' });
            xLabels += `<text x="${x}" y="${height - 20}" text-anchor="middle" class="chart-label">${formattedDate}</text>`;
        }
    });

    // Y-axis labels (3 markers)
    const yLabels = `
        <text x="${paddingLeft - 15}" y="${paddingTop + innerHeight}" text-anchor="end" class="chart-label">0</text>
        <text x="${paddingLeft - 15}" y="${paddingTop + (innerHeight / 2)}" text-anchor="end" class="chart-label">RM ${(max / 2 / 1000).toFixed(1)}k</text>
        <text x="${paddingLeft - 15}" y="${paddingTop}" text-anchor="end" class="chart-label">RM ${(max / 1000).toFixed(1)}k</text>
    `;

    const svg = `
        <svg viewBox="0 0 ${width} ${height}" class="svg-chart">
            <defs>
                <linearGradient id="chartGradient" x1="0" y1="0" x2="0" y2="1">
                    <stop offset="0%" stop-color="var(--primary)" stop-opacity="0.3"/>
                    <stop offset="100%" stop-color="var(--primary)" stop-opacity="0.01"/>
                </linearGradient>
                <style>
                    @keyframes popIn { from { transform: scale(0); opacity: 0; } to { transform: scale(1); opacity: 1; } }
                    .chart-point { transition: all 0.2s; }
                    .chart-point:hover { r: 9; stroke-width: 4; fill: var(--primary); }
                    .chart-label { font-size: 13px; fill: var(--text-muted); font-weight: 500; font-family: inherit; }
                </style>
            </defs>
            <!-- Grid Lines -->
            <line x1="${paddingLeft}" y1="${paddingTop}" x2="${width - paddingRight}" y2="${paddingTop}" stroke="#f1f5f9" stroke-width="1" />
            <line x1="${paddingLeft}" y1="${paddingTop + (innerHeight / 2)}" x2="${width - paddingRight}" y2="${paddingTop + (innerHeight / 2)}" stroke="#f1f5f9" stroke-width="1" />
            <line x1="${paddingLeft}" y1="${paddingTop + innerHeight}" x2="${width - paddingRight}" y2="${paddingTop + innerHeight}" stroke="#e2e8f0" stroke-width="1" />
            
            <path d="M ${paddingLeft},${paddingTop + innerHeight} ${points} L ${width - paddingRight},${paddingTop + innerHeight} Z" fill="url(#chartGradient)" />
            <polyline points="${points}" class="chart-path" />
            ${markers}
            ${xLabels}
            ${yLabels}
        </svg>
    `;

    container.innerHTML = svg;
}

function renderCustomerSegmentChart() {
    const container = document.getElementById('segmentChartContainer');
    if (!container || !analyticsData.customerSegments.length) return;

    const total = analyticsData.customerSegments.reduce((acc, curr) => acc + parseInt(curr.customer_count), 0);
    const colors = ['var(--primary)', 'var(--indigo)', 'var(--violet)', 'var(--primary-light)'];

    let segments = "";
    let legendItems = "";
    let currentOffset = 0;
    const radius = 60;
    const circ = 2 * Math.PI * radius;

    analyticsData.customerSegments.forEach((s, i) => {
        const percent = (s.customer_count / total);
        const dashArray = `${percent * circ} ${circ}`;
        const color = colors[i % colors.length];

        segments += `
            <circle class="pie-segment segment-pie-only" cx="125" cy="125" r="${radius}" 
                    stroke="${color}" stroke-dasharray="${dashArray}" 
                    stroke-dashoffset="-${currentOffset * circ}"
                    stroke-width="120"
                    onmouseover="showTooltip(event, '${s.segment}', '${s.customer_count} Customers (${(percent * 100).toFixed(1)}%)')"
                    onmouseout="hideTooltip()"></circle>
        `;

        legendItems += `
            <div class="legend-item" onmouseover="showTooltip(event, '${s.segment}', '${s.customer_count} Customers (${(percent * 100).toFixed(1)}%)')" onmouseout="hideTooltip()" style="cursor: pointer;">
                <span class="legend-color" style="background: ${color}"></span>
                <span>${s.segment}: <strong>${s.customer_count}</strong></span>
            </div>
        `;
        currentOffset += percent;
    });

    const pieHtml = `
        <div style="display: flex; flex-direction: column; align-items: center; width: 100%;">
            <div class="pie-wrapper">
                <svg viewBox="0 0 250 250" class="pie-chart-svg">
                    ${segments}
                </svg>
            </div>
            <div class="chart-legend">
                ${legendItems}
            </div>
        </div>
    `;

    container.innerHTML = pieHtml;
}

function renderCategoryDistributionChart() {
    const container = document.getElementById('categoryChartContainer');
    if (!container || !analyticsData.categoryStats.length) return;

    const max = Math.max(...analyticsData.categoryStats.map(s => parseFloat(s.revenue)), 1);

    let html = '<div class="vertical-bar-chart">';
    analyticsData.categoryStats.slice(0, 6).forEach(s => {
        const percent = (s.revenue / max) * 100;
        const label = s.category.charAt(0).toUpperCase() + s.category.slice(1);
        const revenue = parseFloat(s.revenue);

        let formattedRev = "RM " + (revenue >= 1000 ? (revenue / 1000).toFixed(1) + "k" : revenue.toFixed(0));

        html += `
            <div class="vertical-bar-item" 
                 onmouseover="showTooltip(event, '${label}', 'Revenue: RM ${revenue.toLocaleString(undefined, { minimumFractionDigits: 2 })}')" 
                 onmouseout="hideTooltip()">
                <div class="v-bar-value">${formattedRev}</div>
                <div class="v-bar-track">
                    <div class="v-bar-fill" style="--final-height: ${percent}%"></div>
                </div>
                <div class="v-bar-label">${label}</div>
            </div>
        `;
    });
    html += '</div>';

    container.innerHTML = html;
}

function renderOrderPipelineChart() {
    const container = document.getElementById('pipelineChartContainer');
    if (!container || !analyticsData.orderStatus.length) return;

    const total = analyticsData.orderStatus.reduce((acc, curr) => acc + parseInt(curr.count), 0);
    const statusColors = {
        'Pending': '#f59e0b',
        'Processing': '#6366f1',
        'Shipped': '#3b82f6',
        'Delivered': '#10b981',
        'Cancelled': '#ef4444'
    };

    let segments = "";
    let currentOffset = 0;
    const radius = 70;
    const circ = 2 * Math.PI * radius;

    analyticsData.orderStatus.forEach((s) => {
        const percent = (s.count / total);
        const dashArray = `${percent * circ} ${circ}`;
        const color = statusColors[s.status] || '#cbd5e1';

        segments += `
            <circle class="pie-segment" cx="125" cy="125" r="${radius}" 
                    stroke="${color}" stroke-dasharray="${dashArray}" 
                    stroke-dashoffset="-${currentOffset * circ}"
                    onmouseover="showTooltip(event, '${s.status}', '${s.count} Orders (${(percent * 100).toFixed(1)}%)')"
                    onmouseout="hideTooltip()"></circle>
        `;
        currentOffset += percent;
    });

    const doughnutHtml = `
        <div style="display: flex; flex-direction: column; align-items: center; width: 100%;">
            <div class="pie-wrapper">
                <svg viewBox="0 0 250 250" class="pie-chart-svg">
                    ${segments}
                </svg>
                <div class="doughnut-hole" onmouseover="showTooltip(event, 'Order Volume', 'Grand Total: ${total}')" onmouseout="hideTooltip()">
                    <span class="doughnut-label">Total Orders</span>
                    <span class="doughnut-value">${total}</span>
                </div>
            </div>
            <div class="chart-legend">
                ${analyticsData.orderStatus.map(s => `
                    <div class="legend-item" onmouseover="showTooltip(event, '${s.status}', '${s.count} Orders')" onmouseout="hideTooltip()">
                        <span class="legend-color" style="background: ${statusColors[s.status] || '#cbd5e1'}"></span>
                        <span>${s.status}: <strong>${s.count}</strong></span>
                    </div>
                `).join('')}
            </div>
        </div>
    `;

    container.innerHTML = doughnutHtml;
}

function exportDeepData() {
    alert("Exporting deep analytics data to CSV...");
    window.location.href = 'export_analytics.php' + window.location.search;
}

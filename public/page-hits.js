/**
 * Page Hits Analytics - Charts and data visualization
 */

// ========================================
// Initialization
// ========================================

document.addEventListener('DOMContentLoaded', function() {
    initPieChart();
});

// ========================================
// Pie Chart - Top Pages
// ========================================

function initPieChart() {
    const pieChartEl = document.querySelector('#pagesPieChart');
    if (!pieChartEl) return;

    const topPages = window.pageHitsConfig.topPages;
    const totalHits = window.pageHitsConfig.totalHits;

    const labels = topPages.map(p => p.page_url);
    const series = topPages.map(p => parseInt(p.total_hits));

    // Calculate "Other" if there are more than 5 pages
    const top5Total = series.reduce((a, b) => a + b, 0);
    const otherTotal = totalHits - top5Total;
    if (otherTotal > 0) {
        labels.push('Other');
        series.push(otherTotal);
    }

    // Gold, Silver, Bronze, then theme colors, then gray for Other
    const colors = [
        '#FFD700',
        '#A9A9A9',
        '#CD7F32',
        config.colors.info,
        config.colors.secondary,
        '#6c757d'
    ];

    const pieConfig = {
        chart: {
            height: 200,
            type: 'donut'
        },
        series: series,
        labels: labels,
        colors: colors.slice(0, series.length),
        stroke: {
            width: 3,
            colors: [config.colors.cardColor]
        },
        dataLabels: {
            enabled: false
        },
        legend: {
            show: false
        },
        states: {
            hover: {
                filter: {
                    type: 'lighten',
                    value: 0.05
                }
            }
        },
        plotOptions: {
            pie: {
                donut: {
                    size: '70%',
                    labels: {
                        show: true,
                        name: {
                            fontSize: '12px'
                        },
                        value: {
                            fontSize: '18px',
                            fontWeight: 600,
                            formatter: val => val + ' hits'
                        },
                        total: {
                            show: true,
                            label: 'Total',
                            fontSize: '12px',
                            formatter: w => w.globals.seriesTotals.reduce((a, b) => a + b, 0)
                        }
                    }
                }
            }
        }
    };

    new ApexCharts(pieChartEl, pieConfig).render();
}

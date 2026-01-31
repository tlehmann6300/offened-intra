/**
 * Admin Dashboard JavaScript
 * Chart.js initialization for member growth chart
 */
document.addEventListener('DOMContentLoaded', function() {
    const ctx = document.getElementById('memberGrowthChart');
    
    if (!ctx) {
        return; // Chart element not found, exit gracefully
    }
    
    // Check if Chart.js is available
    if (typeof Chart === 'undefined') {
        console.error('Chart.js library not loaded');
        return;
    }
    
    // Get data from data attribute (will be set by PHP)
    const memberGrowthDataStr = ctx.getAttribute('data-member-growth');
    if (!memberGrowthDataStr) {
        console.error('Member growth data not found');
        return;
    }
    
    let memberGrowthData;
    try {
        memberGrowthData = JSON.parse(memberGrowthDataStr);
    } catch (e) {
        console.error('Failed to parse member growth data:', e);
        return;
    }
    
    const labels = memberGrowthData.map(item => item.label);
    const data = memberGrowthData.map(item => item.count);
    
    // Create gradient for the chart
    const gradient = ctx.getContext('2d').createLinearGradient(0, 0, 0, 400);
    gradient.addColorStop(0, 'rgba(109, 151, 68, 0.3)');
    gradient.addColorStop(1, 'rgba(109, 151, 68, 0.05)');
    
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [{
                label: 'Gesamtzahl Mitglieder',
                data: data,
                borderColor: 'rgb(109, 151, 68)',
                backgroundColor: gradient,
                borderWidth: 3,
                fill: true,
                tension: 0.4,
                pointRadius: 5,
                pointBackgroundColor: 'rgb(109, 151, 68)',
                pointBorderColor: '#fff',
                pointBorderWidth: 2,
                pointHoverRadius: 7,
                pointHoverBackgroundColor: 'rgb(109, 151, 68)',
                pointHoverBorderColor: '#fff',
                pointHoverBorderWidth: 3
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
                            family: 'Inter, sans-serif',
                            size: 14,
                            weight: '600'
                        },
                        color: 'rgb(32, 35, 74)',
                        padding: 15,
                        usePointStyle: true,
                        pointStyle: 'circle'
                    }
                },
                tooltip: {
                    backgroundColor: 'rgba(255, 255, 255, 0.95)',
                    titleColor: 'rgb(32, 35, 74)',
                    bodyColor: 'rgb(77, 80, 97)',
                    borderColor: 'rgba(109, 151, 68, 0.3)',
                    borderWidth: 1,
                    padding: 12,
                    displayColors: false,
                    callbacks: {
                        label: function(context) {
                            return 'Mitglieder: ' + context.parsed.y;
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        font: {
                            family: 'Inter, sans-serif',
                            size: 12
                        },
                        color: 'rgb(77, 80, 97)',
                        stepSize: 1
                    },
                    grid: {
                        color: 'rgba(32, 35, 74, 0.1)',
                        drawBorder: false
                    }
                },
                x: {
                    ticks: {
                        font: {
                            family: 'Inter, sans-serif',
                            size: 12
                        },
                        color: 'rgb(77, 80, 97)',
                        maxRotation: 45,
                        minRotation: 45
                    },
                    grid: {
                        display: false,
                        drawBorder: false
                    }
                }
            }
        }
    });
});

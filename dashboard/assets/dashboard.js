/**
 * Telegram Bot Dashboard JavaScript
 * Handles all dashboard interactions, API calls, and chart rendering
 */

// Configuration
const API_BASE = 'api.php'; // Backend API endpoint
let charts = {};

// Initialize dashboard on load
document.addEventListener('DOMContentLoaded', function() {
    initializeNavigation();
    initializeForms();
    loadDashboardData();
    
    // Auto-refresh every 30 seconds
    setInterval(loadDashboardData, 30000);
});

/**
 * Navigation handling
 */
function initializeNavigation() {
    const sidebarLinks = document.querySelectorAll('.sidebar-menu a');
    const sections = document.querySelectorAll('.content-section');
    
    sidebarLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            
            // Update active state
            sidebarLinks.forEach(l => l.classList.remove('active'));
            this.classList.add('active');
            
            // Show corresponding section
            const sectionId = this.dataset.section;
            sections.forEach(section => {
                section.classList.add('d-none');
            });
            document.getElementById(`section-${sectionId}`).classList.remove('d-none');
            
            // Update page title
            document.getElementById('pageTitle').textContent = this.querySelector('span').textContent;
            
            // Load section-specific data
            loadSectionData(sectionId);
        });
    });
    
    // Mobile sidebar toggle
    const toggleBtn = document.getElementById('toggleSidebar');
    const sidebar = document.getElementById('sidebar');
    
    if (toggleBtn) {
        toggleBtn.addEventListener('click', function() {
            sidebar.classList.toggle('show');
        });
    }
}

/**
 * Form initialization
 */
function initializeForms() {
    // Broadcast form
    const broadcastForm = document.getElementById('broadcastForm');
    if (broadcastForm) {
        broadcastForm.addEventListener('submit', handleBroadcastSubmit);
    }
    
    // Target audience change handler
    const broadcastTarget = document.getElementById('broadcastTarget');
    if (broadcastTarget) {
        broadcastTarget.addEventListener('change', function() {
            const customDiv = document.getElementById('customChatIdsDiv');
            customDiv.style.display = this.value === 'custom' ? 'block' : 'none';
        });
    }
    
    // Deep link form
    const deepLinkForm = document.getElementById('deepLinkForm');
    if (deepLinkForm) {
        deepLinkForm.addEventListener('submit', handleDeepLinkSubmit);
    }
    
    // Deep link type change handler
    const deepLinkType = document.getElementById('deepLinkType');
    if (deepLinkType) {
        deepLinkType.addEventListener('change', function() {
            const staticParamDiv = document.getElementById('staticParamDiv');
            const dynamicDataDiv = document.getElementById('dynamicDataDiv');
            
            if (this.value === 'dynamic') {
                staticParamDiv.style.display = 'none';
                dynamicDataDiv.style.display = 'block';
            } else if (this.value === 'referral') {
                staticParamDiv.style.display = 'none';
                dynamicDataDiv.style.display = 'none';
            } else {
                staticParamDiv.style.display = 'block';
                dynamicDataDiv.style.display = 'none';
            }
        });
    }
    
    // Settings form
    const settingsForm = document.getElementById('settingsForm');
    if (settingsForm) {
        settingsForm.addEventListener('submit', handleSettingsSubmit);
    }
}

/**
 * Load dashboard data
 */
async function loadDashboardData() {
    try {
        const response = await fetch(`${API_BASE}?action=get_stats`);
        const data = await response.json();
        
        if (data.success) {
            updateStatsCards(data.stats);
            updateCharts(data);
            updateRecentErrors(data.errors);
            updateTopCommands(data.commands);
        }
    } catch (error) {
        console.error('Error loading dashboard data:', error);
        showToast('Error loading dashboard data', 'danger');
    }
}

/**
 * Load section-specific data
 */
function loadSectionData(sectionId) {
    switch(sectionId) {
        case 'broadcast':
            loadBroadcastHistory();
            break;
        case 'deeplink':
            loadDeepLinks();
            break;
        case 'users':
            loadUsers();
            break;
        case 'commands':
            loadCommandStats();
            break;
        case 'errors':
            loadErrors();
            break;
    }
}

/**
 * Update stats cards
 */
function updateStatsCards(stats) {
    document.getElementById('statTotalUsers').textContent = formatNumber(stats.total_users || 0);
    document.getElementById('statActiveUsers').textContent = formatNumber(stats.active_users_24h || 0);
    document.getElementById('statMessages').textContent = formatNumber(stats.total_messages || 0);
    document.getElementById('statSuccessRate').textContent = `${stats.success_rate || 100}%`;
}

/**
 * Update charts
 */
function updateCharts(data) {
    updateUserGrowthChart(data.user_growth || []);
    updateMessageTypesChart(data.message_types || []);
    updateHourlyActivityChart(data.hourly_activity || []);
}

/**
 * User Growth Chart
 */
function updateUserGrowthChart(growthData) {
    const ctx = document.getElementById('userGrowthChart');
    if (!ctx) return;
    
    const labels = growthData.map(d => d.date);
    const newUsers = growthData.map(d => d.new_users);
    const totalUsers = growthData.map(d => d.total_users);
    
    if (charts.userGrowth) {
        charts.userGrowth.destroy();
    }
    
    charts.userGrowth = new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [{
                label: 'New Users',
                data: newUsers,
                borderColor: '#0088cc',
                backgroundColor: 'rgba(0, 136, 204, 0.1)',
                fill: true,
                tension: 0.4
            }, {
                label: 'Total Users',
                data: totalUsers,
                borderColor: '#28a745',
                backgroundColor: 'rgba(40, 167, 69, 0.1)',
                fill: false,
                tension: 0.4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'top'
                }
            },
            scales: {
                y: {
                    beginAtZero: true
                }
            }
        }
    });
}

/**
 * Message Types Chart
 */
function updateMessageTypesChart(messageTypes) {
    const ctx = document.getElementById('messageTypesChart');
    if (!ctx) return;
    
    const labels = messageTypes.map(d => d.message_type);
    const counts = messageTypes.map(d => d.count);
    
    if (charts.messageTypes) {
        charts.messageTypes.destroy();
    }
    
    charts.messageTypes = new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: labels,
            datasets: [{
                data: counts,
                backgroundColor: [
                    '#0088cc', '#28a745', '#fd7e14', '#6f42c1',
                    '#dc3545', '#17a2b8', '#ffc107', '#20c997'
                ]
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom'
                }
            }
        }
    });
}

/**
 * Hourly Activity Chart
 */
function updateHourlyActivityChart(hourlyData) {
    const ctx = document.getElementById('hourlyActivityChart');
    if (!ctx) return;
    
    // Create 24 hours array
    const hours = Array.from({length: 24}, (_, i) => i.toString().padStart(2, '0'));
    const counts = new Array(24).fill(0);
    
    hourlyData.forEach(d => {
        const hour = parseInt(d.hour);
        if (!isNaN(hour)) {
            counts[hour] = d.count;
        }
    });
    
    if (charts.hourlyActivity) {
        charts.hourlyActivity.destroy();
    }
    
    charts.hourlyActivity = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: hours,
            datasets: [{
                label: 'Messages',
                data: counts,
                backgroundColor: 'rgba(0, 136, 204, 0.7)',
                borderColor: '#0088cc',
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                }
            },
            scales: {
                y: {
                    beginAtZero: true
                }
            }
        }
    });
}

/**
 * Update recent errors table
 */
function updateRecentErrors(errors) {
    const tbody = document.getElementById('recentErrorsTable');
    if (!tbody) return;
    
    if (!errors || errors.length === 0) {
        tbody.innerHTML = '<tr><td colspan="3" class="text-center text-muted">No recent errors</td></tr>';
        return;
    }
    
    tbody.innerHTML = errors.slice(0, 5).map(error => `
        <tr>
            <td><span class="badge badge-soft-danger">${escapeHtml(error.error_type)}</span></td>
            <td class="text-truncate" style="max-width: 200px;">${escapeHtml(error.error_message)}</td>
            <td><small class="text-muted">${formatDate(error.created_at)}</small></td>
        </tr>
    `).join('');
}

/**
 * Update top commands table
 */
function updateTopCommands(commands) {
    const tbody = document.getElementById('topCommandsTable');
    if (!tbody) return;
    
    if (!commands || commands.length === 0) {
        tbody.innerHTML = '<tr><td colspan="3" class="text-center text-muted">No command data</td></tr>';
        return;
    }
    
    tbody.innerHTML = commands.slice(0, 5).map(cmd => `
        <tr>
            <td><code>/${escapeHtml(cmd.command)}</code></td>
            <td>${formatNumber(cmd.executions)}</td>
            <td>${Math.round(cmd.avg_time)}ms</td>
        </tr>
    `).join('');
}

/**
 * Handle broadcast form submission
 */
async function handleBroadcastSubmit(e) {
    e.preventDefault();
    
    const formData = {
        text: document.getElementById('broadcastText').value,
        parse_mode: document.getElementById('broadcastParseMode').value,
        target: document.getElementById('broadcastTarget').value,
        chat_ids: document.getElementById('customChatIds').value,
        disable_notification: document.getElementById('broadcastDisableNotification').checked
    };
    
    try {
        const response = await fetch(`${API_BASE}?action=create_broadcast`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(formData)
        });
        
        const result = await response.json();
        
        if (result.success) {
            showToast('Broadcast job created successfully!', 'success');
            document.getElementById('broadcastForm').reset();
            loadBroadcastHistory();
        } else {
            showToast(result.error || 'Failed to create broadcast', 'danger');
        }
    } catch (error) {
        showToast('Error creating broadcast', 'danger');
        console.error(error);
    }
}

/**
 * Handle deep link form submission
 */
async function handleDeepLinkSubmit(e) {
    e.preventDefault();
    
    const linkType = document.getElementById('deepLinkType').value;
    const formData = {
        type: linkType,
        param: document.getElementById('deepLinkParam').value,
        data: document.getElementById('deepLinkData').value,
        max_uses: document.getElementById('deepLinkMaxUses').value,
        expires_at: document.getElementById('deepLinkExpires').value
    };
    
    try {
        const response = await fetch(`${API_BASE}?action=create_deeplink`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(formData)
        });
        
        const result = await response.json();
        
        if (result.success) {
            showToast('Deep link created successfully!', 'success');
            
            // Show the generated link
            if (result.url) {
                const url = prompt('Your deep link URL:', result.url);
            }
            
            document.getElementById('deepLinkForm').reset();
            loadDeepLinks();
        } else {
            showToast(result.error || 'Failed to create deep link', 'danger');
        }
    } catch (error) {
        showToast('Error creating deep link', 'danger');
        console.error(error);
    }
}

/**
 * Handle settings form submission
 */
async function handleSettingsSubmit(e) {
    e.preventDefault();
    
    const formData = {
        bot_token: document.getElementById('settingBotToken').value,
        admin_ids: document.getElementById('settingAdminIds').value,
        log_level: document.getElementById('settingLogLevel').value
    };
    
    try {
        const response = await fetch(`${API_BASE}?action=save_settings`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(formData)
        });
        
        const result = await response.json();
        
        if (result.success) {
            showToast('Settings saved successfully!', 'success');
        } else {
            showToast(result.error || 'Failed to save settings', 'danger');
        }
    } catch (error) {
        showToast('Error saving settings', 'danger');
        console.error(error);
    }
}

/**
 * Load broadcast history
 */
async function loadBroadcastHistory() {
    try {
        const response = await fetch(`${API_BASE}?action=get_broadcasts`);
        const data = await response.json();
        
        const tbody = document.getElementById('broadcastHistoryTable');
        if (!tbody) return;
        
        if (!data.broadcasts || data.broadcasts.length === 0) {
            tbody.innerHTML = '<tr><td colspan="3" class="text-center text-muted">No broadcasts yet</td></tr>';
            return;
        }
        
        tbody.innerHTML = data.broadcasts.map(broadcast => {
            const statusClass = broadcast.status === 'completed' ? 'badge-soft-success' :
                               broadcast.status === 'running' ? 'badge-soft-info' :
                               broadcast.status === 'failed' ? 'badge-soft-danger' : 'badge-soft-warning';
            
            return `
                <tr>
                    <td><small>${formatDate(broadcast.created_at)}</small></td>
                    <td><span class="badge ${statusClass}">${broadcast.status}</span></td>
                    <td><small>${broadcast.success_count}/${broadcast.error_count}</small></td>
                </tr>
            `;
        }).join('');
    } catch (error) {
        console.error('Error loading broadcast history:', error);
    }
}

/**
 * Load deep links
 */
async function loadDeepLinks() {
    try {
        const response = await fetch(`${API_BASE}?action=get_deeplinks`);
        const data = await response.json();
        
        const tbody = document.getElementById('deepLinksTable');
        if (!tbody) return;
        
        if (!data.links || data.links.length === 0) {
            tbody.innerHTML = '<tr><td colspan="4" class="text-center text-muted">No deep links yet</td></tr>';
            return;
        }
        
        tbody.innerHTML = data.links.map(link => {
            const isActive = link.is_active && 
                            (!link.expires_at || new Date(link.expires_at) > new Date()) &&
                            (link.max_usage === null || link.usage_count < link.max_usage);
            
            return `
                <tr>
                    <td><code>${escapeHtml(link.param)}</code></td>
                    <td>${link.usage_count}${link.max_usage ? '/' + link.max_usage : ''}</td>
                    <td>
                        <span class="badge ${isActive ? 'badge-soft-success' : 'badge-soft-danger'}">
                            ${isActive ? 'Active' : 'Inactive'}
                        </span>
                    </td>
                    <td>
                        <button class="btn btn-sm btn-outline-primary" onclick="copyLink('${escapeHtml(link.param)}')">
                            <i class="bi bi-clipboard"></i>
                        </button>
                        <button class="btn btn-sm btn-outline-${isActive ? 'danger' : 'success'}" 
                                onclick="toggleLink(${link.id}, ${isActive ? 0 : 1})">
                            <i class="bi bi-${isActive ? 'x' : 'check'}"></i>
                        </button>
                    </td>
                </tr>
            `;
        }).join('');
    } catch (error) {
        console.error('Error loading deep links:', error);
    }
}

/**
 * Load users
 */
async function loadUsers() {
    try {
        const search = document.getElementById('userSearch')?.value || '';
        const response = await fetch(`${API_BASE}?action=get_users&search=${encodeURIComponent(search)}`);
        const data = await response.json();
        
        const tbody = document.getElementById('usersTable');
        if (!tbody) return;
        
        if (!data.users || data.users.length === 0) {
            tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted">No users found</td></tr>';
            return;
        }
        
        tbody.innerHTML = data.users.map(user => {
            const lastSeen = new Date(user.last_seen);
            const isActive = (new Date() - lastSeen) < (24 * 60 * 60 * 1000);
            
            return `
                <tr>
                    <td>${user.user_id}</td>
                    <td>${escapeHtml(user.first_name || '')} ${escapeHtml(user.last_name || '')}</td>
                    <td>${user.username ? '@' + escapeHtml(user.username) : '-'}</td>
                    <td>${formatNumber(user.message_count || 0)}</td>
                    <td><small>${formatDate(user.last_seen)}</small></td>
                    <td>
                        <span class="badge ${isActive ? 'badge-soft-success' : 'badge-soft-secondary'}">
                            ${isActive ? 'Active' : 'Inactive'}
                        </span>
                    </td>
                </tr>
            `;
        }).join('');
    } catch (error) {
        console.error('Error loading users:', error);
    }
}

/**
 * Load command statistics
 */
async function loadCommandStats() {
    try {
        const response = await fetch(`${API_BASE}?action=get_command_stats`);
        const data = await response.json();
        
        const tbody = document.getElementById('commandsStatsTable');
        if (!tbody) return;
        
        if (!data.commands || data.commands.length === 0) {
            tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted">No command data</td></tr>';
            return;
        }
        
        tbody.innerHTML = data.commands.map(cmd => {
            const successRate = cmd.executions > 0 
                ? Math.round((cmd.successes / cmd.executions) * 100) 
                : 0;
            
            return `
                <tr>
                    <td><code>/${escapeHtml(cmd.command)}</code></td>
                    <td>${formatNumber(cmd.executions)}</td>
                    <td>${formatNumber(cmd.successes)}</td>
                    <td>${formatNumber(cmd.failures)}</td>
                    <td>
                        <div class="progress" style="height: 20px;">
                            <div class="progress-bar bg-success" style="width: ${successRate}%"></div>
                        </div>
                        <small>${successRate}%</small>
                    </td>
                    <td>${Math.round(cmd.avg_time)}ms</td>
                </tr>
            `;
        }).join('');
    } catch (error) {
        console.error('Error loading command stats:', error);
    }
}

/**
 * Load errors
 */
async function loadErrors() {
    try {
        const response = await fetch(`${API_BASE}?action=get_errors`);
        const data = await response.json();
        
        const tbody = document.getElementById('errorsTable');
        if (!tbody) return;
        
        if (!data.errors || data.errors.length === 0) {
            tbody.innerHTML = '<tr><td colspan="4" class="text-center text-muted">No errors found</td></tr>';
            return;
        }
        
        tbody.innerHTML = data.errors.map(error => `
            <tr>
                <td><span class="badge badge-soft-danger">${escapeHtml(error.error_type)}</span></td>
                <td>${escapeHtml(error.error_message)}</td>
                <td>${error.user_id || '-'}</td>
                <td><small>${formatDate(error.created_at)}</small></td>
            </tr>
        `).join('');
    } catch (error) {
        console.error('Error loading errors:', error);
    }
}

/**
 * Copy deep link to clipboard
 */
function copyLink(param) {
    const botUsername = document.getElementById('botUsername').textContent.replace('@', '');
    const url = `https://t.me/${botUsername}?start=${param}`;
    
    navigator.clipboard.writeText(url).then(() => {
        showToast('Link copied to clipboard!', 'success');
    }).catch(err => {
        showToast('Failed to copy link', 'danger');
    });
}

/**
 * Toggle deep link status
 */
async function toggleLink(linkId, activate) {
    try {
        const response = await fetch(`${API_BASE}?action=toggle_deeplink`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ link_id: linkId, active: activate })
        });
        
        const result = await response.json();
        
        if (result.success) {
            showToast(`Link ${activate ? 'activated' : 'deactivated'}!`, 'success');
            loadDeepLinks();
        } else {
            showToast(result.error || 'Failed to toggle link', 'danger');
        }
    } catch (error) {
        showToast('Error toggling link', 'danger');
    }
}

/**
 * Export data
 */
async function exportData() {
    try {
        const response = await fetch(`${API_BASE}?action=export_data`);
        const data = await response.json();
        
        const blob = new Blob([JSON.stringify(data, null, 2)], {type: 'application/json'});
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `bot_statistics_${new Date().toISOString().split('T')[0]}.json`;
        a.click();
        URL.revokeObjectURL(url);
        
        showToast('Data exported successfully!', 'success');
    } catch (error) {
        showToast('Error exporting data', 'danger');
    }
}

/**
 * Cleanup old data
 */
async function cleanupData() {
    const days = document.getElementById('cleanupDays').value;
    
    if (!confirm(`Are you sure you want to delete data older than ${days} days?`)) {
        return;
    }
    
    try {
        const response = await fetch(`${API_BASE}?action=cleanup_data`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ days: parseInt(days) })
        });
        
        const result = await response.json();
        
        if (result.success) {
            showToast(`${result.deleted} records deleted!`, 'success');
            loadDashboardData();
        } else {
            showToast(result.error || 'Failed to cleanup data', 'danger');
        }
    } catch (error) {
        showToast('Error cleaning up data', 'danger');
    }
}

/**
 * Clear cache
 */
async function clearCache() {
    if (!confirm('Are you sure you want to clear the cache?')) {
        return;
    }
    
    try {
        const response = await fetch(`${API_BASE}?action=clear_cache`, {
            method: 'POST'
        });
        
        const result = await response.json();
        
        if (result.success) {
            showToast('Cache cleared successfully!', 'success');
        } else {
            showToast(result.error || 'Failed to clear cache', 'danger');
        }
    } catch (error) {
        showToast('Error clearing cache', 'danger');
    }
}

/**
 * Clear old errors
 */
async function clearErrors() {
    if (!confirm('Are you sure you want to clear old errors?')) {
        return;
    }
    
    try {
        const response = await fetch(`${API_BASE}?action=clear_errors`, {
            method: 'POST'
        });
        
        const result = await response.json();
        
        if (result.success) {
            showToast('Errors cleared successfully!', 'success');
            loadErrors();
        } else {
            showToast(result.error || 'Failed to clear errors', 'danger');
        }
    } catch (error) {
        showToast('Error clearing errors', 'danger');
    }
}

/**
 * Show toast notification
 */
function showToast(message, type = 'info') {
    const toastEl = document.getElementById('liveToast');
    const toastBody = toastEl.querySelector('.toast-body');
    
    toastBody.textContent = message;
    toastEl.className = `toast align-items-center text-white bg-${type} border-0`;
    
    const toast = new bootstrap.Toast(toastEl);
    toast.show();
}

/**
 * Utility functions
 */
function formatNumber(num) {
    if (num >= 1000000) {
        return (num / 1000000).toFixed(1) + 'M';
    } else if (num >= 1000) {
        return (num / 1000).toFixed(1) + 'K';
    }
    return num.toString();
}

function formatDate(dateString) {
    if (!dateString) return '-';
    const date = new Date(dateString);
    return date.toLocaleDateString() + ' ' + date.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
}

function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

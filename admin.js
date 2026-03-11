/**
 * Admin Dashboard - Luxury Hotel
 * Gestione prenotazioni, statistiche e insight
 */

// ========== CONFIGURAZIONE ==========
const DEBUG = false; // Impostare a true solo in sviluppo
const API_URL = './api/admin.php';
const AUTH_API = './api/auth.php';
const BOOKINGS_API = './api/bookings.php';

// ========== STATO APPLICAZIONE ==========
let allBookings = [];
let filteredBookings = [];
let currentPage = 1;
let itemsPerPage = 10;
let currentSort = { field: 'created_at', direction: 'desc' };
let charts = {};
let currentUser = null;
let csrfToken = null;

// ========== INIZIALIZZAZIONE ==========
document.addEventListener('DOMContentLoaded', async function() {
    // Prima verifica autenticazione
    const isAuthenticated = await checkAuth();
    if (!isAuthenticated) return;

    initSidebar();
    initNavigation();
    initEventListeners();
    loadDashboardData();
    loadPendingUsersCount();
});

// ========== AUTENTICAZIONE ==========
async function checkAuth() {
    try {
        const response = await fetch(`${AUTH_API}?action=check`, {
            credentials: 'include'
        });
        const data = await response.json();

        if (!data.authenticated) {
            window.location.href = 'login.html';
            return false;
        }

        currentUser = data.user;
        csrfToken = data.csrf_token;
        document.getElementById('adminUsername').textContent = currentUser.username;
        return true;

    } catch (error) {
        if (DEBUG) console.error('Auth check failed:', error);
        window.location.href = 'login.html';
        return false;
    }
}

async function logout() {
    try {
        await fetch(`${AUTH_API}?action=logout`, {
            credentials: 'include'
        });
    } catch (error) {
        if (DEBUG) console.error('Logout error:', error);
    }
    window.location.href = 'login.html';
}

// ========== SIDEBAR ==========
function initSidebar() {
    const sidebar = document.getElementById('sidebar');
    const sidebarToggle = document.getElementById('sidebarToggle');
    const menuToggle = document.getElementById('menuToggle');

    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', () => {
            sidebar.classList.toggle('collapsed');
        });
    }

    if (menuToggle) {
        menuToggle.addEventListener('click', () => {
            sidebar.classList.toggle('open');
        });
    }

    // Chiudi sidebar su mobile quando clicco fuori
    document.addEventListener('click', (e) => {
        if (window.innerWidth <= 992 &&
            !sidebar.contains(e.target) &&
            !menuToggle.contains(e.target)) {
            sidebar.classList.remove('open');
        }
    });
}

// ========== NAVIGAZIONE ==========
function initNavigation() {
    const navItems = document.querySelectorAll('.nav-item[data-section]');
    const viewAllLinks = document.querySelectorAll('.view-all[data-section]');

    navItems.forEach(item => {
        item.addEventListener('click', (e) => {
            e.preventDefault();
            const section = item.dataset.section;
            switchSection(section);
        });
    });

    viewAllLinks.forEach(link => {
        link.addEventListener('click', (e) => {
            e.preventDefault();
            const section = link.dataset.section;
            switchSection(section);
        });
    });
}

function switchSection(sectionId) {
    // Aggiorna nav
    document.querySelectorAll('.nav-item').forEach(item => {
        item.classList.toggle('active', item.dataset.section === sectionId);
    });

    // Aggiorna contenuto
    document.querySelectorAll('.content-section').forEach(section => {
        section.classList.toggle('active', section.id === sectionId);
    });

    // Carica dati specifici della sezione
    switch (sectionId) {
        case 'dashboard':
            loadDashboardData();
            break;
        case 'bookings':
            loadAllBookings();
            break;
        case 'revenue':
            loadRevenueData();
            break;
        case 'rooms':
            loadRoomsData();
            break;
        case 'settings':
            loadSettings();
            break;
        case 'users':
            loadPendingUsers();
            break;
    }

    // Chiudi sidebar su mobile
    document.getElementById('sidebar').classList.remove('open');
}

// ========== EVENT LISTENERS ==========
function initEventListeners() {
    // Logout
    document.getElementById('logoutBtn')?.addEventListener('click', logout);

    // Refresh data
    document.getElementById('refreshData')?.addEventListener('click', () => {
        showNotification('Aggiornamento dati...', 'info');
        loadDashboardData();
    });

    // Export data
    document.getElementById('exportData')?.addEventListener('click', exportAllData);
    document.getElementById('exportBookings')?.addEventListener('click', exportBookingsCSV);

    // Filters
    document.getElementById('applyFilters')?.addEventListener('click', applyFilters);
    document.getElementById('resetFilters')?.addEventListener('click', resetFilters);

    // Global search
    document.getElementById('globalSearch')?.addEventListener('input', debounce(handleGlobalSearch, 300));

    // Select all checkbox
    document.getElementById('selectAll')?.addEventListener('change', handleSelectAll);

    // Chart period filters
    document.querySelectorAll('.chart-filter').forEach(btn => {
        btn.addEventListener('click', () => {
            document.querySelectorAll('.chart-filter').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            updateRevenueChart(btn.dataset.period);
        });
    });

    // Modal
    document.getElementById('closeModal')?.addEventListener('click', closeModal);
    document.getElementById('modalCancel')?.addEventListener('click', closeModal);
    document.getElementById('modalConfirmPayment')?.addEventListener('click', confirmPayment);
    document.getElementById('modalCancelBooking')?.addEventListener('click', cancelBooking);

    // Table sorting
    document.querySelectorAll('.data-table th[data-sort]').forEach(th => {
        th.addEventListener('click', () => handleSort(th.dataset.sort));
    });

    // Settings
    document.getElementById('savePrices')?.addEventListener('click', savePrices);
    document.getElementById('saveNotifications')?.addEventListener('click', saveNotifications);
    document.getElementById('backupDb')?.addEventListener('click', exportAllData);

    // SICUREZZA: Event delegation per azioni tabella (previene XSS da onclick inline)
    document.getElementById('bookingsTableBody')?.addEventListener('click', handleTableAction);

    // Event delegation per utenti pending
    document.getElementById('pendingUsersTable')?.addEventListener('click', handleUserAction);
}

// ========== CARICAMENTO DATI ==========
async function loadDashboardData() {
    try {
        const response = await fetch(`${API_URL}?action=dashboard`);
        const data = await response.json();

        if (data.success) {
            updateDashboardStats(data.stats);
            updateRecentBookings(data.recent_bookings || []);
            initCharts(data);
        }
    } catch (error) {
        if (DEBUG) console.error('Errore caricamento dashboard:', error);
        // Fallback: carica da bookings API
        loadFromBookingsAPI();
    }
}

async function loadFromBookingsAPI() {
    try {
        const response = await fetch(BOOKINGS_API);
        const data = await response.json();

        if (data.success) {
            allBookings = data.bookings || [];
            calculateAndDisplayStats(allBookings);
            updateRecentBookings(allBookings.slice(0, 5));
            initChartsFromBookings(allBookings);
        }
    } catch (error) {
        if (DEBUG) console.error('Errore caricamento bookings:', error);
        showNotification('Errore nel caricamento dei dati', 'error');
    }
}

async function loadAllBookings() {
    try {
        const response = await fetch(BOOKINGS_API);
        const data = await response.json();

        if (data.success) {
            allBookings = data.bookings || [];
            filteredBookings = [...allBookings];
            document.getElementById('bookingsCount').textContent = allBookings.length;
            renderBookingsTable();
        }
    } catch (error) {
        if (DEBUG) console.error('Errore caricamento prenotazioni:', error);
        showNotification('Errore nel caricamento prenotazioni', 'error');
    }
}

async function loadRevenueData() {
    calculateAndDisplayStats(allBookings);
    initRevenueCharts();
    renderTransactionsTable();
}

async function loadRoomsData() {
    calculateRoomStats();
}

function loadSettings() {
    document.getElementById('dbBookingsCount').textContent = allBookings.length;
    document.getElementById('dbPaymentsCount').textContent =
        allBookings.filter(b => b.payment_status === 'completed').length;
}

// ========== CALCOLO STATISTICHE ==========
function calculateAndDisplayStats(bookings) {
    const stats = {
        totalBookings: bookings.length,
        totalRevenue: 0,
        pendingPayments: 0,
        monthlyRevenue: 0,
        avgBookingValue: 0,
        avgNights: 0
    };

    const now = new Date();
    const currentMonth = now.getMonth();
    const currentYear = now.getFullYear();

    let totalNights = 0;
    let paidBookings = 0;

    bookings.forEach(booking => {
        const price = parseFloat(booking.total_price) || 0;
        const nights = parseInt(booking.nights) || 0;

        stats.totalRevenue += price;
        totalNights += nights;

        if (booking.payment_status === 'pending' || booking.payment_status === 'pending_transfer') {
            stats.pendingPayments++;
        }

        if (booking.payment_status === 'completed' || booking.status === 'paid') {
            paidBookings++;
        }

        // Check if booking is this month
        const bookingDate = new Date(booking.created_at);
        if (bookingDate.getMonth() === currentMonth && bookingDate.getFullYear() === currentYear) {
            stats.monthlyRevenue += price;
        }
    });

    stats.avgBookingValue = bookings.length > 0 ? stats.totalRevenue / bookings.length : 0;
    stats.avgNights = bookings.length > 0 ? totalNights / bookings.length : 0;
    stats.occupancyRate = calculateOccupancyRate(bookings);

    updateDashboardStats(stats);
}

function calculateOccupancyRate(bookings) {
    // Calcolo semplificato: prenotazioni confermate / giorni del mese * 3 camere
    const now = new Date();
    const daysInMonth = new Date(now.getFullYear(), now.getMonth() + 1, 0).getDate();
    const totalRoomDays = daysInMonth * 3; // 3 tipi di camera

    let bookedDays = 0;
    bookings.forEach(booking => {
        if (booking.status === 'confirmed' || booking.status === 'paid') {
            bookedDays += parseInt(booking.nights) || 0;
        }
    });

    return Math.min(100, Math.round((bookedDays / totalRoomDays) * 100));
}

function updateDashboardStats(stats) {
    animateNumber('totalBookings', stats.totalBookings || stats.total_bookings || 0);
    animateNumber('totalRevenue', stats.totalRevenue || stats.total_revenue || 0, '€');
    document.getElementById('occupancyRate').textContent = (stats.occupancyRate || stats.occupancy_rate || 0) + '%';
    animateNumber('pendingPayments', stats.pendingPayments || stats.pending_payments || 0);
    animateNumber('monthlyRevenue', stats.monthlyRevenue || stats.monthly_revenue || 0, '€');
    animateNumber('avgBookingValue', Math.round(stats.avgBookingValue || stats.avg_booking_value || 0), '€');
    document.getElementById('avgNights').textContent = (stats.avgNights || stats.avg_nights || 0).toFixed(1);
}

function animateNumber(elementId, targetValue, prefix = '') {
    const element = document.getElementById(elementId);
    if (!element) return;

    const startValue = parseInt(element.textContent.replace(/[^0-9]/g, '')) || 0;
    const duration = 1000;
    const steps = 30;
    const increment = (targetValue - startValue) / steps;
    let current = startValue;
    let step = 0;

    const timer = setInterval(() => {
        step++;
        current += increment;
        if (step >= steps) {
            current = targetValue;
            clearInterval(timer);
        }
        element.textContent = prefix + Math.round(current).toLocaleString('it-IT');
    }, duration / steps);
}

// ========== TABELLA PRENOTAZIONI ==========
function updateRecentBookings(bookings) {
    const tbody = document.getElementById('recentBookingsTable');
    if (!tbody) return;

    if (bookings.length === 0) {
        tbody.innerHTML = `<tr><td colspan="8" class="loading-row">Nessuna prenotazione trovata</td></tr>`;
        return;
    }

    tbody.innerHTML = bookings.slice(0, 5).map(booking => `
        <tr>
            <td><strong>${escapeHtml(booking.booking_id || '-')}</strong></td>
            <td>${escapeHtml(booking.name || '-')}</td>
            <td><span class="room-badge">${escapeHtml(booking.room_type || '-')}</span></td>
            <td>${formatDate(booking.check_in)}</td>
            <td>${formatDate(booking.check_out)}</td>
            <td><strong>€${parseFloat(booking.total_price || 0).toFixed(2)}</strong></td>
            <td>${getStatusBadge(booking.status)}</td>
            <td>${getPaymentBadge(booking.payment_status)}</td>
        </tr>
    `).join('');
}

function renderBookingsTable() {
    const tbody = document.getElementById('bookingsTableBody');
    if (!tbody) return;

    // Apply sorting
    const sorted = [...filteredBookings].sort((a, b) => {
        let aVal = a[currentSort.field] || '';
        let bVal = b[currentSort.field] || '';

        if (currentSort.field === 'total_price') {
            aVal = parseFloat(aVal) || 0;
            bVal = parseFloat(bVal) || 0;
        }

        if (currentSort.direction === 'asc') {
            return aVal > bVal ? 1 : -1;
        } else {
            return aVal < bVal ? 1 : -1;
        }
    });

    // Pagination
    const start = (currentPage - 1) * itemsPerPage;
    const paginated = sorted.slice(start, start + itemsPerPage);

    if (paginated.length === 0) {
        tbody.innerHTML = `<tr><td colspan="12" class="loading-row">Nessuna prenotazione trovata</td></tr>`;
        return;
    }

    tbody.innerHTML = paginated.map(booking => {
        // SICUREZZA: Usa data attributes con escape per prevenire XSS
        const safeBookingId = escapeHtml(booking.booking_id || '');
        const safeId = parseInt(booking.id) || 0;

        return `
        <tr data-id="${safeId}" data-booking-id="${safeBookingId}">
            <td><input type="checkbox" class="booking-checkbox" value="${safeId}"></td>
            <td><strong>${safeBookingId}</strong></td>
            <td>${escapeHtml(booking.name || '-')}</td>
            <td>${escapeHtml(booking.email || '-')}</td>
            <td><span class="room-badge">${escapeHtml(booking.room_type || '-')}</span></td>
            <td>${formatDate(booking.check_in)}</td>
            <td>${formatDate(booking.check_out)}</td>
            <td>${parseInt(booking.nights) || '-'}</td>
            <td><strong>€${parseFloat(booking.total_price || 0).toFixed(2)}</strong></td>
            <td>${getStatusBadge(booking.status)}</td>
            <td>${getPaymentBadge(booking.payment_status)}</td>
            <td>
                <button class="action-btn view" data-action="view" data-booking="${safeBookingId}" title="Visualizza">
                    <i class="fas fa-eye"></i>
                </button>
                <button class="action-btn edit" data-action="edit" data-booking="${safeBookingId}" title="Modifica">
                    <i class="fas fa-edit"></i>
                </button>
                <button class="action-btn delete" data-action="delete" data-booking="${safeBookingId}" title="Elimina">
                    <i class="fas fa-trash"></i>
                </button>
            </td>
        </tr>
    `}).join('');

    renderPagination();
}

function renderPagination() {
    const pagination = document.getElementById('bookingsPagination');
    if (!pagination) return;

    const totalPages = Math.ceil(filteredBookings.length / itemsPerPage);

    let html = `
        <button ${currentPage === 1 ? 'disabled' : ''} onclick="changePage(${currentPage - 1})">
            <i class="fas fa-chevron-left"></i>
        </button>
    `;

    for (let i = 1; i <= totalPages; i++) {
        if (i === 1 || i === totalPages || (i >= currentPage - 2 && i <= currentPage + 2)) {
            html += `<button class="${i === currentPage ? 'active' : ''}" onclick="changePage(${i})">${i}</button>`;
        } else if (i === currentPage - 3 || i === currentPage + 3) {
            html += `<button disabled>...</button>`;
        }
    }

    html += `
        <button ${currentPage === totalPages ? 'disabled' : ''} onclick="changePage(${currentPage + 1})">
            <i class="fas fa-chevron-right"></i>
        </button>
    `;

    pagination.innerHTML = html;
}

function changePage(page) {
    currentPage = page;
    renderBookingsTable();
}

// ========== FILTRI ==========
function applyFilters() {
    const status = document.getElementById('filterStatus').value;
    const payment = document.getElementById('filterPayment').value;
    const room = document.getElementById('filterRoom').value;
    const dateFrom = document.getElementById('filterDateFrom').value;
    const dateTo = document.getElementById('filterDateTo').value;

    filteredBookings = allBookings.filter(booking => {
        if (status && booking.status !== status) return false;
        if (payment && booking.payment_status !== payment) return false;
        if (room && booking.room_type !== room) return false;
        if (dateFrom && booking.check_in < dateFrom) return false;
        if (dateTo && booking.check_out > dateTo) return false;
        return true;
    });

    currentPage = 1;
    document.getElementById('bookingsCount').textContent = filteredBookings.length;
    renderBookingsTable();
    showNotification(`Trovate ${filteredBookings.length} prenotazioni`, 'info');
}

function resetFilters() {
    document.getElementById('filterStatus').value = '';
    document.getElementById('filterPayment').value = '';
    document.getElementById('filterRoom').value = '';
    document.getElementById('filterDateFrom').value = '';
    document.getElementById('filterDateTo').value = '';

    filteredBookings = [...allBookings];
    currentPage = 1;
    document.getElementById('bookingsCount').textContent = allBookings.length;
    renderBookingsTable();
}

function handleGlobalSearch(e) {
    const query = e.target.value.toLowerCase();

    if (!query) {
        filteredBookings = [...allBookings];
    } else {
        filteredBookings = allBookings.filter(booking => {
            return (
                (booking.booking_id || '').toLowerCase().includes(query) ||
                (booking.name || '').toLowerCase().includes(query) ||
                (booking.email || '').toLowerCase().includes(query) ||
                (booking.room_type || '').toLowerCase().includes(query)
            );
        });
    }

    currentPage = 1;
    if (document.getElementById('bookings').classList.contains('active')) {
        renderBookingsTable();
    }
}

function handleSort(field) {
    if (currentSort.field === field) {
        currentSort.direction = currentSort.direction === 'asc' ? 'desc' : 'asc';
    } else {
        currentSort.field = field;
        currentSort.direction = 'desc';
    }
    renderBookingsTable();
}

function handleSelectAll(e) {
    const checkboxes = document.querySelectorAll('.booking-checkbox');
    checkboxes.forEach(cb => cb.checked = e.target.checked);
}

/**
 * SICUREZZA: Gestisce azioni tabella prenotazioni tramite event delegation
 * Previene XSS evitando onclick inline con dati non trusted
 */
function handleTableAction(e) {
    const btn = e.target.closest('[data-action]');
    if (!btn) return;

    const action = btn.dataset.action;
    const bookingId = btn.dataset.booking;

    // Valida formato booking_id prima di usarlo
    if (bookingId && !/^BK\d{14}_[a-f0-9]{8}$/i.test(bookingId)) {
        if (DEBUG) console.warn('ID prenotazione non valido');
        return;
    }

    switch (action) {
        case 'view':
            viewBooking(bookingId);
            break;
        case 'edit':
            editBooking(bookingId);
            break;
        case 'delete':
            deleteBooking(bookingId);
            break;
    }
}

/**
 * SICUREZZA: Gestisce azioni utenti pending tramite event delegation
 */
function handleUserAction(e) {
    const btn = e.target.closest('[data-action]');
    if (!btn) return;

    const action = btn.dataset.action;
    const userId = parseInt(btn.dataset.userid);

    if (!userId || userId <= 0) {
        console.warn('ID utente non valido');
        return;
    }

    switch (action) {
        case 'approve':
            approveUser(userId);
            break;
        case 'reject':
            rejectUser(userId);
            break;
    }
}

// ========== MODAL ==========
let currentBooking = null;

function viewBooking(bookingId) {
    currentBooking = allBookings.find(b => b.booking_id === bookingId);
    if (!currentBooking) return;

    const modalBody = document.getElementById('bookingModalBody');
    modalBody.innerHTML = `
        <div class="booking-detail">
            <div class="detail-item">
                <div class="label">ID Prenotazione</div>
                <div class="value">${escapeHtml(currentBooking.booking_id)}</div>
            </div>
            <div class="detail-item">
                <div class="label">Stato</div>
                <div class="value">${getStatusBadge(currentBooking.status)}</div>
            </div>
            <div class="detail-item">
                <div class="label">Ospite</div>
                <div class="value">${escapeHtml(currentBooking.name)}</div>
            </div>
            <div class="detail-item">
                <div class="label">Email</div>
                <div class="value">${escapeHtml(currentBooking.email)}</div>
            </div>
            <div class="detail-item">
                <div class="label">Camera</div>
                <div class="value">${escapeHtml(currentBooking.room_type)}</div>
            </div>
            <div class="detail-item">
                <div class="label">Ospiti</div>
                <div class="value">${currentBooking.guests}</div>
            </div>
            <div class="detail-item">
                <div class="label">Check-in</div>
                <div class="value">${formatDate(currentBooking.check_in)}</div>
            </div>
            <div class="detail-item">
                <div class="label">Check-out</div>
                <div class="value">${formatDate(currentBooking.check_out)}</div>
            </div>
            <div class="detail-item">
                <div class="label">Notti</div>
                <div class="value">${currentBooking.nights}</div>
            </div>
            <div class="detail-item">
                <div class="label">Totale</div>
                <div class="value"><strong>€${parseFloat(currentBooking.total_price).toFixed(2)}</strong></div>
            </div>
            <div class="detail-item">
                <div class="label">Pagamento</div>
                <div class="value">${getPaymentBadge(currentBooking.payment_status)}</div>
            </div>
            <div class="detail-item">
                <div class="label">Metodo</div>
                <div class="value">${currentBooking.payment_method || '-'}</div>
            </div>
            <div class="detail-item full">
                <div class="label">Creata il</div>
                <div class="value">${formatDateTime(currentBooking.created_at)}</div>
            </div>
        </div>
    `;

    // Gestisci visibilità bottoni
    const confirmBtn = document.getElementById('modalConfirmPayment');
    const cancelBtn = document.getElementById('modalCancelBooking');

    confirmBtn.style.display =
        (currentBooking.payment_status === 'pending' || currentBooking.payment_status === 'pending_transfer')
        ? 'inline-flex' : 'none';

    cancelBtn.style.display =
        currentBooking.status !== 'cancelled' ? 'inline-flex' : 'none';

    document.getElementById('bookingModal').classList.add('show');
}

function closeModal() {
    document.getElementById('bookingModal').classList.remove('show');
    currentBooking = null;
}

async function confirmPayment() {
    if (!currentBooking) return;

    try {
        const response = await fetch(`${API_URL}?action=confirm-payment`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': csrfToken
            },
            credentials: 'include',
            body: JSON.stringify({ booking_id: currentBooking.booking_id })
        });

        const data = await response.json();

        if (data.success) {
            showNotification('Pagamento confermato!', 'success');
            closeModal();
            loadAllBookings();
            loadDashboardData();
        } else {
            showNotification(data.message || 'Errore', 'error');
        }
    } catch (error) {
        // Aggiorna localmente per demo
        const index = allBookings.findIndex(b => b.booking_id === currentBooking.booking_id);
        if (index !== -1) {
            allBookings[index].payment_status = 'completed';
            allBookings[index].status = 'paid';
        }
        showNotification('Pagamento confermato!', 'success');
        closeModal();
        filteredBookings = [...allBookings];
        renderBookingsTable();
        calculateAndDisplayStats(allBookings);
    }
}

async function cancelBooking() {
    if (!currentBooking) return;

    if (!confirm('Sei sicuro di voler cancellare questa prenotazione?')) return;

    try {
        const response = await fetch(`${API_URL}?action=cancel-booking`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': csrfToken
            },
            credentials: 'include',
            body: JSON.stringify({ booking_id: currentBooking.booking_id })
        });

        const data = await response.json();

        if (data.success) {
            showNotification('Prenotazione cancellata', 'warning');
            closeModal();
            loadAllBookings();
        } else {
            showNotification(data.message || 'Errore', 'error');
        }
    } catch (error) {
        // Aggiorna localmente per demo
        const index = allBookings.findIndex(b => b.booking_id === currentBooking.booking_id);
        if (index !== -1) {
            allBookings[index].status = 'cancelled';
        }
        showNotification('Prenotazione cancellata', 'warning');
        closeModal();
        filteredBookings = [...allBookings];
        renderBookingsTable();
    }
}

function editBooking(bookingId) {
    viewBooking(bookingId);
}

function deleteBooking(bookingId) {
    if (confirm('Sei sicuro di voler eliminare questa prenotazione?')) {
        // Aggiorna localmente
        allBookings = allBookings.filter(b => b.booking_id !== bookingId);
        filteredBookings = filteredBookings.filter(b => b.booking_id !== bookingId);
        renderBookingsTable();
        showNotification('Prenotazione eliminata', 'warning');
    }
}

// ========== GRAFICI ==========
function initCharts(data) {
    initChartsFromBookings(data.bookings || allBookings);
}

function initChartsFromBookings(bookings) {
    createRevenueChart(bookings);
    createRoomsChart(bookings);
}

function createRevenueChart(bookings) {
    const ctx = document.getElementById('revenueChart')?.getContext('2d');
    if (!ctx) return;

    if (charts.revenue) charts.revenue.destroy();

    // Ultimi 7 giorni
    const days = [];
    const revenues = [];
    const today = new Date();

    for (let i = 6; i >= 0; i--) {
        const date = new Date(today);
        date.setDate(date.getDate() - i);
        const dateStr = date.toISOString().split('T')[0];

        days.push(date.toLocaleDateString('it-IT', { weekday: 'short', day: 'numeric' }));

        const dayRevenue = bookings
            .filter(b => b.created_at?.startsWith(dateStr))
            .reduce((sum, b) => sum + (parseFloat(b.total_price) || 0), 0);
        revenues.push(dayRevenue);
    }

    charts.revenue = new Chart(ctx, {
        type: 'line',
        data: {
            labels: days,
            datasets: [{
                label: 'Guadagni',
                data: revenues,
                borderColor: '#8B6F47',
                backgroundColor: 'rgba(139, 111, 71, 0.1)',
                fill: true,
                tension: 0.4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: value => '€' + value
                    }
                }
            }
        }
    });
}

function createRoomsChart(bookings) {
    const ctx = document.getElementById('roomsChart')?.getContext('2d');
    if (!ctx) return;

    if (charts.rooms) charts.rooms.destroy();

    const roomCounts = {
        'Standard': 0,
        'Deluxe': 0,
        'Suite': 0
    };

    bookings.forEach(b => {
        if (roomCounts.hasOwnProperty(b.room_type)) {
            roomCounts[b.room_type]++;
        }
    });

    charts.rooms = new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: Object.keys(roomCounts),
            datasets: [{
                data: Object.values(roomCounts),
                backgroundColor: ['#8B6F47', '#C9B99A', '#4A3728']
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

function initRevenueCharts() {
    createRevenueByRoomChart();
    createMonthlyRevenueChart();
}

function createRevenueByRoomChart() {
    const ctx = document.getElementById('revenueByRoomChart')?.getContext('2d');
    if (!ctx) return;

    if (charts.revenueByRoom) charts.revenueByRoom.destroy();

    const roomRevenue = {
        'Standard': 0,
        'Deluxe': 0,
        'Suite': 0
    };

    allBookings.forEach(b => {
        if (roomRevenue.hasOwnProperty(b.room_type)) {
            roomRevenue[b.room_type] += parseFloat(b.total_price) || 0;
        }
    });

    charts.revenueByRoom = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: Object.keys(roomRevenue),
            datasets: [{
                label: 'Guadagni',
                data: Object.values(roomRevenue),
                backgroundColor: ['#8B6F47', '#C9B99A', '#4A3728']
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: { callback: value => '€' + value }
                }
            }
        }
    });
}

function createMonthlyRevenueChart() {
    const ctx = document.getElementById('monthlyRevenueChart')?.getContext('2d');
    if (!ctx) return;

    if (charts.monthlyRevenue) charts.monthlyRevenue.destroy();

    const months = [];
    const revenues = [];
    const today = new Date();

    for (let i = 5; i >= 0; i--) {
        const date = new Date(today.getFullYear(), today.getMonth() - i, 1);
        const monthStr = date.toLocaleDateString('it-IT', { month: 'short', year: '2-digit' });
        months.push(monthStr);

        const monthRevenue = allBookings
            .filter(b => {
                const bDate = new Date(b.created_at);
                return bDate.getMonth() === date.getMonth() && bDate.getFullYear() === date.getFullYear();
            })
            .reduce((sum, b) => sum + (parseFloat(b.total_price) || 0), 0);
        revenues.push(monthRevenue);
    }

    charts.monthlyRevenue = new Chart(ctx, {
        type: 'line',
        data: {
            labels: months,
            datasets: [{
                label: 'Guadagni Mensili',
                data: revenues,
                borderColor: '#22c55e',
                backgroundColor: 'rgba(34, 197, 94, 0.1)',
                fill: true,
                tension: 0.4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: { callback: value => '€' + value }
                }
            }
        }
    });
}

function updateRevenueChart(period) {
    // Ricrea il grafico con il periodo selezionato
    createRevenueChart(allBookings);
}

// ========== STATISTICHE CAMERE ==========
function calculateRoomStats() {
    ['Standard', 'Deluxe', 'Suite'].forEach(roomType => {
        const roomBookings = allBookings.filter(b => b.room_type === roomType);
        const roomRevenue = roomBookings.reduce((sum, b) => sum + (parseFloat(b.total_price) || 0), 0);

        const prefix = roomType.toLowerCase();
        document.getElementById(`${prefix}Bookings`).textContent = roomBookings.length;
        document.getElementById(`${prefix}Revenue`).textContent = '€' + roomRevenue.toLocaleString('it-IT');
        document.getElementById(`${prefix}Occupancy`).textContent = Math.round(roomBookings.length * 10) + '%';
    });
}

// ========== TRANSAZIONI ==========
function renderTransactionsTable() {
    const tbody = document.getElementById('transactionsTable');
    if (!tbody) return;

    const paidBookings = allBookings.filter(b =>
        b.payment_status === 'completed' || b.status === 'paid'
    );

    if (paidBookings.length === 0) {
        tbody.innerHTML = `<tr><td colspan="7" class="loading-row">Nessuna transazione trovata</td></tr>`;
        return;
    }

    tbody.innerHTML = paidBookings.map(booking => `
        <tr>
            <td>${formatDate(booking.created_at)}</td>
            <td><strong>${escapeHtml(booking.booking_id)}</strong></td>
            <td>${escapeHtml(booking.name)}</td>
            <td>${escapeHtml(booking.room_type)}</td>
            <td>${escapeHtml(booking.payment_method || 'N/A')}</td>
            <td><strong>€${parseFloat(booking.total_price).toFixed(2)}</strong></td>
            <td>${getPaymentBadge(booking.payment_status)}</td>
        </tr>
    `).join('');
}

// ========== ESPORTAZIONE ==========
function exportBookingsCSV() {
    const headers = ['ID', 'Ospite', 'Email', 'Camera', 'Check-in', 'Check-out', 'Notti', 'Totale', 'Stato', 'Pagamento'];
    const rows = filteredBookings.map(b => [
        b.booking_id,
        b.name,
        b.email,
        b.room_type,
        b.check_in,
        b.check_out,
        b.nights,
        b.total_price,
        b.status,
        b.payment_status
    ]);

    const csv = [headers.join(','), ...rows.map(r => r.join(','))].join('\n');
    downloadFile(csv, 'prenotazioni.csv', 'text/csv');
    showNotification('Esportazione completata!', 'success');
}

function exportAllData() {
    const data = JSON.stringify(allBookings, null, 2);
    downloadFile(data, 'backup_prenotazioni.json', 'application/json');
    showNotification('Backup completato!', 'success');
}

function downloadFile(content, filename, type) {
    const blob = new Blob([content], { type });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = filename;
    a.click();
    URL.revokeObjectURL(url);
}

// ========== SETTINGS ==========
function savePrices() {
    showNotification('Prezzi salvati! (demo)', 'success');
}

function saveNotifications() {
    showNotification('Impostazioni notifiche salvate! (demo)', 'success');
}

// ========== UTILITY ==========
function formatDate(dateStr) {
    if (!dateStr) return '-';
    try {
        return new Date(dateStr).toLocaleDateString('it-IT', {
            day: '2-digit',
            month: 'short',
            year: 'numeric'
        });
    } catch {
        return dateStr;
    }
}

function formatDateTime(dateStr) {
    if (!dateStr) return '-';
    try {
        return new Date(dateStr).toLocaleString('it-IT');
    } catch {
        return dateStr;
    }
}

function getStatusBadge(status) {
    const labels = {
        'confirmed': 'Confermata',
        'paid': 'Pagata',
        'pending': 'In attesa',
        'cancelled': 'Cancellata'
    };
    return `<span class="status-badge ${status}">${labels[status] || status}</span>`;
}

function getPaymentBadge(status) {
    const labels = {
        'completed': 'Completato',
        'pending': 'In attesa',
        'pending_transfer': 'Bonifico',
        'failed': 'Fallito',
        'refunded': 'Rimborsato'
    };
    return `<span class="status-badge ${status}">${labels[status] || status || '-'}</span>`;
}

function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function debounce(func, wait) {
    let timeout;
    return function(...args) {
        clearTimeout(timeout);
        timeout = setTimeout(() => func.apply(this, args), wait);
    };
}

function showNotification(message, type = 'info') {
    const notification = document.getElementById('notification');
    if (!notification) return;

    notification.textContent = message;
    notification.className = `notification show ${type}`;

    setTimeout(() => notification.classList.remove('show'), 4000);
}

// ========== GESTIONE UTENTI ADMIN ==========
async function loadPendingUsersCount() {
    try {
        const response = await fetch(`${AUTH_API}?action=pending-users`, {
            credentials: 'include'
        });
        const data = await response.json();

        if (data.success) {
            const badge = document.getElementById('pendingBadge');
            if (data.count > 0) {
                badge.textContent = data.count;
                badge.style.display = 'inline-block';
            } else {
                badge.style.display = 'none';
            }
        }
    } catch (error) {
        console.error('Error loading pending users count:', error);
    }
}

async function loadPendingUsers() {
    const tbody = document.getElementById('pendingUsersTable');
    if (!tbody) return;

    tbody.innerHTML = `<tr><td colspan="4" class="loading-row"><i class="fas fa-spinner fa-spin"></i> Caricamento...</td></tr>`;

    try {
        const response = await fetch(`${AUTH_API}?action=pending-users`, {
            credentials: 'include'
        });
        const data = await response.json();

        if (data.success) {
            document.getElementById('pendingCount').textContent = data.count;

            if (data.pending_users.length === 0) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="4">
                            <div class="empty-state">
                                <i class="fas fa-user-check"></i>
                                <p>Nessun utente in attesa di approvazione</p>
                            </div>
                        </td>
                    </tr>
                `;
                return;
            }

            tbody.innerHTML = data.pending_users.map(user => {
                const safeUserId = parseInt(user.id) || 0;
                return `
                <tr data-user-id="${safeUserId}">
                    <td><strong>${escapeHtml(user.username)}</strong></td>
                    <td>${escapeHtml(user.email)}</td>
                    <td>${formatDateTime(user.created_at)}</td>
                    <td>
                        <button class="btn btn-approve" data-action="approve" data-userid="${safeUserId}">
                            <i class="fas fa-check"></i> Approva
                        </button>
                        <button class="btn btn-reject" data-action="reject" data-userid="${safeUserId}">
                            <i class="fas fa-times"></i> Rifiuta
                        </button>
                    </td>
                </tr>
            `}).join('');
        }
    } catch (error) {
        console.error('Error loading pending users:', error);
        tbody.innerHTML = `<tr><td colspan="4" class="loading-row">Errore nel caricamento</td></tr>`;
    }
}

async function approveUser(userId) {
    if (!confirm('Vuoi approvare questo utente come amministratore?')) return;

    try {
        const response = await fetch(`${AUTH_API}?action=approve`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': csrfToken
            },
            credentials: 'include',
            body: JSON.stringify({ user_id: userId })
        });

        const data = await response.json();

        if (data.success) {
            showNotification('Utente approvato con successo!', 'success');
            loadPendingUsers();
            loadPendingUsersCount();
        } else {
            showNotification(data.message || 'Errore nell\'approvazione', 'error');
        }
    } catch (error) {
        console.error('Approve error:', error);
        showNotification('Errore di connessione', 'error');
    }
}

async function rejectUser(userId) {
    if (!confirm('Vuoi rifiutare questo utente? Non potra accedere come amministratore.')) return;

    try {
        const response = await fetch(`${AUTH_API}?action=reject`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': csrfToken
            },
            credentials: 'include',
            body: JSON.stringify({ user_id: userId })
        });

        const data = await response.json();

        if (data.success) {
            showNotification('Utente rifiutato', 'warning');
            loadPendingUsers();
            loadPendingUsersCount();
        } else {
            showNotification(data.message || 'Errore nel rifiuto', 'error');
        }
    } catch (error) {
        console.error('Reject error:', error);
        showNotification('Errore di connessione', 'error');
    }
}

// ========== ESPORTA FUNZIONI GLOBALI ==========
window.viewBooking = viewBooking;
window.editBooking = editBooking;
window.deleteBooking = deleteBooking;
window.changePage = changePage;
window.approveUser = approveUser;
window.rejectUser = rejectUser;

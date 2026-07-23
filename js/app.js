/* ============================================================
   PharmOS — front-end application logic
   Vanilla JS, Fetch API only. No frameworks/build step required.
   ============================================================ */

const API = {
  session: 'api/session.php',
  logout: 'api/logout.php',
  dashboard: 'api/dashboard.php',
  drugs: 'api/drugs.php',
  scan: 'api/scan.php',
  sell: 'api/sell.php',
  sales: 'api/sales.php',
};

let currentUser = null;
let salesPage = 1;

/* ---------------- Helpers ---------------- */

function money(n) {
  return 'Le' + Number(n).toFixed(2);
}

function fmtDate(d) {
  if (!d) return '—';
  const dt = new Date(d + 'T00:00:00');
  return dt.toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' });
}

function fmtDateTime(d) {
  const dt = new Date(d.replace(' ', 'T'));
  return dt.toLocaleString(undefined, { year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' });
}

function escapeHtml(str) {
  const div = document.createElement('div');
  div.textContent = str ?? '';
  return div.innerHTML;
}

function toast(message, type = 'info') {
  const stack = document.getElementById('toast-stack');
  const el = document.createElement('div');
  el.className = 'toast ' + type;
  el.textContent = message;
  stack.appendChild(el);
  setTimeout(() => {
    el.style.opacity = '0';
    el.style.transition = 'opacity 0.25s ease';
    setTimeout(() => el.remove(), 250);
  }, 3800);
}

async function api(url, options = {}) {
  const res = await fetch(url, {
    headers: { 'Content-Type': 'application/json' },
    ...options,
  });
  let data;
  try {
    data = await res.json();
  } catch (e) {
    throw new Error('Unexpected server response.');
  }
  if (res.status === 401) {
    window.location.href = 'login.html';
    throw new Error('Session expired.');
  }
  if (!res.ok || data.success === false) {
    const err = new Error(data.error || 'Something went wrong.');
    err.payload = data;
    err.status = res.status;
    throw err;
  }
  return data;
}

/* ---------------- Auth / session bootstrap ---------------- */

async function bootstrap() {
  try {
    const data = await api(API.session);
    if (!data.authenticated) {
      window.location.href = 'login.html';
      return;
    }
    currentUser = data.user;
    renderUser();
    initNav();
    initClock();
    initDashboard();
    initScan();
    initInventory();
    initSalesLog();
    loadView('dashboard');
  } catch (e) {
    window.location.href = 'login.html';
  }
}

function renderUser() {
  document.getElementById('user-name').textContent = currentUser.full_name;
  document.getElementById('user-role').textContent = currentUser.role;
  document.getElementById('user-avatar').textContent = currentUser.full_name.slice(0, 1).toUpperCase();
}

document.getElementById('logout-btn').addEventListener('click', async () => {
  try {
    await api(API.logout, { method: 'POST' });
  } finally {
    window.location.href = 'login.html';
  }
});

/* ---------------- Navigation ---------------- */

function initNav() {
  document.querySelectorAll('.nav-item').forEach((btn) => {
    btn.addEventListener('click', () => loadView(btn.dataset.view));
  });
  document.getElementById('menu-toggle').addEventListener('click', () => {
    document.getElementById('sidebar').classList.toggle('open');
  });
}

const viewTitles = {
  dashboard: 'Dashboard',
  scan: 'Scan Out',
  inventory: 'Inventory',
  sales: 'Sales Log',
  financial: 'Financial Statement',
   users: 'User Management',
};

function loadView(view) {
  document.querySelectorAll('.view').forEach((v) => v.classList.remove('active'));
  document.getElementById('view-' + view).classList.add('active');
  document.querySelectorAll('.nav-item').forEach((b) => b.classList.toggle('active', b.dataset.view === view));
  document.getElementById('view-title').textContent = viewTitles[view];
  document.getElementById('sidebar').classList.remove('open');

  if (view === 'dashboard') refreshDashboard();
  if (view === 'inventory') refreshInventory();
  if (view === 'sales') refreshSales();
}

function initClock() {
  const el = document.getElementById('clock');
  const tick = () => { el.textContent = new Date().toLocaleString(); };
  tick();
  setInterval(tick, 1000);
}

/* ---------------- Dashboard ---------------- */

function initDashboard() {
  refreshDashboard();
}

async function refreshDashboard() {
  try {
    const data = await api(API.dashboard);
    document.getElementById('m-total-items').textContent = data.metrics.total_items;
    document.getElementById('m-low-stock').textContent = data.metrics.low_stock_count;
    document.getElementById('m-today-sales').textContent = data.metrics.today_sales_count;
    document.getElementById('m-today-revenue').textContent = money(data.metrics.today_revenue) + ' revenue';
    document.getElementById('m-near-expiry').textContent = data.metrics.near_expiry_count;

    renderRecentActivity(data.recent_activity);
    renderAlerts(data.low_stock, data.near_expiry);
  } catch (e) {
    toast(e.message, 'error');
  }
}

function renderRecentActivity(items) {
  const wrap = document.getElementById('recent-activity-wrap');
  if (!items.length) {
    wrap.innerHTML = '<div class="empty-state">No dispensations yet today.</div>';
    return;
  }
  wrap.innerHTML = `<table><tbody>${items.map((i) => `
    <tr>
      <td><strong>${escapeHtml(i.drug_name)}</strong><br><span class="text-muted" style="font-size:12px;">by ${escapeHtml(i.user_name)}</span></td>
      <td class="mono">×${i.quantity}</td>
      <td>${money(i.total_price)}</td>
      <td class="text-muted" style="font-size:12px;">${fmtDateTime(i.sold_at)}</td>
    </tr>`).join('')}</tbody></table>`;
}

function renderAlerts(lowStock, nearExpiry) {
  const wrap = document.getElementById('alerts-wrap');
  if (!lowStock.length && !nearExpiry.length) {
    wrap.innerHTML = '<div class="empty-state">All stock levels and expiry dates look healthy.</div>';
    return;
  }
  let rows = '';
  lowStock.slice(0, 6).forEach((d) => {
    rows += `<tr><td><strong>${escapeHtml(d.name)}</strong></td><td><span class="badge ${d.stock_quantity == 0 ? 'badge-out' : 'badge-low'}">${d.stock_quantity == 0 ? 'Out of stock' : 'Low stock'}</span></td><td class="mono">${d.stock_quantity}/${d.reorder_level}</td></tr>`;
  });
  nearExpiry.slice(0, 6).forEach((d) => {
    rows += `<tr><td><strong>${escapeHtml(d.name)}</strong></td><td><span class="badge badge-expiry">Near expiry</span></td><td class="mono">${fmtDate(d.expiry_date)}</td></tr>`;
  });
  wrap.innerHTML = `<table><tbody>${rows}</tbody></table>`;
}

/* ---------------- Scan Out ---------------- */

let lastScannedDrug = null;

function initScan() {
  const input = document.getElementById('barcode-input');
  input.addEventListener('keydown', (e) => {
    if (e.key === 'Enter') {
      e.preventDefault();
      doLookup(input.value.trim());
    }
  });
  document.getElementById('lookup-btn').addEventListener('click', () => doLookup(input.value.trim()));
  document.getElementById('clear-scan-btn').addEventListener('click', clearScanResult);
  document.getElementById('confirm-sale-btn').addEventListener('click', confirmSale);
}

async function doLookup(barcode) {
  if (!barcode) return;
  setScanStatus('Looking up ' + barcode + '…');
  try {
    const data = await api(API.scan, { method: 'POST', body: JSON.stringify({ barcode }) });
    lastScannedDrug = data.drug;
    renderScanResult(data);
    setScanStatus('Found: ' + data.drug.name);
  } catch (e) {
    lastScannedDrug = null;
    document.getElementById('scan-result').classList.remove('show');
    if (e.status === 404) {
      toast('Drug not found in inventory.', 'error');
      setScanStatus('No match for "' + barcode + '"');
    } else {
      toast(e.message, 'error');
      setScanStatus('Error during lookup');
    }
  }
  document.getElementById('barcode-input').value = '';
  document.getElementById('barcode-input').focus();
}

function setScanStatus(text) {
  document.getElementById('scan-status-text').textContent = text;
}

function renderScanResult(data) {
  const d = data.drug;
  const panel = document.getElementById('scan-result');
  panel.classList.add('show');

  document.getElementById('sr-name').textContent = d.name;
  document.getElementById('sr-meta').textContent = `${d.category} · ${d.strength} · ${d.form} · #${d.barcode}`;
  document.getElementById('sr-stock').textContent = d.stock_quantity;
  document.getElementById('sr-reorder').textContent = d.reorder_level;
  document.getElementById('sr-expiry').textContent = fmtDate(d.expiry_date);
  document.getElementById('sr-price').textContent = money(d.price);

  const badge = document.getElementById('sr-badge');
  const alertsWrap = document.getElementById('scan-alerts');
  alertsWrap.innerHTML = '';
  const confirmBtn = document.getElementById('confirm-sale-btn');
  confirmBtn.disabled = false;

  const statusMap = {
    ok:            { badge: 'badge-ok', label: 'In stock' },
    low_stock:     { badge: 'badge-low', label: 'Low stock' },
    out_of_stock:  { badge: 'badge-out', label: 'Out of stock' },
    near_expiry:   { badge: 'badge-expiry', label: 'Near expiry' },
    expired:       { badge: 'badge-out', label: 'Expired' },
  };
  const s = statusMap[data.status] || statusMap.ok;
  badge.className = 'badge ' + s.badge;
  badge.textContent = s.label;

  if (data.status === 'out_of_stock') {
    alertsWrap.innerHTML += '<div class="scan-alert danger">⛔ Out of stock — this item cannot be dispensed.</div>';
    confirmBtn.disabled = true;
  }
  if (data.status === 'expired') {
    alertsWrap.innerHTML += '<div class="scan-alert danger">⛔ This batch has expired — do not dispense.</div>';
    confirmBtn.disabled = true;
  }
  if (data.status === 'near_expiry') {
    alertsWrap.innerHTML += `<div class="scan-alert warn">⚠ Expires in ${data.days_to_expiry} day(s) — check the batch before dispensing.</div>`;
  }
  if (data.status === 'low_stock') {
    alertsWrap.innerHTML += '<div class="scan-alert warn">⚠ Stock is at or below the reorder level.</div>';
  }

  document.getElementById('sell-qty').value = 1;
  document.getElementById('sell-qty').max = d.stock_quantity;
}

function clearScanResult() {
  lastScannedDrug = null;
  document.getElementById('scan-result').classList.remove('show');
  setScanStatus('Ready — waiting for input');
  document.getElementById('barcode-input').focus();
}

async function confirmSale() {
  if (!lastScannedDrug) return;
  const qty = parseInt(document.getElementById('sell-qty').value, 10) || 1;
  const btn = document.getElementById('confirm-sale-btn');
  btn.disabled = true;
  btn.textContent = 'Processing…';

  try {
    const data = await api(API.sell, {
      method: 'POST',
      body: JSON.stringify({ barcode: lastScannedDrug.barcode, quantity: qty }),
    });
    toast(`Dispensed ${data.sale.quantity} × ${data.sale.drug_name} — ${money(data.sale.total_price)}`, 'success');
    if (data.near_expiry) {
      toast(`Note: ${data.sale.drug_name} expires in ${data.days_to_expiry} day(s).`, 'warn');
    }
    clearScanResult();
    refreshDashboard();
  } catch (e) {
    toast(e.message, 'error');
  } finally {
    btn.disabled = false;
    btn.textContent = 'Confirm Dispensation';
  }
}

/* ---------------- Inventory ---------------- */

let inventoryCache = [];

function initInventory() {
  document.getElementById('add-drug-btn').addEventListener('click', () => openDrugModal());
  document.getElementById('drug-modal-close').addEventListener('click', closeDrugModal);
  document.getElementById('drug-cancel-btn').addEventListener('click', closeDrugModal);
  document.getElementById('drug-modal-backdrop').addEventListener('click', (e) => {
    if (e.target.id === 'drug-modal-backdrop') closeDrugModal();
  });
  document.getElementById('drug-form').addEventListener('submit', saveDrug);

  let searchTimer;
  document.getElementById('inventory-search').addEventListener('input', () => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(refreshInventory, 300);
  });
  document.getElementById('low-stock-filter').addEventListener('change', refreshInventory);
}

async function refreshInventory() {
  const q = document.getElementById('inventory-search').value.trim();
  const lowOnly = document.getElementById('low-stock-filter').checked;
  const params = new URLSearchParams();
  if (q) params.set('q', q);
  if (lowOnly) params.set('low_stock', '1');

  try {
    const data = await api(API.drugs + '?' + params.toString());
    inventoryCache = data.drugs;
    renderInventoryTable(data.drugs);
  } catch (e) {
    toast(e.message, 'error');
  }
}

function renderInventoryTable(drugs) {
  const tbody = document.getElementById('inventory-tbody');
  if (!drugs.length) {
    tbody.innerHTML = '<tr><td colspan="9" class="empty-state">No drugs match your search.</td></tr>';
    return;
  }
  const today = new Date();
  tbody.innerHTML = drugs.map((d) => {
    const daysLeft = Math.floor((new Date(d.expiry_date) - today) / 86400000);
    let stockBadge = '<span class="badge badge-ok">OK</span>';
    if (d.stock_quantity == 0) stockBadge = '<span class="badge badge-out">Out</span>';
    else if (d.stock_quantity <= d.reorder_level) stockBadge = '<span class="badge badge-low">Low</span>';

    const expiryBadge = daysLeft < 0
      ? '<span class="badge badge-out">Expired</span>'
      : daysLeft <= 30 ? '<span class="badge badge-expiry">Soon</span>' : '';

    return `<tr>
      <td><strong>${escapeHtml(d.name)}</strong><div class="text-muted" style="font-size:12px;">${escapeHtml(d.strength)}</div></td>
      <td class="mono">${escapeHtml(d.barcode)}</td>
      <td>${escapeHtml(d.category)}</td>
      <td>${escapeHtml(d.form)}</td>
      <td class="mono">${d.stock_quantity} ${stockBadge}</td>
      <td class="mono">${d.reorder_level}</td>
      <td>${fmtDate(d.expiry_date)} ${expiryBadge}</td>
      <td class="mono">${money(d.price)}</td>
      <td>
        <div class="flex gap-8">
          <button class="btn btn-ghost btn-sm" onclick="editDrug(${d.id})">Edit</button>
          <button class="btn btn-danger btn-sm" onclick="deleteDrug(${d.id}, '${escapeHtml(d.name).replace(/'/g, "\\'")}')">Delete</button>
        </div>
      </td>
    </tr>`;
  }).join('');
}

function openDrugModal(drug = null) {
  document.getElementById('drug-modal-title').textContent = drug ? 'Edit Drug' : 'Add Drug';
  document.getElementById('drug-id').value = drug ? drug.id : '';
  document.getElementById('drug-barcode').value = drug ? drug.barcode : '';
  document.getElementById('drug-name').value = drug ? drug.name : '';
  document.getElementById('drug-category').value = drug ? drug.category : '';
  document.getElementById('drug-strength').value = drug ? drug.strength : '';
  document.getElementById('drug-form').value = drug ? drug.form : 'Tablet';
  document.getElementById('drug-price').value = drug ? drug.price : '';
  document.getElementById('drug-stock').value = drug ? drug.stock_quantity : '';
  document.getElementById('drug-reorder').value = drug ? drug.reorder_level : '';
  document.getElementById('drug-expiry').value = drug ? drug.expiry_date : '';
  document.getElementById('drug-modal-backdrop').classList.add('show');
}

function closeDrugModal() {
  document.getElementById('drug-modal-backdrop').classList.remove('show');
}

function editDrug(id) {
  const drug = inventoryCache.find((d) => d.id === id);
  if (drug) openDrugModal(drug);
}

async function deleteDrug(id, name) {
  if (!confirm(`Remove "${name}" from inventory? This cannot be undone.`)) return;
  try {
    await api(API.drugs + '?id=' + id, { method: 'DELETE' });
    toast('Drug removed.', 'success');
    refreshInventory();
    refreshDashboard();
  } catch (e) {
    toast(e.message, 'error');
  }
}

async function saveDrug(e) {
  e.preventDefault();
  const id = document.getElementById('drug-id').value;
  const payload = {
    barcode: document.getElementById('drug-barcode').value.trim(),
    name: document.getElementById('drug-name').value.trim(),
    category: document.getElementById('drug-category').value.trim(),
    strength: document.getElementById('drug-strength').value.trim(),
    form: document.getElementById('drug-form').value,
    price: parseFloat(document.getElementById('drug-price').value),
    stock_quantity: parseInt(document.getElementById('drug-stock').value, 10),
    reorder_level: parseInt(document.getElementById('drug-reorder').value, 10),
    expiry_date: document.getElementById('drug-expiry').value,
  };
  if (id) payload.id = parseInt(id, 10);

  const btn = document.getElementById('drug-save-btn');
  btn.disabled = true;

  try {
    await api(API.drugs, { method: id ? 'PUT' : 'POST', body: JSON.stringify(payload) });
    toast(id ? 'Drug updated.' : 'Drug added.', 'success');
    closeDrugModal();
    refreshInventory();
    refreshDashboard();
  } catch (e) {
    toast(e.message, 'error');
  } finally {
    btn.disabled = false;
  }
}
/* ---------------- Sales Log ---------------- */

function initSalesLog() {
  let searchTimer;
  document.getElementById('sales-search').addEventListener('input', () => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => { salesPage = 1; refreshSales(); }, 300);
  });
  document.getElementById('sales-date-filter').addEventListener('change', () => { salesPage = 1; refreshSales(); });
}

async function refreshSales() {
  const q = document.getElementById('sales-search').value.trim();
  const date = document.getElementById('sales-date-filter').value;
  const params = new URLSearchParams({ page: salesPage, per_page: 20 });
  if (q) params.set('q', q);
  if (date) params.set('date', date);

  try {
    const data = await api(API.sales + '?' + params.toString());
    renderSalesTable(data.sales);
    renderSalesPagination(data.pagination);
  } catch (e) {
    toast(e.message, 'error');
  }
}

function renderSalesTable(rows) {
  const tbody = document.getElementById('sales-tbody');
  if (!rows.length) {
    tbody.innerHTML = '<tr><td colspan="6" class="empty-state">No transactions found.</td></tr>';
    return;
  }
  tbody.innerHTML = rows.map((r) => `
    <tr>
      <td><strong>${escapeHtml(r.drug_name)}</strong><div class="text-muted mono" style="font-size:12px;">#${escapeHtml(r.barcode)}</div></td>
      <td class="mono">${r.quantity}</td>
      <td class="mono">${money(r.unit_price)}</td>
      <td class="mono">${money(r.total_price)}</td>
      <td>${escapeHtml(r.user_name)}</td>
      <td class="text-muted" style="font-size:13px;">${fmtDateTime(r.sold_at)}</td>
    </tr>`).join('');
}

function renderSalesPagination(p) {
  const wrap = document.getElementById('sales-pagination');
  if (p.pages <= 1) { wrap.innerHTML = ''; return; }
  wrap.innerHTML = `
    <button class="btn btn-ghost btn-sm" ${p.page <= 1 ? 'disabled' : ''} onclick="changeSalesPage(${p.page - 1})">Prev</button>
    <span class="text-muted mono" style="font-size:13px; align-self:center;">Page ${p.page} of ${p.pages}</span>
    <button class="btn btn-ghost btn-sm" ${p.page >= p.pages ? 'disabled' : ''} onclick="changeSalesPage(${p.page + 1})">Next</button>
  `;
}

function changeSalesPage(page) {
  salesPage = page;
  refreshSales();
}

/* ---------------- Boot ---------------- */
bootstrap();

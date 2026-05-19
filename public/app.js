const state = {
  range: 'today',
  leads: [],
  timers: new Map(),
};

const api = {
  async get(path) {
    const res = await fetch(path);
    return res.json();
  },
  async send(path, method, body) {
    const res = await fetch(path, {
      method,
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(body),
    });
    return res.json();
  },
};

const body = document.querySelector('#leads-body');
const subtitle = document.querySelector('#subtitle');

function money(value) {
  if (value === null || value === undefined || value === '') return '';
  return Number(value).toLocaleString('en-KE');
}

function secondsToClock(seconds) {
  const total = Math.max(0, Number(seconds || 0));
  const mins = String(Math.floor(total / 60)).padStart(2, '0');
  const secs = String(total % 60).padStart(2, '0');
  return `${mins}:${secs}`;
}

function leadSeconds(lead) {
  if (lead.call_ended_at) return Number(lead.call_duration_seconds || 0);
  if (!lead.call_started_at) return Number(lead.call_duration_seconds || 0);
  const started = new Date(lead.call_started_at.replace(' ', 'T'));
  return Math.floor((Date.now() - started.getTime()) / 1000);
}

async function refreshStats() {
  const stats = await api.get('../api/stats.php');
  document.querySelector('#metric-total').textContent = stats.today.total;
  document.querySelector('#metric-bought').textContent = stats.today.bought;
  document.querySelector('#metric-quoting').textContent = stats.today.quoting;
  document.querySelector('#metric-ignored').textContent = stats.today.ignored_calls;
}

function dataListId(type, id) {
  return `${type}-${id}`;
}

async function populateSuggestions(input, type) {
  const value = input.value.trim();
  const listId = input.getAttribute('list');
  const list = document.getElementById(listId);
  if (!list || !value) {
    if (list) list.innerHTML = '';
    return;
  }
  const data = await api.get(`../api/search.php?type=${type}&q=${encodeURIComponent(value)}`);
  list.innerHTML = data.items.map((item) => {
    if (type === 'areas') {
      return `<option value="${item.area}" data-fee="${item.fee}">${item.zone} - KES ${money(item.fee)}</option>`;
    }
    return `<option value="${item.name}" data-price="${item.selling_price}">${item.sku} - KES ${money(item.selling_price)}</option>`;
  }).join('');
}

async function updateLead(id, patch) {
  const result = await api.send('../api/leads.php', 'PATCH', { id, ...patch });
  if (!result.ok) alert(result.error || 'Could not update lead');
  await refreshStats();
}

function renderLeads() {
  clearInterval(state.timerInterval);
  if (state.leads.length === 0) {
    const label = state.range === 'today' ? '0 leads today' : 'No leads found';
    body.innerHTML = `<tr><td colspan="8" class="empty">${label}</td></tr>`;
    return;
  }

  body.innerHTML = state.leads.map((lead) => {
    const productList = dataListId('products', lead.id);
    const areaList = dataListId('areas', lead.id);
    return `
      <tr data-id="${lead.id}">
        <td class="phone-cell">
          <strong>${lead.phone_display}</strong>
          <small>${lead.source}${lead.contact_type ? ` / ${lead.contact_type}` : ''}</small>
        </td>
        <td><input data-field="customer_name" value="${escapeHtml(lead.customer_name || '')}"></td>
        <td class="timer" data-start="${lead.call_started_at || ''}" data-ended="${lead.call_ended_at || ''}" data-seconds="${lead.call_duration_seconds || 0}">${secondsToClock(leadSeconds(lead))}</td>
        <td>
          <input data-field="product_name" list="${productList}" value="${escapeHtml(lead.product_name || '')}" placeholder="Search product">
          <datalist id="${productList}"></datalist>
          <input data-field="quoted_amount" type="number" value="${lead.quoted_amount || ''}" placeholder="Quote amount">
        </td>
        <td>
          <input data-field="delivery_area" list="${areaList}" value="${escapeHtml(lead.delivery_area || '')}" placeholder="Search area">
          <datalist id="${areaList}"></datalist>
        </td>
        <td>
          <select data-field="resolution">
            ${['quoting', 'bought', 'n/a', 'no_answer', 'callback', 'wrong_number', 'lost'].map((value) => (
              `<option value="${value}" ${lead.resolution === value ? 'selected' : ''}>${value.replace('_', ' ')}</option>`
            )).join('')}
          </select>
          <input data-field="notes" value="${escapeHtml(lead.notes || '')}" placeholder="Notes">
        </td>
        <td class="followups">
          ${[1, 2, 3].map((n) => `
            <label><input type="checkbox" data-field="followup_${n}_done" ${Number(lead[`followup_${n}_done`]) ? 'checked' : ''}>${n}</label>
          `).join('')}
        </td>
        <td class="actions">
          <button data-contact-type="supplier">Supplier</button>
          <button data-contact-type="delivery">Delivery</button>
          <button data-contact-type="staff">Staff</button>
        </td>
      </tr>
    `;
  }).join('');

  state.timerInterval = setInterval(updateTimers, 1000);
}

function updateTimers() {
  document.querySelectorAll('.timer').forEach((cell) => {
    if (cell.dataset.ended || !cell.dataset.start) {
      cell.textContent = secondsToClock(cell.dataset.seconds);
      return;
    }
    const started = new Date(cell.dataset.start.replace(' ', 'T'));
    cell.textContent = secondsToClock(Math.floor((Date.now() - started.getTime()) / 1000));
  });
}

function escapeHtml(value) {
  return String(value).replace(/[&<>"']/g, (char) => ({
    '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'
  }[char]));
}

async function loadLeads() {
  let url = `../api/leads.php?range=${state.range}`;
  if (state.range === 'custom') {
    url += `&start=${document.querySelector('#start-date').value}&end=${document.querySelector('#end-date').value}`;
  }
  const data = await api.get(url);
  state.leads = data.leads || [];
  subtitle.textContent = state.range === 'followups' ? 'Follow-ups due' : `${state.range.replace('days', ' days ago')} leads`;
  renderLeads();
  await refreshStats();
}

body.addEventListener('change', async (event) => {
  const row = event.target.closest('tr[data-id]');
  if (!row || !event.target.dataset.field) return;
  const field = event.target.dataset.field;
  const value = event.target.type === 'checkbox' ? (event.target.checked ? 1 : 0) : event.target.value;
  await updateLead(Number(row.dataset.id), { [field]: value });
});

body.addEventListener('input', (event) => {
  if (event.target.dataset.field === 'product_name') populateSuggestions(event.target, 'products');
  if (event.target.dataset.field === 'delivery_area') populateSuggestions(event.target, 'areas');
});

body.addEventListener('click', async (event) => {
  const type = event.target.dataset.contactType;
  if (!type) return;
  const row = event.target.closest('tr[data-id]');
  const lead = state.leads.find((item) => Number(item.id) === Number(row.dataset.id));
  const nameInput = row.querySelector('[data-field="customer_name"]');
  const result = await api.send('../api/contacts.php', 'POST', {
    phone: lead.phone_display,
    name: nameInput.value,
    type,
    ignored: 1,
    notes: `Added from lead #${lead.id}`,
  });
  if (result.ok) {
    event.target.textContent = 'Saved';
    await loadLeads();
  }
});

document.querySelectorAll('.tab').forEach((tab) => {
  tab.addEventListener('click', () => {
    document.querySelectorAll('.tab').forEach((item) => item.classList.remove('active'));
    tab.classList.add('active');
    state.range = tab.dataset.range;
    loadLeads();
  });
});

document.querySelector('#custom-range').addEventListener('click', () => {
  document.querySelectorAll('.tab').forEach((item) => item.classList.remove('active'));
  state.range = 'custom';
  loadLeads();
});

const dialog = document.querySelector('#manual-dialog');
document.querySelector('#manual-lead').addEventListener('click', () => dialog.showModal());
document.querySelector('#save-manual').addEventListener('click', async (event) => {
  event.preventDefault();
  const phone = document.querySelector('#manual-phone').value.trim();
  if (!phone) return;
  await api.send('../api/leads.php', 'POST', {
    phone,
    customer_name: document.querySelector('#manual-name').value.trim(),
    source: 'manual',
  });
  dialog.close();
  document.querySelector('#manual-phone').value = '';
  document.querySelector('#manual-name').value = '';
  await loadLeads();
});

loadLeads();
setInterval(loadLeads, 15000);

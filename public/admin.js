const api = {
  async get(path) {
    const res = await fetch(path);
    return parseApiResponse(res);
  },
  async send(path, method, body) {
    const res = await fetch(path, {
      method,
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(body),
    });
    return parseApiResponse(res);
  },
};

async function parseApiResponse(res) {
  const text = await res.text();
  try {
    const data = JSON.parse(text);
    if (!res.ok || data.ok === false) {
      throw new Error(data.error || `API request failed with ${res.status}`);
    }
    return data;
  } catch (error) {
    if (error instanceof SyntaxError) {
      const preview = text.replace(/<[^>]+>/g, ' ').replace(/\s+/g, ' ').trim().slice(0, 240);
      throw new Error(preview || `API returned non-JSON response from ${res.url}`);
    }
    throw error;
  }
}

function escapeHtml(value) {
  return String(value ?? '').replace(/[&<>"']/g, (char) => ({
    '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'
  }[char]));
}

async function loadStats() {
  const stats = await api.get('../api/stats.php');
  document.querySelector('#avg-daily').textContent = stats.average_daily_leads_30d;
  document.querySelector('#conversion').textContent = `${stats.today.conversion_rate}%`;
  document.querySelector('#followup-rate').textContent = `${stats.today.followup_rate}%`;
  document.querySelector('#admin-ignored').textContent = stats.today.ignored_calls;
  document.querySelector('#top-products').innerHTML = stats.top_products.length
    ? stats.top_products.map((item) => `<li>${escapeHtml(item.product_name)} <strong>${item.count}</strong></li>`).join('')
    : '<li>No product enquiries yet</li>';
}

async function loadContacts() {
  const data = await api.get('../api/contacts.php');
  document.querySelector('#contacts-body').innerHTML = data.contacts.length
    ? data.contacts.map((contact) => `
      <tr>
        <td>${escapeHtml(contact.phone_display)}</td>
        <td>${escapeHtml(contact.name)}</td>
        <td>${escapeHtml(contact.type)}</td>
        <td>${Number(contact.ignored) ? 'Yes' : 'No'}</td>
        <td>${escapeHtml(contact.notes)}</td>
      </tr>
    `).join('')
    : '<tr><td colspan="5" class="empty">No saved contacts yet</td></tr>';
}

document.querySelector('#contact-form').addEventListener('submit', async (event) => {
  event.preventDefault();
  const form = new FormData(event.currentTarget);
  const type = form.get('type');
  const ignored = ['supplier', 'delivery', 'staff', 'spam'].includes(type);
  const result = await api.send('../api/contacts.php', 'POST', {
    phone: form.get('phone'),
    name: form.get('name'),
    type,
    ignored,
    notes: form.get('notes'),
  });
  if (!result.ok) {
    alert(result.error || 'Could not save contact');
    return;
  }
  event.currentTarget.reset();
  await Promise.all([loadStats(), loadContacts()]);
});

Promise.all([loadStats(), loadContacts()]).catch((error) => {
  document.querySelector('#contacts-body').innerHTML = `<tr><td colspan="5" class="empty">${escapeHtml(error.message)}</td></tr>`;
  console.error(error);
});

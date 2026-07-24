<?php
$pageTitle   = 'Usuários';
$pageScripts = <<<'JS'
<script>
let allAccounts = [];

document.addEventListener('DOMContentLoaded', async () => {
  await Promise.all([loadAccounts(), loadUsers()]);
});

async function loadAccounts() {
  try {
    const data = await apiFetch('/api/accounts');
    allAccounts = data.data || [];
  } catch(e) {}
}

async function loadUsers() {
  const tbody = document.getElementById('tbody');
  tbody.innerHTML = '<tr><td colspan="5" class="text-center"><span class="loading loading-spinner loading-sm"></span></td></tr>';
  try {
    const data = await apiFetch('/api/users');
    const rows = data.data || [];
    if (!rows.length) {
      tbody.innerHTML = '<tr><td colspan="5" class="text-center opacity-60 py-6">Nenhum usuário cadastrado. Os usuários aparecem aqui após realizarem o primeiro login com Google.</td></tr>';
      return;
    }
    tbody.innerHTML = rows.map(r => {
      const accounts = r.account_labels ? esc(r.account_labels) : '<span class="opacity-40">Nenhuma</span>';
      const lastLogin = r.token_expires_at
        ? new Date(r.token_expires_at).toLocaleDateString('pt-BR')
        : '<span class="opacity-40">—</span>';
      return `<tr>
        <td>
          <div class="font-medium text-sm">${esc(r.name || '—')}</div>
          <div class="text-xs opacity-60">${esc(r.email)}</div>
        </td>
        <td><span class="badge badge-sm ${r.role === 'admin' ? 'badge-warning' : 'badge-ghost'}">${esc(r.role)}</span></td>
        <td class="text-xs max-w-xs">${accounts}</td>
        <td class="text-xs opacity-60">${lastLogin}</td>
        <td class="whitespace-nowrap">
          <button onclick="openLinkModal(${r.id}, '${esc(r.email)}', '${esc(r.account_ids || '')}')"
                  class="btn btn-xs btn-ghost">Vincular Contas</button>
          <button onclick="deleteUser(${r.id}, '${esc(r.email)}')"
                  class="btn btn-xs btn-ghost btn-error">Remover</button>
        </td>
      </tr>`;
    }).join('');
  } catch(e) {
    tbody.innerHTML = `<tr><td colspan="5" class="text-error text-sm p-4">${e.message}</td></tr>`;
  }
}

function openLinkModal(userId, email, accountIdsStr) {
  document.getElementById('linkUserId').value  = userId;
  document.getElementById('linkUserEmail').textContent = email;

  const linked = accountIdsStr ? accountIdsStr.split(',').map(s => s.trim()).filter(Boolean) : [];

  const container = document.getElementById('accountCheckboxes');
  if (!allAccounts.length) {
    container.innerHTML = '<p class="text-sm opacity-60">Nenhuma conta disponível. Cadastre contas primeiro.</p>';
  } else {
    container.innerHTML = allAccounts.map(a => `
      <label class="flex items-center gap-3 p-2 rounded-lg hover:bg-base-200 cursor-pointer">
        <input type="checkbox" class="checkbox checkbox-sm checkbox-primary"
               value="${a.id}" ${linked.includes(String(a.id)) ? 'checked' : ''}>
        <div>
          <div class="text-sm font-medium">${esc(a.label)}</div>
          <div class="text-xs opacity-60">${esc(a.account_key)}</div>
        </div>
      </label>
    `).join('');
  }

  document.getElementById('linkModal').showModal();
}

async function saveLinkAccounts() {
  const userId     = document.getElementById('linkUserId').value;
  const checkboxes = document.querySelectorAll('#accountCheckboxes input[type=checkbox]');
  const accountIds = Array.from(checkboxes).filter(c => c.checked).map(c => parseInt(c.value));

  try {
    await apiFetch(`/api/users/${userId}/accounts`, {
      method: 'PUT',
      body: JSON.stringify({ account_ids: accountIds })
    });
    document.getElementById('linkModal').close();
    loadUsers();
  } catch(e) {
    alert('Erro ao salvar: ' + e.message);
  }
}

async function deleteUser(userId, email) {
  if (!confirm(`Remover o usuário "${email}"? Esta ação não pode ser desfeita.`)) return;
  try {
    await apiFetch(`/api/users/${userId}`, { method: 'DELETE' });
    loadUsers();
  } catch(e) {
    alert('Erro: ' + e.message);
  }
}

function esc(s) { const d = document.createElement('div'); d.textContent = String(s || ''); return d.innerHTML; }
</script>
JS;

ob_start();
?>
<div class="space-y-4">
  <div class="flex justify-between items-center">
    <div>
      <h2 class="text-lg font-semibold">Usuários</h2>
      <p class="text-xs opacity-60 mt-0.5">Usuários que fizeram login com Google. Vincule contas Meta para dar acesso ao gerador.</p>
    </div>
    <button onclick="loadUsers()" class="btn btn-sm btn-ghost">
      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
      Atualizar
    </button>
  </div>

  <div class="card bg-base-100 shadow-sm overflow-x-auto">
    <table class="table table-sm">
      <thead>
        <tr>
          <th>Usuário</th>
          <th>Role</th>
          <th>Contas vinculadas</th>
          <th>Último acesso</th>
          <th>Ações</th>
        </tr>
      </thead>
      <tbody id="tbody">
        <tr><td colspan="5" class="text-center"><span class="loading loading-spinner loading-sm"></span></td></tr>
      </tbody>
    </table>
  </div>

  <div class="alert alert-info text-sm">
    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
    <span>Usuários sem contas vinculadas não conseguem acessar o Gerador de Campanhas.</span>
  </div>
</div>

<!-- Modal: vincular contas -->
<dialog id="linkModal" class="modal">
  <div class="modal-box max-w-md">
    <h3 class="font-bold text-lg mb-1">Vincular Contas</h3>
    <p class="text-sm opacity-60 mb-4">Usuário: <span id="linkUserEmail" class="font-medium text-base-content"></span></p>
    <input type="hidden" id="linkUserId">

    <div id="accountCheckboxes" class="space-y-1 max-h-72 overflow-y-auto border border-base-300 rounded-lg p-2">
      <span class="loading loading-spinner loading-sm"></span>
    </div>

    <div class="modal-action">
      <button onclick="document.getElementById('linkModal').close()" class="btn btn-sm btn-ghost">Cancelar</button>
      <button onclick="saveLinkAccounts()" class="btn btn-sm btn-primary">Salvar</button>
    </div>
  </div>
  <form method="dialog" class="modal-backdrop"><button>Fechar</button></form>
</dialog>
<?php
$pageContent = ob_get_clean();
require __DIR__ . '/layout.php';

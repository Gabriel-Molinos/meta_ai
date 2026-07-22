<?php
$pageTitle   = 'Contas';
$pageScripts = <<<'JS'
<script>
document.addEventListener('DOMContentLoaded', loadAccounts);

async function loadAccounts() {
  try {
    const data = await apiFetch('/api/accounts');
    const rows = data.data || [];
    const tbody = document.getElementById('tbody');
    if (!rows.length) {
      tbody.innerHTML = '<tr><td colspan="6" class="text-center opacity-60">Nenhuma conta cadastrada.</td></tr>';
      return;
    }
    tbody.innerHTML = rows.map(r => `<tr>
      <td class="font-mono text-xs">${esc(r.account_key)}</td>
      <td>${esc(r.label)}</td>
      <td class="text-xs opacity-70">${esc(r.meta_account_id)}</td>
      <td class="text-xs opacity-70">${esc(r.av_domain)}</td>
      <td><span class="badge badge-sm ${r.active ? 'badge-success' : 'badge-ghost'}">${r.active ? 'Ativa' : 'Inativa'}</span></td>
      <td>
        <button onclick="editAccount('${esc(r.account_key)}')" class="btn btn-xs btn-ghost">Editar</button>
        <button onclick="deleteAccount('${esc(r.account_key)}')" class="btn btn-xs btn-ghost btn-error">Remover</button>
      </td>
    </tr>`).join('');
  } catch(e) {
    document.getElementById('tbody').innerHTML = `<tr><td colspan="6" class="text-error">${e.message}</td></tr>`;
  }
}

function openModal() {
  document.getElementById('form').reset();
  document.getElementById('editKey').value = '';
  document.getElementById('modal').showModal();
}

function editAccount(key) {
  document.getElementById('editKey').value = key;
  document.getElementById('accountKey').value = key;
  document.getElementById('accountKey').readOnly = true;
  document.getElementById('modal').showModal();
}

async function saveAccount() {
  const editKey = document.getElementById('editKey').value;
  const data = {
    account_key:       document.getElementById('accountKey').value.trim(),
    label:             document.getElementById('label').value.trim(),
    meta_app_id:       document.getElementById('metaAppId').value.trim(),
    meta_app_secret:   document.getElementById('metaAppSecret').value.trim(),
    meta_access_token: document.getElementById('metaAccessToken').value.trim(),
    meta_account_id:   document.getElementById('metaAccountId').value.trim(),
    av_api_key:        document.getElementById('avApiKey').value.trim(),
    av_network_code:   document.getElementById('avNetworkCode').value.trim(),
    av_domain:         document.getElementById('avDomain').value.trim(),
  };

  try {
    if (editKey) {
      await apiFetch(`/api/accounts/${editKey}`, { method: 'PUT', body: JSON.stringify(data) });
    } else {
      await apiFetch('/api/accounts', { method: 'POST', body: JSON.stringify(data) });
    }
    document.getElementById('modal').close();
    document.getElementById('accountKey').readOnly = false;
    loadAccounts();
  } catch(e) {
    alert('Erro: ' + e.message);
  }
}

async function deleteAccount(key) {
  if (!confirm(`Remover conta "${key}"?`)) return;
  try {
    await apiFetch(`/api/accounts/${key}`, { method: 'DELETE' });
    loadAccounts();
  } catch(e) {
    alert('Erro: ' + e.message);
  }
}

function esc(s) { const d=document.createElement('div'); d.textContent=String(s||''); return d.innerHTML; }
</script>
JS;

ob_start();
?>
<div class="space-y-4">
  <div class="flex justify-between items-center">
    <h2 class="text-lg font-semibold">Contas Meta Ads</h2>
    <button onclick="openModal()" class="btn btn-sm btn-primary">+ Nova Conta</button>
  </div>

  <div class="card bg-base-100 shadow-sm overflow-x-auto">
    <table class="table table-sm">
      <thead>
        <tr><th>Chave</th><th>Label</th><th>Meta Account ID</th><th>Domínio AV</th><th>Status</th><th>Ações</th></tr>
      </thead>
      <tbody id="tbody">
        <tr><td colspan="6" class="text-center"><span class="loading loading-spinner loading-sm"></span></td></tr>
      </tbody>
    </table>
  </div>
</div>

<!-- Modal de criação/edição -->
<dialog id="modal" class="modal">
  <div class="modal-box max-w-2xl">
    <h3 class="font-bold text-lg mb-4">Conta Meta Ads</h3>
    <input type="hidden" id="editKey">
    <form id="form" class="grid grid-cols-1 md:grid-cols-2 gap-3" onsubmit="return false">
      <div class="form-control">
        <label class="label label-text text-xs">Chave única (account_key) *</label>
        <input id="accountKey" type="text" placeholder="ex: minha-conta" class="input input-sm input-bordered" required>
      </div>
      <div class="form-control">
        <label class="label label-text text-xs">Label (nome legível) *</label>
        <input id="label" type="text" placeholder="Minha Conta" class="input input-sm input-bordered" required>
      </div>
      <div class="form-control">
        <label class="label label-text text-xs">Meta App ID</label>
        <input id="metaAppId" type="text" placeholder="1234567890" class="input input-sm input-bordered">
      </div>
      <div class="form-control">
        <label class="label label-text text-xs">Meta App Secret</label>
        <input id="metaAppSecret" type="password" placeholder="••••••••" class="input input-sm input-bordered">
      </div>
      <div class="form-control md:col-span-2">
        <label class="label label-text text-xs">Meta Access Token</label>
        <input id="metaAccessToken" type="password" placeholder="EAA..." class="input input-sm input-bordered">
      </div>
      <div class="form-control">
        <label class="label label-text text-xs">Meta Account ID</label>
        <input id="metaAccountId" type="text" placeholder="act_XXXXXXXXXX" class="input input-sm input-bordered">
      </div>
      <div class="form-control">
        <label class="label label-text text-xs">AV API Key</label>
        <input id="avApiKey" type="password" placeholder="••••••••" class="input input-sm input-bordered">
      </div>
      <div class="form-control">
        <label class="label label-text text-xs">AV Network Code</label>
        <input id="avNetworkCode" type="text" placeholder="123456" class="input input-sm input-bordered">
      </div>
      <div class="form-control">
        <label class="label label-text text-xs">AV Domain</label>
        <input id="avDomain" type="text" placeholder="meusite.com.br" class="input input-sm input-bordered">
      </div>
    </form>
    <div class="modal-action">
      <button onclick="document.getElementById('modal').close()" class="btn btn-sm btn-ghost">Cancelar</button>
      <button onclick="saveAccount()" class="btn btn-sm btn-primary">Salvar</button>
    </div>
  </div>
  <form method="dialog" class="modal-backdrop"><button>Fechar</button></form>
</dialog>
<?php
$pageContent = ob_get_clean();
require __DIR__ . '/layout.php';

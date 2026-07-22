<?php
$pageTitle   = 'Campanhas';
$pageScripts = <<<'JS'
<script>
document.addEventListener('DOMContentLoaded', () => loadCampaigns());

async function loadCampaigns() {
  const params = new URLSearchParams({
    account_key:   document.getElementById('accountFilter').value,
    campaign_name: document.getElementById('nameFilter').value,
    start_date:    document.getElementById('startDate').value,
    end_date:      document.getElementById('endDate').value,
  });

  const tbody = document.getElementById('tbody');
  tbody.innerHTML = '<tr><td colspan="9" class="text-center"><span class="loading loading-spinner loading-sm"></span></td></tr>';

  try {
    const data = await apiFetch('/api/campaigns?' + params);
    const rows = data.data || [];
    if (!rows.length) {
      tbody.innerHTML = '<tr><td colspan="9" class="text-center opacity-60">Sem resultados.</td></tr>';
      return;
    }
    tbody.innerHTML = rows.map(r => {
      const roas = parseFloat(r.roas || 0);
      const badge = roas >= 1.5 ? 'badge-success' : roas >= 0.8 ? 'badge-warning' : 'badge-error';
      return `<tr>
        <td class="text-xs">${esc(r.report_date)}</td>
        <td class="text-sm max-w-[200px] truncate" title="${esc(r.campaign_name)}">${esc(r.campaign_name)}</td>
        <td class="text-xs opacity-60">${esc(r.account_key)}</td>
        <td>$${fmt(r.spend_usd)}</td>
        <td>$${fmt(r.av_revenue_usd)}</td>
        <td><span class="badge badge-sm ${badge}">${roas.toFixed(2)}</span></td>
        <td>${fmtNum(r.impressions)}</td>
        <td>${fmt(r.ctr, 3)}%</td>
        <td>${fmtNum(r.av_sessions)}</td>
      </tr>`;
    }).join('');
    document.getElementById('count').textContent = rows.length + ' registros';
  } catch(e) {
    tbody.innerHTML = `<tr><td colspan="9" class="text-error">${e.message}</td></tr>`;
  }
}

function fmt(v, d=2) { return parseFloat(v||0).toFixed(d).replace(/\B(?=(\d{3})+(?!\d))/g,','); }
function fmtNum(v) { return parseInt(v||0).toLocaleString('pt-BR'); }
function esc(s) { const d=document.createElement('div'); d.textContent=s; return d.innerHTML; }
</script>
JS;

$defaultEnd   = date('Y-m-d');
$defaultStart = date('Y-m-d', strtotime('-30 days'));

ob_start();
?>
<div class="space-y-4">
  <div class="card bg-base-100 shadow-sm">
    <div class="card-body py-3">
      <div class="flex flex-wrap gap-2 items-end">
        <div>
          <label class="label label-text text-xs pb-1">Conta</label>
          <input id="accountFilter" type="text" placeholder="account_key" class="input input-sm input-bordered w-36">
        </div>
        <div>
          <label class="label label-text text-xs pb-1">Campanha</label>
          <input id="nameFilter" type="text" placeholder="Nome..." class="input input-sm input-bordered w-44">
        </div>
        <div>
          <label class="label label-text text-xs pb-1">De</label>
          <input id="startDate" type="date" value="<?= $defaultStart ?>" class="input input-sm input-bordered">
        </div>
        <div>
          <label class="label label-text text-xs pb-1">Até</label>
          <input id="endDate" type="date" value="<?= $defaultEnd ?>" class="input input-sm input-bordered">
        </div>
        <button onclick="loadCampaigns()" class="btn btn-sm btn-primary">Buscar</button>
        <span id="count" class="text-xs opacity-60 self-end mb-1"></span>
      </div>
    </div>
  </div>

  <div class="card bg-base-100 shadow-sm">
    <div class="overflow-x-auto">
      <table class="table table-xs">
        <thead>
          <tr>
            <th>Data</th>
            <th>Campanha</th>
            <th>Conta</th>
            <th>Gasto</th>
            <th>Receita AV</th>
            <th>ROAS</th>
            <th>Impressões</th>
            <th>CTR</th>
            <th>Sessões AV</th>
          </tr>
        </thead>
        <tbody id="tbody">
          <tr><td colspan="9" class="text-center">
            <span class="loading loading-spinner loading-sm"></span>
          </td></tr>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php
$pageContent = ob_get_clean();
require __DIR__ . '/layout.php';

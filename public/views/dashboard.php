<?php
$pageTitle   = 'Dashboard';
$pageScripts = <<<'JS'
<script>
document.addEventListener('DOMContentLoaded', () => loadDashboard());

async function loadDashboard() {
  const days = document.getElementById('days').value;
  const accountKey = document.getElementById('accountFilter').value;

  try {
    const params = new URLSearchParams({ days });
    if (accountKey) params.set('account_key', accountKey);

    const data = await apiFetch('/api/dashboard?' + params);
    renderOverview(data.overview || {});
    renderTopTable(data.top_roas || []);
  } catch(e) {
    document.getElementById('overview').innerHTML = `<div class="alert alert-error">${e.message}</div>`;
  }
}

function renderOverview(o) {
  const roas = parseFloat(o.roas || 0).toFixed(2);
  const roasClass = o.roas >= 1.5 ? 'text-success' : o.roas >= 0.8 ? 'text-warning' : 'text-error';

  document.getElementById('overview').innerHTML = `
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
      ${stat('Campanhas', o.campaigns ?? 0, '')}
      ${stat('Gasto Meta', '$' + fmt(o.spend_usd, 2), '')}
      ${stat('Receita AV', '$' + fmt(o.av_revenue_usd, 2), '')}
      ${stat('ROAS', roas, roasClass)}
      ${stat('Impressões', fmtNum(o.impressions), '')}
      ${stat('Cliques', fmtNum(o.clicks), '')}
      ${stat('Sessões AV', fmtNum(o.av_sessions), '')}
    </div>`;
}

function stat(label, value, cls) {
  return `<div class="stat bg-base-100 rounded-xl shadow-sm">
    <div class="stat-title text-xs">${label}</div>
    <div class="stat-value text-2xl ${cls}">${value}</div>
  </div>`;
}

function renderTopTable(rows) {
  if (!rows.length) {
    document.getElementById('topTable').innerHTML = '<p class="opacity-60 text-sm">Sem dados.</p>';
    return;
  }
  const trs = rows.map(r => {
    const roas = parseFloat(r.roas || 0);
    const badge = roas >= 1.5 ? 'badge-success' : roas >= 0.8 ? 'badge-warning' : 'badge-error';
    return `<tr>
      <td class="text-sm">${esc(r.campaign_name)}</td>
      <td class="text-xs opacity-60">${esc(r.account_key)}</td>
      <td>$${fmt(r.spend_usd)}</td>
      <td>$${fmt(r.av_revenue_usd)}</td>
      <td><span class="badge ${badge}">${roas.toFixed(2)}</span></td>
      <td>${fmtNum(r.impressions)}</td>
      <td>${fmtNum(r.av_sessions)}</td>
    </tr>`;
  }).join('');

  document.getElementById('topTable').innerHTML = `
    <div class="overflow-x-auto">
      <table class="table table-sm">
        <thead><tr>
          <th>Campanha</th><th>Conta</th><th>Gasto</th><th>Receita AV</th><th>ROAS</th><th>Impressões</th><th>Sessões AV</th>
        </tr></thead>
        <tbody>${trs}</tbody>
      </table>
    </div>`;
}

function fmt(v, d=2) { return parseFloat(v||0).toFixed(d).replace(/\B(?=(\d{3})+(?!\d))/g,','); }
function fmtNum(v) { return parseInt(v||0).toLocaleString('pt-BR'); }
function esc(s) { const d=document.createElement('div'); d.textContent=s; return d.innerHTML; }
</script>
JS;

ob_start();
?>
<div class="space-y-6">
  <div class="flex flex-wrap items-center gap-3">
    <select id="days" onchange="loadDashboard()" class="select select-bordered select-sm">
      <option value="7">Últimos 7 dias</option>
      <option value="30" selected>Últimos 30 dias</option>
      <option value="60">Últimos 60 dias</option>
    </select>
    <input id="accountFilter" type="text" placeholder="Filtrar por conta (account_key)"
           class="input input-bordered input-sm w-64" onchange="loadDashboard()">
    <button onclick="loadDashboard()" class="btn btn-sm btn-primary">Atualizar</button>
  </div>

  <div id="overview" class="flex items-center gap-3">
    <span class="loading loading-spinner loading-sm"></span>
    <span class="text-sm opacity-60">Carregando métricas...</span>
  </div>

  <div class="card bg-base-100 shadow-sm">
    <div class="card-body">
      <h2 class="card-title text-base">Top Campanhas por ROAS</h2>
      <div id="topTable">
        <span class="loading loading-spinner loading-sm"></span>
      </div>
    </div>
  </div>
</div>
<?php
$pageContent = ob_get_clean();
require __DIR__ . '/layout.php';

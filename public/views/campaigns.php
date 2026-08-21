<?php
$pageTitle = 'Campanhas';

$defaultEnd   = date('Y-m-d', strtotime('-1 day'));
$defaultStart = date('Y-m-d', strtotime('-7 days'));

ob_start();
?>
<style>
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500;600;700&display=swap');

.rp-root{
  --void:#0a0e13; --panel:#121922; --panel-2:#1a2229; --raised:#1f2932;
  --hair:#242f3a; --hair-soft:#1a232c;
  --text:#e7edf3; --dim:#8291a3; --dim-2:#54626f;
  --cyan:#4dd8c4; --cyan-dim:rgba(77,216,196,.14);
  --amber:#ffb454; --amber-dim:rgba(255,180,84,.14);
  --red:#ff6b6b; --red-dim:rgba(255,107,107,.12);
  --violet:#b98eff; --violet-dim:rgba(185,142,255,.14);
  position:relative; isolation:isolate; box-sizing:border-box;
  background:
    radial-gradient(ellipse 900px 500px at 12% -10%, rgba(77,216,196,.08), transparent 60%),
    radial-gradient(ellipse 700px 400px at 100% 0%, rgba(255,180,84,.05), transparent 60%),
    var(--void);
  border:1px solid var(--hair); border-radius:20px;
  color:var(--text); font-family:'Inter',sans-serif;
  padding:22px; min-height:calc(100vh - 5rem);
}
.rp-root *{ box-sizing:border-box; }
.rp-root::before{
  content:''; position:absolute; inset:0; pointer-events:none; z-index:0; border-radius:20px;
  background-image:radial-gradient(rgba(255,255,255,.035) 1px, transparent 1px);
  background-size:22px 22px;
  mask-image:radial-gradient(ellipse 80% 45% at 50% 0%, black, transparent 75%);
  -webkit-mask-image:radial-gradient(ellipse 80% 45% at 50% 0%, black, transparent 75%);
}
.rp-root > *{ position:relative; z-index:1; }
.rp-mono{ font-family:'JetBrains Mono',monospace; }

.rp-eyebrow{ font-family:'JetBrains Mono',monospace; font-size:10.5px; font-weight:600; letter-spacing:.09em; text-transform:uppercase; color:var(--dim); display:flex; align-items:center; gap:6px; }
.rp-eyebrow::before{ content:''; width:5px; height:5px; border-radius:1px; background:var(--cyan); box-shadow:0 0 6px var(--cyan); flex-shrink:0; }
.rp-title{ font-size:22px; font-weight:800; letter-spacing:-.01em; margin-top:2px; }

.rp-header{ display:flex; flex-wrap:wrap; justify-content:space-between; align-items:flex-end; gap:14px; padding-bottom:16px; margin-bottom:18px; border-bottom:1px solid var(--hair); }

.rp-toolbar{ display:flex; flex-wrap:wrap; gap:10px; align-items:flex-end; margin-bottom:16px; }
.rp-field{ display:flex; flex-direction:column; gap:4px; }
.rp-field-label{ font-family:'JetBrains Mono',monospace; font-size:9.5px; font-weight:600; letter-spacing:.06em; text-transform:uppercase; color:var(--dim-2); }

.rp-select,.rp-input{
  background:var(--panel-2); border:1px solid var(--hair); color:var(--text);
  border-radius:8px; padding:7px 10px; font-size:12.5px;
  transition:border-color .15s, box-shadow .15s;
}
.rp-select{ cursor:pointer; appearance:none; min-width:150px;
  background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%238291a3' stroke-width='2'%3E%3Cpath d='M6 9l6 6 6-6'/%3E%3C/svg%3E");
  background-repeat:no-repeat; background-position:right 8px center; background-size:14px; padding-right:28px;
}
.rp-select:focus,.rp-input:focus{ outline:none; border-color:var(--cyan); box-shadow:0 0 0 3px var(--cyan-dim); }
.rp-input::placeholder{ color:var(--dim-2); }

.rp-btn{
  display:inline-flex; align-items:center; gap:6px; font-size:12.5px; font-weight:600;
  border-radius:8px; padding:7px 14px; border:1px solid var(--cyan); background:var(--cyan); color:#04211d;
  cursor:pointer; transition:all .15s;
}
.rp-btn:hover{ box-shadow:0 0 18px rgba(77,216,196,.45); filter:brightness(1.05); }
.rp-count{ font-family:'JetBrains Mono',monospace; font-size:11px; color:var(--dim-2); align-self:center; }

.rp-panel{ background:var(--panel); border:1px solid var(--hair); border-radius:14px; padding:16px; animation:rpRise .45s cubic-bezier(.16,1,.3,1) both; }
@keyframes rpRise{ from{ opacity:0; transform:translateY(8px);} to{ opacity:1; transform:translateY(0);} }

.rp-table-wrap{ overflow-x:auto; }
.rp-table{ width:100%; border-collapse:collapse; font-size:12.5px; }
.rp-table th{ text-align:left; font-family:'JetBrains Mono',monospace; font-size:9.5px; letter-spacing:.06em; text-transform:uppercase; color:var(--dim-2); padding:0 10px 10px; font-weight:600; white-space:nowrap; position:sticky; top:0; background:var(--panel); }
.rp-table td{ padding:9px 10px; border-top:1px solid var(--hair-soft); vertical-align:middle; white-space:nowrap; }
.rp-table tr:hover td{ background:var(--panel-2); }
.rp-table td.num,.rp-table th.num{ text-align:right; font-family:'JetBrains Mono',monospace; }
.rp-table td.name{ white-space:normal; max-width:280px; }
.rp-table td.date{ font-family:'JetBrains Mono',monospace; color:var(--dim); font-size:11.5px; }

.rp-badge{ font-family:'JetBrains Mono',monospace; font-size:11.5px; font-weight:700; padding:3px 9px; border-radius:5px; border:1px solid; display:inline-block; }
.rp-badge-good{ color:var(--cyan); border-color:rgba(77,216,196,.4); background:var(--cyan-dim); }
.rp-badge-mid{ color:var(--amber); border-color:rgba(255,180,84,.4); background:var(--amber-dim); }
.rp-badge-bad{ color:var(--red); border-color:rgba(255,107,107,.4); background:var(--red-dim); }

.rp-empty{ text-align:center; padding:30px; color:var(--dim); font-size:12.5px; }
.rp-spinner{ width:13px; height:13px; border-radius:50%; border:2px solid var(--hair); border-top-color:var(--cyan); animation:rpSpin .6s linear infinite; display:inline-block; }
@keyframes rpSpin{ to{ transform:rotate(360deg); } }

.rp-root ::-webkit-scrollbar{ width:8px; height:8px; }
.rp-root ::-webkit-scrollbar-thumb{ background:var(--hair); border-radius:8px; }
.rp-root ::-webkit-scrollbar-track{ background:transparent; }

@media (prefers-reduced-motion: reduce){ .rp-panel, .rp-spinner{ animation:none !important; } }
</style>

<div class="rp-root">
  <div class="rp-header">
    <div>
      <div class="rp-eyebrow">Meta Ads × ActiveView</div>
      <div class="rp-title">Campanhas</div>
    </div>
  </div>

  <div class="rp-toolbar">
    <div class="rp-field">
      <label class="rp-field-label">Conta</label>
      <select id="accountFilter" class="rp-select" onchange="loadCampaigns()">
        <option value="">Todas as contas</option>
      </select>
    </div>
    <div class="rp-field">
      <label class="rp-field-label">Campanha</label>
      <input id="nameFilter" type="text" placeholder="Buscar por nome..." class="rp-input" style="width:180px">
    </div>
    <div class="rp-field">
      <label class="rp-field-label">De</label>
      <input id="startDate" type="date" value="<?= $defaultStart ?>" class="rp-input">
    </div>
    <div class="rp-field">
      <label class="rp-field-label">Até</label>
      <input id="endDate" type="date" value="<?= $defaultEnd ?>" class="rp-input">
    </div>
    <button onclick="loadCampaigns()" class="rp-btn">
      <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5"><circle cx="11" cy="11" r="7"/><path stroke-linecap="round" d="M21 21l-4.35-4.35"/></svg>
      Buscar
    </button>
    <span id="count" class="rp-count"></span>
  </div>

  <div class="rp-panel" style="margin-bottom:14px;">
    <div style="font-size:13.5px;font-weight:700;margin-bottom:12px;display:flex;align-items:center;gap:8px;">
      Custo × Receita
      <span class="rp-eyebrow" style="margin-left:auto">Linha = ROI diário</span>
    </div>
    <div id="chartWrap" style="position:relative; height:260px;">
      <div class="rp-empty"><span class="rp-spinner"></span></div>
    </div>
  </div>

  <div class="rp-panel">
    <div id="tbody-wrap" class="rp-table-wrap">
      <div class="rp-empty"><span class="rp-spinner"></span></div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', () => { loadAccounts(); loadCampaigns(); });

let chartInstance = null;
function renderChart(rows) {
  const wrap = document.getElementById('chartWrap');
  if (!rows.length) {
    if (chartInstance) { chartInstance.destroy(); chartInstance = null; }
    wrap.innerHTML = '<div class="rp-empty">Sem dados nesse período.</div>';
    return;
  }

  // Agrega as linhas (já filtradas por conta/campanha/data) por report_date.
  const byDate = new Map();
  for (const r of rows) {
    const d = r.report_date;
    if (!byDate.has(d)) byDate.set(d, { spend: 0, revenue: 0 });
    const acc = byDate.get(d);
    acc.spend   += parseFloat(r.spend_usd || 0);
    acc.revenue += parseFloat(r.av_revenue_usd || 0);
  }
  const dates = Array.from(byDate.keys()).sort();

  if (!wrap.querySelector('canvas')) {
    wrap.innerHTML = '<canvas id="dailyChart"></canvas>';
  }
  const ctx = document.getElementById('dailyChart');

  const labels  = dates.map(d => new Date(d + 'T00:00:00').toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit' }));
  const spend   = dates.map(d => byDate.get(d).spend);
  const revenue = dates.map(d => byDate.get(d).revenue);
  const roi     = dates.map(d => {
    const { spend: s, revenue: rv } = byDate.get(d);
    return s > 0 ? ((rv - s) / s) * 100 : 0;
  });

  const dim  = getComputedStyle(document.documentElement).getPropertyValue('--dim').trim() || '#8291a3';
  const hair = getComputedStyle(document.documentElement).getPropertyValue('--hair-soft').trim() || '#1a232c';

  if (chartInstance) chartInstance.destroy();
  chartInstance = new Chart(ctx, {
    data: {
      labels,
      datasets: [
        { type: 'bar',  label: 'Gasto',      data: spend,   backgroundColor: 'rgba(255,180,84,.55)', borderRadius: 3, yAxisID: 'y' },
        { type: 'bar',  label: 'Receita AV', data: revenue, backgroundColor: 'rgba(77,216,196,.55)', borderRadius: 3, yAxisID: 'y' },
        { type: 'line', label: 'ROI', data: roi, borderColor: '#b98eff', backgroundColor: '#b98eff',
          borderWidth: 2, pointRadius: 2, tension: .3, yAxisID: 'y1' },
      ],
    },
    options: {
      responsive: true, maintainAspectRatio: false,
      interaction: { mode: 'index', intersect: false },
      plugins: {
        legend: { labels: { color: dim, font: { family: 'Inter', size: 11 } } },
        tooltip: {
          callbacks: {
            label: (item) => item.dataset.yAxisID === 'y1'
              ? `ROI: ${item.parsed.y.toFixed(1)}%`
              : `${item.dataset.label}: $${item.parsed.y.toFixed(2)}`,
          },
        },
      },
      scales: {
        x:  { grid: { color: hair }, ticks: { color: dim, font: { family: 'JetBrains Mono', size: 10 } } },
        y:  { position: 'left',  grid: { color: hair }, ticks: { color: dim, font: { family: 'JetBrains Mono', size: 10 }, callback: (v) => '$' + v } },
        y1: { position: 'right', grid: { display: false }, ticks: { color: dim, font: { family: 'JetBrains Mono', size: 10 }, callback: (v) => v + '%' } },
      },
    },
  });
}

async function loadAccounts() {
  const sel = document.getElementById('accountFilter');
  try {
    const data = await apiFetch('/api/accounts/list');
    const accounts = data.data || [];
    sel.innerHTML = '<option value="">Todas as contas</option>' +
      accounts.map(a => `<option value="${esc(a.account_key)}">${esc(a.label)}</option>`).join('');
  } catch (e) {
    sel.innerHTML = '<option value="">Erro ao carregar contas</option>';
  }
}

function roasTier(roas) {
  // roas = (receita - custo) / custo — 0 é o ponto de equilíbrio.
  const r = parseFloat(roas || 0);
  if (r >= 0.5) return 'good';
  if (r >= 0) return 'mid';
  return 'bad';
}
function fmtRoasPct(roas) { return (parseFloat(roas || 0) * 100).toFixed(1) + '%'; }

async function loadCampaigns() {
  const params = new URLSearchParams({
    account_key:   document.getElementById('accountFilter').value,
    campaign_name: document.getElementById('nameFilter').value,
    start_date:    document.getElementById('startDate').value,
    end_date:      document.getElementById('endDate').value,
  });

  const wrap = document.getElementById('tbody-wrap');
  wrap.innerHTML = '<div class="rp-empty"><span class="rp-spinner"></span></div>';

  try {
    const data = await apiFetch('/api/campaigns?' + params);
    const rows = data.data || [];
    renderChart(rows);
    if (!rows.length) {
      wrap.innerHTML = '<div class="rp-empty">Sem resultados para esse filtro.</div>';
      document.getElementById('count').textContent = '';
      return;
    }
    const trs = rows.map(r => {
      const tier = roasTier(r.roas);
      return `<tr>
        <td class="date">${esc(r.report_date)}</td>
        <td class="name" title="${esc(r.campaign_name)}">${esc(r.campaign_name)}</td>
        <td class="rp-mono" style="color:var(--dim);font-size:11px">${esc(r.account_key)}</td>
        <td class="num">$${fmt(r.spend_usd)}</td>
        <td class="num">$${fmt(r.av_revenue_usd)}</td>
        <td class="num"><span class="rp-badge rp-badge-${tier}">${fmtRoasPct(r.roas)}</span></td>
        <td class="num">${fmtNum(r.impressions)}</td>
        <td class="num">${fmt(r.ctr, 3)}%</td>
        <td class="num">${fmtNum(r.av_sessions)}</td>
      </tr>`;
    }).join('');

    wrap.innerHTML = `
      <table class="rp-table">
        <thead><tr>
          <th>Data</th><th>Campanha</th><th>Conta</th><th class="num">Gasto</th>
          <th class="num">Receita AV</th><th class="num">ROAS</th><th class="num">Impressões</th>
          <th class="num">CTR</th><th class="num">Sessões AV</th>
        </tr></thead>
        <tbody>${trs}</tbody>
      </table>`;
    document.getElementById('count').textContent = rows.length + ' registros';
  } catch (e) {
    wrap.innerHTML = `<div class="rp-empty">${esc(e.message)}</div>`;
  }
}

function fmt(v, d=2) { return parseFloat(v||0).toFixed(d).replace(/\B(?=(\d{3})+(?!\d))/g,','); }
function fmtNum(v) { return parseInt(v||0).toLocaleString('pt-BR'); }
function esc(s) { const d=document.createElement('div'); d.textContent=s??''; return d.innerHTML; }
</script>
<?php
$pageContent = ob_get_clean();
require __DIR__ . '/layout.php';

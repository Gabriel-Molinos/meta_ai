<?php
$pageTitle = 'Dashboard';
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
.rp-period{ font-family:'JetBrains Mono',monospace; font-size:11px; color:var(--dim-2); margin-top:4px; }

.rp-header{ display:flex; flex-wrap:wrap; justify-content:space-between; align-items:flex-end; gap:14px; padding-bottom:16px; margin-bottom:18px; border-bottom:1px solid var(--hair); }
.rp-filters{ display:flex; flex-wrap:wrap; gap:8px; align-items:flex-end; }
.rp-field{ display:flex; flex-direction:column; gap:4px; }
.rp-field-label{ font-family:'JetBrains Mono',monospace; font-size:9.5px; font-weight:600; letter-spacing:.06em; text-transform:uppercase; color:var(--dim-2); }

.rp-select{
  background:var(--panel-2); border:1px solid var(--hair); color:var(--text);
  border-radius:8px; padding:7px 10px; font-size:12.5px; cursor:pointer; min-width:150px;
  transition:border-color .15s, box-shadow .15s; appearance:none;
  background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%238291a3' stroke-width='2'%3E%3Cpath d='M6 9l6 6 6-6'/%3E%3C/svg%3E");
  background-repeat:no-repeat; background-position:right 8px center; background-size:14px; padding-right:28px;
}
.rp-select:focus{ outline:none; border-color:var(--cyan); box-shadow:0 0 0 3px var(--cyan-dim); }

.rp-btn{
  display:inline-flex; align-items:center; gap:6px; font-size:12.5px; font-weight:600;
  border-radius:8px; padding:7px 14px; border:1px solid var(--cyan); background:var(--cyan); color:#04211d;
  cursor:pointer; transition:all .15s;
}
.rp-btn:hover{ box-shadow:0 0 18px rgba(77,216,196,.45); filter:brightness(1.05); }
.rp-btn svg{ width:13px; height:13px; }

.rp-hero{
  display:flex; flex-wrap:wrap; align-items:center; justify-content:space-between; gap:16px;
  padding:24px 28px; border-radius:16px; background:var(--panel); border:1px solid var(--hair);
  margin-bottom:14px; position:relative; overflow:hidden;
  animation:rpRise .5s cubic-bezier(.16,1,.3,1) both;
}
.rp-hero::after{ content:''; position:absolute; inset:0; opacity:.6; pointer-events:none;
  background:radial-gradient(ellipse 420px 220px at 92% 0%, var(--glow, rgba(77,216,196,.18)), transparent 70%); }
.rp-hero-label{ font-family:'JetBrains Mono',monospace; font-size:11px; letter-spacing:.08em; text-transform:uppercase; color:var(--dim); }
.rp-hero-value{ font-family:'JetBrains Mono',monospace; font-size:58px; font-weight:700; line-height:1; letter-spacing:-.02em; margin-top:6px; }
.rp-hero-value.is-good{ color:var(--cyan); text-shadow:0 0 30px rgba(77,216,196,.35); }
.rp-hero-value.is-mid{ color:var(--amber); text-shadow:0 0 30px rgba(255,180,84,.3); }
.rp-hero-value.is-bad{ color:var(--red); text-shadow:0 0 30px rgba(255,107,107,.3); }
.rp-hero-sub{ font-size:12px; color:var(--dim); margin-top:8px; max-width:260px; }
.rp-hero-fig{ display:flex; gap:22px; }
.rp-hero-fig-item{ text-align:right; }
.rp-hero-fig-label{ font-family:'JetBrains Mono',monospace; font-size:9.5px; letter-spacing:.06em; text-transform:uppercase; color:var(--dim-2); }
.rp-hero-fig-value{ font-family:'JetBrains Mono',monospace; font-size:19px; font-weight:700; margin-top:4px; }

.rp-stats-grid{ display:grid; grid-template-columns:repeat(auto-fit, minmax(140px,1fr)); gap:10px; margin-bottom:20px; }
.rp-stat{ background:var(--panel); border:1px solid var(--hair); border-radius:12px; padding:14px; animation:rpRise .5s cubic-bezier(.16,1,.3,1) both; }
.rp-stats-grid .rp-stat:nth-child(1){ animation-delay:.02s; }
.rp-stats-grid .rp-stat:nth-child(2){ animation-delay:.06s; }
.rp-stats-grid .rp-stat:nth-child(3){ animation-delay:.10s; }
.rp-stats-grid .rp-stat:nth-child(4){ animation-delay:.14s; }
.rp-stat-label{ font-family:'JetBrains Mono',monospace; font-size:9.5px; letter-spacing:.06em; text-transform:uppercase; color:var(--dim-2); margin-bottom:6px; }
.rp-stat-value{ font-family:'JetBrains Mono',monospace; font-size:20px; font-weight:700; color:var(--text); }

@keyframes rpRise{ from{ opacity:0; transform:translateY(8px);} to{ opacity:1; transform:translateY(0);} }

.rp-panel{ background:var(--panel); border:1px solid var(--hair); border-radius:14px; padding:16px; }
.rp-panel-title{ font-size:13.5px; font-weight:700; margin-bottom:12px; display:flex; align-items:center; gap:8px; }
.rp-panel-title .rp-eyebrow{ margin-left:auto; }

.rp-table-wrap{ overflow-x:auto; }
.rp-table{ width:100%; border-collapse:collapse; font-size:12.5px; }
.rp-table th{ text-align:left; font-family:'JetBrains Mono',monospace; font-size:9.5px; letter-spacing:.06em; text-transform:uppercase; color:var(--dim-2); padding:0 10px 10px; font-weight:600; white-space:nowrap; }
.rp-table td{ padding:10px; border-top:1px solid var(--hair-soft); vertical-align:middle; white-space:nowrap; }
.rp-table tr:hover td{ background:var(--panel-2); }
.rp-table td.num,.rp-table th.num{ text-align:right; font-family:'JetBrains Mono',monospace; }
.rp-table td.name{ white-space:normal; max-width:280px; }

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

@media (prefers-reduced-motion: reduce){ .rp-hero, .rp-stat, .rp-spinner{ animation:none !important; } }
</style>

<div class="rp-root">
  <div class="rp-header">
    <div>
      <div class="rp-eyebrow">Performance · Meta Ads × ActiveView</div>
      <div class="rp-title">Dashboard</div>
      <div class="rp-period" id="periodLabel">carregando período...</div>
    </div>
    <div class="rp-filters">
      <div class="rp-field">
        <label class="rp-field-label">Conta</label>
        <select id="accountFilter" class="rp-select" onchange="loadDashboard()">
          <option value="">Todas as contas</option>
        </select>
      </div>
      <div class="rp-field">
        <label class="rp-field-label">Período</label>
        <select id="days" class="rp-select" onchange="loadDashboard()">
          <option value="7" selected>Últimos 7 dias</option>
          <option value="14">Últimos 14 dias</option>
          <option value="30">Últimos 30 dias</option>
          <option value="60">Últimos 60 dias</option>
        </select>
      </div>
      <button onclick="loadDashboard()" class="rp-btn">
        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
        Atualizar
      </button>
    </div>
  </div>

  <div id="overview">
    <div class="rp-hero"><span class="rp-spinner"></span></div>
  </div>

  <div class="rp-panel">
    <div class="rp-panel-title">
      Top campanhas por ROAS
      <span class="rp-eyebrow">Ordenado por retorno</span>
    </div>
    <div id="topTable" class="rp-table-wrap">
      <div class="rp-empty"><span class="rp-spinner"></span></div>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => { loadAccounts(); loadDashboard(); });

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

function updatePeriodLabel(days) {
  const end = new Date(); end.setDate(end.getDate() - 1);
  const start = new Date(); start.setDate(start.getDate() - Number(days));
  const f = d => d.toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit' });
  document.getElementById('periodLabel').textContent =
    `${f(start)} — ${f(end)} · não inclui hoje`;
}

async function loadDashboard() {
  const days = document.getElementById('days').value;
  const accountKey = document.getElementById('accountFilter').value;
  updatePeriodLabel(days);

  try {
    const params = new URLSearchParams({ days });
    if (accountKey) params.set('account_key', accountKey);

    const data = await apiFetch('/api/dashboard?' + params);
    renderOverview(data.overview || {});
    renderTopTable(data.top_roas || []);
  } catch (e) {
    document.getElementById('overview').innerHTML = `<div class="rp-empty">${esc(e.message)}</div>`;
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

function renderOverview(o) {
  const tier = roasTier(o.roas);
  const glow = { good: 'rgba(77,216,196,.18)', mid: 'rgba(255,180,84,.16)', bad: 'rgba(255,107,107,.16)' }[tier];

  document.getElementById('overview').innerHTML = `
    <div class="rp-hero" style="--glow:${glow}">
      <div>
        <div class="rp-hero-label">ROAS médio do período</div>
        <div class="rp-hero-value is-${tier}">${fmtRoasPct(o.roas)}</div>
        <div class="rp-hero-sub">Retorno da receita ActiveView sobre o investimento em Meta Ads.</div>
      </div>
      <div class="rp-hero-fig">
        ${heroFig('Gasto Meta', '$' + fmt(o.spend_usd))}
        ${heroFig('Receita AV', '$' + fmt(o.av_revenue_usd))}
      </div>
    </div>
    <div class="rp-stats-grid">
      ${stat('Campanhas', o.campaigns ?? 0)}
      ${stat('Impressões', fmtNum(o.impressions))}
      ${stat('Cliques', fmtNum(o.clicks))}
      ${stat('Sessões AV', fmtNum(o.av_sessions))}
    </div>`;
}

function heroFig(label, value) {
  return `<div class="rp-hero-fig-item">
    <div class="rp-hero-fig-label">${label}</div>
    <div class="rp-hero-fig-value">${value}</div>
  </div>`;
}
function stat(label, value) {
  return `<div class="rp-stat">
    <div class="rp-stat-label">${label}</div>
    <div class="rp-stat-value">${value}</div>
  </div>`;
}

function renderTopTable(rows) {
  if (!rows.length) {
    document.getElementById('topTable').innerHTML = '<div class="rp-empty">Sem dados nesse período.</div>';
    return;
  }
  const trs = rows.map(r => {
    const tier = roasTier(r.roas);
    return `<tr>
      <td class="name">${esc(r.campaign_name)}</td>
      <td class="rp-mono" style="color:var(--dim);font-size:11px">${esc(r.account_key)}</td>
      <td class="num">$${fmt(r.spend_usd)}</td>
      <td class="num">$${fmt(r.av_revenue_usd)}</td>
      <td class="num"><span class="rp-badge rp-badge-${tier}">${fmtRoasPct(r.roas)}</span></td>
      <td class="num">${fmtNum(r.impressions)}</td>
      <td class="num">${fmtNum(r.av_sessions)}</td>
    </tr>`;
  }).join('');

  document.getElementById('topTable').innerHTML = `
    <table class="rp-table">
      <thead><tr>
        <th>Campanha</th><th>Conta</th><th class="num">Gasto</th><th class="num">Receita AV</th>
        <th class="num">ROAS</th><th class="num">Impressões</th><th class="num">Sessões AV</th>
      </tr></thead>
      <tbody>${trs}</tbody>
    </table>`;
}

function fmt(v, d=2) { return parseFloat(v||0).toFixed(d).replace(/\B(?=(\d{3})+(?!\d))/g,','); }
function fmtNum(v) { return parseInt(v||0).toLocaleString('pt-BR'); }
function esc(s) { const d=document.createElement('div'); d.textContent=s??''; return d.innerHTML; }
</script>
<?php
$pageContent = ob_get_clean();
require __DIR__ . '/layout.php';

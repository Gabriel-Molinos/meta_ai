<?php
$pageTitle   = 'Análise IA';
$pageScripts = <<<'JS'
<script>
async function runAnalysis() {
  const btn = document.getElementById('analyzeBtn');
  const result = document.getElementById('result');
  btn.disabled = true;
  btn.innerHTML = '<span class="loading loading-spinner loading-xs"></span> Analisando...';
  result.innerHTML = '<div class="flex items-center gap-2 opacity-60"><span class="loading loading-dots loading-sm"></span> Gemini processando...</div>';

  try {
    const body = {
      days:        parseInt(document.getElementById('days').value),
      account_key: document.getElementById('accountFilter').value.trim(),
    };
    const data = await apiFetch('/api/ai/analyze', { method: 'POST', body: JSON.stringify(body) });
    result.innerHTML = '<div class="prose max-w-none">' + marked.parse(data.analysis || 'Sem resposta.') + '</div>';
  } catch(e) {
    result.innerHTML = `<div class="alert alert-error">${e.message}</div>`;
  } finally {
    btn.disabled = false;
    btn.textContent = 'Analisar Campanhas';
  }
}

async function runTrend() {
  const btn = document.getElementById('trendBtn');
  const result = document.getElementById('trendResult');
  btn.disabled = true;
  btn.innerHTML = '<span class="loading loading-spinner loading-xs"></span> Analisando...';
  result.innerHTML = '<span class="loading loading-dots loading-sm"></span>';

  try {
    const body = {
      days:        parseInt(document.getElementById('days').value),
      account_key: document.getElementById('accountFilter').value.trim(),
    };
    const data = await apiFetch('/api/ai/trend', { method: 'POST', body: JSON.stringify(body) });
    const verdicts = data.verdicts || [];

    if (!verdicts.length) {
      result.innerHTML = '<p class="opacity-60 text-sm">Sem dados para análise.</p>';
      return;
    }

    const colors = { SCALE: 'badge-success', MAINTAIN: 'badge-warning', PAUSE: 'badge-error', INVESTIGATE: 'badge-info' };
    result.innerHTML = verdicts.map(v => `
      <div class="border border-base-300 rounded-lg p-3">
        <div class="flex items-center gap-2 mb-1">
          <span class="badge badge-sm ${colors[v.verdict] || 'badge-ghost'}">${v.verdict}</span>
          <span class="text-sm font-medium">${esc(v.campaign_id)}</span>
        </div>
        <p class="text-xs opacity-70">${esc(v.reasoning)}</p>
        <p class="text-xs mt-1 font-medium">→ ${esc(v.action)}</p>
      </div>`).join('');
  } catch(e) {
    result.innerHTML = `<div class="alert alert-error">${e.message}</div>`;
  } finally {
    btn.disabled = false;
    btn.textContent = 'Analisar Tendências (JSON)';
  }
}

function esc(s) { const d=document.createElement('div'); d.textContent=String(s||''); return d.innerHTML; }
</script>
JS;

ob_start();
?>
<div class="space-y-6 max-w-4xl">
  <div class="card bg-base-100 shadow-sm">
    <div class="card-body py-3">
      <div class="flex flex-wrap gap-2 items-end">
        <div>
          <label class="label label-text text-xs pb-1">Período</label>
          <select id="days" class="select select-bordered select-sm">
            <option value="7">Últimos 7 dias</option>
            <option value="30" selected>Últimos 30 dias</option>
            <option value="60">Últimos 60 dias</option>
          </select>
        </div>
        <div>
          <label class="label label-text text-xs pb-1">Conta (opcional)</label>
          <input id="accountFilter" type="text" placeholder="account_key" class="input input-sm input-bordered w-40">
        </div>
        <button id="analyzeBtn" onclick="runAnalysis()" class="btn btn-sm btn-primary">Analisar Campanhas</button>
        <button id="trendBtn" onclick="runTrend()" class="btn btn-sm btn-secondary">Analisar Tendências (JSON)</button>
      </div>
    </div>
  </div>

  <div class="card bg-base-100 shadow-sm">
    <div class="card-body">
      <h3 class="font-semibold text-sm mb-3 opacity-70">Análise Narrativa — Gemini 2.5 Flash</h3>
      <div id="result" class="text-sm text-base-content/70">
        Clique em <strong>Analisar Campanhas</strong> para gerar a análise.
      </div>
    </div>
  </div>

  <div class="card bg-base-100 shadow-sm">
    <div class="card-body">
      <h3 class="font-semibold text-sm mb-3 opacity-70">Vereditos por Campanha</h3>
      <div id="trendResult" class="space-y-2 text-sm">
        Clique em <strong>Analisar Tendências</strong> para ver os vereditos por campanha.
      </div>
    </div>
  </div>
</div>
<?php
$pageContent = ob_get_clean();
require __DIR__ . '/../layout.php';

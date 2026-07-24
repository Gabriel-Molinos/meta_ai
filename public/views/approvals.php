<?php
$pageTitle = 'Aprovações de Campanhas';

$pageScripts = <<<'SCRIPTS'
<script>
const REJECTED_FIELD_LABELS = {
  campaign_name:   'Nome da campanha',
  objective:       'Objetivo',
  budget:          'Orçamento',
  targeting:       'Segmentação (países, idades, idiomas)',
  placements:      'Posicionamentos',
  pixel:           'Pixel / Conversão',
  creative:        'Criativo (imagem/vídeo)',
  ad_copy:         'Textos do anúncio',
  destination_url: 'URL de destino',
  schedule:        'Data de início',
};

const OBJECTIVE_LABELS = {
  OUTCOME_SALES:         'Vendas',
  OUTCOME_LEADS:         'Geração de Cadastros',
  OUTCOME_TRAFFIC:       'Tráfego',
  OUTCOME_AWARENESS:     'Reconhecimento',
  OUTCOME_ENGAGEMENT:    'Engajamento',
  OUTCOME_APP_PROMOTION: 'Promoção de App',
};

let allDrafts = [];
let currentTab = 'pending';
let reviewDraft = null;

function esc(s){ const d=document.createElement('div');d.textContent=String(s||'');return d.innerHTML; }
function apiKey(){
  return decodeURIComponent((document.cookie.match(/_auth=([^;]+)/)||[])[1]||'') || sessionStorage.getItem('apiKey') || '';
}
async function apiFetch(url, opts={}){
  const res = await fetch(url, {
    ...opts,
    headers: { 'Authorization': 'Bearer '+apiKey(), 'Content-Type': 'application/json', ...(opts.headers||{}) }
  });
  if(!res.ok) throw new Error(await res.text());
  return res.json();
}

async function loadDrafts(){
  try {
    const data = await apiFetch('/api/approvals');
    allDrafts = data.data || [];
    renderTab(currentTab);
  } catch(e) {
    document.getElementById('tableBody').innerHTML = '<tr><td colspan="6" class="text-center text-error py-8">Erro ao carregar: '+esc(e.message)+'</td></tr>';
  }
}

function renderTab(tab){
  currentTab = tab;
  document.querySelectorAll('.tab-btn').forEach(b => b.classList.toggle('tab-active', b.dataset.tab === tab));
  const filtered = allDrafts.filter(d => d.status === tab);
  const tbody = document.getElementById('tableBody');
  if(!filtered.length){
    tbody.innerHTML = '<tr><td colspan="6" class="text-center opacity-50 py-8">Nenhuma campanha '+tab+'</td></tr>';
    return;
  }
  tbody.innerHTML = filtered.map(d => `
    <tr>
      <td class="font-medium">${esc(d.campaign_name||'(sem nome)')}</td>
      <td class="text-sm opacity-70">${esc(d.user_email)}<br><span class="text-xs opacity-50">${esc(d.user_name||'')}</span></td>
      <td class="text-sm">${esc(d.account_key)}</td>
      <td class="text-xs">${esc(new Date(d.created_at).toLocaleDateString('pt-BR'))}</td>
      <td>${statusBadge(d.status)}</td>
      <td>
        <button onclick="openReview(${d.id})" class="btn btn-xs btn-primary">Revisar</button>
      </td>
    </tr>`).join('');
}

function statusBadge(s){
  const map = {pending:'badge-warning',approved:'badge-success',rejected:'badge-error'};
  const lbl = {pending:'Pendente',approved:'Aprovada',rejected:'Rejeitada'};
  return `<span class="badge ${map[s]||'badge-ghost'} badge-sm">${lbl[s]||s}</span>`;
}

async function openReview(id){
  document.getElementById('reviewPanel').classList.remove('hidden');
  document.getElementById('reviewContent').innerHTML = '<div class="flex justify-center py-12"><span class="loading loading-spinner loading-lg"></span></div>';
  try {
    const data = await apiFetch('/api/approvals/'+id);
    reviewDraft = data.data;
    renderReview(reviewDraft);
  } catch(e) {
    document.getElementById('reviewContent').innerHTML = '<div class="text-error">Erro: '+esc(e.message)+'</div>';
  }
}

function renderReview(d){
  const p = d.payload || {};
  const creatives = d.creatives || [];
  const ads = p.ads || [];

  let creativePreviews = '';
  creatives.forEach((c,i) => {
    const ad = ads[i] || {};
    if(!c.data_base64) return;
    const src = `data:${c.mime_type};base64,${c.data_base64}`;
    const media = c.media_type === 'video'
      ? `<video src="${src}" controls class="max-w-full max-h-48 rounded"></video>`
      : `<img src="${src}" class="max-w-full max-h-48 rounded object-cover">`;
    creativePreviews += `
      <div class="card bg-base-200 p-3 space-y-1">
        <div class="font-medium text-sm">${esc(ad.name||'Anúncio '+(i+1))}</div>
        ${media}
        ${ad.headline?`<div class="text-sm font-semibold">${esc(ad.headline)}</div>`:''}
        ${ad.primary_text?`<div class="text-xs opacity-70">${esc(ad.primary_text)}</div>`:''}
        ${ad.cta?`<span class="badge badge-ghost badge-xs">${esc(ad.cta)}</span>`:''}
      </div>`;
  });

  const countries = (p.countries||[]).join(', ');
  const platforms = (p.publisher_platforms||[]).join(', ');

  document.getElementById('reviewContent').innerHTML = `
    <div class="space-y-4">
      <div class="flex items-center justify-between">
        <div>
          <h3 class="font-bold text-lg">${esc(p.campaign_name||'')}</h3>
          <div class="text-sm opacity-60">${esc(d.user_email)} · ${esc(d.account_key)}</div>
        </div>
        ${statusBadge(d.status)}
      </div>

      ${d.status==='rejected'&&d.rejection_reason?`
      <div class="alert alert-error alert-sm">
        <div>
          <div class="font-semibold text-sm">Motivo anterior da rejeição:</div>
          <div class="text-sm">${esc(d.rejection_reason)}</div>
          ${(d.rejected_fields||[]).length?`<div class="text-xs mt-1">Campos: ${(d.rejected_fields||[]).map(f=>esc(REJECTED_FIELD_LABELS[f]||f)).join(', ')}</div>`:''}
        </div>
      </div>`:''}

      <div class="grid grid-cols-2 gap-3 text-sm">
        <div class="bg-base-200 p-3 rounded-lg">
          <div class="opacity-50 text-xs mb-1">Objetivo</div>
          <div>${esc(OBJECTIVE_LABELS[p.objective]||p.objective)}</div>
        </div>
        <div class="bg-base-200 p-3 rounded-lg">
          <div class="opacity-50 text-xs mb-1">Orçamento diário</div>
          <div>R$ ${esc(p.daily_budget||'')}</div>
        </div>
        <div class="bg-base-200 p-3 rounded-lg">
          <div class="opacity-50 text-xs mb-1">Países</div>
          <div>${esc(countries)}</div>
        </div>
        <div class="bg-base-200 p-3 rounded-lg">
          <div class="opacity-50 text-xs mb-1">Plataformas</div>
          <div>${esc(platforms)}</div>
        </div>
        <div class="bg-base-200 p-3 rounded-lg">
          <div class="opacity-50 text-xs mb-1">Idade</div>
          <div>${esc(p.age_min||18)} – ${esc(p.age_max||65)} anos</div>
        </div>
        <div class="bg-base-200 p-3 rounded-lg">
          <div class="opacity-50 text-xs mb-1">Início</div>
          <div>${esc(p.start_time||'')}</div>
        </div>
        <div class="bg-base-200 p-3 rounded-lg col-span-2">
          <div class="opacity-50 text-xs mb-1">URL de destino</div>
          <div class="truncate">${esc(p.destination_url||'')}</div>
        </div>
        <div class="bg-base-200 p-3 rounded-lg">
          <div class="opacity-50 text-xs mb-1">Pixel</div>
          <div>${esc(p.pixel_id||'—')}</div>
        </div>
        <div class="bg-base-200 p-3 rounded-lg">
          <div class="opacity-50 text-xs mb-1">Evento</div>
          <div>${esc(p.pixel_event||p.custom_conversion_id||'—')}</div>
        </div>
      </div>

      ${creativePreviews?`<div><div class="font-semibold text-sm mb-2">Criativos</div><div class="grid grid-cols-1 gap-3">${creativePreviews}</div></div>`:''}

      ${d.status==='pending'?`
      <div class="flex gap-2 pt-2">
        <button onclick="approveDraft(${d.id})" class="btn btn-success flex-1">✓ Aprovar</button>
        <button onclick="openRejectModal()" class="btn btn-error flex-1">✗ Rejeitar</button>
      </div>`:''}
      ${d.status==='approved'?`<div class="alert alert-success text-sm">Aprovada — Campaign ID: ${esc(d.meta_campaign_id||'')}</div>`:''}
    </div>`;
}

async function approveDraft(id){
  const btn = event.target;
  btn.disabled = true;
  btn.innerHTML = '<span class="loading loading-spinner loading-xs"></span> Aprovando...';
  try {
    await apiFetch('/api/approvals/'+id+'/approve', { method:'POST', body:'{}' });
    closeReview();
    await loadDrafts();
    alert('Campanha aprovada e criada no Meta com sucesso!');
  } catch(e) {
    alert('Erro ao aprovar: '+e.message);
    btn.disabled = false;
    btn.innerHTML = '✓ Aprovar';
  }
}

function openRejectModal(){
  document.getElementById('rejectModal').classList.remove('hidden');
}

function closeRejectModal(){
  document.getElementById('rejectModal').classList.add('hidden');
  document.getElementById('rejectReason').value = '';
  document.querySelectorAll('.chk-reject').forEach(c => c.checked = false);
}

async function confirmReject(){
  const reason = document.getElementById('rejectReason').value.trim();
  if(!reason){ alert('Informe o motivo da rejeição.'); return; }
  const fields = [...document.querySelectorAll('.chk-reject:checked')].map(c => c.value);
  const btn = document.getElementById('btnConfirmReject');
  btn.disabled = true;
  btn.innerHTML = '<span class="loading loading-spinner loading-xs"></span>';
  try {
    await apiFetch('/api/approvals/'+reviewDraft.id+'/reject', {
      method: 'POST',
      body: JSON.stringify({ reason, rejected_fields: fields })
    });
    closeRejectModal();
    closeReview();
    await loadDrafts();
  } catch(e) {
    alert('Erro: '+e.message);
    btn.disabled = false;
    btn.innerHTML = 'Confirmar Rejeição';
  }
}

function closeReview(){
  document.getElementById('reviewPanel').classList.add('hidden');
  reviewDraft = null;
}

document.addEventListener('DOMContentLoaded', loadDrafts);
</script>
SCRIPTS;

ob_start();
?>
<div class="space-y-4">
  <div class="flex items-center justify-between">
    <h2 class="text-xl font-bold">Aprovações de Campanhas</h2>
    <button onclick="loadDrafts()" class="btn btn-ghost btn-sm">↻ Atualizar</button>
  </div>

  <!-- Tabs -->
  <div class="tabs tabs-boxed">
    <button class="tab tab-btn tab-active" data-tab="pending"   onclick="renderTab('pending')">Pendentes</button>
    <button class="tab tab-btn"            data-tab="approved"  onclick="renderTab('approved')">Aprovadas</button>
    <button class="tab tab-btn"            data-tab="rejected"  onclick="renderTab('rejected')">Rejeitadas</button>
  </div>

  <!-- Tabela -->
  <div class="card bg-base-100 shadow-sm overflow-x-auto">
    <table class="table table-zebra">
      <thead>
        <tr>
          <th>Campanha</th>
          <th>Usuário</th>
          <th>Conta</th>
          <th>Data</th>
          <th>Status</th>
          <th>Ações</th>
        </tr>
      </thead>
      <tbody id="tableBody">
        <tr><td colspan="6" class="text-center py-8"><span class="loading loading-spinner"></span></td></tr>
      </tbody>
    </table>
  </div>
</div>

<!-- Painel de revisão (slide-over) -->
<div id="reviewPanel" class="hidden fixed inset-0 z-50 flex">
  <div class="flex-1 bg-black/40" onclick="closeReview()"></div>
  <div class="w-full max-w-xl bg-base-100 h-full overflow-y-auto shadow-2xl flex flex-col">
    <div class="flex items-center justify-between p-4 border-b border-base-300 sticky top-0 bg-base-100 z-10">
      <h3 class="font-bold text-lg">Detalhes da Campanha</h3>
      <button onclick="closeReview()" class="btn btn-ghost btn-sm btn-square">✕</button>
    </div>
    <div id="reviewContent" class="p-4 flex-1"></div>
  </div>
</div>

<!-- Modal de rejeição -->
<div id="rejectModal" class="hidden fixed inset-0 z-[60] flex items-center justify-center bg-black/50 p-4">
  <div class="card bg-base-100 w-full max-w-md shadow-xl">
    <div class="card-body space-y-4">
      <h3 class="card-title text-base">Rejeitar Campanha</h3>

      <div>
        <label class="label"><span class="label-text font-medium text-sm">Campos com problemas</span></label>
        <div class="grid grid-cols-2 gap-1">
          <?php
          $rejectFields = [
            'campaign_name'   => 'Nome da campanha',
            'objective'       => 'Objetivo',
            'budget'          => 'Orçamento',
            'targeting'       => 'Segmentação',
            'placements'      => 'Posicionamentos',
            'pixel'           => 'Pixel / Conversão',
            'creative'        => 'Criativo',
            'ad_copy'         => 'Textos do anúncio',
            'destination_url' => 'URL de destino',
            'schedule'        => 'Data de início',
          ];
          foreach ($rejectFields as $key => $label): ?>
          <label class="flex items-center gap-2 text-sm cursor-pointer">
            <input type="checkbox" class="checkbox checkbox-sm chk-reject" value="<?= htmlspecialchars($key) ?>">
            <?= htmlspecialchars($label) ?>
          </label>
          <?php endforeach; ?>
        </div>
      </div>

      <div>
        <label class="label"><span class="label-text font-medium text-sm">Motivo da rejeição <span class="text-error">*</span></span></label>
        <textarea id="rejectReason" rows="3" placeholder="Descreva o motivo..."
                  class="textarea textarea-bordered w-full text-sm"></textarea>
      </div>

      <div class="flex gap-2 justify-end">
        <button onclick="closeRejectModal()" class="btn btn-ghost btn-sm">Cancelar</button>
        <button id="btnConfirmReject" onclick="confirmReject()" class="btn btn-error btn-sm">Confirmar Rejeição</button>
      </div>
    </div>
  </div>
</div>
<?php
$pageContent = ob_get_clean();
require __DIR__ . '/layout.php';

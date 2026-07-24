<?php
$pageTitle = 'Minhas Campanhas';

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

let currentFeedback = null;

function esc(s){ const d=document.createElement('div');d.textContent=String(s||'');return d.innerHTML; }
function apiKey(){
  return decodeURIComponent((document.cookie.match(/_auth=([^;]+)/)||[])[1]||'') || sessionStorage.getItem('apiKey') || '';
}
async function apiFetch(url, opts={}){
  const res = await fetch(url, {
    ...opts,
    headers: { 'Authorization': 'Bearer '+apiKey(), ...(opts.headers||{}) }
  });
  if(!res.ok) throw new Error(await res.text());
  return res.json();
}

async function loadCampaigns(){
  const tbody = document.getElementById('tableBody');
  tbody.innerHTML = '<tr><td colspan="5" class="text-center py-8"><span class="loading loading-spinner"></span></td></tr>';
  try {
    const data = await apiFetch('/api/my-campaigns');
    const campaigns = data.data || [];
    if(!campaigns.length){
      tbody.innerHTML = '<tr><td colspan="5" class="text-center opacity-50 py-8">Nenhuma campanha encontrada. Crie sua primeira campanha!</td></tr>';
      return;
    }
    tbody.innerHTML = campaigns.map(c => `
      <tr>
        <td class="font-medium">${esc(c.campaign_name||'(sem nome)')}</td>
        <td class="text-sm">${esc(c.account_key)}</td>
        <td class="text-xs">${esc(new Date(c.created_at).toLocaleDateString('pt-BR'))}</td>
        <td>${statusBadge(c.status)}</td>
        <td>${actionBtn(c)}</td>
      </tr>`).join('');
  } catch(e) {
    tbody.innerHTML = '<tr><td colspan="5" class="text-center text-error py-8">Erro ao carregar: '+esc(e.message)+'</td></tr>';
  }
}

function statusBadge(s){
  const map = {pending:'badge-warning',approved:'badge-success',rejected:'badge-error'};
  const lbl = {pending:'Aguardando aprovação',approved:'Aprovada',rejected:'Rejeitada'};
  return `<span class="badge ${map[s]||'badge-ghost'} badge-sm">${lbl[s]||s}</span>`;
}

function actionBtn(c){
  if(c.status === 'approved' && c.meta_campaign_id){
    return `<a href="https://business.facebook.com/adsmanager/manage/campaigns?selected_campaign_ids=${esc(c.meta_campaign_id)}" target="_blank" class="btn btn-xs btn-success">Ver no Meta ↗</a>`;
  }
  if(c.status === 'rejected'){
    return `<button onclick="openFeedback(${JSON.stringify(c).replace(/"/g,'&quot;')})" class="btn btn-xs btn-warning">Ver Feedback</button>`;
  }
  return '<span class="text-xs opacity-40">—</span>';
}

function openFeedback(c){
  currentFeedback = c;
  const fields = c.rejected_fields || [];
  const fieldsList = fields.length
    ? fields.map(f => `<li class="text-error">• ${esc(REJECTED_FIELD_LABELS[f]||f)}</li>`).join('')
    : '<li class="opacity-50">Nenhum campo específico indicado</li>';

  document.getElementById('feedbackContent').innerHTML = `
    <div class="space-y-3">
      <div>
        <div class="font-semibold text-sm mb-1">Campos a corrigir:</div>
        <ul class="space-y-1 text-sm">${fieldsList}</ul>
      </div>
      ${c.rejection_reason ? `
      <div>
        <div class="font-semibold text-sm mb-1">Motivo:</div>
        <div class="text-sm bg-base-200 p-3 rounded-lg">${esc(c.rejection_reason)}</div>
      </div>` : ''}
    </div>`;
  document.getElementById('feedbackModal').classList.remove('hidden');
}

function closeFeedback(){
  document.getElementById('feedbackModal').classList.add('hidden');
  currentFeedback = null;
}

function editAndResubmit(){
  if(!currentFeedback) return;
  location.href = '/generator?draft_id=' + currentFeedback.id;
}

document.addEventListener('DOMContentLoaded', loadCampaigns);
</script>
SCRIPTS;

ob_start();
?>
<div class="space-y-4">
  <div class="flex items-center justify-between">
    <h2 class="text-xl font-bold">Minhas Campanhas</h2>
    <div class="flex gap-2">
      <button onclick="loadCampaigns()" class="btn btn-ghost btn-sm">↻ Atualizar</button>
      <a href="/generator" class="btn btn-primary btn-sm">+ Nova Campanha</a>
    </div>
  </div>

  <div class="card bg-base-100 shadow-sm overflow-x-auto">
    <table class="table table-zebra">
      <thead>
        <tr>
          <th>Campanha</th>
          <th>Conta</th>
          <th>Data</th>
          <th>Status</th>
          <th>Ações</th>
        </tr>
      </thead>
      <tbody id="tableBody">
        <tr><td colspan="5" class="text-center py-8"><span class="loading loading-spinner"></span></td></tr>
      </tbody>
    </table>
  </div>
</div>

<!-- Modal de feedback -->
<div id="feedbackModal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
  <div class="card bg-base-100 w-full max-w-md shadow-xl">
    <div class="card-body space-y-4">
      <h3 class="card-title text-base text-error">Campanha Rejeitada</h3>
      <div id="feedbackContent"></div>
      <div class="flex gap-2 justify-end">
        <button onclick="closeFeedback()" class="btn btn-ghost btn-sm">Fechar</button>
        <button onclick="editAndResubmit()" class="btn btn-warning btn-sm">Editar e Reenviar</button>
      </div>
    </div>
  </div>
</div>
<?php
$pageContent = ob_get_clean();
require __DIR__ . '/../layout.php';

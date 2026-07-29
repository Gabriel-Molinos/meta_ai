<?php
$pageTitle = 'Gerador de Campanhas';
$isAdmin = ($GLOBALS['_authType'] ?? 'admin') === 'admin';

$pageScripts = <<<'JS'
<script>
// ── Dados estáticos Meta ──────────────────────────────────────────────────────
const OBJECTIVES = [
  { value: 'OUTCOME_SALES',          label: 'Vendas',               icon: '🛒', hint: 'Direciona conversões e compras' },
  { value: 'OUTCOME_LEADS',          label: 'Geração de Cadastros', icon: '📋', hint: 'Captura dados de potenciais clientes' },
  { value: 'OUTCOME_TRAFFIC',        label: 'Tráfego',              icon: '🔗', hint: 'Envia pessoas ao seu site' },
  { value: 'OUTCOME_AWARENESS',      label: 'Reconhecimento',       icon: '👁',  hint: 'Aumenta visibilidade da marca' },
  { value: 'OUTCOME_ENGAGEMENT',     label: 'Engajamento',          icon: '💬', hint: 'Aumenta interações com o conteúdo' },
  { value: 'OUTCOME_APP_PROMOTION',  label: 'Promoção de App',      icon: '📱', hint: 'Instala ou engaja com seu aplicativo' },
];

const COUNTRIES = [
  ['BR','Brasil'],['US','Estados Unidos'],['PT','Portugal'],['AR','Argentina'],
  ['MX','México'],['CO','Colômbia'],['CL','Chile'],['PE','Peru'],['UY','Uruguai'],
  ['BO','Bolívia'],['EC','Equador'],['PY','Paraguai'],['VE','Venezuela'],
  ['GB','Reino Unido'],['DE','Alemanha'],['FR','França'],['IT','Itália'],
  ['ES','Espanha'],['NL','Países Baixos'],['BE','Bélgica'],['CH','Suíça'],
  ['AT','Áustria'],['SE','Suécia'],['NO','Noruega'],['DK','Dinamarca'],
  ['FI','Finlândia'],['PL','Polônia'],['CZ','República Tcheca'],['HU','Hungria'],
  ['RO','Romênia'],['GR','Grécia'],['CA','Canadá'],['AU','Austrália'],
  ['NZ','Nova Zelândia'],['JP','Japão'],['KR','Coreia do Sul'],['IN','Índia'],
  ['SG','Singapura'],['MY','Malásia'],['TH','Tailândia'],['ID','Indonésia'],
  ['PH','Filipinas'],['ZA','África do Sul'],['NG','Nigéria'],['EG','Egito'],
  ['SA','Arábia Saudita'],['AE','Emirados Árabes'],['TR','Turquia'],['IL','Israel'],
  ['MA','Marrocos'],['DZ','Argélia'],['TN','Tunísia'],['RU','Rússia'],
  ['UA','Ucrânia'],['IE','Irlanda'],['CN','China'],
];

const LOCALES = [
  ['16','Português (Brasil)'],['31','Português (Portugal)'],['6','English (US)'],
  ['24','English (UK)'],['7','Español (España)'],['23','Español (todos)'],
  ['9','Français (France)'],['44','Français (Canadá)'],['5','Deutsch'],['10','Italiano'],
  ['11','日本語'],['20','中文(简体)'],['21','中文(繁體 HK)'],['22','中文(繁體 TW)'],
  ['28','العربية'],['14','Nederlands'],['17','Русский'],['18','Svenska'],
  ['13','Norsk'],['4','Dansk'],['15','Polski'],['2','Čeština'],
  ['32','Română'],['30','Magyar'],['39','Ελληνικά'],['8','Suomi'],
  ['19','Türkçe'],['12','한국어'],['25','Bahasa Indonesia'],
];

const CTAS = [
  ['LEARN_MORE','Saiba Mais'],['SHOP_NOW','Comprar Agora'],['SIGN_UP','Inscrever-se'],
  ['CONTACT_US','Fale Conosco'],['BOOK_NOW','Reservar'],['DOWNLOAD','Baixar'],
  ['GET_OFFER','Ver Oferta'],['SUBSCRIBE','Assinar'],['APPLY_NOW','Candidatar-se'],
  ['ORDER_NOW','Pedir Agora'],['GET_QUOTE','Pedir Cotação'],['CALL_NOW','Ligar Agora'],
  ['SEND_MESSAGE','Enviar Mensagem'],['WATCH_MORE','Ver Mais'],
];

const PLACEMENTS = {
  image: [
    { label: 'Feed (quadrado)',    dims: '1080 × 1080 px — proporção 1:1' },
    { label: 'Feed (retângulo)',   dims: '1200 × 628 px — proporção 1.91:1' },
    { label: 'Feed (vertical)',    dims: '1080 × 1350 px — proporção 4:5' },
    { label: 'Stories / Reels',   dims: '1080 × 1920 px — proporção 9:16' },
  ],
  video: [
    { label: 'Feed (landscape)',   dims: '1280 × 720 px — 16:9 — até 240 s' },
    { label: 'Feed (quadrado)',    dims: '1080 × 1080 px — 1:1 — até 240 s' },
    { label: 'Feed (vertical)',    dims: '1080 × 1350 px — 4:5 — até 240 s' },
    { label: 'Reels / Stories',   dims: '1080 × 1920 px — 9:16 — até 90 s' },
  ],
};

// ── Estado do formulário ───────────────────────────────────────────────────────
const state = {
  step: 1,
  account_key: '', objective: '', campaign_name: '',
  countries: [], locales: [], daily_budget: '', start_time: '',
  age_min: 18, age_max: 65, advantage_audience: 1,
  campaign_status: 'PAUSED',
  publisher_platforms: ['facebook','instagram'],
  facebook_positions:  ['feed','facebook_reels','story'],
  instagram_positions: ['stream','reels','story'],
  messenger_positions: [],
  pixel_id: '', pixel_event: '', custom_conversion_id: '', page_id: '', destination_url: '',
  custom_event_str: '', instagram_user_id: '',
  ads: [],
};

// ── Config injetado pelo PHP ───────────────────────────────────────────────────
JS;
$pageScripts .= "\nconst IS_ADMIN = " . ($isAdmin ? 'true' : 'false') . ";\n";
$pageScripts .= <<<'JS'

// ── Helpers ───────────────────────────────────────────────────────────────────
function esc(s){ const d=document.createElement('div');d.textContent=String(s||'');return d.innerHTML; }
function apiKey(){
  return decodeURIComponent((document.cookie.match(/_auth=([^;]+)/)||[])[1]||'') || sessionStorage.getItem('apiKey') || document.getElementById('apiKeyInput')?.value || '';
}
async function apiFetch(url,opts={}){
  const res=await fetch(url,{...opts,headers:{'Authorization':'Bearer '+apiKey(),...(opts.headers||{})}});
  if(!res.ok) throw new Error(await res.text());
  return res.json();
}
function apiErrorMessage(err){
  try { return JSON.parse(err.message).message || err.message; } catch { return err.message; }
}

// ── Navegação entre passos ─────────────────────────────────────────────────────
function setStep(n){
  state.step=n;
  document.querySelectorAll('.step-panel').forEach((p,i)=>{
    p.classList.toggle('hidden', i+1!==n);
  });
  document.querySelectorAll('.step-btn').forEach((b,i)=>{
    b.classList.toggle('step-primary', i+1<=n);
    b.classList.toggle('step-neutral', i+1>n);
  });
  document.getElementById('btnPrev').disabled = n===1;
  document.getElementById('btnNext').classList.toggle('hidden', n===5);
  document.getElementById('btnCreate').classList.toggle('hidden', n!==5);
  if(n===3 && state.account_key) loadPixelsAndPages();
  if(n===5) renderReview();
}

function nextStep(){
  if(!validateStep(state.step)) return;
  saveStep(state.step);
  if(state.step<5) setStep(state.step+1);
}
function prevStep(){ if(state.step>1) setStep(state.step-1); }

function saveStep(n){
  if(n===1){
    state.account_key    = document.getElementById('s1_account').value;
    state.campaign_name  = document.getElementById('s1_name').value.trim();
    state.objective      = document.querySelector('input[name="objective"]:checked')?.value||'';
    state.campaign_status= document.getElementById('s1_status').checked ? 'ACTIVE' : 'PAUSED';
  } else if(n===2){
    state.countries          = [...document.querySelectorAll('.chk-country:checked')].map(c=>c.value);
    state.locales            = [...document.querySelectorAll('.chk-locale:checked')].map(c=>c.value);
    state.daily_budget       = document.getElementById('s2_budget').value;
    state.start_time         = document.getElementById('s2_start').value;
    state.age_min            = parseInt(document.getElementById('s2_age_min').value) || 18;
    state.age_max            = parseInt(document.getElementById('s2_age_max').value) || 65;
    state.advantage_audience = document.getElementById('s2_advantage').checked ? 1 : 0;
    state.publisher_platforms = [...document.querySelectorAll('.chk-platform:checked')].map(c=>c.value);
    state.facebook_positions  = [...document.querySelectorAll('.chk-fb-pos:checked')].map(c=>c.value);
    state.instagram_positions = [...document.querySelectorAll('.chk-ig-pos:checked')].map(c=>c.value);
    state.messenger_positions = [...document.querySelectorAll('.chk-ms-pos:checked')].map(c=>c.value);
  } else if(n===3){
    state.pixel_id            = document.getElementById('s3_pixel').value;
    state.pixel_event         = document.getElementById('s3_event').value;
    state.custom_conversion_id= document.getElementById('s3_custom_conversion').value;
    state.page_id             = document.getElementById('s3_page').value;
    state.destination_url     = document.getElementById('s3_url').value.trim();
    state.custom_event_str    = document.getElementById('s3_custom_event_str').value.trim();
    state.instagram_user_id   = document.getElementById('s3_instagram').value.trim();
  }
}

function validateStep(n){
  if(n===1){
    if(!document.getElementById('s1_account').value){ alert('Selecione uma conta.'); return false; }
    if(!document.getElementById('s1_name').value.trim()){ alert('Informe o nome da campanha.'); return false; }
    if(!document.querySelector('input[name="objective"]:checked')){ alert('Selecione um objetivo.'); return false; }
  } else if(n===2){
    const ctry=[...document.querySelectorAll('.chk-country:checked')];
    if(!ctry.length){ alert('Selecione ao menos um país.'); return false; }
    if(!document.getElementById('s2_budget').value){ alert('Informe o orçamento diário.'); return false; }
    if(!document.getElementById('s2_start').value){ alert('Informe a data de início.'); return false; }
  } else if(n===3){
    if(!document.getElementById('s3_pixel').value){ alert('Selecione um pixel.'); return false; }
    const hasEvent=document.getElementById('s3_event').value;
    const hasConversion=document.getElementById('s3_custom_conversion').value;
    if(!hasEvent && !hasConversion){ alert('Selecione um evento de conversão ou uma conversão personalizada.'); return false; }
    if(!document.getElementById('s3_page').value){ alert('Selecione uma página do Facebook.'); return false; }
    if(!document.getElementById('s3_url').value.trim()){ alert('Informe a URL de destino.'); return false; }
  } else if(n===4){
    if(!state.ads.length){ alert('Adicione ao menos um anúncio.'); return false; }
    for(const ad of state.ads){
      if(!ad.name.trim()){ alert('Preencha o nome de todos os anúncios.'); return false; }
      if(!ad.file){ alert(`Adicione o arquivo de mídia ao anúncio "${ad.name||'sem nome'}".`); return false; }
    }
  }
  return true;
}

// ── Passo 1: Carregar contas ───────────────────────────────────────────────────
async function loadAccounts(){
  const sel=document.getElementById('s1_account');
  sel.innerHTML='<option value="">Carregando...</option>';
  try{
    const data=await apiFetch('/api/accounts/list');
    const accounts=data.data||[];
    sel.innerHTML='<option value="">Selecione uma conta</option>'+
      accounts.map(a=>`<option value="${esc(a.account_key)}">${esc(a.label)}</option>`).join('');
  } catch(e){ sel.innerHTML='<option value="">Erro ao carregar</option>'; }
}

// ── Passo 3: Pixels, Eventos e Páginas ────────────────────────────────────────
async function loadPixelsAndPages(){
  const ak=state.account_key||document.getElementById('s1_account').value;
  if(!ak) return;
  const pxSel=document.getElementById('s3_pixel');
  const pgSel=document.getElementById('s3_page');
  pxSel.innerHTML='<option value="">Carregando pixels...</option>';
  pgSel.innerHTML='<option value="">Carregando páginas...</option>';

  const [px,pg]=await Promise.allSettled([
    apiFetch(`/api/generator/pixels?account_key=${encodeURIComponent(ak)}`),
    apiFetch(`/api/generator/pages?account_key=${encodeURIComponent(ak)}`),
  ]);

  if(px.status==='fulfilled'){
    const pixels=px.value.data||[];
    pxSel.innerHTML= pixels.length
      ? '<option value="">Selecione um pixel</option>'+pixels.map(p=>`<option value="${esc(p.id)}">${esc(p.name)} (${esc(p.id)})</option>`).join('')
      : '<option value="">Nenhum pixel encontrado</option>';
  } else pxSel.innerHTML=`<option value="">Erro: ${esc(apiErrorMessage(px.reason))}</option>`;

  if(pg.status==='fulfilled'){
    const pages=pg.value.data||[];
    pgSel.innerHTML= pages.length
      ? '<option value="">Selecione uma página</option>'+pages.map(p=>`<option value="${esc(p.id)}">${esc(p.name)}</option>`).join('')
      : '<option value="">Nenhuma página encontrada</option>';
  } else pgSel.innerHTML=`<option value="">Erro: ${esc(apiErrorMessage(pg.reason))}</option>`;
  loadCustomConversions();
}

async function loadEvents(){
  const ak=state.account_key||document.getElementById('s1_account').value;
  const px=document.getElementById('s3_pixel').value;
  const evSel=document.getElementById('s3_event');
  if(!px){ evSel.innerHTML='<option value="">Selecione um pixel primeiro</option>'; return; }
  evSel.innerHTML='<option value="">Carregando eventos...</option>';
  try{
    const data=await apiFetch(`/api/generator/events?account_key=${encodeURIComponent(ak)}&pixel_id=${encodeURIComponent(px)}`);
    const STANDARD_EVENTS=new Set(['AddPaymentInfo','AddToCart','AddToWishlist','CompleteRegistration',
      'Contact','CustomizeProduct','Donate','FindLocation','InitiateCheckout','Lead',
      'Purchase','Schedule','Search','StartTrial','SubmitApplication','Subscribe','ViewContent','OTHER']);
    const events=data.data||[];
    evSel.innerHTML='<option value="">Selecione um evento</option>'+events.map(e=>{
      const label=STANDARD_EVENTS.has(e)?e:`${e} (personalizado)`;
      return `<option value="${esc(e)}">${esc(label)}</option>`;
    }).join('');
    evSel.onchange = () => {
      const isCustom=evSel.value!==''&&!STANDARD_EVENTS.has(evSel.value);
      const wrap=document.getElementById('s3_custom_event_str_wrap');
      const strInput=document.getElementById('s3_custom_event_str');
      wrap.classList.toggle('hidden', evSel.value!=='OTHER'&&!isCustom);
      if(isCustom) strInput.value=evSel.value;
      else if(evSel.value!=='OTHER') strInput.value='';
    };
  } catch(e){ evSel.innerHTML='<option value="">Erro ao carregar eventos</option>'; }
}

async function loadCustomConversions(){
  const ak=state.account_key||document.getElementById('s1_account').value;
  const px=document.getElementById('s3_pixel').value;
  const cvSel=document.getElementById('s3_custom_conversion');
  if(!px){ cvSel.innerHTML='<option value="">Selecione um pixel primeiro</option>'; return; }
  cvSel.innerHTML='<option value="">Carregando...</option>';
  try{
    const data=await apiFetch(`/api/generator/customconversions?account_key=${encodeURIComponent(ak)}&pixel_id=${encodeURIComponent(px)}`);
    const cvs=data.data||[];
    if(cvs.length===0){
      cvSel.innerHTML='<option value="">Nenhuma conversão personalizada encontrada para este pixel</option>';
    } else {
      cvSel.innerHTML='<option value="">Nenhuma (usar evento do pixel)</option>'+cvs.map(c=>`<option value="${esc(c.id)}">${esc(c.name)}</option>`).join('');
    }
  } catch(e){ cvSel.innerHTML='<option value="">Erro ao carregar conversões</option>'; }
}

function onPixelChange(){
  loadEvents();
  loadCustomConversions();
}

// ── Toggle posicionamentos por plataforma ─────────────────────────────────────
function togglePlatformPositions(chk, posClass){
  const platformMap = {'fb-pos':'facebook','ig-pos':'instagram','ms-pos':'messenger'};
  const container = document.getElementById('positions-' + platformMap[posClass]);
  if(!container) return;
  if(chk.checked){
    container.classList.remove('opacity-40','pointer-events-none');
  } else {
    container.classList.add('opacity-40','pointer-events-none');
    container.querySelectorAll('.' + posClass).forEach(c => c.checked = false);
  }
}

// ── Filtro de países ───────────────────────────────────────────────────────────
function filterCountries(){
  const q=document.getElementById('countrySearch').value.toLowerCase();
  document.querySelectorAll('.country-item').forEach(el=>{
    el.classList.toggle('hidden', q&&!el.dataset.label.toLowerCase().includes(q));
  });
}

// ── Passo 4: Gerenciar anúncios ────────────────────────────────────────────────
let adIdCounter=0;
function addAd(){
  const id=adIdCounter++;
  state.ads.push({id,name:'',primary_text:'',headline:'',link_description:'',url_tags:'',cta:'LEARN_MORE',media_type:'image',file:null,preview:null});
  renderAds();
}

function removeAd(id){
  state.ads=state.ads.filter(a=>a.id!==id);
  renderAds();
}

function updateAd(id,field,value){
  const ad=state.ads.find(a=>a.id===id);
  if(ad) ad[field]=value;
  if(field==='media_type') renderAdCard(id);
}

function handleFile(id,input){
  const ad=state.ads.find(a=>a.id===id);
  if(!ad||!input.files[0]) return;
  ad.file=input.files[0];
  ad.preview=URL.createObjectURL(ad.file);
  const preview=document.getElementById(`preview_${id}`);
  if(preview){
    const isVideo=ad.file.type.startsWith('video/');
    preview.innerHTML=isVideo
      ? `<video src="${ad.preview}" class="max-h-32 rounded" controls muted></video>`
      : `<img src="${ad.preview}" class="max-h-32 rounded object-contain">`;
  }
}

function renderAds(){
  const container=document.getElementById('adsContainer');
  if(!state.ads.length){
    container.innerHTML='<p class="text-sm opacity-50 text-center py-8">Nenhum anúncio adicionado. Clique em "+ Adicionar Anúncio".</p>';
    return;
  }
  container.innerHTML=state.ads.map(ad=>buildAdCard(ad)).join('');
}

function buildAdCard(ad){
  const ctaOpts=CTAS.map(([v,l])=>`<option value="${v}" ${ad.cta===v?'selected':''}>${l}</option>`).join('');
  const dims=PLACEMENTS[ad.media_type]||PLACEMENTS.image;
  const dimsHtml=dims.map(d=>`<li><strong>${d.label}:</strong> ${d.dims}</li>`).join('');
  const fileAccept=ad.media_type==='video'?'video/*':'image/*';
  return `
  <div class="card bg-base-100 border border-base-300 shadow-sm" id="adcard_${ad.id}">
    <div class="card-body p-4 space-y-3">
      <div class="flex items-center justify-between">
        <span class="font-semibold text-sm">Anúncio ${state.ads.indexOf(ad)+1}</span>
        <button onclick="removeAd(${ad.id})" class="btn btn-xs btn-ghost btn-error">✕ Remover</button>
      </div>
      <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
        <div>
          <label class="label label-text text-xs pb-1">Nome do anúncio</label>
          <input type="text" value="${esc(ad.name)}" onchange="updateAd(${ad.id},'name',this.value)"
                 class="input input-sm input-bordered w-full" placeholder="Ex: Anúncio Principal">
        </div>
        <div>
          <label class="label label-text text-xs pb-1">Título (headline)</label>
          <input type="text" value="${esc(ad.headline)}" onchange="updateAd(${ad.id},'headline',this.value)"
                 class="input input-sm input-bordered w-full" placeholder="Ex: Aproveite a oferta!">
        </div>
      </div>
      <div>
        <label class="label label-text text-xs pb-1">Texto principal</label>
        <textarea onchange="updateAd(${ad.id},'primary_text',this.value)"
                  class="textarea textarea-bordered textarea-sm w-full h-16"
                  placeholder="Texto que aparece acima do anúncio...">${esc(ad.primary_text)}</textarea>
      </div>
      <div>
        <label class="label label-text text-xs pb-1">Descrição do link</label>
        <input type="text" value="${esc(ad.link_description||'')}" onchange="updateAd(${ad.id},'link_description',this.value)"
               class="input input-sm input-bordered w-full" placeholder="Ex: ⭐ ⭐ ⭐ ⭐ ⭐ 4.9">
      </div>
      <div>
        <label class="label label-text text-xs pb-1">Parâmetros de URL <span class="opacity-50">(url_tags — suporta variáveis do Meta)</span></label>
        <input type="text" value="${esc(ad.url_tags||'')}" onchange="updateAd(${ad.id},'url_tags',this.value)"
               class="input input-sm input-bordered w-full font-mono"
               placeholder="utm_source=facebook&utm_medium=paid&utm_campaign={{campaign.name}}&utm_content={{ad.name}}">
      </div>
      <div class="grid grid-cols-2 gap-3">
        <div>
          <label class="label label-text text-xs pb-1">CTA</label>
          <select onchange="updateAd(${ad.id},'cta',this.value)" class="select select-sm select-bordered w-full">
            ${ctaOpts}
          </select>
        </div>
        <div>
          <label class="label label-text text-xs pb-1">Tipo de mídia</label>
          <div class="flex gap-2 mt-1">
            <label class="flex items-center gap-1 cursor-pointer">
              <input type="radio" name="mt_${ad.id}" value="image" class="radio radio-sm"
                     ${ad.media_type==='image'?'checked':''} onchange="updateAd(${ad.id},'media_type','image')">
              <span class="text-sm">Imagem</span>
            </label>
            <label class="flex items-center gap-1 cursor-pointer">
              <input type="radio" name="mt_${ad.id}" value="video" class="radio radio-sm"
                     ${ad.media_type==='video'?'checked':''} onchange="updateAd(${ad.id},'media_type','video')">
              <span class="text-sm">Vídeo</span>
            </label>
          </div>
        </div>
      </div>
      <div class="alert alert-info py-2 text-xs">
        <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        <div><strong>Tamanhos recomendados (${ad.media_type==='video'?'Vídeo':'Imagem'}):</strong><ul class="mt-1 space-y-0.5">${dimsHtml}</ul></div>
      </div>
      <div class="border-2 border-dashed border-base-300 rounded-lg p-4 text-center cursor-pointer hover:border-primary transition-colors"
           onclick="document.getElementById('file_${ad.id}').click()">
        <input type="file" id="file_${ad.id}" class="hidden" accept="${fileAccept}"
               onchange="handleFile(${ad.id},this)" data-ad-index="${state.ads.indexOf(ad)}">
        <div id="preview_${ad.id}" class="flex justify-center">
          ${ad.preview
            ? (ad.media_type==='video'
                ? `<video src="${ad.preview}" class="max-h-32 rounded" controls muted></video>`
                : `<img src="${ad.preview}" class="max-h-32 rounded object-contain">`)
            : `<div class="text-sm opacity-50"><div class="text-3xl mb-1">${ad.media_type==='video'?'🎬':'🖼'}</div>Clique para selecionar ${ad.media_type==='video'?'vídeo':'imagem'}</div>`}
        </div>
      </div>
    </div>
  </div>`;
}

function renderAdCard(id){
  const ad=state.ads.find(a=>a.id===id);
  if(!ad) return;
  const card=document.getElementById(`adcard_${id}`);
  if(card) card.outerHTML=buildAdCard(ad);
}

// ── Passo 5: Revisão ──────────────────────────────────────────────────────────
const PLACEMENT_LABELS = {
  facebook: 'Facebook', instagram: 'Instagram', messenger: 'Messenger',
  feed: 'Feed', reels: 'Reels', facebook_reels: 'Reels', story: 'Stories', stream: 'Feed',
  marketplace: 'Marketplace', video_feeds: 'Vídeos', right_hand_column: 'Coluna Direita',
  search: 'Busca', instream_video: 'In-stream', explore: 'Explorar',
  sponsored_messages: 'Caixa de Entrada',
};
function formatPlacements(){
  const parts=[];
  if(state.publisher_platforms.includes('facebook')&&state.facebook_positions.length)
    parts.push('FB: '+state.facebook_positions.map(p=>PLACEMENT_LABELS[p]||p).join(', '));
  if(state.publisher_platforms.includes('instagram')&&state.instagram_positions.length)
    parts.push('IG: '+state.instagram_positions.map(p=>PLACEMENT_LABELS[p]||p).join(', '));
  if(state.publisher_platforms.includes('messenger')&&state.messenger_positions.length)
    parts.push('Msg: '+state.messenger_positions.map(p=>PLACEMENT_LABELS[p]||p).join(', '));
  return parts.join(' | ')||'—';
}

function renderReview(){
  saveStep(4);
  const objLabel=OBJECTIVES.find(o=>o.value===state.objective)?.label||state.objective;
  const countryNames=state.countries.map(c=>COUNTRIES.find(x=>x[0]===c)?.[1]||c).join(', ')||'—';
  const localeNames=state.locales.map(l=>LOCALES.find(x=>x[0]===l)?.[1]||l).join(', ')||'Todos';

  document.getElementById('reviewContent').innerHTML=`
  <div class="space-y-4">
    <div class="card bg-base-200 p-4">
      <h3 class="font-bold text-sm mb-2">📣 Campanha</h3>
      <div class="grid grid-cols-2 gap-x-6 gap-y-1 text-sm">
        <span class="opacity-60">Conta</span>      <span>${esc(state.account_key)}</span>
        <span class="opacity-60">Nome</span>       <span>${esc(state.campaign_name)}</span>
        <span class="opacity-60">Objetivo</span>   <span>${esc(objLabel)}</span>
        <span class="opacity-60">Status inicial</span> <span class="${state.campaign_status==='ACTIVE'?'text-success font-semibold':'text-warning font-semibold'}">${state.campaign_status==='ACTIVE'?'▶ ATIVA':'⏸ PAUSADA'}</span>
      </div>
    </div>
    <div class="card bg-base-200 p-4">
      <h3 class="font-bold text-sm mb-2">🎯 Segmentação</h3>
      <div class="grid grid-cols-2 gap-x-6 gap-y-1 text-sm">
        <span class="opacity-60">Países</span>       <span>${esc(countryNames)}</span>
        <span class="opacity-60">Idiomas</span>      <span>${esc(localeNames)}</span>
        <span class="opacity-60">Orçamento/dia</span><span>€ ${parseFloat(state.daily_budget||0).toFixed(2)}</span>
        <span class="opacity-60">Início</span>       <span>${esc(state.start_time)}</span>
        <span class="opacity-60">Faixa etária</span>    <span>${esc(state.age_min)} – ${esc(state.age_max)} anos</span>
        <span class="opacity-60">Advantage+</span>      <span>${state.advantage_audience ? 'Sim' : 'Não'}</span>
        <span class="opacity-60">Posicionamentos</span> <span class="text-xs">${esc(formatPlacements())}</span>
      </div>
    </div>
    <div class="card bg-base-200 p-4">
      <h3 class="font-bold text-sm mb-2">🔍 Pixel & Conversão</h3>
      <div class="grid grid-cols-2 gap-x-6 gap-y-1 text-sm">
        <span class="opacity-60">Pixel ID</span>    <span>${esc(state.pixel_id)}</span>
        <span class="opacity-60">Evento</span>      <span>${esc(state.pixel_event||'—')}</span>
        <span class="opacity-60">Conversão personalizada</span> <span>${esc(state.custom_conversion_id||'—')}</span>
        <span class="opacity-60">Evento custom</span> <span>${esc(state.custom_event_str||'—')}</span>
        <span class="opacity-60">Instagram ID</span>  <span>${esc(state.instagram_user_id||'—')}</span>
        <span class="opacity-60">Página ID</span>   <span>${esc(state.page_id)}</span>
        <span class="opacity-60">URL destino</span> <span class="break-all">${esc(state.destination_url)}</span>
      </div>
    </div>
    <div class="card bg-base-200 p-4">
      <h3 class="font-bold text-sm mb-2">🖼 Anúncios (${state.ads.length})</h3>
      <ul class="space-y-1 text-sm">
        ${state.ads.map((a,i)=>`<li class="space-y-0.5">
          <div class="flex items-center gap-2">
            <span class="badge badge-sm badge-neutral">${i+1}</span>
            <span>${esc(a.name||'Sem nome')}</span>
            <span class="badge badge-sm badge-ghost">${a.media_type==='video'?'🎬 Vídeo':'🖼 Imagem'}</span>
            ${a.file?`<span class="text-success text-xs">✓ ${esc(a.file.name)}</span>`:'<span class="text-error text-xs">⚠ sem arquivo</span>'}
          </div>
          ${a.url_tags?`<div class="text-xs opacity-60 pl-6 font-mono">${esc(a.url_tags)}</div>`:''}
        </li>`).join('')}
      </ul>
    </div>
    ${state.campaign_status==='ACTIVE'
      ? `<div class="alert alert-success text-sm">
           <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
           A campanha será criada com status <strong>ATIVA</strong> e começará a veicular imediatamente.
         </div>`
      : `<div class="alert alert-warning text-sm">
           <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
           A campanha será criada com status <strong>PAUSADA</strong>. Ative manualmente no Gerenciador de Anúncios do Meta.
         </div>`
    }
  </div>`;
}

// ── Criação / Submissão da campanha ───────────────────────────────────────────
async function createCampaign(){
  const btn=document.getElementById('btnCreate');
  btn.disabled=true;
  btn.innerHTML=IS_ADMIN
    ? '<span class="loading loading-spinner loading-xs"></span> Criando...'
    : '<span class="loading loading-spinner loading-xs"></span> Enviando...';

  const formData=new FormData();
  formData.append('account_key',     state.account_key);
  formData.append('campaign_name',   state.campaign_name);
  formData.append('objective',       state.objective);
  formData.append('campaign_status', state.campaign_status);
  formData.append('daily_budget', state.daily_budget);
  formData.append('start_time',   state.start_time);
  formData.append('pixel_id',     state.pixel_id);
  formData.append('pixel_event',  state.pixel_event);
  formData.append('page_id',      state.page_id);
  formData.append('age_min',            state.age_min);
  formData.append('age_max',            state.age_max);
  formData.append('advantage_audience', state.advantage_audience);
  formData.append('custom_event_str',    state.custom_event_str);
  formData.append('custom_conversion_id', state.custom_conversion_id);
  formData.append('instagram_user_id',  state.instagram_user_id);
  formData.append('destination_url',    state.destination_url);

  state.countries.forEach(c=>formData.append('countries[]',c));
  state.locales.forEach(l=>formData.append('locales[]',l));
  state.publisher_platforms.forEach(p=>formData.append('publisher_platforms[]',p));
  state.facebook_positions.forEach(p=>formData.append('facebook_positions[]',p));
  state.instagram_positions.forEach(p=>formData.append('instagram_positions[]',p));
  state.messenger_positions.forEach(p=>formData.append('messenger_positions[]',p));

  state.ads.forEach((ad,i)=>{
    formData.append(`ads[${i}][name]`,             ad.name);
    formData.append(`ads[${i}][primary_text]`,     ad.primary_text);
    formData.append(`ads[${i}][headline]`,         ad.headline);
    formData.append(`ads[${i}][cta]`,              ad.cta);
    formData.append(`ads[${i}][media_type]`,       ad.media_type);
    formData.append(`ads[${i}][destination_url]`,  state.destination_url);
    formData.append(`ads[${i}][link_description]`, ad.link_description||'');
    formData.append(`ads[${i}][url_tags]`,         ad.url_tags||'');
    if(ad.file) formData.append(`ads_files[${i}]`, ad.file);
  });

  const endpoint = IS_ADMIN ? '/api/generator/create' : '/api/generator/submit';

  try{
    const res=await fetch(endpoint,{
      method:'POST',
      headers:{'Authorization':'Bearer '+apiKey()},
      body:formData,
    });
    const json=await res.json();

    if(IS_ADMIN){
      if(json.success){
        document.getElementById('resultModal').classList.remove('hidden');
        document.getElementById('resultContent').innerHTML=`
          <div class="space-y-2 text-sm">
            <div class="flex items-center gap-2 text-success font-bold text-base">✓ Campanha criada com sucesso!</div>
            <div><span class="opacity-60">Campaign ID:</span> <code class="badge badge-ghost">${esc(json.campaign_id)}</code></div>
            <div><span class="opacity-60">Ad Set ID:</span>  <code class="badge badge-ghost">${esc(json.adset_id)}</code></div>
            <div class="mt-2 font-semibold">Anúncios:</div>
            ${(json.ads||[]).map(a=>`
              <div class="pl-3 border-l-2 border-success">
                <div>${esc(a.ad_name)}</div>
                ${a.ad_id?`<div class="opacity-60 text-xs">Ad ID: ${esc(a.ad_id)} · Creative: ${esc(a.creative_id)}</div>`:''}
                ${a.error?`<div class="text-error text-xs">${esc(a.error)}</div>`:''}
              </div>`).join('')}
          </div>`;
      } else {
        alert('Erro ao criar campanha:\n'+(json.error||'Erro desconhecido'));
      }
    } else {
      if(json.status==='success'){
        document.getElementById('resultModal').classList.remove('hidden');
        document.getElementById('resultContent').innerHTML=`
          <div class="space-y-2 text-sm">
            <div class="flex items-center gap-2 text-success font-bold text-base">✓ Campanha enviada para aprovação!</div>
            <div class="opacity-70">Sua campanha foi enviada para revisão. Acompanhe o status em <a href="/my-campaigns" class="link link-primary">Minhas Campanhas</a>.</div>
          </div>`;
      } else {
        alert('Erro ao enviar campanha:\n'+(json.error||'Erro desconhecido'));
      }
    }
  } catch(e){
    alert('Erro de rede:\n'+e.message);
  } finally {
    btn.disabled=false;
    btn.innerHTML=IS_ADMIN ? '🚀 Criar Campanha' : '📤 Enviar para Aprovação';
  }
}

// ── Pré-preenchimento de draft rejeitado ──────────────────────────────────────
async function loadDraft(id){
  try{
    const data = await apiFetch('/api/my-campaigns/'+id);
    const draft = data.data;
    if(!draft) return;
    const p = draft.payload || {};
    const rejectedFields = draft.rejected_fields || [];

    // Aguarda contas carregarem
    await new Promise(r => setTimeout(r, 800));

    // Step 1
    const accSel = document.getElementById('s1_account');
    if(p.account_key) accSel.value = p.account_key;
    if(p.campaign_name) document.getElementById('s1_name').value = p.campaign_name;
    if(p.objective){
      const radio = document.querySelector(`input[name="objective"][value="${p.objective}"]`);
      if(radio) radio.checked = true;
    }
    if(p.campaign_status){
      document.getElementById('s1_status').checked = p.campaign_status === 'ACTIVE';
    }
    state.account_key = p.account_key || '';

    // Step 2
    if(p.countries) document.querySelectorAll('.chk-country').forEach(c=>{c.checked=p.countries.includes(c.value);});
    if(p.locales) document.querySelectorAll('.chk-locale').forEach(c=>{c.checked=p.locales.includes(c.value);});
    if(p.daily_budget) document.getElementById('s2_budget').value = p.daily_budget;
    if(p.start_time) document.getElementById('s2_start').value = p.start_time;
    if(p.age_min) document.getElementById('s2_age_min').value = p.age_min;
    if(p.age_max) document.getElementById('s2_age_max').value = p.age_max;
    document.getElementById('s2_advantage').checked = p.advantage_audience != 0;
    if(p.publisher_platforms) document.querySelectorAll('.chk-platform').forEach(c=>{c.checked=p.publisher_platforms.includes(c.value);});
    if(p.facebook_positions) document.querySelectorAll('.chk-fb-pos').forEach(c=>{c.checked=p.facebook_positions.includes(c.value);});
    if(p.instagram_positions) document.querySelectorAll('.chk-ig-pos').forEach(c=>{c.checked=p.instagram_positions.includes(c.value);});
    if(p.messenger_positions) document.querySelectorAll('.chk-ms-pos').forEach(c=>{c.checked=p.messenger_positions.includes(c.value);});

    // Step 3 — carrega pixel/pages depois de definir account_key
    state.account_key = p.account_key || '';
    if(state.account_key){
      await loadPixelsAndPages();
      if(p.pixel_id){ document.getElementById('s3_pixel').value=p.pixel_id; await loadEvents(); await loadCustomConversions(); }
      if(p.pixel_event) document.getElementById('s3_event').value=p.pixel_event;
      if(p.custom_conversion_id) document.getElementById('s3_custom_conversion').value=p.custom_conversion_id;
      if(p.page_id) document.getElementById('s3_page').value=p.page_id;
    }
    if(p.destination_url) document.getElementById('s3_url').value=p.destination_url;
    if(p.custom_event_str) document.getElementById('s3_custom_event_str').value=p.custom_event_str;
    if(p.instagram_user_id) document.getElementById('s3_instagram').value=p.instagram_user_id;

    // Highlight rejected fields
    const fieldHighlightMap = {
      campaign_name:   ['s1_name'],
      objective:       [],
      budget:          ['s2_budget'],
      targeting:       ['s2_age_min','s2_age_max'],
      pixel:           ['s3_pixel','s3_event','s3_custom_conversion'],
      creative:        [],
      ad_copy:         [],
      destination_url: ['s3_url'],
      schedule:        ['s2_start'],
    };
    rejectedFields.forEach(f=>{
      (fieldHighlightMap[f]||[]).forEach(id=>{
        const el=document.getElementById(id);
        if(el) el.classList.add('input-error','border-error');
      });
    });

    if(rejectedFields.length){
      const banner = document.createElement('div');
      banner.className = 'alert alert-warning mb-4';
      banner.innerHTML = '<span>⚠️ Campos marcados em vermelho precisam de correção antes do reenvio.</span>';
      document.querySelector('.space-y-4.max-w-4xl').prepend(banner);
    }
  } catch(e){
    console.error('Erro ao carregar draft:', e);
  }
}

// ── Init ───────────────────────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', async ()=>{
  // Atualiza label do botão de submit
  const btn = document.getElementById('btnCreate');
  if(btn && !IS_ADMIN){
    btn.textContent = '📤 Enviar para Aprovação';
    btn.classList.remove('btn-success');
    btn.classList.add('btn-primary');
  }

  loadAccounts();
  document.getElementById('s2_start').value=new Date().toISOString().slice(0,10);
  setStep(1);
  addAd();

  // Verifica se há draft_id na URL para pré-preenchimento
  const draftId = new URLSearchParams(location.search).get('draft_id');
  if(draftId){
    await loadDraft(draftId);
  }
});
</script>
JS;

ob_start();
?>
<div class="space-y-4 max-w-4xl mx-auto">
  <div class="flex items-center justify-between">
    <h2 class="text-xl font-bold">Gerador de Campanhas</h2>
    <span class="badge badge-primary badge-outline">Meta Ads</span>
  </div>

  <!-- Stepper -->
  <ul class="steps steps-horizontal w-full text-xs">
    <li class="step step-btn step-primary" data-content="1">Campanha</li>
    <li class="step step-btn step-neutral" data-content="2">Segmentação</li>
    <li class="step step-btn step-neutral" data-content="3">Pixel</li>
    <li class="step step-btn step-neutral" data-content="4">Anúncios</li>
    <li class="step step-btn step-neutral" data-content="5">Revisão</li>
  </ul>

  <!-- ── PASSO 1: Campanha ───────────────────────────────────────────────── -->
  <div class="step-panel card bg-base-100 shadow-sm">
    <div class="card-body space-y-4">
      <h3 class="card-title text-base">1. Dados da Campanha</h3>

      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
          <label class="label label-text text-xs pb-1">Conta</label>
          <select id="s1_account" class="select select-bordered w-full">
            <option value="">Carregando...</option>
          </select>
        </div>
        <div>
          <label class="label label-text text-xs pb-1">Nome da campanha</label>
          <input id="s1_name" type="text" class="input input-bordered w-full" placeholder="Ex: Vendas Julho 2026">
        </div>
      </div>

      <div class="flex items-center gap-4 p-3 rounded-lg border border-base-300">
        <label class="flex items-center gap-3 cursor-pointer select-none">
          <input type="checkbox" id="s1_status" class="toggle toggle-success"
                 onchange="document.getElementById('s1_status_label').textContent=this.checked?'Ativa':'Pausada';
                           document.getElementById('s1_status_hint').textContent=this.checked?'A campanha começará a veicular imediatamente após a criação.':'A campanha será criada pausada. Ative manualmente no Meta.';">
          <div>
            <div class="text-sm font-semibold">Status inicial: <span id="s1_status_label">Pausada</span></div>
            <div class="text-xs opacity-60" id="s1_status_hint">A campanha será criada pausada. Ative manualmente no Meta.</div>
          </div>
        </label>
      </div>

      <div>
        <label class="label label-text text-xs pb-1">Objetivo da campanha</label>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2">
          <?php
          $objectives = [
            ['OUTCOME_SALES',         'Vendas',               '🛒', 'Direciona conversões e compras'],
            ['OUTCOME_LEADS',         'Geração de Cadastros', '📋', 'Captura dados de potenciais clientes'],
            ['OUTCOME_TRAFFIC',       'Tráfego',              '🔗', 'Envia pessoas ao seu site'],
            ['OUTCOME_AWARENESS',     'Reconhecimento',       '👁',  'Aumenta visibilidade da marca'],
            ['OUTCOME_ENGAGEMENT',    'Engajamento',          '💬', 'Aumenta interações com o conteúdo'],
            ['OUTCOME_APP_PROMOTION', 'Promoção de App',      '📱', 'Instala ou engaja com seu app'],
          ];
          foreach ($objectives as [$val, $label, $icon, $hint]):
          ?>
          <label class="border border-base-300 rounded-lg p-3 cursor-pointer hover:border-primary transition-colors has-[:checked]:border-primary has-[:checked]:bg-primary/5">
            <input type="radio" name="objective" value="<?= $val ?>" class="hidden">
            <div class="flex items-start gap-2">
              <span class="text-xl"><?= $icon ?></span>
              <div>
                <div class="font-semibold text-sm"><?= $label ?></div>
                <div class="text-xs opacity-60"><?= $hint ?></div>
              </div>
            </div>
          </label>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </div>

  <!-- ── PASSO 2: Segmentação ───────────────────────────────────────────── -->
  <div class="step-panel card bg-base-100 shadow-sm hidden">
    <div class="card-body space-y-4">
      <h3 class="card-title text-base">2. Segmentação</h3>

      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
          <label class="label label-text text-xs pb-1">Orçamento diário (€)</label>
          <input id="s2_budget" type="number" min="1" step="0.01" class="input input-bordered w-full" placeholder="Ex: 10.00">
        </div>
        <div>
          <label class="label label-text text-xs pb-1">Data de início</label>
          <input id="s2_start" type="date" class="input input-bordered w-full">
        </div>
      </div>

      <div>
        <label class="label label-text text-xs pb-1">Países</label>
        <input id="countrySearch" type="text" placeholder="Buscar país..." oninput="filterCountries()"
               class="input input-sm input-bordered w-full mb-2">
        <div class="border border-base-300 rounded-lg p-3 max-h-48 overflow-y-auto grid grid-cols-2 sm:grid-cols-3 gap-1">
          <?php
          $countries = [
            ['BR','Brasil'],['US','Estados Unidos'],['PT','Portugal'],['AR','Argentina'],
            ['MX','México'],['CO','Colômbia'],['CL','Chile'],['PE','Peru'],['UY','Uruguai'],
            ['BO','Bolívia'],['EC','Equador'],['PY','Paraguai'],['VE','Venezuela'],
            ['GB','Reino Unido'],['DE','Alemanha'],['FR','França'],['IT','Itália'],
            ['ES','Espanha'],['NL','Países Baixos'],['BE','Bélgica'],['CH','Suíça'],
            ['AT','Áustria'],['SE','Suécia'],['NO','Noruega'],['DK','Dinamarca'],
            ['FI','Finlândia'],['PL','Polônia'],['CZ','Rep. Tcheca'],['HU','Hungria'],
            ['RO','Romênia'],['GR','Grécia'],['CA','Canadá'],['AU','Austrália'],
            ['NZ','Nova Zelândia'],['JP','Japão'],['KR','Coreia do Sul'],['IN','Índia'],
            ['SG','Singapura'],['MY','Malásia'],['TH','Tailândia'],['ID','Indonésia'],
            ['PH','Filipinas'],['ZA','África do Sul'],['NG','Nigéria'],['EG','Egito'],
            ['SA','Arábia Saudita'],['AE','Emirados Árabes'],['TR','Turquia'],['IL','Israel'],
            ['MA','Marrocos'],['RU','Rússia'],['CN','China'],['IE','Irlanda'],
          ];
          foreach ($countries as [$code, $name]):
          ?>
          <label class="country-item flex items-center gap-1.5 cursor-pointer text-sm hover:bg-base-200 rounded px-1 py-0.5"
                 data-label="<?= strtolower($name) ?>">
            <input type="checkbox" class="chk-country checkbox checkbox-xs checkbox-primary" value="<?= $code ?>">
            <span><?= $name ?></span>
          </label>
          <?php endforeach; ?>
        </div>
      </div>

      <div>
        <label class="label label-text text-xs pb-1">Idiomas</label>
        <div class="border border-base-300 rounded-lg p-3 max-h-36 overflow-y-auto grid grid-cols-2 sm:grid-cols-3 gap-1">
          <?php
          $locales = [
            ['16','Português (Brasil)'],['31','Português (Portugal)'],['6','English (US)'],
            ['24','English (UK)'],['7','Español (España)'],['23','Español (todos)'],
            ['9','Français (France)'],['44','Français (Canadá)'],['5','Deutsch'],['10','Italiano'],
            ['11','日本語'],['20','中文(简体)'],['21','中文(繁體 HK)'],['22','中文(繁體 TW)'],
            ['28','العربية'],['14','Nederlands'],['17','Русский'],['18','Svenska'],
            ['13','Norsk'],['4','Dansk'],['15','Polski'],['2','Čeština'],
            ['32','Română'],['30','Magyar'],['39','Ελληνικά'],['8','Suomi'],
            ['19','Türkçe'],['12','한국어'],['25','Bahasa Indonesia'],
          ];
          foreach ($locales as [$id, $name]):
          ?>
          <label class="flex items-center gap-1.5 cursor-pointer text-sm hover:bg-base-200 rounded px-1 py-0.5">
            <input type="checkbox" class="chk-locale checkbox checkbox-xs checkbox-primary" value="<?= $id ?>">
            <span><?= $name ?></span>
          </label>
          <?php endforeach; ?>
        </div>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div>
          <label class="label label-text text-xs pb-1">Idade mínima</label>
          <input id="s2_age_min" type="number" min="18" max="65" value="18" class="input input-bordered w-full">
        </div>
        <div>
          <label class="label label-text text-xs pb-1">Idade máxima</label>
          <input id="s2_age_max" type="number" min="18" max="65" value="65" class="input input-bordered w-full">
        </div>
        <div class="flex flex-col justify-end pb-1">
          <label class="label label-text text-xs pb-1">Advantage+ Audience</label>
          <label class="flex items-center gap-2 cursor-pointer">
            <input type="checkbox" id="s2_advantage" class="toggle toggle-primary toggle-sm" checked>
            <span class="text-sm">Ativado</span>
          </label>
        </div>
      </div>

      <!-- Posicionamentos -->
      <div>
        <label class="label label-text text-xs pb-1">Posicionamentos</label>
        <div class="border border-base-300 rounded-lg p-3 space-y-3">

          <!-- Facebook -->
          <div>
            <div class="flex items-center gap-2 mb-1.5">
              <input type="checkbox" class="chk-platform checkbox checkbox-xs checkbox-primary" value="facebook" checked
                     onchange="togglePlatformPositions(this,'fb-pos')">
              <span class="text-sm font-semibold">Facebook</span>
            </div>
            <div id="positions-facebook" class="pl-5 grid grid-cols-2 sm:grid-cols-4 gap-1">
              <?php foreach ([['feed','Feed'],['facebook_reels','Reels'],['story','Stories'],['marketplace','Marketplace'],['video_feeds','Vídeos'],['right_hand_column','Coluna Direita'],['search','Busca'],['instream_video','In-stream']] as [$v,$l]): ?>
              <label class="flex items-center gap-1.5 cursor-pointer text-sm hover:bg-base-200 rounded px-1 py-0.5">
                <input type="checkbox" class="chk-fb-pos checkbox checkbox-xs checkbox-secondary" value="<?= $v ?>"
                  <?= in_array($v, ['feed','facebook_reels','story']) ? 'checked' : '' ?>>
                <span><?= $l ?></span>
              </label>
              <?php endforeach; ?>
            </div>
          </div>

          <!-- Instagram -->
          <div>
            <div class="flex items-center gap-2 mb-1.5">
              <input type="checkbox" class="chk-platform checkbox checkbox-xs checkbox-primary" value="instagram" checked
                     onchange="togglePlatformPositions(this,'ig-pos')">
              <span class="text-sm font-semibold">Instagram</span>
            </div>
            <div id="positions-instagram" class="pl-5 grid grid-cols-2 sm:grid-cols-4 gap-1">
              <?php foreach ([['stream','Feed'],['reels','Reels'],['story','Stories'],['explore','Explorar']] as [$v,$l]): ?>
              <label class="flex items-center gap-1.5 cursor-pointer text-sm hover:bg-base-200 rounded px-1 py-0.5">
                <input type="checkbox" class="chk-ig-pos checkbox checkbox-xs checkbox-secondary" value="<?= $v ?>"
                  <?= in_array($v, ['stream','reels','story']) ? 'checked' : '' ?>>
                <span><?= $l ?></span>
              </label>
              <?php endforeach; ?>
            </div>
          </div>

          <!-- Messenger -->
          <div>
            <div class="flex items-center gap-2 mb-1.5">
              <input type="checkbox" class="chk-platform checkbox checkbox-xs checkbox-primary" value="messenger"
                     onchange="togglePlatformPositions(this,'ms-pos')">
              <span class="text-sm font-semibold">Messenger</span>
            </div>
            <div id="positions-messenger" class="pl-5 grid grid-cols-2 sm:grid-cols-4 gap-1 opacity-40 pointer-events-none">
              <?php foreach ([['sponsored_messages','Caixa de Entrada'],['story','Stories']] as [$v,$l]): ?>
              <label class="flex items-center gap-1.5 cursor-pointer text-sm hover:bg-base-200 rounded px-1 py-0.5">
                <input type="checkbox" class="chk-ms-pos checkbox checkbox-xs checkbox-secondary" value="<?= $v ?>">
                <span><?= $l ?></span>
              </label>
              <?php endforeach; ?>
            </div>
          </div>

          <!-- Audience Network -->
          <div class="flex items-center gap-2 opacity-50">
            <input type="checkbox" class="checkbox checkbox-xs" disabled>
            <span class="text-sm font-semibold">Audience Network</span>
            <span class="badge badge-xs badge-warning">Sempre desativado</span>
          </div>

        </div>
      </div>

    </div>
  </div>

  <!-- ── PASSO 3: Pixel & Conversão ────────────────────────────────────── -->
  <div class="step-panel card bg-base-100 shadow-sm hidden">
    <div class="card-body space-y-4">
      <h3 class="card-title text-base">3. Pixel & Conversão</h3>

      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
          <label class="label label-text text-xs pb-1">Pixel do Meta</label>
          <select id="s3_pixel" class="select select-bordered w-full" onchange="onPixelChange()">
            <option value="">Selecione uma conta primeiro</option>
          </select>
        </div>
        <div>
          <label class="label label-text text-xs pb-1">Evento de conversão</label>
          <select id="s3_event" class="select select-bordered w-full">
            <option value="">Selecione um pixel primeiro</option>
          </select>
        </div>
        <div id="s3_custom_event_str_wrap" class="hidden">
          <label class="label label-text text-xs pb-1">Nome do evento customizado</label>
          <input id="s3_custom_event_str" type="text" class="input input-bordered w-full" placeholder="Ex: AdClick">
        </div>
        <div>
          <label class="label label-text text-xs pb-1">Conversão personalizada <span class="opacity-50">(opcional — substitui evento do pixel)</span></label>
          <select id="s3_custom_conversion" class="select select-bordered w-full">
            <option value="">Selecione um pixel primeiro</option>
          </select>
        </div>
        <div>
          <label class="label label-text text-xs pb-1">ID da conta do Instagram (opcional)</label>
          <input id="s3_instagram" type="text" class="input input-bordered w-full" placeholder="Ex: 17841469630403537">
        </div>
        <div>
          <label class="label label-text text-xs pb-1">Página do Facebook</label>
          <select id="s3_page" class="select select-bordered w-full">
            <option value="">Selecione uma conta primeiro</option>
          </select>
        </div>
        <div>
          <label class="label label-text text-xs pb-1">URL de destino</label>
          <input id="s3_url" type="url" class="input input-bordered w-full" placeholder="https://seusite.com">
        </div>
      </div>
    </div>
  </div>

  <!-- ── PASSO 4: Anúncios ─────────────────────────────────────────────── -->
  <div class="step-panel card bg-base-100 shadow-sm hidden">
    <div class="card-body space-y-4">
      <div class="flex items-center justify-between">
        <h3 class="card-title text-base">4. Anúncios</h3>
        <button onclick="addAd()" class="btn btn-sm btn-primary">+ Adicionar Anúncio</button>
      </div>
      <div id="adsContainer" class="space-y-4"></div>
    </div>
  </div>

  <!-- ── PASSO 5: Revisão ───────────────────────────────────────────────── -->
  <div class="step-panel card bg-base-100 shadow-sm hidden">
    <div class="card-body space-y-4">
      <h3 class="card-title text-base">5. Revisão</h3>
      <div id="reviewContent"></div>
    </div>
  </div>

  <!-- Navegação -->
  <div class="flex justify-between">
    <button id="btnPrev" onclick="prevStep()" class="btn btn-ghost" disabled>← Anterior</button>
    <div class="flex gap-2">
      <button id="btnNext" onclick="nextStep()" class="btn btn-primary">Próximo →</button>
      <button id="btnCreate" onclick="createCampaign()" class="btn btn-success hidden">🚀 Criar Campanha</button>
    </div>
  </div>
</div>

<!-- Modal resultado -->
<div id="resultModal" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
  <div class="card bg-base-100 w-full max-w-lg shadow-xl">
    <div class="card-body">
      <h3 class="card-title">Resultado</h3>
      <div id="resultContent"></div>
      <div class="card-actions justify-end mt-4">
        <button onclick="location.reload()" class="btn btn-ghost btn-sm">Nova campanha</button>
        <button onclick="document.getElementById('resultModal').classList.add('hidden')" class="btn btn-primary btn-sm">Fechar</button>
      </div>
    </div>
  </div>
</div>

<style>
.step-btn { cursor: default; }
.has-\[\:checked\]\:border-primary:has(input:checked) { border-color: oklch(var(--p)); }
.has-\[\:checked\]\:bg-primary\/5:has(input:checked) { background-color: oklch(var(--p) / 0.05); }
</style>
<?php
$pageContent = ob_get_clean();
require __DIR__ . '/layout.php';

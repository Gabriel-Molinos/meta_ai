<?php
$pageTitle = 'WordPress — Publicar Post com IA';
$isAdmin   = ($GLOBALS['_authType'] ?? 'admin') === 'admin';

ob_start();
?>

<div class="flex flex-col gap-3" style="height: calc(100vh - 5rem);">

  <!-- ── Publish bar ──────────────────────────────────────────── -->
  <div class="flex flex-wrap gap-2 items-end">

    <div class="flex flex-col gap-1" style="min-width:180px;max-width:220px;flex:1;">
      <label class="text-xs font-semibold opacity-60">Site WordPress</label>
      <select id="siteSelect" class="select select-bordered select-sm w-full">
        <option value="">Selecione um site...</option>
      </select>
    </div>

    <div class="flex flex-col gap-1 flex-1" style="min-width:200px;">
      <label class="text-xs font-semibold opacity-60">Título do post</label>
      <input type="text" id="pageTitle" placeholder="Ex: Why Americans Choose Discover in 2026"
             class="input input-bordered input-sm w-full" />
    </div>

    <div class="flex flex-col gap-1">
      <label class="text-xs font-semibold opacity-60">Tipo</label>
      <select id="postType" class="select select-bordered select-sm">
        <option value="post">Post (blog)</option>
        <option value="page">Page</option>
      </select>
    </div>

    <div class="flex flex-col gap-1">
      <label class="text-xs font-semibold opacity-60">Status</label>
      <select id="pageStatus" class="select select-bordered select-sm">
        <option value="publish">Publicar</option>
        <option value="draft">Rascunho</option>
      </select>
    </div>

    <button id="btnPublish" onclick="publishPage()" disabled
            class="btn btn-primary btn-sm self-end gap-2">
      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
      </svg>
      Publicar no WP
    </button>

    <button onclick="document.getElementById('sitesModal').showModal()"
            class="btn btn-ghost btn-sm self-end gap-2 border border-base-300">
      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
      </svg>
      Gerenciar Sites
    </button>
  </div>

  <!-- ── Publish result ──────────────────────────────────────── -->
  <div id="publishResult" class="hidden"></div>

  <!-- ── Main: Form + Preview ────────────────────────────────── -->
  <div class="flex-1 grid grid-cols-1 lg:grid-cols-2 gap-4 min-h-0">

    <!-- LEFT: Generator form -->
    <div class="flex flex-col gap-3 overflow-y-auto pr-1 min-h-0">

      <!-- Assunto -->
      <div class="flex flex-col gap-1">
        <label class="text-xs font-semibold opacity-60">Assunto do Post</label>
        <textarea id="topic" rows="3"
                  placeholder="Ex: Best credit cards for cashback in the US 2026"
                  class="textarea textarea-bordered text-sm resize-none"></textarea>
      </div>

      <!-- Idioma + Palavras -->
      <div class="grid grid-cols-2 gap-3">
        <div class="flex flex-col gap-1">
          <label class="text-xs font-semibold opacity-60">Idioma</label>
          <select id="language" class="select select-bordered select-sm">
            <option value="English">English</option>
            <option value="Portuguese (Brasil)">Português (BR)</option>
            <option value="Spanish">Español</option>
            <option value="French">Français</option>
            <option value="Italian">Italiano</option>
            <option value="German">Deutsch</option>
          </select>
        </div>
        <div class="flex flex-col gap-1">
          <label class="text-xs font-semibold opacity-60">Qtd. Palavras</label>
          <select id="wordCount" class="select select-bordered select-sm">
            <option value="500">~500</option>
            <option value="800">~800</option>
            <option value="1000" selected>~1.000</option>
            <option value="1500">~1.500</option>
            <option value="2000">~2.000</option>
            <option value="3000">~3.000</option>
          </select>
        </div>
      </div>

      <!-- Botões CTA -->
      <div class="flex flex-col gap-2">
        <div class="flex items-center justify-between">
          <label class="text-xs font-semibold opacity-60">Links dos Botões CTA</label>
          <button type="button" onclick="addButtonRow()"
                  class="btn btn-ghost btn-xs border border-base-300 gap-1">
            + Adicionar
          </button>
        </div>
        <div id="buttonsContainer" class="flex flex-col gap-2"></div>
      </div>

      <!-- Opções -->
      <div class="flex flex-col gap-2 pt-1">
        <label class="flex items-center gap-2 cursor-pointer text-sm">
          <input type="checkbox" id="inclHeader" class="checkbox checkbox-sm checkbox-primary" checked />
          <span>3 botões no início + card de destaque</span>
        </label>
        <label class="flex items-center gap-2 cursor-pointer text-sm">
          <input type="checkbox" id="inclText" class="checkbox checkbox-sm checkbox-primary" checked />
          <span>Texto introdutório antes dos botões CTA</span>
        </label>
      </div>

      <!-- Componentes DaisyUI -->
      <div class="flex flex-col gap-2 pt-1">
        <div class="flex items-center justify-between">
          <label class="text-xs font-semibold opacity-60">Componentes DaisyUI</label>
          <span class="text-[10px] opacity-40">arraste para reordenar</span>
        </div>
        <div id="componentsContainer" class="flex flex-col gap-1"></div>
      </div>

      <!-- Template Visual -->
      <div class="flex flex-col gap-1 pt-1">
        <label class="text-xs font-semibold opacity-60">Template Visual</label>
        <div class="flex gap-2">
          <select id="templateSelect" class="select select-bordered select-sm flex-1">
            <option value="0">— Sem template (prompt padrão) —</option>
          </select>
          <button type="button" onclick="openTemplatesModal()"
                  class="btn btn-ghost btn-xs border border-base-300 self-end shrink-0">
            ⚙ Gerenciar
          </button>
        </div>
      </div>

      <!-- Generate button -->
      <button id="btnGenerate" onclick="generateContent()"
              class="btn btn-accent w-full gap-2">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M13 10V3L4 14h7v7l9-11h-7z"/>
        </svg>
        Gerar Conteúdo com IA
      </button>

      <!-- Generate status -->
      <div id="generateStatus" class="hidden">
        <div class="flex items-center gap-2 text-xs opacity-60">
          <span class="loading loading-spinner loading-xs"></span>
          <span id="generateStatusText">Gerando conteúdo... pode levar 20–40s</span>
        </div>
      </div>

      <!-- Featured image -->
      <div id="featuredSection" class="flex flex-col gap-2">
        <div class="flex items-center justify-between">
          <label class="text-xs font-semibold opacity-60">Imagem Destacada</label>
          <button id="btnGenFeatured" type="button" onclick="generateFeaturedImage()"
                  class="btn btn-ghost btn-xs border border-base-300 gap-1">
            ✦ Gerar com IA
          </button>
        </div>
        <div id="featuredStatus" class="hidden">
          <div class="flex items-center gap-2 text-xs opacity-60">
            <span class="loading loading-spinner loading-xs"></span>
            <span>Gerando imagem destacada... 15–30s</span>
          </div>
        </div>
        <div id="featuredImageArea" class="hidden items-center gap-3 p-2 bg-base-200 rounded-lg border border-base-300">
          <img id="featuredImagePreview" class="w-20 h-12 object-cover rounded shrink-0" src="" alt="" />
          <span class="text-[11px] opacity-60 flex-1 min-w-0 truncate" id="featuredImageInfo"></span>
          <button type="button" onclick="clearFeaturedImage()"
                  class="btn btn-ghost btn-xs text-error shrink-0 px-1">✕</button>
        </div>
      </div>

      <!-- Add Card (shown after generation) -->
      <button id="btnShowAddCard" type="button" onclick="showAddCard()"
              class="hidden btn btn-ghost btn-sm border border-base-300 gap-2 self-start">
        + Adicionar Card
      </button>
      <div id="addCardSection" class="hidden flex-col gap-2 border border-base-300 rounded-lg p-3">
        <div class="flex items-center justify-between">
          <label class="text-xs font-semibold opacity-60">Novo Card</label>
          <button type="button" onclick="hideAddCard()" class="btn btn-ghost btn-xs px-1 opacity-50">✕</button>
        </div>
        <input type="text" id="cardTitle" placeholder="Título do card"
               class="input input-bordered input-sm w-full" />
        <textarea id="cardText" rows="2" placeholder="Texto descritivo..."
                  class="textarea textarea-bordered text-sm resize-none w-full"></textarea>
        <div class="grid grid-cols-2 gap-2">
          <input type="text" id="cardBtnLabel" placeholder="Texto do botão"
                 class="input input-bordered input-sm w-full" />
          <input type="url" id="cardBtnHref" placeholder="https://..."
                 class="input input-bordered input-sm w-full" />
        </div>
        <div class="flex items-center gap-2 flex-wrap">
          <button type="button" id="btnGenCardImage" onclick="generateCardImage()"
                  class="btn btn-ghost btn-xs border border-base-300 gap-1">
            ✦ Gerar imagem com IA
          </button>
          <span id="cardImageSpinner" class="hidden loading loading-spinner loading-xs"></span>
          <div id="cardImageArea" class="hidden items-center gap-2">
            <img id="cardImagePreview" class="w-16 h-10 object-cover rounded shrink-0" src="" alt="" />
            <button type="button" onclick="clearCardImage()"
                    class="btn btn-ghost btn-xs text-error px-1">✕</button>
          </div>
        </div>
        <button type="button" onclick="insertCard()"
                class="btn btn-sm btn-outline w-full">+ Inserir no conteúdo</button>
      </div>

      <!-- HTML viewer (collapsible, shown after generation) -->
      <div id="htmlPanel" class="hidden flex flex-col gap-1">
        <details class="border border-base-300 rounded-lg">
          <summary class="px-3 py-2 text-xs font-semibold opacity-60 cursor-pointer select-none">
            Ver / Editar HTML gerado
          </summary>
          <div class="p-2">
            <textarea id="htmlEditor"
                      class="w-full font-mono text-[11px] textarea textarea-bordered resize-none leading-4"
                      rows="12"
                      onchange="onHtmlEditorChange()"
                      oninput="onHtmlEditorChange()"></textarea>
            <button onclick="refreshPreviewFromEditor()" class="btn btn-ghost btn-xs mt-1 border border-base-300">
              ↺ Atualizar preview
            </button>
          </div>
        </details>
      </div>

    </div>

    <!-- RIGHT: Preview -->
    <div class="flex flex-col gap-2 min-h-0">
      <div class="flex items-center justify-between">
        <label class="text-xs font-semibold opacity-60">Preview</label>
        <div class="flex items-center gap-2">
          <button id="btnEditMode" type="button" onclick="toggleEditMode()"
                  class="hidden btn btn-ghost btn-xs border border-base-300 gap-1">
            ✏ Editar
          </button>
          <label class="flex items-center gap-1.5 text-xs opacity-60 cursor-pointer">
            <input type="checkbox" id="previewDesktop" class="checkbox checkbox-xs" checked
                   onchange="updatePreview()" />
            Desktop (640px)
          </label>
        </div>
      </div>
      <div class="flex-1 border border-base-300 rounded-xl overflow-hidden bg-white min-h-0 relative">
        <div id="previewPlaceholder"
             class="absolute inset-0 flex items-center justify-center text-sm opacity-30 pointer-events-none">
          Preencha o formulário e clique em<br>
          <strong class="ml-1">Gerar Conteúdo com IA</strong>
        </div>
        <iframe id="previewFrame"
                class="w-full h-full border-0"
                sandbox="allow-scripts allow-same-origin"></iframe>
      </div>
    </div>

  </div>
</div>

<!-- ── Sites Management Modal ──────────────────────────────── -->
<dialog id="sitesModal" class="modal">
  <div class="modal-box max-w-2xl">
    <form method="dialog">
      <button class="btn btn-sm btn-circle btn-ghost absolute right-3 top-3">✕</button>
    </form>
    <h3 class="font-bold text-lg mb-4">Gerenciar Sites WordPress</h3>

    <div id="sitesTableWrap" class="mb-6">
      <div class="text-xs opacity-50 italic">Carregando...</div>
    </div>

    <?php if ($isAdmin): ?>
    <div class="divider my-4 text-xs opacity-40">Adicionar / Editar Site</div>

    <form id="siteForm" onsubmit="saveSite(event)" class="grid grid-cols-1 sm:grid-cols-2 gap-3">
      <input type="hidden" id="editSiteId" value="" />

      <div class="sm:col-span-2">
        <label class="label py-1"><span class="label-text text-xs font-semibold">Label</span></label>
        <input id="fLabel" type="text" placeholder="Ex: Meu Blog" required
               class="input input-bordered input-sm w-full" />
      </div>

      <div class="sm:col-span-2">
        <label class="label py-1"><span class="label-text text-xs font-semibold">URL do site WordPress</span></label>
        <input id="fUrl" type="url" placeholder="https://meusite.com" required
               class="input input-bordered input-sm w-full" />
      </div>

      <div>
        <label class="label py-1"><span class="label-text text-xs font-semibold">Usuário WordPress</span></label>
        <input id="fUser" type="text" placeholder="admin" required
               class="input input-bordered input-sm w-full" />
      </div>

      <div>
        <label class="label py-1">
          <span class="label-text text-xs font-semibold">Application Password</span>
          <span class="label-text-alt text-[10px] opacity-50">WP Admin → Users → Application Passwords</span>
        </label>
        <input id="fPass" type="password" placeholder="xxxx xxxx xxxx xxxx xxxx xxxx"
               class="input input-bordered input-sm w-full" />
        <p class="text-[10px] opacity-40 mt-1" id="fPassHint" style="display:none;">
          Deixe em branco para manter a senha atual ao editar.
        </p>
      </div>

      <div class="sm:col-span-2">
        <label class="label py-1">
          <span class="label-text text-xs font-semibold">Conta vinculada</span>
          <span class="label-text-alt text-[10px] opacity-50">Opcional</span>
        </label>
        <select id="fAccountId" class="select select-bordered select-sm w-full">
          <option value="">Sem vínculo</option>
        </select>
      </div>

      <div class="sm:col-span-2 flex gap-2 justify-end mt-1">
        <button type="button" onclick="resetSiteForm()" class="btn btn-ghost btn-sm">Cancelar</button>
        <button type="submit" class="btn btn-primary btn-sm">
          <span id="siteFormBtnText">Salvar Site</span>
        </button>
      </div>
    </form>
    <?php endif; ?>
  </div>
  <form method="dialog" class="modal-backdrop"><button>fechar</button></form>
</dialog>

<!-- ── Templates Management Modal ─────────────────────────────── -->
<dialog id="templatesModal" class="modal">
  <div class="modal-box max-w-3xl">
    <form method="dialog">
      <button class="btn btn-sm btn-circle btn-ghost absolute right-3 top-3">✕</button>
    </form>
    <h3 class="font-bold text-lg mb-4">Gerenciar Templates Visuais</h3>

    <div id="templatesListWrap" class="mb-6">
      <div class="text-xs opacity-50 italic">Carregando...</div>
    </div>

    <?php if ($isAdmin): ?>
    <div class="divider my-4 text-xs opacity-40">Criar / Editar Template</div>

    <form id="templateForm" onsubmit="saveTemplate(event)" class="flex flex-col gap-3">
      <input type="hidden" id="editTemplateId" value="" />

      <div class="grid grid-cols-2 gap-3">
        <div class="col-span-2 sm:col-span-1">
          <label class="label py-1"><span class="label-text text-xs font-semibold">Nome</span></label>
          <input id="tplName" type="text" placeholder="Ex: Estilo Blog Financeiro" required
                 class="input input-bordered input-sm w-full" />
        </div>
        <div class="col-span-2 sm:col-span-1">
          <label class="label py-1"><span class="label-text text-xs font-semibold">Descrição</span></label>
          <input id="tplDescription" type="text" placeholder="Breve descrição do estilo"
                 class="input input-bordered input-sm w-full" />
        </div>
      </div>

      <div>
        <label class="label py-1">
          <span class="label-text text-xs font-semibold">Extrair de URL</span>
          <span class="label-text-alt text-[10px] opacity-50">Gemini extrai e limpa o HTML do artigo</span>
        </label>
        <div class="flex gap-2">
          <input id="tplSourceUrl" type="url" placeholder="https://exemplo.com/algum-post/"
                 class="input input-bordered input-sm flex-1" />
          <button type="button" onclick="extractFromUrl()"
                  class="btn btn-ghost btn-sm border border-base-300 shrink-0 gap-1">
            <span id="extractBtnText">⟳ Extrair</span>
            <span id="extractSpinner" class="hidden loading loading-spinner loading-xs"></span>
          </button>
        </div>
        <p class="text-[10px] opacity-40 mt-1">Preenche o HTML abaixo automaticamente. Pode levar 20–40s.</p>
      </div>

      <div>
        <label class="label py-1">
          <span class="label-text text-xs font-semibold">HTML do Template</span>
          <span class="label-text-alt text-[10px] opacity-50">Deixe vazio para usar o padrão do sistema</span>
        </label>
        <textarea id="tplHtml" rows="10"
                  placeholder="Cole aqui o HTML do template, ou use o botão Extrair de URL acima..."
                  class="textarea textarea-bordered font-mono text-[11px] leading-4 resize-y w-full"></textarea>
      </div>

      <div class="flex gap-2 justify-end mt-1">
        <button type="button" onclick="resetTemplateForm()" class="btn btn-ghost btn-sm">Cancelar</button>
        <button type="submit" class="btn btn-primary btn-sm">
          <span id="tplFormBtnText">Salvar Template</span>
        </button>
      </div>
    </form>
    <?php endif; ?>
  </div>
  <form method="dialog" class="modal-backdrop"><button>fechar</button></form>
</dialog>

<script>
// ── State ────────────────────────────────────────────────────────
const IS_ADMIN        = <?= json_encode($isAdmin) ?>;
let sites             = [];
let allAccounts       = [];
let templates         = [];
let generatedHtml     = '';
let featuredImageB64  = '';
let featuredImageMime = '';
let blocks            = [];
let editMode          = false;
let cardImageB64      = '';
let cardImageMime     = '';

const apiKey  = () => sessionStorage.getItem('apiKey') || '';
const headers = () => ({ 'Content-Type': 'application/json', 'Authorization': 'Bearer ' + apiKey() });

// ── Featured Image ───────────────────────────────────────────────
async function generateFeaturedImage() {
  const topic   = document.getElementById('topic').value.trim();
  const title   = document.getElementById('pageTitle').value.trim();
  const subject = topic || title;
  if (!subject) { alert('Preencha o assunto ou o título do post primeiro.'); return; }

  const btn      = document.getElementById('btnGenFeatured');
  const statusEl = document.getElementById('featuredStatus');
  btn.disabled   = true;
  statusEl.classList.remove('hidden');
  document.getElementById('featuredImageArea').classList.add('hidden');

  try {
    const res  = await fetch('/api/wordpress/generate-featured-image', {
      method: 'POST', headers: headers(),
      body: JSON.stringify({ topic, title }),
    });
    const json = await res.json();
    if (json.status !== 'success') throw new Error(json.error || 'Erro ao gerar imagem');

    featuredImageB64  = json.data;
    featuredImageMime = json.mime_type;

    const preview = document.getElementById('featuredImagePreview');
    preview.src   = `data:${json.mime_type};base64,${json.data}`;
    document.getElementById('featuredImageInfo').textContent =
      json.mime_type.split('/')[1].toUpperCase() + ' • imagem destacada gerada';
    const area = document.getElementById('featuredImageArea');
    area.classList.remove('hidden');
    area.classList.add('flex');
  } catch (err) {
    alert('Erro ao gerar imagem destacada: ' + err.message);
  } finally {
    btn.disabled = false;
    statusEl.classList.add('hidden');
  }
}

function clearFeaturedImage() {
  featuredImageB64  = '';
  featuredImageMime = '';
  document.getElementById('featuredImagePreview').src = '';
  const area = document.getElementById('featuredImageArea');
  area.classList.add('hidden');
  area.classList.remove('flex');
}

// ── Components ───────────────────────────────────────────────────
const COMPONENT_DEFS = [
  {
    type: 'accordion', label: 'Accordion', enabled: true, quantity: 3, color: 'base-200',
    colors: [
      { value: 'base-200', label: 'Padrão' }, { value: 'primary', label: 'Primary' },
      { value: 'secondary', label: 'Secondary' }, { value: 'accent', label: 'Accent' },
      { value: 'neutral', label: 'Neutral' },
    ],
  },
  {
    type: 'buttons', label: 'Botões coloridos', enabled: true, quantity: 2, color: 'primary',
    colors: [
      { value: 'primary', label: 'Primary' }, { value: 'secondary', label: 'Secondary' },
      { value: 'accent', label: 'Accent' }, { value: 'success', label: 'Success' },
      { value: 'warning', label: 'Warning' }, { value: 'error', label: 'Error' },
      { value: 'neutral', label: 'Neutral' },
    ],
  },
  {
    type: 'card', label: 'Card centralizado', enabled: false, quantity: 1, color: 'base-100',
    colors: [
      { value: 'base-100', label: 'Padrão' }, { value: 'base-200', label: 'Base 200' },
      { value: 'primary', label: 'Primary' }, { value: 'secondary', label: 'Secondary' },
      { value: 'accent', label: 'Accent' }, { value: 'neutral', label: 'Neutral' },
    ],
  },
];

let componentOrder = COMPONENT_DEFS.map((_, i) => i);
let dragSrcIdx = null;

function renderComponents() {
  const container = document.getElementById('componentsContainer');
  container.innerHTML = '';
  componentOrder.forEach(defIdx => {
    const def = COMPONENT_DEFS[defIdx];
    const colorOpts = def.colors.map(c =>
      `<option value="${c.value}"${def.color === c.value ? ' selected' : ''}>${c.label}</option>`
    ).join('');
    const qtyOpts = [1,2,3,4,5].map(n =>
      `<option${def.quantity === n ? ' selected' : ''}>${n}</option>`
    ).join('');

    const row = document.createElement('div');
    row.className = 'flex items-center gap-2 p-2 bg-base-200 rounded-lg border border-base-300 select-none w-full min-w-0';
    row.draggable = true;
    row.dataset.defIdx = defIdx;
    row.innerHTML = `
      <span class="cursor-grab text-base-content/30 text-base leading-none" title="Arrastar">⠿⠿</span>
      <input type="checkbox" class="checkbox checkbox-xs checkbox-primary shrink-0"
             ${def.enabled ? 'checked' : ''}
             onchange="COMPONENT_DEFS[${defIdx}].enabled=this.checked" />
      <span class="text-xs font-medium flex-1 truncate min-w-0">${def.label}</span>
      <span class="text-[10px] opacity-40 shrink-0">×</span>
      <select class="select select-bordered select-xs w-11 px-1 shrink-0"
              onchange="COMPONENT_DEFS[${defIdx}].quantity=parseInt(this.value)">${qtyOpts}</select>
      <select class="select select-bordered select-xs w-24 shrink-0"
              onchange="COMPONENT_DEFS[${defIdx}].color=this.value">${colorOpts}</select>`;

    row.addEventListener('dragstart', e => {
      dragSrcIdx = defIdx;
      e.dataTransfer.effectAllowed = 'move';
      setTimeout(() => row.classList.add('opacity-40'), 0);
    });
    row.addEventListener('dragend', () => { row.classList.remove('opacity-40'); dragSrcIdx = null; });
    row.addEventListener('dragover', e => { e.preventDefault(); row.classList.add('ring-1', 'ring-primary'); });
    row.addEventListener('dragleave', () => row.classList.remove('ring-1', 'ring-primary'));
    row.addEventListener('drop', e => {
      e.preventDefault();
      row.classList.remove('ring-1', 'ring-primary');
      if (dragSrcIdx === null || dragSrcIdx === defIdx) return;
      const fromPos = componentOrder.indexOf(dragSrcIdx);
      const toPos   = componentOrder.indexOf(defIdx);
      componentOrder.splice(fromPos, 1);
      componentOrder.splice(toPos, 0, dragSrcIdx);
      renderComponents();
    });

    container.appendChild(row);
  });
}

function getComponents() {
  return componentOrder
    .map(i => COMPONENT_DEFS[i])
    .filter(c => c.enabled)
    .map(c => ({ type: c.type, quantity: c.quantity, color: c.color }));
}

// ── Init ─────────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
  loadSites();
  loadTemplates();
  addButtonRow();
  renderComponents();
  loadAccountsForSelect();
});

// ── Button rows ──────────────────────────────────────────────────
function addButtonRow(label = '', href = '') {
  const container = document.getElementById('buttonsContainer');
  const row = document.createElement('div');
  row.className = 'flex gap-2 items-center';
  row.innerHTML = `
    <input type="text" value="${esc(label)}" placeholder="Label (ex: Get Started)"
           class="input input-bordered input-xs flex-1" style="min-width:100px;" />
    <input type="url" value="${esc(href)}" placeholder="https://exemplo.com"
           class="input input-bordered input-xs flex-[2]" style="min-width:130px;" />
    <button type="button" onclick="this.parentElement.remove()"
            class="btn btn-ghost btn-xs text-error px-1">✕</button>`;
  container.appendChild(row);
}

function getButtons() {
  const rows = document.getElementById('buttonsContainer').children;
  const out = [];
  for (const row of rows) {
    const inputs = row.querySelectorAll('input');
    const label  = inputs[0].value.trim();
    const href   = inputs[1].value.trim();
    if (href) out.push({ label: label || 'Visit Website', href });
  }
  return out;
}

// ── Generate ─────────────────────────────────────────────────────
async function generateContent() {
  const topic     = document.getElementById('topic').value.trim();
  const language  = document.getElementById('language').value;
  const wordCount = parseInt(document.getElementById('wordCount').value, 10);
  const buttons   = getButtons();
  const inclHeader = document.getElementById('inclHeader').checked;
  const inclText   = document.getElementById('inclText').checked;

  if (!topic) { alert('Informe o assunto do post.'); return; }

  const btn       = document.getElementById('btnGenerate');
  const statusEl  = document.getElementById('generateStatus');
  const statusTxt = document.getElementById('generateStatusText');
  btn.disabled = true;
  statusEl.classList.remove('hidden');
  statusTxt.textContent = 'Gerando conteúdo... pode levar 20–40s';

  try {
    const templateId = parseInt(document.getElementById('templateSelect').value || '0', 10);
    const res  = await fetch('/api/wordpress/generate', {
      method: 'POST',
      headers: headers(),
      body: JSON.stringify({
        topic, language, word_count: wordCount, buttons,
        include_header_buttons: inclHeader,
        include_text_before_buttons: inclText,
        components: getComponents(),
        template_id: templateId,
      }),
    });
    const json = await res.json();
    if (json.status !== 'success') throw new Error(json.error || 'Erro ao gerar');

    generatedHtml = json.html;
    blocks = htmlToBlocks(generatedHtml);
    blocksToHtml();

    document.getElementById('htmlPanel').classList.remove('hidden');
    document.getElementById('htmlEditor').value = generatedHtml;
    document.getElementById('btnPublish').disabled = false;
    document.getElementById('btnEditMode').classList.remove('hidden');
    document.getElementById('btnShowAddCard').classList.remove('hidden');
    document.getElementById('previewPlaceholder').style.display = 'none';

    updatePreview();
    statusTxt.textContent = 'Conteúdo gerado!';
    setTimeout(() => statusEl.classList.add('hidden'), 3000);
  } catch (err) {
    statusEl.classList.add('hidden');
    alert('Erro ao gerar: ' + err.message);
  } finally {
    btn.disabled = false;
  }
}

function onHtmlEditorChange() {
  generatedHtml = document.getElementById('htmlEditor').value;
}

function refreshPreviewFromEditor() {
  generatedHtml = document.getElementById('htmlEditor').value;
  blocks = htmlToBlocks(generatedHtml);
  updatePreview();
}

// ── Preview ──────────────────────────────────────────────────────
const PREVIEW_CSS = `
  *{box-sizing:border-box;}
  body{font-family:'Inter',sans-serif;font-size:16px;line-height:1.75;color:#1f2937;max-width:760px;margin:0 auto;padding:32px 24px;}
  h1{font-size:2em;font-weight:800;line-height:1.2;margin:0 0 20px;}
  h2{font-size:1.45em;font-weight:700;line-height:1.3;margin:40px 0 14px;}
  h3{font-size:1.15em;font-weight:600;line-height:1.4;margin:28px 0 8px;}
  p{margin:0 0 16px;}
  ul,ol{margin:0 0 16px;padding-left:28px;}
  li{margin:7px 0;}
  strong{font-weight:700;}
  hr{border:none;border-top:2px solid #e5e7eb;margin:36px 0;}
  a{color:#16a34a;}
  blockquote{border-left:4px solid #16a34a;margin:20px 0;padding:12px 20px;background:#f0fdf4;border-radius:0 8px 8px 0;}
  details summary{cursor:pointer;}
  details[open] summary{margin-bottom:4px;}
`;

const PREVIEW_HEAD = `<!doctype html><html lang="en"><head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1.0"/>
  <link rel="preconnect" href="https://fonts.googleapis.com"/>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet"/>`;

const BTN_COLORS = [
  {hex:'#16a34a',name:'Verde'},   {hex:'#2563eb',name:'Azul'},
  {hex:'#9333ea',name:'Roxo'},    {hex:'#dc2626',name:'Vermelho'},
  {hex:'#ea580c',name:'Laranja'}, {hex:'#0f766e',name:'Teal'},
  {hex:'#374151',name:'Escuro'},  {hex:'#6b7280',name:'Cinza'},
  {hex:'#f59e0b',name:'Âmbar'},
];

function updatePreview() {
  if (!generatedHtml) return;
  const desktop = document.getElementById('previewDesktop').checked;
  const iframe  = document.getElementById('previewFrame');

  if (editMode) {
    const n = blocks.length;
    const wrapped = blocks.map((html, idx) => {
      const hasBtns = hasInlineButtons(html);
      const swatches = hasBtns ? BTN_COLORS.map(c =>
        `<span class="sw" style="background:${c.hex}" title="${c.name}" onclick="parent.postMessage({type:'btn-color',index:${idx},color:'${c.hex}'},'*')"></span>`
      ).join('') : '';
      const countCtrls = hasBtns
        ? `<span class="bsep"></span><button class="tb" onclick="parent.postMessage({type:'btn-count',index:${idx},delta:1},'*')">+btn</button><button class="tb" onclick="parent.postMessage({type:'btn-count',index:${idx},delta:-1},'*')">-btn</button>`
        : '';
      const upBtn = idx > 0
        ? `<button class="tb" onclick="parent.postMessage({type:'move-block',index:${idx},direction:'up'},'*')">↑</button>`
        : `<span class="tb-ph"></span>`;
      const dnBtn = idx < n - 1
        ? `<button class="tb" onclick="parent.postMessage({type:'move-block',index:${idx},direction:'down'},'*')">↓</button>`
        : `<span class="tb-ph"></span>`;

      return `<div class="blk" data-idx="${idx}">
  <div class="blk-bar">
    <span class="blk-num">${idx + 1}</span>
    ${upBtn}${dnBtn}
    ${hasBtns ? `<span class="bsep"></span><span class="bar-lbl">Cor:</span>${swatches}${countCtrls}` : ''}
    <span class="bar-flex"></span>
    <button class="tb tb-del" onclick="parent.postMessage({type:'delete-block',index:${idx}},'*')">✕</button>
  </div>
  <div class="blk-content" contenteditable="true" data-idx="${idx}">${html}</div>
</div>`;
    }).join('\n');

    iframe.srcdoc = PREVIEW_HEAD + `
  <style>
    ${PREVIEW_CSS}
    body{padding-top:0;}
    .blk{margin-bottom:2px;}
    .blk-bar{display:flex;align-items:center;gap:3px;padding:3px 6px;background:#1e293b;border-radius:6px 6px 0 0;
             opacity:0;transition:opacity .15s;position:sticky;top:0;z-index:100;flex-wrap:wrap;}
    .blk:hover .blk-bar,.blk:focus-within .blk-bar{opacity:1;}
    .blk-content{outline:none;}
    .blk-content:hover,.blk-content:focus{outline:2px dashed #3b82f6;border-radius:0 0 4px 4px;outline-offset:-1px;}
    .blk-num{font-size:10px;color:#64748b;min-width:16px;font-weight:700;font-family:monospace;}
    .tb{background:none;border:none;color:#cbd5e1;cursor:pointer;font-size:12px;padding:2px 5px;border-radius:3px;line-height:1.3;}
    .tb:hover{background:#334155;color:#fff;}
    .tb-del{color:#f87171;}
    .tb-del:hover{background:#7f1d1d;color:#fca5a5;}
    .tb-ph{display:inline-block;width:22px;}
    .bsep{width:1px;background:#334155;height:12px;margin:0 3px;flex-shrink:0;}
    .bar-lbl{font-size:9px;color:#64748b;white-space:nowrap;}
    .bar-flex{flex:1;}
    .sw{width:12px;height:12px;border-radius:50%;cursor:pointer;border:1.5px solid transparent;display:inline-block;flex-shrink:0;transition:border-color .1s;}
    .sw:hover{border-color:#fff;}
  </style>
</head><body>${wrapped}</body></html>`;

  } else {
    iframe.srcdoc = PREVIEW_HEAD + `
  <style>${PREVIEW_CSS}</style>
</head><body>${generatedHtml}</body></html>`;
  }

  if (desktop) {
    iframe.style.minWidth = '640px';
    iframe.parentElement.style.overflowX = 'auto';
  } else {
    iframe.style.minWidth = '';
    iframe.parentElement.style.overflowX = '';
  }
}

// ── Block helpers ─────────────────────────────────────────────────
function htmlToBlocks(html) {
  const doc = new DOMParser().parseFromString('<body>' + html + '</body>', 'text/html');
  const top = Array.from(doc.body.children);
  if (top.length === 1 && ['DIV','SECTION','ARTICLE','MAIN'].includes(top[0].tagName)) {
    const inner = Array.from(top[0].children);
    if (inner.length > 1) return inner.map(el => el.outerHTML);
  }
  return top.map(el => el.outerHTML);
}

function blocksToHtml() {
  generatedHtml = blocks.join('\n');
  document.getElementById('htmlEditor').value = generatedHtml;
}

function syncFromIframe() {
  try {
    const iframeDoc = document.getElementById('previewFrame').contentDocument;
    if (!iframeDoc) return;
    const contents = iframeDoc.querySelectorAll('.blk-content');
    if (contents.length !== blocks.length) return;
    blocks = Array.from(contents).map(el => el.innerHTML.trim());
    generatedHtml = blocks.join('\n');
    document.getElementById('htmlEditor').value = generatedHtml;
  } catch(e) {}
}

function syncAndApply(fn) {
  syncFromIframe();
  fn();
  blocksToHtml();
  updatePreview();
}

function hasInlineButtons(html) {
  return /<a[^>]+style="[^"]*background/.test(html);
}

function moveBlock(idx, dir) {
  if (dir === 'up' && idx > 0)
    [blocks[idx-1], blocks[idx]] = [blocks[idx], blocks[idx-1]];
  else if (dir === 'down' && idx < blocks.length - 1)
    [blocks[idx], blocks[idx+1]] = [blocks[idx+1], blocks[idx]];
}

function setButtonColor(idx, color) {
  const doc = new DOMParser().parseFromString('<body>' + blocks[idx] + '</body>', 'text/html');
  doc.body.querySelectorAll('a').forEach(a => {
    if (a.style.background || a.style.backgroundColor) {
      a.style.background = color;
      a.style.backgroundColor = '';
    }
  });
  blocks[idx] = Array.from(doc.body.children).map(el => el.outerHTML).join('\n');
}

function changeButtonCount(idx, delta) {
  const doc = new DOMParser().parseFromString('<body>' + blocks[idx] + '</body>', 'text/html');
  const allBtns = Array.from(doc.body.querySelectorAll('a')).filter(a => a.style.background || a.style.backgroundColor);
  if (!allBtns.length) return;
  const parent = allBtns[0].parentElement;
  if (delta > 0) {
    parent.appendChild(allBtns[allBtns.length - 1].cloneNode(true));
  } else if (delta < 0 && allBtns.length > 1) {
    allBtns[allBtns.length - 1].remove();
  }
  blocks[idx] = Array.from(doc.body.children).map(el => el.outerHTML).join('\n');
}

function buildCardHtml(title, text, btnLabel, btnHref, imgB64, imgMime) {
  const e = v => String(v).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
  const imgHtml = imgB64
    ? `<img src="data:${imgMime};base64,${imgB64}" style="width:100%;height:200px;object-fit:cover;display:block;" alt="${e(title)}" />`
    : `<div style="width:100%;height:120px;background:#f3f4f6;display:flex;align-items:center;justify-content:center;color:#9ca3af;font-size:13px;">⟳ Gerando imagem...</div>`;
  return `<div style="border:1px solid #e5e7eb;border-radius:12px;overflow:hidden;max-width:480px;margin:32px auto;">${imgHtml}<div style="padding:24px;">${title ? `<h3 style="margin:0 0 8px;font-size:1.2em;font-weight:700;">${e(title)}</h3>` : ''}${text ? `<p style="margin:0 0 16px;color:#4b5563;">${e(text)}</p>` : ''}<a href="${e(btnHref)}" style="display:inline-block;padding:10px 24px;background:#16a34a;color:#fff;border-radius:8px;font-weight:600;text-decoration:none;">${e(btnLabel)}</a></div></div>`;
}

async function generateAutoCardImage(cardIdx, title) {
  try {
    const res  = await fetch('/api/wordpress/generate-featured-image', {
      method: 'POST', headers: headers(),
      body: JSON.stringify({ topic: title, title }),
    });
    const json = await res.json();
    if (json.status !== 'success') return;
    if (cardIdx < blocks.length) {
      const card = blocks[cardIdx];
      blocks[cardIdx] = card.replace(
        /src="data:[^"]*"|style="[^"]*height:120px[^"]*"/,
        `src="data:${json.mime_type};base64,${json.data}"`
      );
      blocksToHtml();
      updatePreview();
    }
  } catch(e) {}
}

// ── Visual edit mode ──────────────────────────────────────────────
function toggleEditMode() {
  if (editMode) syncFromIframe();
  editMode = !editMode;
  const btn = document.getElementById('btnEditMode');
  btn.textContent = editMode ? '👁 Preview' : '✏ Editar';
  btn.classList.toggle('btn-warning', editMode);
  updatePreview();
}

window.addEventListener('message', e => {
  if (!e.data) return;
  const { type, index, direction, color, delta } = e.data;
  const idx = parseInt(index);
  if (isNaN(idx) || idx < 0 || idx >= blocks.length) return;

  if (type === 'delete-block') {
    syncAndApply(() => blocks.splice(idx, 1));
  } else if (type === 'move-block') {
    syncAndApply(() => moveBlock(idx, direction));
  } else if (type === 'btn-color') {
    syncAndApply(() => setButtonColor(idx, color));
  } else if (type === 'btn-count') {
    syncAndApply(() => changeButtonCount(idx, parseInt(delta)));
  }
});

// ── Add Card ──────────────────────────────────────────────────────
function showAddCard() {
  document.getElementById('btnShowAddCard').classList.add('hidden');
  const sec = document.getElementById('addCardSection');
  sec.classList.remove('hidden');
  sec.classList.add('flex');
}

function hideAddCard() {
  const sec = document.getElementById('addCardSection');
  sec.classList.add('hidden');
  sec.classList.remove('flex');
  document.getElementById('btnShowAddCard').classList.remove('hidden');
}

async function generateCardImage() {
  const title = document.getElementById('cardTitle').value.trim()
    || document.getElementById('pageTitle').value.trim()
    || document.getElementById('topic').value.trim();
  if (!title) { alert('Preencha o título do card para gerar a imagem.'); return; }

  const btn     = document.getElementById('btnGenCardImage');
  const spinner = document.getElementById('cardImageSpinner');
  btn.disabled  = true;
  spinner.classList.remove('hidden');

  try {
    const res  = await fetch('/api/wordpress/generate-featured-image', {
      method: 'POST', headers: headers(),
      body: JSON.stringify({ topic: title, title }),
    });
    const json = await res.json();
    if (json.status !== 'success') throw new Error(json.error || 'Erro');

    cardImageB64  = json.data;
    cardImageMime = json.mime_type;

    document.getElementById('cardImagePreview').src = `data:${json.mime_type};base64,${json.data}`;
    const area = document.getElementById('cardImageArea');
    area.classList.remove('hidden');
    area.classList.add('flex');
  } catch (err) {
    alert('Erro ao gerar imagem: ' + err.message);
  } finally {
    btn.disabled = false;
    spinner.classList.add('hidden');
  }
}

function clearCardImage() {
  cardImageB64  = '';
  cardImageMime = '';
  document.getElementById('cardImagePreview').src = '';
  const area = document.getElementById('cardImageArea');
  area.classList.add('hidden');
  area.classList.remove('flex');
}

function insertCard() {
  const title   = document.getElementById('cardTitle').value.trim();
  const text    = document.getElementById('cardText').value.trim();
  const btnLbl  = document.getElementById('cardBtnLabel').value.trim() || 'Saiba mais';
  const btnHref = document.getElementById('cardBtnHref').value.trim() || '#';

  if (!title && !text) { alert('Preencha pelo menos o título ou o texto do card.'); return; }

  blocks.push(buildCardHtml(title, text, btnLbl, btnHref, cardImageB64, cardImageMime));
  blocksToHtml();
  updatePreview();

  document.getElementById('cardTitle').value    = '';
  document.getElementById('cardText').value     = '';
  document.getElementById('cardBtnLabel').value = '';
  document.getElementById('cardBtnHref').value  = '';
  clearCardImage();
  hideAddCard();
}

// ── Publish ──────────────────────────────────────────────────────
async function publishPage() {
  if (!generatedHtml) { alert('Gere o conteúdo primeiro.'); return; }

  const siteId   = document.getElementById('siteSelect').value;
  const title    = document.getElementById('pageTitle').value.trim();
  const status   = document.getElementById('pageStatus').value;
  const postType = document.getElementById('postType').value;

  if (!siteId) { alert('Selecione um site WordPress.'); return; }
  if (!title)  { alert('Informe o título do post.'); return; }

  const btn = document.getElementById('btnPublish');
  btn.disabled = true;
  btn.textContent = 'Publicando...';

  const resultEl = document.getElementById('publishResult');
  resultEl.className = 'hidden';

  try {
    const res  = await fetch('/api/wordpress/pages', {
      method: 'POST',
      headers: headers(),
      body: JSON.stringify({
        site_id: parseInt(siteId),
        title,
        html_content: generatedHtml,
        status,
        post_type: postType,
        ...(featuredImageB64 ? { featured_image_b64: featuredImageB64, featured_image_mime: featuredImageMime } : {}),
      }),
    });
    const json = await res.json();
    if (json.status !== 'success') throw new Error(json.error || 'Erro ao publicar');

    const { link, edit_link, id, content_rlen } = json.data;
    const typeLabel   = postType === 'page' ? 'Página' : 'Post';
    const statusLabel = status === 'draft' ? 'rascunho' : 'publicado';
    const viewLabel   = postType === 'page' ? 'Ver página →' : 'Ver post →';
    const debugInfo   = content_rlen > 0
      ? `rendered: ${content_rlen} chars ✓`
      : `rendered: 0 chars ⚠ WordPress filtrou o HTML — usuário precisa ser Administrator`;

    resultEl.className = 'alert alert-success';
    resultEl.innerHTML = `
      <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
      </svg>
      <div class="flex-1">
        <p class="font-bold text-sm">${typeLabel} criado com sucesso! (ID ${id} — ${statusLabel})</p>
        <p class="text-xs opacity-60 mt-1">${debugInfo}</p>
        <div class="flex flex-wrap gap-3 mt-1">
          ${link ? `<a href="${link}" target="_blank" class="link link-success text-xs font-semibold">${viewLabel}</a>` : ''}
          ${edit_link ? `<a href="${edit_link}" target="_blank" class="link text-xs opacity-60">Editar no WP Admin</a>` : ''}
        </div>
      </div>`;
  } catch (err) {
    resultEl.className = 'alert alert-error';
    resultEl.innerHTML = `<span class="font-semibold text-sm">Erro: ${esc(err.message)}</span>`;
  } finally {
    btn.disabled = false;
    btn.innerHTML = `<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
            d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
      Publicar no WP`;
  }
}

// ── Accounts (para select no form de sites) ───────────────────────
async function loadAccountsForSelect() {
  try {
    const res  = await fetch('/api/accounts', { headers: headers() });
    const json = await res.json();
    allAccounts = json.data || [];
    const sel = document.getElementById('fAccountId');
    if (!sel) return;
    allAccounts.forEach(a => {
      const opt = document.createElement('option');
      opt.value = a.id;
      opt.textContent = (a.label || a.account_key) + (a.av_domain ? ' — ' + a.av_domain : '');
      sel.appendChild(opt);
    });
  } catch (e) {
    console.error('loadAccountsForSelect:', e);
  }
}

// ── Sites CRUD ───────────────────────────────────────────────────
async function loadSites() {
  try {
    const res  = await fetch('/api/wordpress/sites', { headers: headers() });
    const json = await res.json();
    sites = json.data || [];
    renderSitesSelect();
    renderSitesTable();
  } catch (e) {
    console.error('loadSites:', e);
  }
}

function renderSitesSelect() {
  const sel = document.getElementById('siteSelect');
  const cur = sel.value;
  sel.innerHTML = '<option value="">Selecione um site...</option>';
  sites.forEach(s => {
    const opt = document.createElement('option');
    opt.value = s.id;
    opt.textContent = s.label + ' — ' + s.url;
    if (String(s.id) === cur) opt.selected = true;
    sel.appendChild(opt);
  });
}

function renderSitesTable() {
  const wrap = document.getElementById('sitesTableWrap');
  if (!sites.length) {
    wrap.innerHTML = '<p class="text-xs opacity-50 italic">Nenhum site cadastrado ainda.</p>';
    return;
  }
  wrap.innerHTML = `
    <div class="overflow-x-auto">
      <table class="table table-sm w-full">
        <thead><tr class="bg-base-200 text-xs">
          <th>Label</th><th>URL</th><th>Conta</th>${IS_ADMIN ? '<th class="text-right">Ações</th>' : ''}
        </tr></thead>
        <tbody>
          ${sites.map(s => `
            <tr>
              <td class="font-semibold text-xs">${esc(s.label)}</td>
              <td class="text-xs opacity-70 max-w-[160px] truncate">${esc(s.url)}</td>
              <td class="text-xs opacity-70">${esc(s.account_label || '—')}</td>
              ${IS_ADMIN ? `<td class="text-right">
                <button onclick="editSite(${s.id})" class="btn btn-ghost btn-xs">✏</button>
                <button onclick="deleteSite(${s.id})" class="btn btn-ghost btn-xs text-error">✕</button>
              </td>` : ''}
            </tr>`).join('')}
        </tbody>
      </table>
    </div>`;
}

function editSite(id) {
  const s = sites.find(x => x.id === id);
  if (!s) return;
  document.getElementById('editSiteId').value = s.id;
  document.getElementById('fLabel').value     = s.label;
  document.getElementById('fUrl').value       = s.url;
  document.getElementById('fUser').value      = s.wp_username;
  document.getElementById('fPass').value      = '';
  document.getElementById('fPass').required   = false;
  document.getElementById('fPassHint').style.display = '';
  const accSel = document.getElementById('fAccountId');
  if (accSel) accSel.value = s.account_id ?? '';
  document.getElementById('siteFormBtnText').textContent = 'Atualizar Site';
}

function resetSiteForm() {
  document.getElementById('editSiteId').value = '';
  document.getElementById('siteForm').reset();
  document.getElementById('fPass').required   = true;
  document.getElementById('fPassHint').style.display = 'none';
  const accSel = document.getElementById('fAccountId');
  if (accSel) accSel.value = '';
  document.getElementById('siteFormBtnText').textContent = 'Salvar Site';
}

async function saveSite(e) {
  e.preventDefault();
  const id   = document.getElementById('editSiteId').value;
  const pass = document.getElementById('fPass').value;
  const accSel    = document.getElementById('fAccountId');
  const accountId = accSel ? accSel.value : '';
  const body = {
    label:       document.getElementById('fLabel').value.trim(),
    url:         document.getElementById('fUrl').value.trim(),
    wp_username: document.getElementById('fUser').value.trim(),
    account_id:  accountId !== '' ? parseInt(accountId) : null,
  };
  if (pass) body.wp_app_password = pass;

  const btn     = e.target.querySelector('[type=submit]');
  const btnSpan = document.getElementById('siteFormBtnText');
  btn.disabled = true;
  btnSpan.textContent = 'Salvando...';

  try {
    const url    = id ? `/api/wordpress/sites/${id}` : '/api/wordpress/sites';
    const method = id ? 'PUT' : 'POST';
    const res    = await fetch(url, { method, headers: headers(), body: JSON.stringify(body) });
    const json   = await res.json();
    if (json.status !== 'success') throw new Error(json.error || 'Erro ao salvar');
    await loadSites();
    resetSiteForm();
  } catch (err) {
    alert('Erro: ' + err.message);
  } finally {
    btn.disabled = false;
    btnSpan.textContent = id ? 'Atualizar Site' : 'Salvar Site';
  }
}

async function deleteSite(id) {
  if (!confirm('Remover este site? A ação não pode ser desfeita.')) return;
  await fetch(`/api/wordpress/sites/${id}`, { method: 'DELETE', headers: headers() });
  await loadSites();
}

// ── Templates ────────────────────────────────────────────────────
async function loadTemplates() {
  try {
    const res  = await fetch('/api/wordpress/templates', { headers: headers() });
    const data = await res.json();
    if (!Array.isArray(data)) return;
    templates = data;

    const sel = document.getElementById('templateSelect');
    const prev = sel.value;
    sel.innerHTML = '<option value="0">— Sem template (prompt padrão) —</option>';
    for (const tpl of templates) {
      const opt = document.createElement('option');
      opt.value = tpl.id;
      opt.textContent = tpl.name + (tpl.is_system ? ' ★' : '');
      sel.appendChild(opt);
    }
    if (prev && [...sel.options].some(o => o.value === prev)) {
      sel.value = prev;
    } else {
      sel.value = '0';
    }

    renderTemplatesList();
  } catch (_) {}
}

function renderTemplatesList() {
  const wrap = document.getElementById('templatesListWrap');
  if (!wrap) return;
  if (!templates.length) {
    wrap.innerHTML = '<div class="text-xs opacity-50 italic">Nenhum template cadastrado.</div>';
    return;
  }
  wrap.innerHTML = '<div class="flex flex-col gap-2">' + templates.map(tpl => `
    <div class="flex items-start justify-between gap-2 p-3 border border-base-300 rounded-lg">
      <div class="flex flex-col gap-0.5 min-w-0">
        <div class="flex items-center gap-2 flex-wrap">
          <span class="font-semibold text-sm">${esc(tpl.name)}</span>
          ${tpl.is_system ? '<span class="badge badge-xs badge-neutral">Sistema</span>' : '<span class="badge badge-xs badge-primary">Custom</span>'}
        </div>
        ${tpl.description ? `<span class="text-[11px] opacity-60">${esc(tpl.description)}</span>` : ''}
        ${tpl.source_url  ? `<a href="${esc(tpl.source_url)}" target="_blank" class="text-[10px] text-primary opacity-70 truncate">${esc(tpl.source_url)}</a>` : ''}
      </div>
      <div class="flex gap-1 shrink-0">
        ${(IS_ADMIN && !tpl.is_system) ? `<button onclick="editTemplate(${tpl.id})" class="btn btn-ghost btn-xs border border-base-300">Editar</button>` : ''}
        ${(IS_ADMIN && !tpl.is_system) ? `<button onclick="deleteTemplate(${tpl.id})" class="btn btn-ghost btn-xs text-error border border-error/30">✕</button>` : ''}
      </div>
    </div>`).join('') + '</div>';
}

function openTemplatesModal() {
  document.getElementById('templatesModal').showModal();
  loadTemplates();
}

function resetTemplateForm() {
  document.getElementById('editTemplateId').value = '';
  document.getElementById('tplName').value = '';
  document.getElementById('tplDescription').value = '';
  document.getElementById('tplSourceUrl').value = '';
  document.getElementById('tplHtml').value = '';
  document.getElementById('tplFormBtnText').textContent = 'Salvar Template';
}

async function editTemplate(id) {
  const res  = await fetch(`/api/wordpress/templates/${id}`, { headers: headers() });
  if (!res.ok) return;
  const full = await res.json();
  document.getElementById('editTemplateId').value    = id;
  document.getElementById('tplName').value           = full.name || '';
  document.getElementById('tplDescription').value    = full.description || '';
  document.getElementById('tplSourceUrl').value      = full.source_url || '';
  document.getElementById('tplHtml').value           = full.html || '';
  document.getElementById('tplFormBtnText').textContent = 'Atualizar Template';
  document.getElementById('tplName').focus();
}

async function saveTemplate(e) {
  e.preventDefault();
  const id   = document.getElementById('editTemplateId').value;
  const body = {
    name:        document.getElementById('tplName').value.trim(),
    description: document.getElementById('tplDescription').value.trim(),
    source_url:  document.getElementById('tplSourceUrl').value.trim(),
    html:        document.getElementById('tplHtml').value.trim(),
  };
  const btn = document.getElementById('tplFormBtnText');
  btn.textContent = 'Salvando...';
  try {
    const method = id ? 'PUT' : 'POST';
    const url    = id ? `/api/wordpress/templates/${id}` : '/api/wordpress/templates';
    const res    = await fetch(url, { method, headers: headers(), body: JSON.stringify(body) });
    const json   = await res.json();
    if (json.status !== 'success' && !res.ok) throw new Error(json.error || 'Erro ao salvar');
    resetTemplateForm();
    await loadTemplates();
  } catch (err) {
    alert('Erro: ' + err.message);
  } finally {
    btn.textContent = id ? 'Atualizar Template' : 'Salvar Template';
  }
}

async function deleteTemplate(id) {
  if (!confirm('Remover este template? Ação não pode ser desfeita.')) return;
  const res  = await fetch(`/api/wordpress/templates/${id}`, { method: 'DELETE', headers: headers() });
  const json = await res.json();
  if (!res.ok) { alert(json.error || 'Erro ao remover'); return; }
  await loadTemplates();
}

async function extractFromUrl() {
  const url = document.getElementById('tplSourceUrl').value.trim();
  if (!url) { alert('Informe a URL antes de extrair.'); return; }
  const btn  = document.getElementById('extractBtnText');
  const spin = document.getElementById('extractSpinner');
  btn.textContent = 'Extraindo...';
  spin.classList.remove('hidden');
  try {
    const res  = await fetch('/api/wordpress/templates/generate-from-url', {
      method: 'POST', headers: headers(), body: JSON.stringify({ url }),
    });
    const json = await res.json();
    if (json.status !== 'success') throw new Error(json.error || 'Erro ao extrair');
    document.getElementById('tplHtml').value = json.html;
  } catch (err) {
    alert('Erro ao extrair: ' + err.message);
  } finally {
    btn.textContent = '⟳ Extrair';
    spin.classList.add('hidden');
  }
}

// ── Utils ────────────────────────────────────────────────────────
function esc(v) {
  return String(v ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;')
    .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}
</script>

<?php
$pageContent = ob_get_clean();
require __DIR__ . '/../layout.php';

<?php
$pageTitle   = $pageTitle   ?? 'Meta Ads Dashboard';
$pageContent = $pageContent ?? '';
$pageScripts = $pageScripts ?? '';

$uri         = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
$isAccounts  = str_starts_with($uri, '/accounts');
$isGenerator = str_starts_with($uri, '/generator');
?>
<!DOCTYPE html>
<html lang="pt-BR" data-theme="light">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($pageTitle) ?> — Meta Ads</title>
  <link href="https://cdn.jsdelivr.net/npm/daisyui@4/dist/full.min.css" rel="stylesheet">
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
  <?php if (!empty($GLOBALS['_authToken'])): ?>
  <script>
    try { sessionStorage.setItem('apiKey', <?= json_encode($GLOBALS['_authToken']) ?>); } catch(e){}
  </script>
  <?php endif; ?>
  <style>
    .prose { max-width: none; }
    .prose h1, .prose h2, .prose h3 { font-weight: 700; margin-bottom: .5rem; }
    .prose ul { list-style: disc; padding-left: 1.5rem; }
    .prose strong { font-weight: 700; }
  </style>
</head>
<body class="bg-base-200">

<div class="drawer lg:drawer-open">
  <input id="sidebar-toggle" type="checkbox" class="drawer-toggle" />

  <div class="drawer-content flex flex-col min-h-screen">

    <!-- Navbar -->
    <header class="navbar bg-base-100 border-b border-base-300 sticky top-0 z-30 px-4 gap-2 shadow-sm">
      <div class="flex-none lg:hidden">
        <label for="sidebar-toggle" aria-label="Abrir menu" class="btn btn-square btn-ghost btn-sm">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
          </svg>
        </label>
      </div>
      <div class="flex-1 min-w-0 px-1">
        <h1 class="font-semibold text-sm truncate opacity-80"><?= htmlspecialchars($pageTitle) ?></h1>
      </div>
      <div class="flex-none flex items-center gap-2">
        <div class="hidden sm:flex items-center gap-2">
          <input type="password" id="apiKeyInput" placeholder="Bearer token"
                 class="input input-sm input-bordered w-40 lg:w-48">
          <button onclick="connectApi()" class="btn btn-sm btn-primary">Conectar</button>
        </div>
        <a href="/logout" class="btn btn-sm btn-ghost btn-error">Sair</a>
      </div>
    </header>

    <!-- Conteúdo -->
    <main class="flex-1 p-4 lg:p-6">
      <?= $pageContent ?>
    </main>
  </div>

  <!-- Sidebar -->
  <div class="drawer-side z-40">
    <label for="sidebar-toggle" aria-label="Fechar menu" class="drawer-overlay"></label>
    <aside class="bg-base-100 border-r border-base-300 min-h-screen w-64 flex flex-col">
      <div class="p-4 border-b border-base-300">
        <div class="flex items-center gap-2">
          <div class="w-8 h-8 bg-primary rounded-lg flex items-center justify-center">
            <svg class="w-5 h-5 text-white" fill="currentColor" viewBox="0 0 24 24">
              <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>
            </svg>
          </div>
          <span class="font-bold text-lg">Meta Ads</span>
        </div>
      </div>
      <nav class="flex-1 p-3 space-y-1">
        <a href="/accounts"  class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium transition-colors <?= $isAccounts ? 'bg-primary text-primary-content' : 'hover:bg-base-200' ?>">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
          Contas
        </a>
        <a href="/generator" class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium transition-colors <?= $isGenerator ? 'bg-primary text-primary-content' : 'hover:bg-base-200' ?>">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
          Gerador de Campanhas
        </a>
      </nav>
    </aside>
  </div>
</div>

<script>
function apiKey() {
  return sessionStorage.getItem('apiKey') || document.getElementById('apiKeyInput')?.value || '';
}
function connectApi() {
  const k = document.getElementById('apiKeyInput').value.trim();
  if (k) { sessionStorage.setItem('apiKey', k); location.reload(); }
}
async function apiFetch(url, opts = {}) {
  const res = await fetch(url, {
    ...opts,
    headers: { 'Authorization': 'Bearer ' + apiKey(), 'Content-Type': 'application/json', ...(opts.headers || {}) }
  });
  if (!res.ok) throw new Error(await res.text());
  return res.json();
}
</script>
<?= $pageScripts ?>
</body>
</html>

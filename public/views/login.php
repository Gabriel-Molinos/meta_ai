<!DOCTYPE html>
<html lang="pt-BR" data-theme="light">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Login — Meta Ads</title>
  <link href="https://cdn.jsdelivr.net/npm/daisyui@4/dist/full.min.css" rel="stylesheet">
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-base-200 min-h-screen flex items-center justify-center">
  <div class="card w-full max-w-sm bg-base-100 shadow-xl">
    <div class="card-body gap-4">
      <div class="flex items-center gap-3">
        <div class="w-10 h-10 bg-primary rounded-xl flex items-center justify-center">
          <svg class="w-6 h-6 text-white" fill="currentColor" viewBox="0 0 24 24">
            <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>
          </svg>
        </div>
        <div>
          <h2 class="text-xl font-bold">Meta Ads</h2>
          <p class="text-xs opacity-60">Sistema de Análise</p>
        </div>
      </div>

      <?php if (!empty($error)): ?>
      <div class="alert alert-error">
        <span><?= $error ?></span>
      </div>
      <?php endif; ?>

      <a href="/oauth/google" class="btn btn-primary w-full gap-2">
        <svg class="w-5 h-5" viewBox="0 0 24 24">
          <path fill="currentColor" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
          <path fill="currentColor" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
          <path fill="currentColor" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/>
          <path fill="currentColor" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/>
        </svg>
        Entrar com Google
      </a>

      <div class="divider text-xs opacity-50">acesso administrativo</div>

      <div class="form-control gap-2">
        <input type="password" id="password" placeholder="API Key / Bearer Token"
               class="input input-bordered w-full input-sm"
               onkeydown="if(event.key==='Enter') adminLogin()">
        <button onclick="adminLogin()" class="btn btn-outline btn-sm w-full">Entrar como Admin</button>
      </div>

      <div id="error-msg" class="alert alert-error hidden">
        <span id="error-text"></span>
      </div>
    </div>
  </div>

  <script>
  async function adminLogin() {
    const password = document.getElementById('password').value.trim();
    if (!password) return;
    try {
      const res = await fetch('/api/auth/admin-login', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ password })
      });
      if (!res.ok) {
        const err = await res.json();
        showError(err.message || 'Credenciais inválidas');
        return;
      }
      location.href = '/generator';
    } catch (e) {
      showError('Erro de conexão: ' + e.message);
    }
  }
  function showError(msg) {
    document.getElementById('error-text').textContent = msg;
    document.getElementById('error-msg').classList.remove('hidden');
  }
  </script>
</body>
</html>

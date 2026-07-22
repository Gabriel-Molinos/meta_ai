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
    <div class="card-body">
      <div class="flex items-center gap-3 mb-4">
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

      <div id="error" class="alert alert-error hidden mb-3">
        <span id="errorMsg"></span>
      </div>

      <div class="form-control gap-3">
        <label class="label"><span class="label-text font-medium">API Key / Bearer Token</span></label>
        <input type="password" id="password" placeholder="••••••••••••••••"
               class="input input-bordered w-full"
               onkeydown="if(event.key==='Enter') login()">
        <button onclick="login()" class="btn btn-primary w-full">Entrar</button>
      </div>

      <div class="mt-4 p-3 bg-base-200 rounded-lg">
        <p class="text-xs opacity-60 leading-relaxed">
          Use o token configurado em <code>APP_API_KEY</code> no arquivo <code>.env</code> para acessar o sistema.
        </p>
      </div>
    </div>
  </div>

  <script>
  async function login() {
    const password = document.getElementById('password').value.trim();
    if (!password) return;

    try {
      const res = await fetch('/api/auth/login', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ password })
      });

      if (!res.ok) {
        const err = await res.json();
        showError(err.message || 'Credenciais inválidas');
        return;
      }

      sessionStorage.setItem('apiKey', password);
      location.href = '/dashboard';
    } catch (e) {
      showError('Erro de conexão: ' + e.message);
    }
  }

  function showError(msg) {
    document.getElementById('errorMsg').textContent = msg;
    document.getElementById('error').classList.remove('hidden');
  }
  </script>
</body>
</html>

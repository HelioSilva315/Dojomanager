<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Entrar — <?= APP_NAME ?></title>
<link rel="stylesheet" href="/assets/css/app.css">
</head>
<body class="bg-page">

<div class="login-layout">
  <!-- Painel esquerdo: hero -->
  <div class="login-hero" aria-hidden="true">
    <div class="login-hero-content">
      <?php if ($logoPath): ?>
        <img src="<?= htmlspecialchars($logoPath) ?>" alt="Logo da academia" class="login-logo-img">
      <?php else: ?>
        <svg width="64" height="64" viewBox="0 0 64 64" fill="none" aria-hidden="true">
          <rect width="64" height="64" rx="16" fill="rgba(255,255,255,0.15)"/>
          <path d="M32 12L20 26h24L32 12zM20 26v26h24V26H20z" fill="rgba(255,255,255,0.9)"/>
        </svg>
      <?php endif; ?>
      <h1 class="login-hero-title"><?= APP_NAME ?></h1>
      <p class="login-hero-sub">Gestão completa da sua academia de artes marciais</p>
    </div>
  </div>

  <!-- Painel direito: formulário -->
  <div class="login-form-panel">
    <div class="login-form-wrap">
      <h2>Bem-vindo de volta</h2>
      <p class="text-muted mb-6">Insira suas credenciais para acessar o sistema.</p>

      <?php if (!empty($_SESSION['flash_erro'])): ?>
        <div class="alert alert-danger" role="alert">
          <?= htmlspecialchars($_SESSION['flash_erro']) ?>
        </div>
        <?php unset($_SESSION['flash_erro']); ?>
      <?php endif; ?>

      <form method="POST" action="/login" novalidate>
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrfToken) ?>">

        <div class="form-group">
          <label for="email">E-mail</label>
          <input type="email" id="email" name="email" required
                 autocomplete="email" placeholder="seu@email.com.br"
                 class="form-control">
        </div>

        <div class="form-group">
          <label for="senha">Senha</label>
          <div class="input-group">
            <input type="password" id="senha" name="senha" required
                   autocomplete="current-password" placeholder="••••••••"
                   class="form-control">
            <button type="button" class="btn-eye" aria-label="Mostrar senha"
                    onclick="toggleSenha('senha',this)">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>
              </svg>
            </button>
          </div>
        </div>

        <button type="submit" class="btn btn-primary btn-full mt-4">Entrar</button>
      </form>
    </div>
  </div>
</div>

<script>
function toggleSenha(id, btn) {
  const i = document.getElementById(id);
  i.type = i.type === 'password' ? 'text' : 'password';
}
</script>
</body>
</html>

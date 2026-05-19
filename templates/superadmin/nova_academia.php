<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Nova Academia — <?= APP_NAME ?></title>
<link rel="stylesheet" href="/assets/css/app.css">
</head>
<body>
<div class="app-layout">
  <aside class="sidebar">
    <a href="/superadmin" class="sidebar-brand">
      <div class="brand-icon">⚡</div>
      <div><div class="brand-text"><?= APP_NAME ?></div><div class="brand-sub">Super Admin</div></div>
    </a>
    <nav class="sidebar-nav">
      <a href="/superadmin" class="nav-link">🏠 Dashboard Global</a>
      <a href="/superadmin/academias/nova" class="nav-link active">➕ Nova Academia</a>
      <a href="/logout" class="nav-link">🚪 Sair</a>
    </nav>
  </aside>

  <div class="main-content">
    <header class="topbar">
      <span class="topbar-title">Nova Academia</span>
    </header>
    <div class="page-body">

      <?php if (!empty($_SESSION['flash_erros'])): ?>
        <div class="alert alert-danger">
          <?php foreach ($_SESSION['flash_erros'] as $e): ?>
            <div>• <?= htmlspecialchars($e) ?></div>
          <?php endforeach; unset($_SESSION['flash_erros']); ?>
        </div>
      <?php endif; ?>

      <div class="card" style="max-width:680px">
        <div class="card-title">🏫 Dados da Academia</div>
        <form method="POST" action="/superadmin/academias">
          <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrfToken) ?>">

          <div class="form-row">
            <div class="form-group" style="grid-column:1/-1">
              <label class="required">Nome da Academia</label>
              <input type="text" name="nome" class="form-control" placeholder="Ex: Academia Tigre" required>
            </div>
            <div class="form-group">
              <label class="required">E-mail da Academia</label>
              <input type="email" name="email" class="form-control" placeholder="contato@academia.com.br" required>
            </div>
            <div class="form-group">
              <label class="required">Plano</label>
              <select name="plano_id" class="form-control" required>
                <?php foreach ($planos as $p): ?>
                  <option value="<?= $p['id'] ?>">
                    <?= htmlspecialchars($p['nome']) ?> — até <?= $p['max_alunos'] ?> alunos
                    (R$ <?= number_format($p['preco_mensal'],2,',','.') ?>/mês)
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>

          <div class="form-row" style="margin-top:20px">
            <div class="form-group" style="grid-column:1/-1">
              <div style="font-size:13px;font-weight:600;color:var(--text-muted);border-bottom:0.5px solid var(--border);padding-bottom:6px;margin-bottom:4px">
                👤 Administrador da Academia
              </div>
            </div>
            <div class="form-group">
              <label class="required">Nome do Admin</label>
              <input type="text" name="admin_nome" class="form-control" placeholder="Nome completo" required>
            </div>
            <div class="form-group">
              <label class="required">E-mail do Admin</label>
              <input type="email" name="admin_email" class="form-control" placeholder="admin@academia.com.br" required>
            </div>
            <div class="form-group" style="grid-column:1/-1">
              <label class="required">Senha do Admin</label>
              <input type="password" name="admin_senha" class="form-control"
                     placeholder="Mínimo 8 caracteres, maiúscula, número e símbolo" required>
              <span style="font-size:11px;color:var(--text-muted)">
                Ex: Academia@2026 — mínimo 8 chars, 1 maiúscula, 1 número, 1 símbolo
              </span>
            </div>
          </div>

          <div class="alert alert-info" style="margin-top:16px">
            ℹ️ A academia iniciará com <strong>30 dias de trial gratuito</strong>.
            As modalidades padrão (Jiu-Jitsu, Muay Thai, Karatê, Judô, Boxe) serão criadas automaticamente.
          </div>

          <div style="display:flex;gap:8px;justify-content:flex-end;margin-top:20px">
            <a href="/superadmin" class="btn">Cancelar</a>
            <button type="submit" class="btn btn-primary">✅ Criar Academia</button>
          </div>
        </form>
      </div>

    </div>
  </div>
</div>
</body>
</html>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Super Admin — <?= APP_NAME ?></title>
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
      <div class="nav-section">Painel</div>
      <a href="/superadmin" class="nav-link active">🏠 Dashboard Global</a>
      <a href="/superadmin/academias/nova" class="nav-link">➕ Nova Academia</a>
      <div class="nav-section">Conta</div>
      <a href="/logout" class="nav-link">🚪 Sair</a>
    </nav>
  </aside>

  <div class="main-content">
    <header class="topbar">
      <span class="topbar-title">Dashboard Global</span>
      <span style="font-size:13px;color:var(--text-muted)">👋 <?= htmlspecialchars($_SESSION['user_nome']) ?></span>
    </header>

    <div class="page-body">

      <?php if (!empty($_SESSION['flash_ok'])): ?>
        <div class="alert alert-success"><?= htmlspecialchars($_SESSION['flash_ok']) ?></div>
        <?php unset($_SESSION['flash_ok']); ?>
      <?php endif; ?>

      <!-- Stats globais -->
      <div class="stats-grid" style="margin-bottom:20px">
        <div class="stat-card">
          <div class="stat-label">🏫 Academias Ativas</div>
          <div class="stat-value"><?= $stats['academias_ativas'] ?></div>
          <div class="stat-sub"><?= $stats['total_academias'] ?> total</div>
        </div>
        <div class="stat-card">
          <div class="stat-label">👥 Alunos na Plataforma</div>
          <div class="stat-value"><?= number_format($stats['total_alunos']) ?></div>
          <div class="stat-sub">Todos os tenants</div>
        </div>
        <div class="stat-card">
          <div class="stat-label">👤 Usuários Cadastrados</div>
          <div class="stat-value"><?= $stats['total_usuarios'] ?></div>
          <div class="stat-sub">Excluindo super admins</div>
        </div>
        <div class="stat-card">
          <div class="stat-label">💰 MRR Estimado</div>
          <div class="stat-value">R$ <?= number_format($stats['mrr'], 2, ',', '.') ?></div>
          <div class="stat-sub">Receita mensal recorrente</div>
        </div>
      </div>

      <!-- Tabela de academias -->
      <div class="card">
        <div class="card-title">
          🏫 Academias Cadastradas
          <a href="/superadmin/academias/nova" class="btn btn-primary btn-sm" style="margin-left:auto">➕ Nova Academia</a>
        </div>
        <div class="table-wrap">
          <table>
            <thead>
              <tr>
                <th>Academia</th><th>Slug / URL</th><th>Plano</th>
                <th>Alunos</th><th>Usuários</th><th>Expira em</th><th>Status</th><th>Ações</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($academias as $ac): ?>
              <tr>
                <td>
                  <strong><?= htmlspecialchars($ac['nome']) ?></strong><br>
                  <span style="font-size:11px;color:var(--text-muted)"><?= htmlspecialchars($ac['email']) ?></span>
                </td>
                <td>
                  <code style="font-size:12px"><?= htmlspecialchars($ac['slug']) ?></code>
                </td>
                <td>
                  <span class="pill pill-blue"><?= htmlspecialchars($ac['plano_nome']) ?></span>
                  <div style="font-size:11px;color:var(--text-muted);margin-top:2px">R$ <?= number_format($ac['preco_mensal'],2,',','.') ?>/mês</div>
                </td>
                <td><?= $ac['total_alunos'] ?></td>
                <td><?= $ac['total_usuarios'] ?></td>
                <td style="font-size:12px">
                  <?php if ($ac['plano_expira_em']): ?>
                    <?php $exp = new DateTime($ac['plano_expira_em']); $hoje = new DateTime(); $diff = $hoje->diff($exp)->days; ?>
                    <span class="pill <?= $diff <= 7 ? 'pill-red' : ($diff <= 30 ? 'pill-amber' : 'pill-green') ?>">
                      <?= $exp->format('d/m/Y') ?>
                    </span>
                  <?php else: ?>—<?php endif; ?>
                </td>
                <td>
                  <span class="pill <?= $ac['ativo'] ? 'pill-green' : 'pill-red' ?>">
                    <?= $ac['ativo'] ? 'Ativa' : 'Suspensa' ?>
                  </span>
                </td>
                <td style="white-space:nowrap">
                  <a href="/superadmin/academias/<?= $ac['id'] ?>" class="btn btn-sm">Ver</a>
                  <form method="POST" action="/superadmin/academias/<?= $ac['id'] ?>/toggle" style="display:inline">
                    <input type="hidden" name="_csrf" value="<?= htmlspecialchars(DojoManager\Services\CsrfService::generate()) ?>">
                    <button type="submit" class="btn btn-sm <?= $ac['ativo'] ? '' : 'btn-primary' ?>"
                            onclick="return confirm('Confirma?')">
                      <?= $ac['ativo'] ? '🔒 Suspender' : '✅ Ativar' ?>
                    </button>
                  </form>
                </td>
              </tr>
              <?php endforeach; ?>
              <?php if (empty($academias)): ?>
              <tr><td colspan="8" style="text-align:center;color:var(--text-muted);padding:24px">
                Nenhuma academia cadastrada ainda. <a href="/superadmin/academias/nova">Criar a primeira</a>
              </td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>

    </div>
  </div>
</div>
</body>
</html>

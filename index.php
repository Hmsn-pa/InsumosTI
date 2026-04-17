<!DOCTYPE html>
<html lang="pt-BR" data-theme="light">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
  <meta name="theme-color" content="#0f172a">
  <link rel="manifest" href="manifest.json">
  <title>Insumos T.I.</title>
  <script>(function(){var t=localStorage.getItem('theme');if(t==='dark')document.documentElement.setAttribute('data-theme','dark');})()</script>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.2/dist/chart.umd.min.js"></script>
  <link rel="stylesheet" href="assets/css/app.css">
  <script>(function(){
    if(window!==window.top)document.documentElement.setAttribute("data-embed","glpi");
    var _p=new URLSearchParams(window.location.search);
    var _tok=_p.get("sso_token");
    if(_tok){
      try{localStorage.setItem("insumos_token",_tok);}catch(e){}
      var _pg=_p.get("sso_page");
      if(_pg){try{localStorage.setItem("insumos_sso_page",_pg);}catch(e){}}
      window.history.replaceState({},"",window.location.pathname);
    } else {
      var _existingTok = localStorage.getItem("insumos_token");
      if(!_existingTok && window===window.top){
        window.location.href="http://10.10.1.15/ti/plugins/insumos/front/main.php";
      }
    }
  })()</script>
  <style>[data-embed="glpi"] .sidebar,[data-embed="glpi"] .topbar,[data-embed="glpi"] .sidebar-overlay{display:none!important}[data-embed="glpi"] .main{margin-left:0!important}[data-embed="glpi"] .page-content{padding:20px!important}  /* Fix para modo embed no GLPI */
  [data-embed="glpi"] #mainContent { min-height: 100vh; width: 100%; }
  [data-embed="glpi"] .page-content.active { display: block !important; width: 100% !important; min-height: 500px !important; padding: 24px !important; }
</style>
</head>
<body>

<div class="sidebar-overlay" id="sidebarOverlay" onclick="Sidebar.close()"></div>

<aside class="sidebar" id="sidebar">
  <div class="sidebar-logo">
    <img src="icon-192.png" alt="Logo" style="flex-shrink:0">
    <div class="logo-text">Controle de Insumos<span>T.I. — Belém/PA</span></div>
  </div>
  <nav class="sidebar-nav">
    <div class="nav-label">Visão Geral</div>
    <button class="nav-item active" data-page="dashboard" data-tooltip="Dashboard" onclick="navTo('dashboard')"><span class="icon">📊</span><span class="nav-item-text"> Dashboard</span></button>
    <button class="nav-item perm-estoque" data-page="estoque" data-tooltip="Estoque" onclick="navTo('estoque')"><span class="icon">📦</span><span class="nav-item-text"> Estoque</span></button>
    <button class="nav-item perm-estoque" data-page="painel-estoque" data-tooltip="Painel de Estoque" onclick="navTo('painel-estoque')"><span class="icon">🗂️</span><span class="nav-item-text"> Painel de Estoque</span></button>
    <div class="nav-label">Movimentações</div>
    <button class="nav-item perm-entrada" data-page="entrada" data-tooltip="Entrada" onclick="navTo('entrada')"><span class="icon">⬆️</span><span class="nav-item-text"> Entrada</span></button>
    <button class="nav-item perm-saida" data-page="saida" data-tooltip="Saída" onclick="navTo('saida')"><span class="icon">⬇️</span><span class="nav-item-text"> Saída</span></button>
    <div class="nav-label">Operações</div>
    <button class="nav-item perm-inventario" data-page="inventario" data-tooltip="Inventário" onclick="navTo('inventario')"><span class="icon">🔢</span><span class="nav-item-text"> Inventário</span></button>
    <button class="nav-item perm-historico" data-page="historico" data-tooltip="Histórico" onclick="navTo('historico')"><span class="icon">📋</span><span class="nav-item-text"> Histórico</span></button>
    <button class="nav-item perm-relatorio" data-page="relatorio" data-tooltip="Relatórios" onclick="navTo('relatorio')"><span class="icon">📈</span><span class="nav-item-text"> Relatórios</span></button>
    <div class="nav-label perm-usuarios">Administração</div>
    <button class="nav-item perm-usuarios" data-page="usuarios" data-tooltip="Usuários" onclick="navTo('usuarios')"><span class="icon">👥</span><span class="nav-item-text"> Usuários</span></button>
    <div class="nav-label">Minha Conta</div>
    <button class="nav-item" data-page="perfil" data-tooltip="Meu Perfil" onclick="navTo('perfil')"><span class="icon">👤</span><span class="nav-item-text"> Meu Perfil</span></button>
  </nav>
  <div class="sidebar-bottom">
    <div class="sidebar-user-info" style="font-size:12px;color:var(--text-2);padding:0 14px 10px;display:flex;align-items:center;gap:8px">
      <span class="sidebar-user-avatar" id="sidebarUserAvatar" style="width:28px;height:28px;border-radius:50%;background:var(--brand-grad);display:flex;align-items:center;justify-content:center;color:#fff;font-size:12px;font-weight:700;flex-shrink:0">?</span>
      <div style="overflow:hidden"><div id="sidebarUserNome" style="font-weight:600;color:var(--text);font-size:13px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">...</div>
      <div id="sidebarUserPerfil" style="font-size:10px;text-transform:uppercase;letter-spacing:1px"></div></div>
    </div>
    <button class="theme-toggle" id="themeToggle" onclick="Theme.toggle()"><span>🌙</span><span class="sidebar-bottom-text"> Modo Escuro</span></button>
    <button class="theme-toggle" style="margin-top:6px;color:var(--danger)" onclick="Auth.logout()"><span>🚪</span><span class="sidebar-bottom-text"> Sair</span></button>
  </div>
</aside>

<main class="main" id="mainContent">
  <header class="topbar">
    <div class="topbar-left">
      <button class="burger-btn" id="burgerBtn" onclick="Sidebar.toggle()" title="Recolher menu">☰</button>
      <div class="topbar-title" id="pageTitle">Dashboard</div>
      <span class="topbar-badge" id="topbarPerfil">T.I.</span>
    </div>
    <div class="topbar-right">
      <div class="clock" id="clock">00:00:00</div>
    </div>
  </header>

  <!-- DASHBOARD -->
  <section class="page-content active" id="page-dashboard">
    <div class="cards-grid">
      <div class="metric-card"><div class="metric-icon">📦</div><div class="metric-value" id="metricTotal">—</div><div class="metric-label">Total em Estoque</div></div>
      <div class="metric-card danger"><div class="metric-icon">🚨</div><div class="metric-value" id="metricCriticos">—</div><div class="metric-label">Itens Críticos</div></div>
      <div class="metric-card warn"><div class="metric-icon">⚠️</div><div class="metric-value" id="metricZerados">—</div><div class="metric-label">Itens Zerados</div></div>
      <div class="metric-card info"><div class="metric-icon">🗃️</div><div class="metric-value" id="metricInsumos">—</div><div class="metric-label">Tipos de Insumo</div></div>
    </div>
    <div class="two-col">
      <div class="chart-card"><div class="card-header"><div><div class="card-title">Estoque por Insumo</div><div class="card-subtitle">Comparativo com mínimo</div></div></div><canvas id="chartEstoque" style="max-height:280px"></canvas></div>
      <div class="chart-card"><div class="card-header"><div><div class="card-title">🏆 Mais Consumidos</div><div class="card-subtitle">Últimos 30 dias</div></div></div><ul class="rank-list" id="rankingList"><li class="rank-item"><div class="skeleton" style="width:100%;height:14px"></div></li></ul></div>
    </div>
    <div class="table-card">
      <div class="card-header" style="padding:16px 20px 0"><div><div class="card-title">Últimas Movimentações</div><div class="card-subtitle">5 mais recentes</div></div><button class="btn btn-ghost btn-sm" onclick="navTo('historico')">Ver todas →</button></div>
      <div class="table-wrap"><table><thead><tr><th>Tipo</th><th>Insumo</th><th>Qtd</th><th>Responsável</th><th>Data/Hora</th></tr></thead><tbody id="ultimasTbody"><tr><td colspan="5" class="text-center text-muted">Carregando...</td></tr></tbody></table></div>
    </div>
  </section>

  <!-- ESTOQUE -->
  <section class="page-content" id="page-estoque">
    <div class="flex items-center gap-12 mb-16" style="justify-content:space-between;flex-wrap:wrap">
      <div><h2 style="font-size:17px;font-weight:700">Posição de Estoque</h2><p class="text-muted text-sm">Atualizado em tempo real</p></div>
      <button class="btn btn-ghost btn-sm" onclick="Estoque.load()">🔄 Atualizar</button>
    </div>
    <div class="table-card"><div class="table-wrap"><table><thead><tr><th>Insumo</th><th>Quantidade</th><th>Mínimo</th><th>Status</th></tr></thead><tbody id="estoqueTbody"><tr><td colspan="4" class="text-center text-muted">Carregando...</td></tr></tbody></table></div></div>
    <div class="flex gap-12 mt-16" style="flex-wrap:wrap">
      <span class="badge badge-ok">Normal</span><span class="badge badge-atencao">Atenção</span><span class="badge badge-critico">Crítico</span><span class="badge badge-zerado">Zerado</span>
    </div>
  </section>

  <!-- PAINEL DE ESTOQUE -->
  <section class="page-content" id="page-painel-estoque">

    <!-- Cabeçalho -->
    <div class="flex items-center gap-12 mb-16" style="justify-content:space-between;flex-wrap:wrap;gap:12px">
      <div>
        <h2 style="font-size:17px;font-weight:700">Painel de Estoque</h2>
        <p class="text-muted text-sm">Visão geral de todos os insumos em tempo real</p>
      </div>
      <div class="flex gap-8" style="flex-wrap:wrap">
        <input type="text" id="painelBusca" placeholder="🔍 Buscar insumo..." oninput="PainelEstoque.filtrar()" style="padding:8px 12px;border:1px solid var(--border);border-radius:8px;background:var(--surface-2);color:var(--text);font-size:13px;width:180px">
        <select id="painelFiltroStatus" onchange="PainelEstoque.filtrar()" style="padding:8px 12px;border:1px solid var(--border);border-radius:8px;background:var(--surface-2);color:var(--text);font-size:13px">
          <option value="">Todos os status</option>
          <option value="zerado">Zerado</option>
          <option value="critico">Crítico</option>
          <option value="atencao">Atenção</option>
          <option value="ok">Normal</option>
        </select>
        <button class="btn btn-ghost btn-sm" onclick="PainelEstoque.load()" title="Atualizar">🔄 Atualizar</button>
      </div>
    </div>

    <!-- Cards resumo -->
    <div class="cards-grid" style="margin-bottom:20px">
      <div class="metric-card"><div class="metric-icon">📦</div><div class="metric-value" id="painelTotalItens">—</div><div class="metric-label">Tipos de Insumo</div></div>
      <div class="metric-card"><div class="metric-icon">✅</div><div class="metric-value" id="painelTotalOk" style="background:linear-gradient(135deg,#10b981,#6ee7b7);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text">—</div><div class="metric-label">Estoque Normal</div></div>
      <div class="metric-card warn"><div class="metric-icon">⚠️</div><div class="metric-value" id="painelTotalAtencao">—</div><div class="metric-label">Em Atenção</div></div>
      <div class="metric-card danger"><div class="metric-icon">🚨</div><div class="metric-value" id="painelTotalCritico">—</div><div class="metric-label">Críticos / Zerados</div></div>
    </div>

    <!-- Grade de cards -->
    <div id="painelGrid" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:16px">
      <div class="text-center text-muted" style="grid-column:1/-1;padding:40px">Carregando...</div>
    </div>

  </section>

  <!-- ENTRADA -->
  <section class="page-content" id="page-entrada">
    <div class="mb-24"><h2 style="font-size:17px;font-weight:700">Entrada de Insumos</h2><p class="text-muted text-sm">Registre o abastecimento</p></div>
    <div class="form-card"><form id="formEntrada" autocomplete="off"><div class="form-grid">
      <div class="form-group"><label for="entradaInsumo">Insumo <span class="req">*</span></label><select id="entradaInsumo" name="insumo_id" required><option value="">Selecione...</option></select></div>
      <div class="form-group"><label for="entradaQtd">Quantidade <span class="req">*</span></label><input type="number" id="entradaQtd" name="quantidade" min="1" required></div>
      <div class="form-group full"><label for="entradaDesc">Descrição</label><input type="text" id="entradaDesc" name="descricao" placeholder="Observações adicionais..."></div>
    </div>
    <button type="submit" class="btn btn-primary btn-full mt-16 btn-submit"><span class="spinner"></span> ⬆️ Registrar Entrada</button>
    </form></div>

  </section>
  <!-- SAÍDA -->
  <section class="page-content" id="page-saida">
    <div class="mb-24"><h2 style="font-size:17px;font-weight:700">Saída de Insumos</h2><p class="text-muted text-sm">Registre a dispensação por setor</p></div>
    <div class="form-card"><form id="formSaida" autocomplete="off"><div class="form-grid">
      <div class="form-group"><label for="saidaInsumo">Insumo <span class="req">*</span></label><select id="saidaInsumo" name="insumo_id" required><option value="">Selecione...</option></select><span class="form-hint">O setor é filtrado pelo insumo</span></div>
      <div class="form-group"><label for="saidaSetor">Setor / Área <span class="req">*</span></label><select id="saidaSetor" name="setor_id" required><option value="">Selecione o insumo primeiro...</option></select></div>
      <div class="form-group"><label for="saidaQtd">Quantidade <span class="req">*</span></label><input type="number" id="saidaQtd" name="quantidade" min="1" required></div>
      
      <div class="form-group"><label for="saidaRef">ID do Chamado</label><input type="text" id="saidaRef" name="referencia" placeholder="Ex: 6643" oninput="Saida.buscarChamado(this.value)"><div id="saidaChamadoInfo" style="font-size:12px;color:var(--brand-1);margin-top:4px"></div></div>
      <div class="form-group"><label for="saidaLoc">Localização</label><input type="text" id="saidaLoc" name="localizacao" readonly placeholder="Preenchido automaticamente..."></div>
      <div class="form-group"><label for="saidaDesc">Descrição</label><input type="text" id="saidaDesc" name="descricao" placeholder="Observações adicionais..."></div>
    </div><button type="submit" class="btn btn-danger btn-full mt-16 btn-submit"><span class="spinner"></span> ⬇️ Registrar Saída</button></form></div>
  </section>

  <!-- INVENTÁRIO -->
  <section class="page-content" id="page-inventario">
    <div id="inv-novo">
      <div class="mb-16"><h2 style="font-size:17px;font-weight:700">Inventário Físico</h2><p class="text-muted text-sm">Contagem física com ajuste automático do estoque</p></div>
      <div class="two-col" style="margin-bottom:24px">
        <div class="form-card"><div class="card-header" style="margin-bottom:18px"><div><div class="card-title">📦 Novo Inventário</div><div class="card-subtitle">Todos os insumos serão listados</div></div></div>
          <form id="formAbrirInv">
            
            <div class="form-group mb-16"><label for="invObs">Observação</label><input type="text" id="invObs" name="observacao" placeholder="Ex: Inventário mensal - Abril/2025"></div>
            <button type="submit" class="btn btn-primary btn-full">▶ Iniciar Inventário</button>
          </form>
        </div>
        <div class="chart-card"><div class="card-header"><div><div class="card-title">📋 Histórico</div><div class="card-subtitle">Contagens anteriores</div></div></div><div id="invHistoricoLista" style="margin-top:8px"><p class="text-muted text-sm text-center">Carregando...</p></div></div>
      </div>
    </div>
    <div id="inv-contagem" class="hidden">
      <div class="flex items-center gap-12 mb-16" style="justify-content:space-between;flex-wrap:wrap">
        <div><h2 style="font-size:17px;font-weight:700">🔢 Contagem em Andamento</h2><p class="text-muted text-sm" id="invInfoTexto">—</p></div>
        <div class="flex gap-8" style="flex-wrap:wrap">
          <button class="btn btn-ghost btn-sm" onclick="Inventario.cancelar()">✕ Cancelar</button>
          <button class="btn btn-primary btn-sm" onclick="Inventario.salvar()">💾 Salvar</button>
          <button class="btn btn-danger btn-sm" onclick="Inventario.finalizar()">✅ Finalizar</button>
        </div>
      </div>
      <div class="chart-card mb-16" style="padding:14px 20px">
        <div class="flex items-center gap-12" style="justify-content:space-between;margin-bottom:8px">
          <span style="font-size:13px;font-weight:600">Progresso</span>
          <span id="invProgTexto" style="font-size:13px;color:var(--brand-1);font-weight:700">0 / 0</span>
        </div>
        <div class="progress" style="height:10px"><div class="progress-bar" id="invProgBar" style="width:0%"></div></div>
      </div>
      <div class="table-card"><div class="table-wrap"><table><thead><tr><th>Insumo</th><th>Unid.</th><th style="text-align:center">Qtd. Sistema</th><th style="text-align:center">Qtd. Contada *</th><th style="text-align:center">Diferença</th></tr></thead><tbody id="invCorpoContagem"></tbody></table></div></div>
      <div class="flex gap-8 mt-16" style="justify-content:flex-end;flex-wrap:wrap">
        <button class="btn btn-ghost" onclick="Inventario.cancelar()">✕ Cancelar</button>
        <button class="btn btn-primary" onclick="Inventario.salvar()">💾 Salvar Progresso</button>
        <button class="btn btn-danger" onclick="Inventario.finalizar()">✅ Finalizar e Ajustar Estoque</button>
      </div>
    </div>
    <!-- Modal detalhe -->
    <div id="invModalOverlay" class="hidden" style="position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:200;display:none;align-items:center;justify-content:center;padding:16px">
      <div style="background:var(--surface);border-radius:var(--radius);padding:24px;max-width:700px;width:100%;max-height:85vh;overflow-y:auto;box-shadow:var(--shadow-lg)">
        <div class="flex items-center gap-12 mb-16" style="justify-content:space-between"><div class="card-title" id="invModalTitulo">Detalhe</div><button class="btn btn-ghost btn-sm" onclick="Inventario.fecharModal()">✕</button></div>
        <div id="invModalConteudo"></div>
      </div>
    </div>
  </section>

  <!-- HISTÓRICO -->
  <section class="page-content" id="page-historico">
    <div class="mb-16"><h2 style="font-size:17px;font-weight:700">Histórico de Movimentações</h2><p class="text-muted text-sm">Todas as entradas e saídas</p></div>
    <div class="filter-bar">
      <div class="filter-group"><label>Tipo</label><select id="filterTipo"><option value="">Todos</option><option value="ENTRADA">Entrada</option><option value="SAIDA">Saída</option></select></div>
      <div class="filter-group"><label>Insumo</label><input type="text" id="filterInsumo" placeholder="Buscar..."></div>
      <div class="filter-group"><label>De</label><input type="date" id="filterIni"></div>
      <div class="filter-group"><label>Até</label><input type="date" id="filterFim"></div>
      <div class="filter-group" style="justify-content:flex-end;flex-direction:row;gap:8px;align-items:flex-end">
        <button class="btn btn-primary btn-sm" onclick="Historico.applyFilter()">🔍 Filtrar</button>
        <button class="btn btn-ghost btn-sm" onclick="Historico.clearFilter()">✕</button>
      </div>
    </div>
    <div class="table-card">
      <div style="padding:12px 16px;border-bottom:1px solid var(--border);font-size:12px;color:var(--text-2)"><span id="historicInfo">Carregando...</span></div>
      <div class="table-wrap"><table><thead><tr><th>Tipo</th><th>Insumo</th><th>Qtd</th><th>Responsável</th><th class="hist-col-setor">Setor</th><th class="hist-col-desc">Descrição</th><th>Data/Hora</th></tr></thead><tbody id="historicTbody"><tr><td colspan="7" class="text-center text-muted">Carregando...</td></tr></tbody></table></div>
      <div class="pagination" id="historicPag"></div>
    </div>
  </section>

  <!-- RELATÓRIOS -->
  <section class="page-content" id="page-relatorio">
    <div class="mb-16"><h2 style="font-size:17px;font-weight:700">Relatórios</h2><p class="text-muted text-sm">Gere relatórios e exporte dados</p></div>
    <div class="filter-bar" style="margin-bottom:20px">
      <div class="filter-group"><label>Tipo de Relatório</label>
        <select id="relTipo">
          <option value="movimentacoes">Movimentações</option>
          <option value="estoque_atual">Posição de Estoque</option>
          <option value="consumo_por_insumo">Consumo por Insumo</option>
          <option value="inventarios">Inventários</option>
        </select>
      </div>
      <div class="filter-group" id="relPeriodoGrp"><label>De</label><input type="date" id="relIni" value=""></div>
      <div class="filter-group" id="relPeriodoGrp2"><label>Até</label><input type="date" id="relFim" value=""></div>
      <div class="filter-group" style="justify-content:flex-end;flex-direction:row;gap:8px;align-items:flex-end">
        <button class="btn btn-primary btn-sm" onclick="Relatorio.gerar()">📊 Gerar</button>
        <button class="btn btn-ghost btn-sm" onclick="Relatorio.exportarCSV()">⬇ CSV</button>
      </div>
    </div>
    <div id="relResumo" class="cards-grid" style="display:none;margin-bottom:20px"></div>
    <div class="table-card" id="relTabela" style="display:none">
      <div class="card-header" style="padding:14px 16px 0"><div class="card-title" id="relTituloTabela">Resultado</div><div class="card-subtitle" id="relSubtitulo"></div></div>
      <div class="table-wrap"><table id="relTable"><thead id="relThead"></thead><tbody id="relTbody"></tbody></table></div>
    </div>
    <div id="relVazio" class="text-center text-muted" style="padding:40px">Selecione o tipo e clique em Gerar</div>
  </section>

  <!-- USUÁRIOS -->
  <section class="page-content" id="page-usuarios">
    <div class="flex items-center gap-12 mb-16" style="justify-content:space-between;flex-wrap:wrap">
      <div><h2 style="font-size:17px;font-weight:700">Gerenciar Usuários</h2><p class="text-muted text-sm">Crie, edite e gerencie permissões</p></div>
      <button class="btn btn-primary btn-sm" onclick="Usuarios.abrirModal()">+ Novo Usuário</button>
    </div>
    <div class="table-card">
      <div class="table-wrap"><table><thead><tr><th>Nome</th><th>E-mail</th><th>Perfil</th><th>Status</th><th>Último Login</th><th>Ações</th></tr></thead><tbody id="usuariosTbody"><tr><td colspan="6" class="text-center text-muted">Carregando...</td></tr></tbody></table></div>
    </div>
  </section>

  <!-- MEU PERFIL -->
  <section class="page-content" id="page-perfil">
    <div class="mb-24"><h2 style="font-size:17px;font-weight:700">Meu Perfil</h2><p class="text-muted text-sm">Informações da sua conta</p></div>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;max-width:800px">
      <div class="form-card">
        <div class="card-title mb-16" style="font-size:14px">👤 Dados da Conta</div>
        <div id="perfilInfo" style="font-size:14px;line-height:2">Carregando...</div>
      </div>
      <div class="form-card">
        <div class="card-title mb-16" style="font-size:14px">🔒 Alterar Senha</div>
        <form id="formSenha"><div class="form-group mb-16"><label>Senha Atual</label><input type="password" id="senhaAtual" required></div>
        <div class="form-group mb-16"><label>Nova Senha</label><input type="password" id="senhaNova" required></div>
        <div class="form-group mb-16"><label>Confirmar Nova Senha</label><input type="password" id="senhaConfirm" required></div>
        <button type="submit" class="btn btn-primary btn-full">Alterar Senha</button></form>
      </div>
    </div>
  </section>

</main>

<!-- ══ MODAL INSUMO (painel de estoque) ══ -->
<div id="insumoModal" style="display:none;position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,0.55);align-items:center;justify-content:center;padding:16px">
  <div style="background:var(--surface);border-radius:var(--radius);padding:24px;max-width:480px;width:100%;box-shadow:0 24px 80px rgba(0,0,0,0.35)">
    <div class="flex items-center gap-12" style="justify-content:space-between;margin-bottom:20px">
      <div class="card-title" id="insumoModalTitulo" style="font-size:16px">Novo Insumo</div>
      <button onclick="PainelEstoque.fecharModal()" style="background:var(--surface-2);border:1px solid var(--border);border-radius:8px;padding:6px 12px;cursor:pointer;font-size:18px;color:var(--text-2);line-height:1">✕</button>
    </div>
    <form id="formInsumo">
      <input type="hidden" id="insumoId">
      <div class="form-grid" style="gap:14px">
        <div class="form-group full">
          <label for="insumoNome">Nome do Insumo <span class="req">*</span></label>
          <input type="text" id="insumoNome" placeholder="Ex: Etiqueta 100x50" required autocomplete="off">
        </div>
        <div class="form-group">
          <label for="insumoUnidade">Unidade <span class="req">*</span></label>
          <select id="insumoUnidade">
            <option value="un">Unidade (un)</option>
            <option value="cx">Caixa (cx)</option>
            <option value="rolo">Rolo</option>
            <option value="pct">Pacote (pct)</option>
            <option value="resma">Resma</option>
          </select>
        </div>
        <div class="form-group">
          <label for="insumoMinimo">Estoque Mínimo <span class="req">*</span></label>
          <input type="number" id="insumoMinimo" min="0" value="5" required>
          <span class="form-hint">Alerta é ativado abaixo desse valor</span>
        </div>
        <div class="form-group full" id="insumoQtdGrp" style="display:none">
          <label for="insumoQtdInicial">Quantidade Inicial em Estoque</label>
          <input type="number" id="insumoQtdInicial" min="0" value="0">
          <span class="form-hint">Lança uma entrada automática com esse valor</span>
        </div>
        <div class="form-group full" id="insumoAtivoGrp" style="display:none">
          <label for="insumoAtivo">Status</label>
          <select id="insumoAtivo">
            <option value="1">Ativo</option>
            <option value="0">Inativo (oculto no sistema)</option>
          </select>
        </div>
      </div>
      <div class="flex gap-8" style="justify-content:flex-end;margin-top:20px">
        <button type="button" class="btn btn-ghost" onclick="PainelEstoque.fecharModal()">Cancelar</button>
        <button type="submit" class="btn btn-primary" id="btnSalvarInsumo">💾 Salvar</button>
      </div>
    </form>
  </div>
</div>

<!-- ══ MODAL USUÁRIO (fora das sections para evitar conflito de z-index) ══ -->
<div id="usuarioModal" style="display:none;position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,0.55);align-items:center;justify-content:center;padding:16px">
  <div style="background:var(--surface);border-radius:var(--radius);padding:24px;max-width:560px;width:100%;max-height:92vh;overflow-y:auto;box-shadow:0 24px 80px rgba(0,0,0,0.35);position:relative">
    <div class="flex items-center gap-12" style="justify-content:space-between;margin-bottom:20px">
      <div class="card-title" id="usuarioModalTitulo" style="font-size:16px">Novo Usuário</div>
      <button onclick="Usuarios.fecharModal()" style="background:var(--surface-2);border:1px solid var(--border);border-radius:8px;padding:6px 12px;cursor:pointer;font-size:18px;color:var(--text-2);line-height:1">✕</button>
    </div>
    <form id="formUsuario">
      <input type="hidden" id="usuarioId">
      <div class="form-grid" style="gap:14px">
        <div class="form-group">
          <label for="usuarioNome">Nome <span class="req">*</span></label>
          <input type="text" id="usuarioNome" autocomplete="off" required>
        </div>
        <div class="form-group">
          <label for="usuarioEmail">E-mail <span class="req">*</span></label>
          <input type="email" id="usuarioEmail" autocomplete="off" required>
        </div>
        <div class="form-group">
          <label for="usuarioPerfil">Perfil <span class="req">*</span></label>
          <select id="usuarioPerfil">
            <option value="3">Funcionário</option>
            <option value="2">Admin</option>
            <option value="1">Super Admin</option>
          </select>
        </div>
        <div class="form-group">
          <label for="usuarioAtivo">Status</label>
          <select id="usuarioAtivo">
            <option value="1">Ativo</option>
            <option value="0">Inativo</option>
          </select>
        </div>
        <div class="form-group full">
          <label id="senhaLabel" for="usuarioSenha">Senha <span class="req">*</span></label>
          <input type="password" id="usuarioSenha" placeholder="Mínimo 6 caracteres" autocomplete="new-password">
        </div>
      </div>

      <!-- Bloco de permissões — só superadmin -->
      <div id="permissoesBloco" style="display:none;margin-top:20px">
        <div style="font-size:13px;font-weight:700;color:var(--text);margin-bottom:12px;padding-bottom:8px;border-bottom:1px solid var(--border)">🔐 Permissões do usuário</div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">

          <button type="button" id="btn-pEntrada" onclick="Usuarios.togglePerm(this,'pEntrada')"
            style="display:flex;align-items:center;gap:10px;padding:12px 14px;background:var(--surface-2);border:2px solid var(--border);border-radius:8px;cursor:pointer;transition:all .2s;text-align:left;width:100%">
            <span style="font-size:18px">⬆️</span>
            <span style="font-size:13px;color:var(--text);font-weight:500">Registrar Entrada</span>
          </button>

          <button type="button" id="btn-pSaida" onclick="Usuarios.togglePerm(this,'pSaida')"
            style="display:flex;align-items:center;gap:10px;padding:12px 14px;background:var(--surface-2);border:2px solid var(--border);border-radius:8px;cursor:pointer;transition:all .2s;text-align:left;width:100%">
            <span style="font-size:18px">⬇️</span>
            <span style="font-size:13px;color:var(--text);font-weight:500">Registrar Saída</span>
          </button>

          <button type="button" id="btn-pEstoque" onclick="Usuarios.togglePerm(this,'pEstoque')"
            style="display:flex;align-items:center;gap:10px;padding:12px 14px;background:var(--surface-2);border:2px solid var(--border);border-radius:8px;cursor:pointer;transition:all .2s;text-align:left;width:100%">
            <span style="font-size:18px">📦</span>
            <span style="font-size:13px;color:var(--text);font-weight:500">Ver Estoque</span>
          </button>

          <button type="button" id="btn-pInventario" onclick="Usuarios.togglePerm(this,'pInventario')"
            style="display:flex;align-items:center;gap:10px;padding:12px 14px;background:var(--surface-2);border:2px solid var(--border);border-radius:8px;cursor:pointer;transition:all .2s;text-align:left;width:100%">
            <span style="font-size:18px">🔢</span>
            <span style="font-size:13px;color:var(--text);font-weight:500">Inventário</span>
          </button>

          <button type="button" id="btn-pHistorico" onclick="Usuarios.togglePerm(this,'pHistorico')"
            style="display:flex;align-items:center;gap:10px;padding:12px 14px;background:var(--surface-2);border:2px solid var(--border);border-radius:8px;cursor:pointer;transition:all .2s;text-align:left;width:100%">
            <span style="font-size:18px">📋</span>
            <span style="font-size:13px;color:var(--text);font-weight:500">Ver Histórico</span>
          </button>

          <button type="button" id="btn-pRelatorio" onclick="Usuarios.togglePerm(this,'pRelatorio')"
            style="display:flex;align-items:center;gap:10px;padding:12px 14px;background:var(--surface-2);border:2px solid var(--border);border-radius:8px;cursor:pointer;transition:all .2s;text-align:left;width:100%">
            <span style="font-size:18px">📈</span>
            <span style="font-size:13px;color:var(--text);font-weight:500">Relatórios</span>
          </button>

          <button type="button" id="btn-pInvEditar" onclick="Usuarios.togglePerm(this,'pInvEditar')"
            style="display:flex;align-items:center;gap:10px;padding:12px 14px;background:var(--surface-2);border:2px solid var(--border);border-radius:8px;cursor:pointer;transition:all .2s;text-align:left;width:100%">
            <span style="font-size:18px">✏️</span>
            <span style="font-size:13px;color:var(--text);font-weight:500">Editar Inventário</span>
          </button>

          <button type="button" id="btn-pUsuarios" onclick="Usuarios.togglePerm(this,'pUsuarios')"
            style="display:flex;align-items:center;gap:10px;padding:12px 14px;background:var(--surface-2);border:2px solid var(--border);border-radius:8px;cursor:pointer;transition:all .2s;text-align:left;width:100%">
            <span style="font-size:18px">👥</span>
            <span style="font-size:13px;color:var(--text);font-weight:500">Gerenciar Usuários</span>
          </button>

        </div>
        <p style="font-size:11px;color:var(--text-2);margin-top:10px">Clique para ativar ou desativar cada permissão.</p>

        <!-- Hidden inputs que guardam os valores reais -->
        <input type="hidden" id="pEntrada"    value="0">
        <input type="hidden" id="pSaida"      value="0">
        <input type="hidden" id="pEstoque"    value="0">
        <input type="hidden" id="pInventario" value="0">
        <input type="hidden" id="pHistorico"  value="0">
        <input type="hidden" id="pRelatorio"  value="0">
        <input type="hidden" id="pInvEditar"  value="0">
        <input type="hidden" id="pUsuarios"   value="0">
      </div>

      <div class="flex gap-8" style="justify-content:flex-end;margin-top:20px">
        <button type="button" class="btn btn-ghost" onclick="Usuarios.fecharModal()">Cancelar</button>
        <button type="submit" class="btn btn-primary" id="btnSalvarUsuario">💾 Salvar</button>
      </div>
    </form>
  </div>
</div>


<div class="toast-container" id="toastContainer"></div>

<script src="assets/js/app.js"></script>
<script src="assets/js/glpi-bridge.js"></script>
<script>if('serviceWorker'in navigator)navigator.serviceWorker.register('sw.js').catch(()=>{});</script>
</body>
</body>
</html>

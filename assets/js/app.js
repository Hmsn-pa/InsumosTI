'use strict';
const API = 'api.php';

// ─── AUTH ─────────────────────────────────────
const Auth = {
  user: null,
  token: null,

  init() {
    this.token = localStorage.getItem('insumos_token') || '';
    const u = localStorage.getItem('insumos_user');
    if (u) try { this.user = JSON.parse(u); } catch {}
    if (!this.token) { window.location.href = 'login.php'; return false; }
    return true;
  },

  async verificar() {
    try {
      const r = await apiFetch({ action: 'me' });
      if (!r.success) throw new Error();
      this.user = r.usuario;
      localStorage.setItem('insumos_user', JSON.stringify(this.user));
      return true;
    } catch {
      this.logout();
      return false;
    }
  },

  async logout() {
    await fetch(API + '?action=logout', { method: 'POST', headers: authHeaders() }).catch(() => {});
    localStorage.removeItem('insumos_token');
    localStorage.removeItem('insumos_user');
    document.cookie = 'insumos_token=;path=/;max-age=0';
    window.location.href = 'login.php';
  },

  perm(key) {
    if (!this.user) return false;
    if (this.user.perfil === 'superadmin') return true;
    const perms = JSON.parse(localStorage.getItem('insumos_perms') || '{}');
    return !!perms[key];
  },

  isSuperAdmin() { return this.user?.perfil === 'superadmin'; },
  isAdmin()      { return this.user?.perfil === 'admin' || this.isSuperAdmin(); },
};

function authHeaders() {
  return { 'Content-Type': 'application/json', 'Authorization': 'Bearer ' + (Auth.token || localStorage.getItem('insumos_token') || '') };
}

async function apiFetch(params) {
  const url = API + '?' + new URLSearchParams(params);
  const res = await fetch(url, { headers: authHeaders() });
  if (res.status === 401) { Auth.logout(); return {}; }
  return res.json();
}

async function apiPost(action, body) {
  const res = await fetch(API + '?action=' + action, {
    method: 'POST', headers: authHeaders(), body: JSON.stringify(body)
  });
  if (res.status === 401) { Auth.logout(); return {}; }
  return res.json();
}

// ─── TEMA ─────────────────────────────────────
const Theme = {
  init() { const t = localStorage.getItem('theme') || 'light'; this.set(t, false); },
  toggle() { const c = document.documentElement.getAttribute('data-theme'); this.set(c === 'dark' ? 'light' : 'dark'); },
  set(t, save = true) {
    document.documentElement.setAttribute('data-theme', t);
    const btn = document.getElementById('themeToggle');
    if (btn) btn.innerHTML = t === 'dark' ? '<span>☀️</span> Modo Claro' : '<span>🌙</span> Modo Escuro';
    if (save) localStorage.setItem('theme', t);
  }
};

// ─── RELÓGIO ──────────────────────────────────
function startClock() {
  const el = document.getElementById('clock');
  if (!el) return;
  const tick = () => { el.textContent = new Date().toLocaleTimeString('pt-BR'); };
  tick(); setInterval(tick, 1000);
}

// ─── SIDEBAR ──────────────────────────────────
const Sidebar = {
  _isMobile() { return window.innerWidth <= 768; },

  // Alterna entre recolhido e expandido (desktop) ou abre/fecha (mobile)
  toggle() {
    if (this._isMobile()) {
      const sidebar = document.getElementById('sidebar');
      if (sidebar.classList.contains('open')) {
        this.close();
      } else {
        this.open();
      }
    } else {
      this.toggleCollapse();
    }
  },

  // Desktop: recolhe/expande
  toggleCollapse() {
    const sidebar = document.getElementById('sidebar');
    const collapsed = sidebar.classList.toggle('collapsed');
    localStorage.setItem('sidebar_collapsed', collapsed ? '1' : '0');
  },

  // Mobile: abre a gaveta
  open() {
    document.getElementById('sidebar').classList.add('open');
    document.querySelector('.sidebar-overlay').classList.add('visible');
    document.body.style.overflow = 'hidden';
  },

  // Mobile: fecha a gaveta
  close() {
    document.getElementById('sidebar').classList.remove('open');
    document.querySelector('.sidebar-overlay').classList.remove('visible');
    document.body.style.overflow = '';
  },

  // Restaura estado salvo ao carregar
  init() {
    if (!this._isMobile()) {
      const saved = localStorage.getItem('sidebar_collapsed');
      if (saved === '1') {
        document.getElementById('sidebar').classList.add('collapsed');
      }
    }
  }
};

// ─── NAVEGAÇÃO ────────────────────────────────
function navTo(pageId) {
  // Memoriza última página visitada
  try { localStorage.setItem('insumos_last_page', pageId); } catch(e) {}
  // Informa ao pai (GLPI) qual página está ativa
  try { if(window.parent !== window) window.parent.postMessage({insumos_current_page: pageId}, '*'); } catch(e) {}
  document.querySelectorAll('.page-content').forEach(p => p.classList.remove('active'));
  document.querySelectorAll('.nav-item').forEach(n => n.classList.remove('active'));
  const page = document.getElementById('page-' + pageId);
  if (page) page.classList.add('active');
  const nav = document.querySelector(`[data-page="${pageId}"]`);
  if (nav) nav.classList.add('active');
  const titles = {
    dashboard:'Dashboard', estoque:'Estoque', 'painel-estoque':'Painel de Estoque',
    entrada:'Entrada de Insumos', saida:'Saída de Insumos',
    inventario:'Inventário Físico', historico:'Histórico',
    relatorio:'Relatórios', usuarios:'Usuários', perfil:'Meu Perfil'
  };
  setText('pageTitle', titles[pageId] || '');
  Sidebar.close();
  loadPage(pageId);
}

function loadPage(id) {
  switch(id) {
    case 'dashboard':      Dashboard.load();          break;
    case 'estoque':        Estoque.load();            break;
    case 'painel-estoque': PainelEstoque.load();      break;
    case 'entrada':        Formulario.initEntrada();  break;
    case 'saida':          Formulario.initSaida();    break;
    case 'inventario':     Inventario.load();         break;
    case 'historico':      Historico.load();          break;
    case 'relatorio':      Relatorio.init();          break;
    case 'usuarios':       Usuarios.load();           break;
    case 'perfil':         Perfil.load();             break;
  }
}

// ─── PERMISSÕES UI ────────────────────────────
function aplicarPermissoes() {
  const u = Auth.user;
  if (!u) return;

  const perms = JSON.parse(localStorage.getItem('insumos_perms') || '{}');
  const isSA  = u.perfil === 'superadmin';

  // Atualiza barra lateral do usuário
  setText('sidebarUserNome', u.nome);
  const perfilLabels = { superadmin: '🔴 Super Admin', admin: '🔵 Admin', funcionario: '🟢 Funcionário' };
  setText('sidebarUserPerfil', perfilLabels[u.perfil] || u.perfil);
  const avatar = document.getElementById('sidebarUserAvatar');
  if (avatar) avatar.textContent = u.nome.charAt(0).toUpperCase();

  const badge = document.getElementById('topbarPerfil');
  if (badge) badge.textContent = u.perfil === 'superadmin' ? 'Super Admin' : u.perfil === 'admin' ? 'Admin' : 'Funcionário';

  // Esconde itens de menu sem permissão
  const map = {
    'perm-estoque':    isSA || !!perms.p_estoque,
    'perm-entrada':    isSA || !!perms.p_entrada,
    'perm-saida':      isSA || !!perms.p_saida,
    'perm-inventario': isSA || !!perms.p_inventario,
    'perm-historico':  isSA || !!perms.p_historico,
    'perm-relatorio':  isSA || !!perms.p_relatorio,
    'perm-usuarios':   isSA || !!perms.p_usuarios,
  };
  Object.entries(map).forEach(([cls, show]) => {
    document.querySelectorAll('.' + cls).forEach(el => {
      el.style.display = show ? '' : 'none';
    });
  });
}

// ─── TOAST ────────────────────────────────────
const Toast = {
  show(msg, type = 'success', dur = 4000) {
    const icons = { success:'✅', error:'❌', warn:'⚠️' };
    const c = document.getElementById('toastContainer');
    const t = document.createElement('div');
    t.className = `toast ${type}`;
    t.innerHTML = `<span class="toast-icon">${icons[type]||'💬'}</span><span class="toast-msg">${msg}</span>`;
    c.appendChild(t);
    setTimeout(() => { t.style.animation='fadeOut 0.3s ease forwards'; setTimeout(()=>t.remove(),300); }, dur);
  }
};

// ─── UTILS ────────────────────────────────────
function setText(id, val) { const e = document.getElementById(id); if (e) e.textContent = val; }
function esc(s) { return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }
function labelStatus(s) { return {ok:'Normal',atencao:'Atenção',critico:'Crítico',zerado:'Zerado'}[s]||s; }

// ─── DASHBOARD ────────────────────────────────
const Dashboard = {
  chart: null,
  async load() {
    try {
      const r = await apiFetch({ action:'dashboard', t:Date.now() });
      if (!r.success) throw new Error();
      this.renderMetricas(r.metricas);
      this.renderGrafico(r.estoque);
      this.renderRanking(r.ranking);
      this.renderUltimas(r.ultimas_movimentacoes);
    } catch { Toast.show('Erro ao carregar dashboard','error'); }
  },
  renderMetricas(m) {
    setText('metricTotal', m.total_estoque); setText('metricCriticos', m.total_criticos);
    setText('metricZerados', m.total_zerados); setText('metricInsumos', m.total_insumos);
  },
  renderGrafico(estoque) {
    const ctx = document.getElementById('chartEstoque'); if (!ctx) return;
    const labels = estoque.map(i => i.nome.replace('Etiqueta 3cs 33x22 ','Etiq. '));
    const valores = estoque.map(i => i.estoque_atual);
    const minimos = estoque.map(i => i.estoque_minimo);
    const isDark = document.documentElement.getAttribute('data-theme')==='dark';
    const tc = isDark ? '#8fafc8' : '#64748b'; const gc = isDark ? '#1e3048' : '#e2e8f0';
    const data = { labels, datasets: [
      { label:'Estoque Atual', data:valores, backgroundColor:valores.map((v,i)=>v<=0?'rgba(100,116,139,0.7)':v<=minimos[i]*0.5?'rgba(244,63,94,0.75)':v<=minimos[i]?'rgba(245,158,11,0.75)':'rgba(0,212,170,0.75)'), borderColor:valores.map((v,i)=>v<=0?'#64748b':v<=minimos[i]*0.5?'#f43f5e':v<=minimos[i]?'#f59e0b':'#00d4aa'), borderWidth:2, borderRadius:8, borderSkipped:false },
      { label:'Mínimo', data:minimos, type:'line', borderColor:'rgba(244,63,94,0.5)', borderDash:[5,5], borderWidth:2, pointRadius:0, fill:false }
    ]};
    if (this.chart) { this.chart.data=data; this.chart.update(); return; }
    this.chart = new Chart(ctx, { type:'bar', data, options:{ responsive:true, plugins:{ legend:{ labels:{ color:tc, font:{ family:'Sora',size:12 } } } }, scales:{ x:{ ticks:{ color:tc,font:{size:11},maxRotation:45 }, grid:{ color:gc } }, y:{ ticks:{ color:tc }, grid:{ color:gc }, beginAtZero:true } }, animation:{ duration:400 } } });
  },
  renderRanking(ranking) {
    const ul = document.getElementById('rankingList'); if (!ul) return;
    ul.innerHTML = ranking.length===0 ? '<li class="rank-item text-muted text-sm">Sem dados nos últimos 30 dias</li>'
      : ranking.map((r,i) => `<li class="rank-item"><div class="rank-pos">${i+1}</div><div class="rank-name">${esc(r.nome)}</div><div class="rank-val">${r.total_saidas}</div></li>`).join('');
  },
  renderUltimas(movs) {
    const tb = document.getElementById('ultimasTbody'); if (!tb) return;
    tb.innerHTML = movs.map(m => `<tr><td><span class="badge badge-${m.tipo.toLowerCase()}">${m.tipo}</span></td><td>${esc(m.insumo)}</td><td class="font-mono">${m.quantidade}</td><td class="text-muted">${esc((m.responsavel||'').split(' ')[0])}</td><td class="text-muted text-sm">${m.criado_em}</td></tr>`).join('') || '<tr><td colspan="5" class="text-center text-muted">Nenhuma movimentação</td></tr>';
  }
};

// ─── ESTOQUE ──────────────────────────────────
const Estoque = {
  async load() {
    const tb = document.getElementById('estoqueTbody'); if (!tb) return;
    tb.innerHTML = '<tr><td colspan="4" class="text-center"><div class="skeleton" style="margin:auto;width:60%"></div></td></tr>';
    try {
      const r = await apiFetch({ action:'estoque', t:Date.now() });
      if (!r.success) throw new Error();
      tb.innerHTML = r.data.map(item => {
        const pct = Math.min(100, Math.round((item.estoque_atual / Math.max(item.estoque_minimo*2,1))*100));
        const bc  = item.status==='critico'?'danger':item.status==='atencao'?'warn':'';
        return `<tr><td><strong>${esc(item.nome)}</strong></td><td><span class="font-mono" style="font-size:16px;font-weight:700">${item.estoque_atual}</span> <span class="text-muted text-sm">${esc(item.unidade)}</span><div class="progress"><div class="progress-bar ${bc}" style="width:${pct}%"></div></div></td><td class="text-muted font-mono">${item.estoque_minimo}</td><td><span class="badge badge-${item.status}">${labelStatus(item.status)}</span></td></tr>`;
      }).join('') || '<tr><td colspan="4" class="text-center text-muted">Nenhum insumo</td></tr>';
    } catch { tb.innerHTML = '<tr><td colspan="4" class="text-center text-muted">Erro ao carregar</td></tr>'; }
  }
};

// ─── FORMULÁRIOS ──────────────────────────────
const Formulario = {
  insumos:[], responsaveis:[], setoresMap:{},
  async loadBase() {
    if (this.insumos.length===0) {
      const [ri,rr] = await Promise.all([apiFetch({action:'insumos'}), apiFetch({action:'responsaveis'})]);
      this.insumos = ri.data||[]; this.responsaveis = rr.data||[];
    }
  },
  fillSelect(id, items, vk='id', lk='nome', ph='Selecione...') {
    const s = document.getElementById(id); if (!s) return;
    s.innerHTML = `<option value="">${ph}</option>` + items.map(i=>`<option value="${i[vk]}">${esc(i[lk])}</option>`).join('');
  },
  async initEntrada() {
    await this.loadBase();
    this.fillSelect('entradaInsumo', this.insumos);
    this.fillSelect('entradaResponsavel', this.responsaveis);
    this.bindForm('formEntrada','entrada');
  },
  async initSaida() {
    await this.loadBase();
    
    await this.loadBase();
    this.fillSelect('saidaInsumo', this.insumos);
    
    this.fillSelect('saidaSetor', [], 'id','nome','Selecione o insumo primeiro...');
    const sel = document.getElementById('saidaInsumo');
    if (sel && !sel._bound) { sel._bound=true; sel.addEventListener('change', ()=>this.loadSetores(sel.value)); }
    this.bindForm('formSaida','saida');
  },
  async loadSetores(iid) {
    if (!iid) return;
    const r = await apiFetch({ action:'setores', insumo_id:iid });
    this.fillSelect('saidaSetor', r.data||[], 'id','nome','Selecione o setor...');
  },
  bindForm(formId, action) {
    const form = document.getElementById(formId); if (!form||form._bound) return; form._bound=true;
    form.addEventListener('submit', async e => {
      e.preventDefault();
      const btn=form.querySelector('.btn-submit'); const sp=form.querySelector('.spinner');
      const body=Object.fromEntries(new FormData(form).entries());
      ['insumo_id','responsavel_id','setor_id','quantidade'].forEach(k=>{ if(body[k]!==undefined) body[k]=Number(body[k]); });
      btn.disabled=true; if(sp) sp.style.display='inline-block';
      try {
        const r=await apiPost(action,body);
        if(r.success){ Toast.show(r.message,'success'); form.reset(); form._bound=false; if(action==='saida'){ this.fillSelect('saidaSetor',[],'id','nome','Selecione o insumo primeiro...'); const i=document.getElementById('saidaChamadoInfo'); if(i) i.textContent=''; const l=document.getElementById('saidaLoc'); if(l) l.value=''; } }
        else Toast.show(r.message,'error');
      } catch { Toast.show('Erro de conexão','error'); }
      finally { btn.disabled=false; if(sp) sp.style.display='none'; }
    });
  },

  _chamadoTimer: null,
  async buscarChamado(valor) {
    const info = document.getElementById('saidaChamadoInfo');
    const saidaSetor = document.getElementById('saidaSetor');
    const descField = document.getElementById('saidaDesc');
    const locField = document.getElementById('saidaLoc');
    // Remove espaços, tabs e caracteres não numéricos
    const valorLimpo = valor.replace(/[^0-9]/g, '');
    if (!valorLimpo) { if(info) info.textContent=''; return; }
    const id = parseInt(valorLimpo);
    if (!id || id <= 0) { if(info) info.textContent=''; return; }
    // Atualiza o campo com o valor limpo
    const campo = document.getElementById('saidaRef');
    if (campo && campo.value !== valorLimpo) campo.value = valorLimpo;
    // Debounce — só busca 800ms após parar de digitar
    clearTimeout(this._chamadoTimer);
    this._chamadoTimer = setTimeout(async () => {
    if(info) info.innerHTML = '<span style="color:var(--text-2)">🔍 Buscando chamado...</span>';
    try {
      const r = await apiFetch({action:'chamado_info', chamado_id:id});
      if (r.success) {
        const locCompleta = r.localizacao_completa || '';
        const locSimples  = r.localizacao || '';
        if(info) info.innerHTML = `<span style="color:var(--brand-1)">✅ Chamado #${r.chamado_id}: ${esc(r.titulo)}</span><br><span style="color:var(--text-2)">📍 ${esc(locCompleta||'Sem localização')}</span>`;
        if (locField && locCompleta) locField.value = locCompleta;
        if (locSimples && saidaSetor) {
          const opts = Array.from(saidaSetor.options).filter(o => o.value);
          const palavras = locSimples.toUpperCase().split(/[\s>\-\/]+/).filter(p => p.length > 2);
          let melhorMatch = null, melhorScore = 0;
          opts.forEach(o => {
            const texto = o.text.toUpperCase();
            let score = 0;
            palavras.forEach(p => { if(texto.includes(p)) score++; });
            if (score > melhorScore) { melhorScore = score; melhorMatch = o; }
          });
          if (melhorMatch && melhorScore > 0) {
            saidaSetor.value = melhorMatch.value;
            if(info) info.innerHTML += `<br><span style="color:#10b981">✅ Setor: ${esc(melhorMatch.text)}</span>`;
          }
        }
      } else {
        if(info) info.innerHTML = `<span style="color:var(--danger)">❌ ${esc(r.message)}</span>`;
      }
    } catch {
      if(info) info.innerHTML = '<span style="color:var(--danger)">❌ Erro ao buscar chamado.</span>';
    }
    }, 800); // fim debounce
  }
};

const Saida = Formulario;

// ─── HISTÓRICO ────────────────────────────────
const Historico = {
  page:1, filters:{},
  async load(page=1) {
    this.page=page;
    const tb=document.getElementById('historicTbody'); if(!tb) return;
    tb.innerHTML='<tr><td colspan="7" class="text-center text-muted">Carregando...</td></tr>';
    try {
      const r=await apiFetch({action:'historico',page:this.page,limit:20,...this.filters});
      if(!r.success) throw new Error();
      tb.innerHTML=r.data.map(row=>`<tr><td><span class="badge badge-${row.tipo.toLowerCase()}">${row.tipo}</span></td><td>${esc(row.insumo)}</td><td class="font-mono">${row.quantidade}</td><td class="text-muted">${esc((row.responsavel||'').split(' ')[0])}</td><td class="text-muted text-sm hist-col-setor">${row.setor?esc(row.setor):'—'}</td><td class="text-muted text-sm hist-col-desc">${row.descricao?esc(row.descricao):'—'}</td><td class="text-muted text-sm font-mono">${row.criado_em}</td></tr>`).join('')||'<tr><td colspan="7" class="text-center text-muted">Nenhum registro</td></tr>';
      setText('historicInfo',`${r.total} registro(s) encontrado(s)`);
      this.renderPag(r.pages);
    } catch { tb.innerHTML='<tr><td colspan="7" class="text-center text-muted">Erro ao carregar</td></tr>'; }
  },
  renderPag(pages) {
    const p=document.getElementById('historicPag'); if(!p) return; p.innerHTML='';
    if(pages<=1) return;
    const mk=(lbl,pg,dis=false)=>{ const b=document.createElement('button'); b.className='page-btn'+(pg===this.page?' active':''); b.textContent=lbl; b.disabled=dis; if(!dis) b.onclick=()=>this.load(pg); return b; };
    p.appendChild(mk('‹',this.page-1,this.page<=1));
    for(let pg=1;pg<=pages;pg++){ if(pages<=7||pg===1||pg===pages||Math.abs(pg-this.page)<=1) p.appendChild(mk(pg,pg)); else if(Math.abs(pg-this.page)===2){ const d=document.createElement('span'); d.textContent='…'; d.style.cssText='padding:0 4px;color:var(--text-2)'; p.appendChild(d); } }
    p.appendChild(mk('›',this.page+1,this.page>=pages));
  },
  applyFilter() {
    this.filters={};
    const t=document.getElementById('filterTipo')?.value; if(t) this.filters.tipo=t;
    const i=document.getElementById('filterInsumo')?.value; if(i) this.filters.insumo=i;
    const ini=document.getElementById('filterIni')?.value; if(ini) this.filters.data_ini=ini;
    const fim=document.getElementById('filterFim')?.value; if(fim) this.filters.data_fim=fim;
    this.load(1);
  },
  clearFilter() {
    ['filterTipo','filterInsumo','filterIni','filterFim'].forEach(id=>{ const e=document.getElementById(id); if(e) e.value=''; });
    this.filters={}; this.load(1);
  }
};

// ─── INVENTÁRIO ────────────────────────────────
const Inventario = {
  invId:null,
  async load() {
    await Formulario.loadBase();
    Formulario.fillSelect('invResponsavel', Formulario.responsaveis);
    const form=document.getElementById('formAbrirInv');
    if(form&&!form._bound){ form._bound=true; form.addEventListener('submit',async e=>{ e.preventDefault();
      const b=Object.fromEntries(new FormData(form).entries()); b.usuario_id=Auth.user?.id||0;
      const btn=form.querySelector('button[type=submit]'); btn.disabled=true; btn.textContent='Abrindo...';
      const r=await apiPost('inventario_abrir',b).catch(()=>({success:false,message:'Erro'}));
      if(r.success){ Toast.show(r.message,'success'); form.reset(); form._bound=false; await this.verificarAberto(); await this.carregarHistorico(); }
      else Toast.show(r.message,'error');
      btn.disabled=false; btn.textContent='▶ Iniciar Inventário';
    }); }
    await this.verificarAberto(); await this.carregarHistorico();
  },
  async verificarAberto() {
    const r=await apiFetch({action:'inventario_aberto',t:Date.now()});
    if(r.inventario){ this.invId=r.inventario.id; this.mostrarContagem(r.inventario,r.itens); }
    else this.mostrarFormulario();
  },
  mostrarFormulario(){ document.getElementById('inv-novo').classList.remove('hidden'); document.getElementById('inv-contagem').classList.add('hidden'); },
  mostrarContagem(inv,itens){ document.getElementById('inv-novo').classList.add('hidden'); document.getElementById('inv-contagem').classList.remove('hidden'); setText('invInfoTexto',`Responsável: ${inv.responsavel} | Aberto em: ${inv.aberto_em}`); this.renderItens(itens); },
  renderItens(itens) {
    const tb=document.getElementById('invCorpoContagem'); tb.innerHTML='';
    itens.forEach(item=>{ const tr=document.createElement('tr'); tr.dataset.insumoId=item.insumo_id;
      const c=item.qtd_contada??''; const dif=item.qtd_contada!==null?(item.qtd_contada-item.qtd_sistema):'';
      const ds=dif===''?'':`style="color:${dif>0?'#10b981':dif<0?'#f43f5e':'var(--text-2)'};font-weight:700;text-align:center"`;
      tr.innerHTML=`<td><strong>${esc(item.insumo)}</strong></td><td class="text-muted">${esc(item.unidade)}</td><td class="font-mono text-center">${item.qtd_sistema}</td><td style="text-align:center;padding:6px 8px"><input type="number" min="0" class="inv-input" data-insumo="${item.insumo_id}" value="${c}" placeholder="—" style="width:90px;text-align:center;padding:8px;border:2px solid var(--border);border-radius:8px;background:var(--surface-2);color:var(--text);font-family:'JetBrains Mono',monospace;font-size:14px"></td><td class="font-mono" data-dif="${item.insumo_id}" ${ds}>${dif===''?'—':(dif>0?'+'+dif:dif)}</td>`;
      tb.appendChild(tr);
    });
    tb.querySelectorAll('.inv-input').forEach(inp=>{ inp.addEventListener('input',()=>{
      const sis=parseInt(inp.closest('tr').querySelector('td:nth-child(3)').textContent)||0;
      const dc=document.querySelector(`[data-dif="${inp.dataset.insumo}"]`);
      if(inp.value===''){ dc.textContent='—'; dc.removeAttribute('style'); } 
      else { const dif=parseInt(inp.value)-sis; dc.textContent=dif>0?'+'+dif:dif; dc.style.cssText=`color:${dif>0?'#10b981':dif<0?'#f43f5e':'var(--text-2)'};font-weight:700;text-align:center`; }
      this.atualizarProgresso();
    }); });
    this.atualizarProgresso();
  },
  atualizarProgresso(){ const ins=document.querySelectorAll('.inv-input'); const tot=ins.length; const cnt=[...ins].filter(i=>i.value!=='').length; const pct=tot>0?Math.round((cnt/tot)*100):0; const b=document.getElementById('invProgBar'); const t=document.getElementById('invProgTexto'); if(b) b.style.width=pct+'%'; if(t) t.textContent=`${cnt} / ${tot} (${pct}%)`; },
  coletarItens(){ return [...document.querySelectorAll('.inv-input')].map(i=>({insumo_id:parseInt(i.dataset.insumo),qtd_contada:i.value!==''?parseInt(i.value):null})); },
  async salvar(){ if(!this.invId) return; const r=await apiPost('inventario_salvar',{inventario_id:this.invId,itens:this.coletarItens()}); Toast.show(r.message,r.success?'success':'error'); },
  async finalizar(){
    const v=[...document.querySelectorAll('.inv-input')].filter(i=>i.value==='');
    if(v.length>0){ Toast.show(`Faltam ${v.length} item(ns) sem contagem!`,'warn'); v[0].focus(); v[0].scrollIntoView({behavior:'smooth',block:'center'}); return; }
    if(!confirm('Finalizar e ajustar o estoque automaticamente?')) return;
    await this.salvar();
    const r=await apiPost('inventario_finalizar',{inventario_id:this.invId,responsavel_id:Auth.user?.id||1});
    if(r.success){ Toast.show(r.message,'success'); this.invId=null; await this.verificarAberto(); await this.carregarHistorico(); Estoque.load(); }
    else Toast.show(r.message,'error');
  },
  async cancelar(){ if(!confirm('Cancelar? Nenhum ajuste será feito.')) return; const r=await apiPost('inventario_cancelar',{inventario_id:this.invId}); Toast.show(r.message,r.success?'success':'error'); if(r.success){ this.invId=null; this.mostrarFormulario(); await this.carregarHistorico(); } },
  async carregarHistorico(){
    const el=document.getElementById('invHistoricoLista'); if(!el) return;
    const r=await apiFetch({action:'inventarios',t:Date.now()});
    if(!r.success||!r.data?.length){ el.innerHTML='<p class="text-muted text-sm text-center">Nenhum inventário ainda.</p>'; return; }
    const isSA=Auth.isSuperAdmin();
    el.innerHTML=r.data.map(inv=>{
      const sc={FINALIZADO:'#10b981',CANCELADO:'#64748b',ABERTO:'#f59e0b'}[inv.status]||'#64748b';
      return `<div style="padding:12px;border:1px solid var(--border);border-radius:var(--radius-sm);margin-bottom:10px;background:var(--surface-2)"><div class="flex items-center gap-8" style="justify-content:space-between;flex-wrap:wrap"><div><div style="font-size:13px;font-weight:600">#${inv.id} — ${esc((inv.responsavel||'').split(' ')[0])}</div><div class="text-muted text-sm">${inv.aberto_em}${inv.finalizado_em?' → '+inv.finalizado_em:''}</div>${inv.observacao?`<div class="text-muted text-sm">${esc(inv.observacao)}</div>`:''}</div><div class="flex items-center gap-8"><span class="badge" style="background:${sc}22;color:${sc}">${inv.status}</span>${inv.status==='FINALIZADO'?`<span class="text-muted text-sm">${inv.itens_divergentes} div.</span><button class="btn btn-ghost btn-sm" onclick="Inventario.verDetalhe(${inv.id})">🔍 Ver</button>`:''}${isSA?`<button class="btn btn-sm" style="background:#fee2e2;color:#991b1b" onclick="Inventario.excluir(${inv.id})">🗑</button>`:''}</div></div></div>`;
    }).join('');
  },
  async excluir(id){ if(!confirm('Excluir este inventário permanentemente?')) return; const r=await apiPost('inventario_excluir',{inventario_id:id}); Toast.show(r.message,r.success?'success':'error'); if(r.success) await this.carregarHistorico(); },
  async verDetalhe(id){
    const ov=document.getElementById('invModalOverlay'); const ti=document.getElementById('invModalTitulo'); const co=document.getElementById('invModalConteudo');
    ov.classList.remove('hidden'); ov.style.display='flex'; co.innerHTML='<p class="text-center text-muted">Carregando...</p>';
    const r=await apiFetch({action:'inventario_detalhe',id});
    if(!r.success){ co.innerHTML='<p class="text-center text-muted">Erro ao carregar.</p>'; return; }
    ti.textContent=`Inventário #${r.inventario.id} — ${(r.inventario.responsavel||'').split(' ')[0]}`;
    co.innerHTML=`<div class="text-muted text-sm mb-16">Aberto: ${r.inventario.aberto_em}${r.inventario.finalizado_em?' | Finalizado: '+r.inventario.finalizado_em:''}</div><div class="table-wrap"><table><thead><tr><th>Insumo</th><th style="text-align:center">Sistema</th><th style="text-align:center">Contado</th><th style="text-align:center">Diferença</th></tr></thead><tbody>${r.itens.map(it=>{const d=it.diferenca;const ds=d!==null?`<span style="color:${d>0?'#10b981':d<0?'#f43f5e':'var(--text-2)'};font-weight:700">${d>0?'+'+d:d}</span>`:'—';return`<tr><td>${esc(it.insumo)}</td><td class="text-center font-mono">${it.qtd_sistema}</td><td class="text-center font-mono">${it.qtd_contada??'—'}</td><td class="text-center">${ds}</td></tr>`;}).join('')}</tbody></table></div>`;
  },
  fecharModal(){ const ov=document.getElementById('invModalOverlay'); ov.classList.add('hidden'); ov.style.display='none'; }
};

// ─── RELATÓRIOS ───────────────────────────────
const Relatorio = {
  dados:[], tipo:'', periodo:{},
  init() {
    const hoje=new Date(); const ini=new Date(hoje.getFullYear(),hoje.getMonth(),1);
    const fmt=d=>d.toISOString().split('T')[0];
    const ri=document.getElementById('relIni'); const rf=document.getElementById('relFim');
    if(ri&&!ri.value) ri.value=fmt(ini);
    if(rf&&!rf.value) rf.value=fmt(hoje);
  },
  async gerar(){
    const tipo=document.getElementById('relTipo')?.value||'movimentacoes';
    const ini=document.getElementById('relIni')?.value||'';
    const fim=document.getElementById('relFim')?.value||'';
    document.getElementById('relVazio').style.display='none';
    document.getElementById('relTabela').style.display='none';
    document.getElementById('relResumo').style.display='none';
    try {
      const r=await apiFetch({action:'relatorio',tipo,data_ini:ini,data_fim:fim,t:Date.now()});
      if(!r.success){ Toast.show(r.message||'Sem permissão','error'); return; }
      this.dados=r.data||[]; this.tipo=tipo; this.periodo=r.periodo||{};
      if(tipo==='movimentacoes') this.renderMovimentacoes(r);
      else if(tipo==='estoque_atual') this.renderEstoque(r);
      else if(tipo==='consumo_por_insumo') this.renderConsumo(r);
      else if(tipo==='inventarios') this.renderInventarios(r);
    } catch { Toast.show('Erro ao gerar relatório','error'); }
  },
  renderMovimentacoes(r){
    const res=r.resumo||{};
    document.getElementById('relResumo').style.display='grid';
    document.getElementById('relResumo').innerHTML=`
      <div class="metric-card"><div class="metric-icon">📋</div><div class="metric-value" style="font-size:22px">${res.total_registros||0}</div><div class="metric-label">Total de Registros</div></div>
      <div class="metric-card"><div class="metric-icon">⬆️</div><div class="metric-value" style="font-size:22px;background:linear-gradient(90deg,#10b981,#6ee7b7);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text">${res.total_entradas||0}</div><div class="metric-label">Total Entradas</div></div>
      <div class="metric-card danger"><div class="metric-icon">⬇️</div><div class="metric-value" style="font-size:22px">${res.total_saidas||0}</div><div class="metric-label">Total Saídas</div></div>`;
    setText('relTituloTabela','Movimentações'); setText('relSubtitulo',`Período: ${r.periodo?.ini||''} a ${r.periodo?.fim||''}`);
    document.getElementById('relThead').innerHTML='<tr><th>Tipo</th><th>Insumo</th><th>Qtd</th><th>Responsável</th><th>Setor</th><th>Referência</th><th>Data/Hora</th></tr>';
    document.getElementById('relTbody').innerHTML=r.data.map(row=>`<tr><td><span class="badge badge-${row.tipo.toLowerCase()}">${row.tipo}</span></td><td>${esc(row.insumo)}</td><td class="font-mono">${row.quantidade}</td><td>${esc((row.responsavel||'').split(' ')[0])}</td><td class="text-muted text-sm">${row.setor?esc(row.setor):'—'}</td><td class="text-muted text-sm">${row.referencia?esc(row.referencia):'—'}</td><td class="text-muted text-sm font-mono">${row.data_hora}</td></tr>`).join('')||'<tr><td colspan="7" class="text-center text-muted">Sem registros no período</td></tr>';
    document.getElementById('relTabela').style.display='block';
  },
  renderEstoque(r){
    setText('relTituloTabela','Posição de Estoque'); setText('relSubtitulo',`Gerado em: ${r.gerado_em}`);
    document.getElementById('relThead').innerHTML='<tr><th>Insumo</th><th>Unidade</th><th>Estoque Atual</th><th>Mínimo</th><th>Status</th></tr>';
    document.getElementById('relTbody').innerHTML=r.data.map(row=>`<tr><td>${esc(row.nome)}</td><td class="text-muted">${esc(row.unidade)}</td><td class="font-mono">${row.estoque_atual}</td><td class="font-mono text-muted">${row.estoque_minimo}</td><td><span class="badge badge-${row.status}">${labelStatus(row.status)}</span></td></tr>`).join('');
    document.getElementById('relTabela').style.display='block';
  },
  renderConsumo(r){
    setText('relTituloTabela','Consumo por Insumo'); setText('relSubtitulo',`Período: ${r.periodo?.ini||''} a ${r.periodo?.fim||''}`);
    document.getElementById('relThead').innerHTML='<tr><th>Insumo</th><th>Total Saídas</th><th>Total Entradas</th></tr>';
    document.getElementById('relTbody').innerHTML=r.data.map(row=>`<tr><td>${esc(row.insumo)}</td><td class="font-mono" style="color:#f43f5e;font-weight:600">${row.total_saidas}</td><td class="font-mono" style="color:#10b981;font-weight:600">${row.total_entradas}</td></tr>`).join('')||'<tr><td colspan="3" class="text-center text-muted">Sem movimentações no período</td></tr>';
    document.getElementById('relTabela').style.display='block';
  },
  renderInventarios(r){
    setText('relTituloTabela','Inventários'); setText('relSubtitulo',`Período: ${r.periodo?.ini||''} a ${r.periodo?.fim||''}`);
    document.getElementById('relThead').innerHTML='<tr><th>#</th><th>Responsável</th><th>Status</th><th>Abertura</th><th>Fechamento</th><th>Divergências</th><th>Observação</th></tr>';
    document.getElementById('relTbody').innerHTML=r.data.map(row=>{const sc={FINALIZADO:'badge-ok',CANCELADO:'badge-zerado',ABERTO:'badge-atencao'}[row.status]||'';return`<tr><td class="font-mono">${row.id}</td><td>${esc((row.responsavel||'').split(' ')[0])}</td><td><span class="badge ${sc}">${row.status}</span></td><td class="text-sm text-muted font-mono">${row.aberto_em}</td><td class="text-sm text-muted font-mono">${row.finalizado_em||'—'}</td><td class="font-mono">${row.divergencias}</td><td class="text-sm text-muted">${row.observacao?esc(row.observacao):'—'}</td></tr>`;}).join('')||'<tr><td colspan="7" class="text-center text-muted">Sem inventários no período</td></tr>';
    document.getElementById('relTabela').style.display='block';
  },
  exportarCSV(){
    if(!this.dados.length){ Toast.show('Gere um relatório primeiro','warn'); return; }
    const rows=this.dados; const keys=Object.keys(rows[0]);
    const csv=[keys.join(';'),...rows.map(r=>keys.map(k=>'"'+(r[k]??'').toString().replace(/"/g,'""')+'"').join(';'))].join('\n');
    const a=document.createElement('a'); a.href='data:text/csv;charset=utf-8,\uFEFF'+encodeURIComponent(csv);
    a.download=`relatorio_${this.tipo}_${Date.now()}.csv`; a.click();
    Toast.show('CSV exportado!','success');
  }
};

// ─── USUÁRIOS ─────────────────────────────────
// Armazena dados dos usuários carregados para usar no editar sem double-stringify
const _usuariosCache = {};

const Usuarios = {
  usuarioEditandoId: null,

  async load() {
    const tb = document.getElementById('usuariosTbody');
    if (!tb) return;
    tb.innerHTML = '<tr><td colspan="6" class="text-center text-muted">Carregando...</td></tr>';

    const r = await apiFetch({ action: 'usuarios' });
    if (!r.success) {
      tb.innerHTML = '<tr><td colspan="6" class="text-center text-muted">Sem permissão</td></tr>';
      return;
    }

    const isSA = Auth.isSuperAdmin();
    const cor  = { superadmin:'#f43f5e', admin:'#0ea5e9', funcionario:'#00d4aa' };

    // Cacheia os dados para o editar usar diretamente sem serialização HTML
    r.data.forEach(u => { _usuariosCache[u.id] = u; });

    tb.innerHTML = r.data.map(u => `
      <tr>
        <td><strong>${esc(u.nome)}</strong></td>
        <td class="text-muted text-sm">${esc(u.email)}</td>
        <td><span class="badge" style="background:${(cor[u.perfil]||'#888')}22;color:${cor[u.perfil]||'#888'}">${u.perfil}</span></td>
        <td><span class="badge ${u.ativo ? 'badge-ok' : 'badge-zerado'}">${u.ativo ? 'Ativo' : 'Inativo'}</span></td>
        <td class="text-muted text-sm">${u.ultimo_login || '—'}</td>
        <td>
          <div class="flex gap-8">
            ${isSA ? `<button class="btn btn-ghost btn-sm" onclick="Usuarios.editar(${u.id})">✏️ Editar</button>` : ''}
            ${isSA && u.perfil !== 'superadmin' ? `<button class="btn btn-sm" style="background:#fee2e2;color:#991b1b;border:none;border-radius:8px;padding:6px 10px;cursor:pointer" onclick="Usuarios.excluir(${u.id})">🗑</button>` : ''}
          </div>
        </td>
      </tr>
    `).join('') || '<tr><td colspan="6" class="text-center text-muted">Nenhum usuário</td></tr>';
  },

  _abrirModal() {
    const m = document.getElementById('usuarioModal');
    m.style.display = 'flex';
    document.body.style.overflow = 'hidden';
    this._bindForm();
  },

  fecharModal() {
    const m = document.getElementById('usuarioModal');
    m.style.display = 'none';
    document.body.style.overflow = '';
    this.usuarioEditandoId = null;
  },

  abrirModal() {
    this.usuarioEditandoId = null;
    document.getElementById('usuarioModalTitulo').textContent = 'Novo Usuário';
    document.getElementById('formUsuario').reset();
    document.getElementById('usuarioId').value = '';
    document.getElementById('senhaLabel').innerHTML = 'Senha <span class="req">*</span>';

    // Permissões: mostra só para superadmin, com tudo desmarcado
    const pb = document.getElementById('permissoesBloco');
    if (pb) {
      pb.style.display = Auth.isSuperAdmin() ? 'block' : 'none';
      this._resetCheckboxes();
    }

    this._abrirModal();
  },

  editar(id) {
    // Usa o cache em vez de serializar o objeto inteiro no HTML
    const u = _usuariosCache[id];
    if (!u) { Toast.show('Dados do usuário não encontrados. Recarregue a página.', 'error'); return; }

    this.usuarioEditandoId = u.id;
    document.getElementById('usuarioModalTitulo').textContent = 'Editar Usuário';
    document.getElementById('usuarioId').value   = u.id;
    document.getElementById('usuarioNome').value = u.nome;
    document.getElementById('usuarioEmail').value= u.email;
    document.getElementById('usuarioPerfil').value = u.perfil === 'superadmin' ? 1 : u.perfil === 'admin' ? 2 : 3;
    document.getElementById('usuarioAtivo').value  = u.ativo;
    document.getElementById('usuarioSenha').value  = '';
    document.getElementById('senhaLabel').innerHTML = 'Nova Senha <small style="font-size:11px;color:var(--text-2)">(deixe vazio para manter)</small>';

    // Preenche checkboxes de permissões
    const pb = document.getElementById('permissoesBloco');
    if (pb && Auth.isSuperAdmin()) {
      pb.style.display = 'block';
      this._setCheckbox('pEntrada',    'lbl-pEntrada',    u.p_entrada);
      this._setCheckbox('pSaida',      'lbl-pSaida',      u.p_saida);
      this._setCheckbox('pEstoque',    'lbl-pEstoque',    u.p_estoque);
      this._setCheckbox('pInventario', 'lbl-pInventario', u.p_inventario);
      this._setCheckbox('pHistorico',  'lbl-pHistorico',  u.p_historico);
      this._setCheckbox('pRelatorio',  'lbl-pRelatorio',  u.p_relatorio);
      this._setCheckbox('pInvEditar',  'lbl-pInvEditar',  u.p_inv_editar);
      this._setCheckbox('pUsuarios',   'lbl-pUsuarios',   u.p_usuarios);
    } else if (pb) {
      pb.style.display = 'none';
    }

    this._abrirModal();
  },

  // Toggle visual do botão de permissão
  togglePerm(btn, campo) {
    const hidden = document.getElementById(campo);
    if (!hidden) return;
    const ativo = hidden.value === '1';
    this._aplicarEstadoBtn(btn, !ativo);
    hidden.value = ativo ? '0' : '1';
  },

  // Aplica visual ativo/inativo no botão
  _aplicarEstadoBtn(btn, ativo) {
    if (ativo) {
      btn.style.background   = 'rgba(0,212,170,0.12)';
      btn.style.borderColor  = '#00d4aa';
      btn.style.color        = '#00d4aa';
      btn.querySelector('span:last-child').style.color = '#00d4aa';
    } else {
      btn.style.background   = 'var(--surface-2)';
      btn.style.borderColor  = 'var(--border)';
      btn.querySelector('span:last-child').style.color = 'var(--text)';
    }
  },

  // Reseta todos os botões de permissão
  _resetCheckboxes() {
    const perms = ['pEntrada','pSaida','pEstoque','pInventario','pHistorico','pRelatorio','pInvEditar','pUsuarios'];
    perms.forEach(p => {
      const hidden = document.getElementById(p);
      const btn    = document.getElementById('btn-' + p);
      if (hidden) hidden.value = '0';
      if (btn)    this._aplicarEstadoBtn(btn, false);
    });
  },

  // Define o estado de um botão de permissão
  _setCheckbox(cbId, lblId, value) {
    const hidden = document.getElementById(cbId);
    const btn    = document.getElementById('btn-' + cbId);
    if (hidden) hidden.value = value ? '1' : '0';
    if (btn)    this._aplicarEstadoBtn(btn, !!value);
  },

  // Lê o valor atual das permissões dos hidden inputs
  _lerPermissoes(uid) {
    return {
      usuario_id:   uid,
      p_entrada:    document.getElementById('pEntrada')?.value    === '1' ? 1 : 0,
      p_saida:      document.getElementById('pSaida')?.value      === '1' ? 1 : 0,
      p_estoque:    document.getElementById('pEstoque')?.value    === '1' ? 1 : 0,
      p_inventario: document.getElementById('pInventario')?.value === '1' ? 1 : 0,
      p_historico:  document.getElementById('pHistorico')?.value  === '1' ? 1 : 0,
      p_relatorio:  document.getElementById('pRelatorio')?.value  === '1' ? 1 : 0,
      p_inv_editar: document.getElementById('pInvEditar')?.value  === '1' ? 1 : 0,
      p_usuarios:   document.getElementById('pUsuarios')?.value   === '1' ? 1 : 0,
    };
  },

  // Não utilizado mais mas mantido para não quebrar chamadas antigas
  atualizarCheckbox() {},

  _bindForm() {
    const form = document.getElementById('formUsuario');
    if (!form) return;
    form.onsubmit = null;
    form.onsubmit = async (e) => {
      e.preventDefault();
      const id  = this.usuarioEditandoId;
      const btn = document.getElementById('btnSalvarUsuario');
      btn.disabled = true;
      btn.textContent = 'Salvando...';

      const body = {
        nome:      document.getElementById('usuarioNome').value.trim(),
        email:     document.getElementById('usuarioEmail').value.trim(),
        perfil_id: Number(document.getElementById('usuarioPerfil').value),
        ativo:     Number(document.getElementById('usuarioAtivo').value),
      };

      const senha = document.getElementById('usuarioSenha').value;
      if (senha) body[id ? 'nova_senha' : 'senha'] = senha;
      if (id) body.id = id;

      try {
        const r = await apiPost(id ? 'usuario_editar' : 'usuario_criar', body);
        if (r.success) {
          // Salva permissões separadamente se superadmin
          if (Auth.isSuperAdmin()) {
            const uid = id || r.id;
            if (uid) await apiPost('permissoes_salvar', this._lerPermissoes(uid));
          }
          Toast.show(r.message, 'success');
          this.fecharModal();
          this.load();
        } else {
          Toast.show(r.message, 'error');
        }
      } catch {
        Toast.show('Erro de conexão', 'error');
      } finally {
        btn.disabled = false;
        btn.textContent = '💾 Salvar';
      }
    };
  },

  async excluir(id) {
    if (!confirm('Tem certeza que deseja excluir este usuário? Essa ação não pode ser desfeita.')) return;
    const r = await apiPost('usuario_excluir', { id });
    Toast.show(r.message, r.success ? 'success' : 'error');
    if (r.success) this.load();
  }
};

// ─── PERFIL ───────────────────────────────────
const Perfil = {
  async load(){
    const el=document.getElementById('perfilInfo'); if(!el) return;
    const r=await apiFetch({action:'meu_perfil'});
    if(!r.success){ el.textContent='Erro ao carregar'; return; }
    const u=r.data; const u2=Auth.user;
    el.innerHTML=`<div><span class="text-muted">Nome:</span> <strong>${esc(u.nome)}</strong></div><div><span class="text-muted">E-mail:</span> ${esc(u.email)}</div><div><span class="text-muted">Perfil:</span> <span class="badge" style="margin-left:4px">${esc(u2?.perfil||'')}</span></div><div><span class="text-muted">Membro desde:</span> ${u.criado_em}</div><div><span class="text-muted">Último login:</span> ${u.ultimo_login||'—'}</div>`;
    const form=document.getElementById('formSenha'); if(form&&!form._bound){ form._bound=true; form.addEventListener('submit',async e=>{ e.preventDefault();
      const n=document.getElementById('senhaNova').value; const c=document.getElementById('senhaConfirm').value;
      if(n!==c){ Toast.show('As senhas não coincidem','error'); return; }
      const r2=await apiPost('alterar_senha',{senha_atual:document.getElementById('senhaAtual').value,nova_senha:n});
      Toast.show(r2.message,r2.success?'success':'error'); if(r2.success) form.reset();
    }); }
  }
};

// ─── PAINEL DE ESTOQUE ────────────────────────
const _insumoCache = {};

const PainelEstoque = {
  dados: [],
  editandoId: null,

  async load() {
    const grid = document.getElementById('painelGrid');
    if (!grid) return;
    grid.innerHTML = '<div class="text-center text-muted" style="grid-column:1/-1;padding:40px">Carregando...</div>';

    try {
      const r = await apiFetch({ action: 'insumos', full: '1', t: Date.now() });
      if (!r.success) throw new Error(r.message || 'Erro');
      this.dados = r.data;
      r.data.forEach(i => { _insumoCache[i.id] = i; });
      this._renderResumo(r.data);
      this._renderGrid(r.data);
    } catch (e) {
      grid.innerHTML = `<div class="text-center text-muted" style="grid-column:1/-1;padding:40px">${e.message}</div>`;
    }
  },

  filtrar() {
    const busca  = (document.getElementById('painelBusca')?.value || '').toLowerCase();
    const status = document.getElementById('painelFiltroStatus')?.value || '';
    const filtrado = this.dados.filter(i => {
      const nomeBate   = i.nome.toLowerCase().includes(busca);
      const statusBate = !status || i.status === status;
      return nomeBate && statusBate;
    });
    this._renderGrid(filtrado);
  },

  _renderResumo(dados) {
    const ok       = dados.filter(i => i.status === 'ok').length;
    const atencao  = dados.filter(i => i.status === 'atencao').length;
    const criticos = dados.filter(i => i.status === 'critico' || i.status === 'zerado').length;
    setText('painelTotalItens',   dados.length);
    setText('painelTotalOk',      ok);
    setText('painelTotalAtencao', atencao);
    setText('painelTotalCritico', criticos);
  },

  _renderGrid(dados) {
    const grid = document.getElementById('painelGrid');

    const corStatus = {
      ok:      { borda:'#10b981', icone:'✅', label:'Normal'  },
      atencao: { borda:'#f59e0b', icone:'⚠️', label:'Atenção' },
      critico: { borda:'#f43f5e', icone:'🚨', label:'Crítico' },
      zerado:  { borda:'#64748b', icone:'❌', label:'Zerado'  },
    };

    if (!dados.length) {
      grid.innerHTML = '<div class="text-center text-muted" style="grid-column:1/-1;padding:40px">Nenhum insumo encontrado.</div>';
      return;
    }

    grid.innerHTML = dados.map(item => {
      const cor    = corStatus[item.status] || corStatus.ok;
      const pct    = item.estoque_minimo > 0
        ? Math.min(100, Math.round((item.estoque_atual / (item.estoque_minimo * 2)) * 100))
        : 100;
      const barCor = item.status === 'critico' || item.status === 'zerado'
        ? '#f43f5e' : item.status === 'atencao' ? '#f59e0b' : '#10b981';
      const inativo = !item.ativo;

      return `
        <div style="
          background:var(--surface);
          border:1.5px solid ${cor.borda};
          border-radius:var(--radius);
          padding:18px;
          box-shadow:var(--shadow);
          opacity:${inativo ? 0.5 : 1};
          position:relative;
          transition:transform .2s, box-shadow .2s
        " onmouseover="this.style.transform='translateY(-2px)';this.style.boxShadow='var(--shadow-lg)'"
           onmouseout="this.style.transform='';this.style.boxShadow='var(--shadow)'">

          ${inativo ? `<div style="position:absolute;top:10px;right:10px;background:#64748b;color:#fff;font-size:10px;padding:2px 8px;border-radius:20px;font-weight:700;letter-spacing:0.5px">INATIVO</div>` : ''}

          <!-- Nome e ícone -->
          <div class="flex items-center gap-8" style="margin-bottom:14px;justify-content:space-between">
            <div style="flex:1;min-width:0">
              <div style="font-size:14px;font-weight:700;color:var(--text);white-space:nowrap;overflow:hidden;text-overflow:ellipsis" title="${esc(item.nome)}">${esc(item.nome)}</div>
              <div style="font-size:11px;color:var(--text-2);margin-top:3px">Unidade: <strong style="color:var(--text)">${esc(item.unidade)}</strong></div>
            </div>
            <span style="font-size:22px;flex-shrink:0">${cor.icone}</span>
          </div>

          <!-- Quantidade em destaque -->
          <div style="display:flex;align-items:baseline;gap:6px;margin-bottom:8px">
            <span style="font-size:40px;font-weight:700;color:${cor.borda};line-height:1">${item.estoque_atual}</span>
            <span style="font-size:13px;color:var(--text-2)">${esc(item.unidade)}</span>
          </div>

          <!-- Barra de progresso -->
          <div style="height:7px;background:var(--border);border-radius:99px;overflow:hidden;margin-bottom:14px">
            <div style="height:100%;width:${pct}%;background:${barCor};border-radius:99px;transition:width .5s ease"></div>
          </div>

          <!-- Grade de informações -->
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;font-size:12px">
            <div style="background:var(--surface-2);border-radius:8px;padding:8px 10px;border:1px solid var(--border)">
              <div style="color:var(--text-2);margin-bottom:2px">Mínimo</div>
              <div style="font-weight:700;color:var(--text);font-size:14px">${item.estoque_minimo} <span style="font-size:11px;font-weight:400">${esc(item.unidade)}</span></div>
            </div>
            <div style="background:var(--surface-2);border-radius:8px;padding:8px 10px;border:1px solid var(--border)">
              <div style="color:var(--text-2);margin-bottom:2px">Status</div>
              <div style="font-weight:700;color:${cor.borda};font-size:14px">${cor.label}</div>
            </div>
            <div style="background:var(--surface-2);border-radius:8px;padding:8px 10px;border:1px solid var(--border)">
              <div style="color:var(--text-2);margin-bottom:2px">Entradas</div>
              <div style="font-weight:700;color:#10b981;font-size:14px">${item.total_entradas}</div>
            </div>
            <div style="background:var(--surface-2);border-radius:8px;padding:8px 10px;border:1px solid var(--border)">
              <div style="color:var(--text-2);margin-bottom:2px">Saídas</div>
              <div style="font-weight:700;color:#f43f5e;font-size:14px">${item.total_saidas}</div>
            </div>
          </div>

          <!-- Última movimentação -->
          <div style="margin-top:10px;font-size:11px;color:var(--text-2);padding-top:10px;border-top:1px solid var(--border)">
            Última movimentação: <strong style="color:var(--text)">${item.ultima_mov || 'Nenhuma'}</strong>
          </div>
        </div>
      `;
    }).join('');
  },

  abrirModalNovo() {
    this.editandoId = null;
    document.getElementById('insumoModalTitulo').textContent = 'Novo Insumo';
    document.getElementById('formInsumo').reset();
    document.getElementById('insumoId').value   = '';
    document.getElementById('insumoMinimo').value = '5';
    document.getElementById('insumoQtdGrp').style.display   = 'block'; // mostra qtd inicial
    document.getElementById('insumoAtivoGrp').style.display = 'none';  // esconde status
    this._abrirModal();
  },

  editar(id) {
    const item = _insumoCache[id];
    if (!item) { Toast.show('Recarregue a página e tente novamente.', 'warn'); return; }

    this.editandoId = id;
    document.getElementById('insumoModalTitulo').textContent = 'Editar Insumo';
    document.getElementById('insumoId').value      = id;
    document.getElementById('insumoNome').value    = item.nome;
    document.getElementById('insumoUnidade').value = item.unidade;
    document.getElementById('insumoMinimo').value  = item.estoque_minimo;
    document.getElementById('insumoAtivo').value   = item.ativo;
    document.getElementById('insumoQtdGrp').style.display   = 'none';  // esconde qtd inicial
    document.getElementById('insumoAtivoGrp').style.display = 'block'; // mostra status
    this._abrirModal();
  },

  _abrirModal() {
    const m = document.getElementById('insumoModal');
    m.style.display = 'flex';
    document.body.style.overflow = 'hidden';

    const form = document.getElementById('formInsumo');
    form.onsubmit = null;
    form.onsubmit = async (e) => {
      e.preventDefault();
      const btn = document.getElementById('btnSalvarInsumo');
      btn.disabled = true; btn.textContent = 'Salvando...';

      const id = this.editandoId;
      const body = {
        nome:           document.getElementById('insumoNome').value.trim(),
        unidade:        document.getElementById('insumoUnidade').value,
        estoque_minimo: Number(document.getElementById('insumoMinimo').value),
      };

      if (id) {
        body.id    = id;
        body.ativo = Number(document.getElementById('insumoAtivo').value);
      } else {
        body.qtd_inicial = Number(document.getElementById('insumoQtdInicial').value || 0);
      }

      try {
        const r = await apiPost(id ? 'insumo_editar' : 'insumo_criar', body);
        if (r.success) {
          Toast.show(r.message, 'success');
          this.fecharModal();
          this.load();
        } else {
          Toast.show(r.message, 'error');
        }
      } catch {
        Toast.show('Erro de conexão', 'error');
      } finally {
        btn.disabled = false; btn.textContent = '💾 Salvar';
      }
    };
  },

  fecharModal() {
    document.getElementById('insumoModal').style.display = 'none';
    document.body.style.overflow = '';
    this.editandoId = null;
  },

  async toggle(id) {
    const item = _insumoCache[id];
    const acao = item?.ativo ? 'desativar' : 'reativar';
    if (!confirm(`Deseja ${acao} o insumo "${item?.nome}"?`)) return;
    const r = await apiPost('insumo_toggle', { id });
    Toast.show(r.message, r.success ? 'success' : 'error');
    if (r.success) this.load();
  }
};

// ─── INIT ─────────────────────────────────────
document.addEventListener('DOMContentLoaded', async () => {
  if (!Auth.init()) return;
  Theme.init();
  Sidebar.init();
  startClock();
  const modalUsuario = document.getElementById('usuarioModal');
  if (modalUsuario) modalUsuario.addEventListener('click', e => { if (e.target === modalUsuario) Usuarios.fecharModal(); });
  const modalInsumo = document.getElementById('insumoModal');
  if (modalInsumo) modalInsumo.addEventListener('click', e => { if (e.target === modalInsumo) PainelEstoque.fecharModal(); });
  try {
    const r = await apiFetch({ action: 'me' });
    if (r.success && r.usuario) {
      Auth.user = r.usuario;
      localStorage.setItem('insumos_user', JSON.stringify(r.usuario));
      localStorage.setItem('insumos_perms', JSON.stringify(r.usuario));
    }
  } catch {}
  aplicarPermissoes();
  const hashPage = window.location.hash.replace('#','') || '';
  const validPages = ['dashboard','estoque','painel-estoque','entrada','saida','inventario','historico','relatorio','usuarios','perfil'];
  const _ssoPage = localStorage.getItem('insumos_sso_page');
  const _lastPage = localStorage.getItem('insumos_last_page');
  if (_ssoPage && _ssoPage !== 'dashboard') {
    // Página específica solicitada — vai direto para ela
    localStorage.removeItem('insumos_sso_page');
    navTo(validPages.includes(_ssoPage) ? _ssoPage : 'dashboard');
  } else if (_lastPage && validPages.includes(_lastPage)) {
    // Restaura última página visitada
    localStorage.removeItem('insumos_sso_page');
    navTo(_lastPage);
  } else if (hashPage && validPages.includes(hashPage)) {
    navTo(hashPage);
  } else {
    navTo('dashboard');
  }
  window.addEventListener('message', (e) => {
    if (e.data && e.data.insumos_page) navTo(e.data.insumos_page);
  });
});

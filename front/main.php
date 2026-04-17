<?php
include('../../../inc/includes.php');
Session::checkLoginUser();

define('PLUGIN_INSUMOS_SSO_KEY', 'InsumosSSOSecret2025');

$insumos_url = PLUGIN_INSUMOS_URL;
$glpi_id    = (int)($_SESSION['glpiID']    ?? 0);
$glpi_email = ($_SESSION['glpiemail'] ?? '') ?: ($_SESSION['glpiname'] . '@glpi.local');
$glpi_user  = $_SESSION['glpiname']        ?? 'usuario';
$glpi_perfil= $_SESSION['glpiactiveprofile']['name'] ?? 'normal';
$timestamp  = time();
$payload    = $glpi_id . '|' . $glpi_email . '|' . $timestamp;
$signature  = hash_hmac('sha256', $payload, PLUGIN_INSUMOS_SSO_KEY);
$pagina     = preg_replace('/[^a-z\-]/', '', $_GET['page'] ?? 'dashboard');

$iframe_url = $insumos_url . '/sso.php'
   . '?uid='    . urlencode((string)$glpi_id)
   . '&email='  . urlencode($glpi_email)
   . '&nome='   . urlencode($glpi_user)
   . '&perfil=' . urlencode($glpi_perfil)
   . '&ts='     . $timestamp
   . '&sig='    . $signature
   . '&page='   . $pagina;

$paginas = [
   'dashboard'      => ['fas fa-chart-bar',    'Dashboard'],
   'estoque'        => ['fas fa-boxes',         'Estoque'],
   'painel-estoque' => ['fas fa-th-large',      'Painel de Estoque'],
   'entrada'        => ['fas fa-arrow-up',      'Entrada'],
   'saida'          => ['fas fa-arrow-down',    'Saída'],
   'inventario'     => ['fas fa-clipboard-list','Inventário'],
   'historico'      => ['fas fa-history',       'Histórico'],
   'relatorio'      => ['fas fa-chart-line',    'Relatórios'],
];

error_log('MAIN.PHP chamado para uid='.$glpi_id.' email='.$glpi_email);
Html::header('Insumos T.I.', $_SERVER['PHP_SELF'], 'insumos', 'PluginInsumos');
?>
<style>
.ins-nav{display:flex;align-items:stretch;gap:0;padding:0 8px;background:var(--glpi-mainmenu-bg,#2c3e50);border-bottom:3px solid rgba(255,255,255,0.08);flex-wrap:wrap;min-height:46px}
.ins-brand{display:flex;align-items:center;gap:8px;color:#fff;font-weight:700;font-size:14px;padding:0 16px 0 4px;margin-right:4px;border-right:1px solid rgba(255,255,255,0.12);white-space:nowrap;letter-spacing:.3px}
.ins-brand i{color:var(--glpi-primary,#0d6efd);font-size:17px}
.ins-tab{display:inline-flex;align-items:center;gap:7px;padding:0 15px;color:rgba(255,255,255,0.65);text-decoration:none;font-size:13px;font-weight:500;border-bottom:3px solid transparent;margin-bottom:-3px;cursor:pointer;transition:color .15s,border-color .15s,background .15s;white-space:nowrap;background:transparent;border-top:none;border-left:none;border-right:none;font-family:inherit}
.ins-tab:hover{color:#fff;background:rgba(255,255,255,0.06);border-bottom-color:rgba(255,255,255,0.25)}
.ins-tab.active{color:#fff;border-bottom-color:var(--glpi-primary,#0d6efd);background:rgba(13,110,253,0.1)}
.ins-tab i{font-size:13px}
.ins-user{margin-left:auto;color:rgba(255,255,255,0.45);font-size:12px;display:flex;align-items:center;gap:6px;padding:0 8px}
#ins-frame{width:100%;border:none;display:block;background:#f4f4f4}
</style>

<div class="container-fluid" style="padding:0">
  <nav class="ins-nav">
    <div class="ins-brand"><i class="fas fa-box-open"></i> Insumos T.I.</div>

    <?php foreach ($paginas as $key => [$icon, $label]): ?>
    <?php if ($key === 'painel-estoque'): ?>
    <a class="ins-tab" href="http://10.10.1.15/insumos_ti/painel.php" target="_blank" style="text-decoration:none">
      <i class="<?= $icon ?>"></i><?= $label ?>
    </a>
    <?php else: ?>
    <button class="ins-tab <?= $pagina===$key?'active':'' ?>"
            onclick="irPara('<?= $key ?>')">
      <i class="<?= $icon ?>"></i><?= $label ?>
    </button>
    <?php endif; ?>
    <?php endforeach; ?>

    <div class="ins-user"><i class="fas fa-user-circle"></i><?= htmlspecialchars($glpi_user) ?></div>
  </nav>

  <iframe id="ins-frame" src="<?= htmlspecialchars($iframe_url) ?>" title="Insumos T.I."></iframe>
</div>

<script>
var _iframeReady = false;
var _pendingPage = null;

function irPara(page) {
  document.querySelectorAll('.ins-tab').forEach(function(t){
    t.classList.toggle('active', t.getAttribute('onclick') && t.getAttribute('onclick').indexOf("'"+page+"'") !== -1);
  });
  var frame = document.getElementById('ins-frame');
  if (_iframeReady) {
    frame.contentWindow.postMessage({ insumos_page: page }, '*');
  } else {
    _pendingPage = page;
  }
}

function ajustar() {
  var nav   = document.querySelector('.ins-nav');
  var frame = document.getElementById('ins-frame');
  if (!nav || !frame) return;
  var h = window.innerHeight - nav.getBoundingClientRect().top - nav.offsetHeight;
  frame.style.height = Math.max(h, 400) + 'px';
}

document.getElementById('ins-frame').addEventListener('load', function() {
  _iframeReady = true;
  ajustar();
  if (_pendingPage) {
    var frame = document.getElementById('ins-frame');
    frame.contentWindow.postMessage({ insumos_page: _pendingPage }, '*');
    _pendingPage = null;
  }
});

ajustar();
window.addEventListener('resize', ajustar);
</script>

<?php Html::footer(); ?>
<!-- DEBUG: <?php echo htmlspecialchars($iframe_url); ?> -->

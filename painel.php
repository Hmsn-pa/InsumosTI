<?php
require_once __DIR__ . '/includes/config.php';
try {
    $pdo = db();
    $stmt = $pdo->query("
        SELECT i.id, i.nome, i.unidade, i.estoque_minimo,
               COALESCE(SUM(CASE m.tipo WHEN 'ENTRADA' THEN m.quantidade ELSE -m.quantidade END), 0) AS estoque_atual,
               MAX(m.criado_em) AS ultima_mov
        FROM insumos i
        LEFT JOIN movimentacoes m ON m.insumo_id = i.id
        WHERE i.ativo = 1
        GROUP BY i.id, i.nome, i.unidade, i.estoque_minimo
        ORDER BY i.nome ASC
    ");
    $insumos = $stmt->fetchAll();
    foreach ($insumos as &$item) {
        $atual  = (int)$item['estoque_atual'];
        $minimo = (int)$item['estoque_minimo'];
        if ($atual <= 0)                       $item['status'] = 'zerado';
        elseif ($atual <= intval($minimo*0.5)) $item['status'] = 'critico';
        elseif ($atual <= $minimo)             $item['status'] = 'atencao';
        else                                   $item['status'] = 'normal';
        $item['estoque_atual'] = $atual;
        $max = max($minimo * 2, $atual);
        $item['pct'] = $max > 0 ? min(100, round(($atual / $max) * 100)) : 0;
        $item['cobertura'] = $minimo > 0 ? min(100, round(($atual / $minimo) * 100)) : 100;
    }
    unset($item);
    $normal  = count(array_filter($insumos, fn($i) => $i['status'] === 'normal'));
    $atencao = count(array_filter($insumos, fn($i) => $i['status'] === 'atencao'));
    $critico = count(array_filter($insumos, fn($i) => $i['status'] === 'critico'));
    $zerado  = count(array_filter($insumos, fn($i) => $i['status'] === 'zerado'));
} catch (Exception $e) {
    $insumos = [];
    $normal = $atencao = $critico = $zerado = 0;
}
$ultima_atualizacao = date('d/m/Y H:i:s');
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Painel de Estoque — T.I.</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;700&display=swap" rel="stylesheet">
<style>
:root{--bg:#0a0f1e;--surface:#0f1829;--surface2:#152035;--border:#1e3050;--brand:#00d4aa;--brand2:#0096ff;--warn:#f59e0b;--danger:#ef4444;--purple:#8b5cf6;--text:#d1e8ff;--text2:#6b8aaa;--radius:12px}
*{box-sizing:border-box;margin:0;padding:0}
html,body{height:100%;background:var(--bg);color:var(--text);font-family:'Inter',sans-serif}
body{padding:16px;background-image:radial-gradient(ellipse at 10% 0%,rgba(0,212,170,.04) 0%,transparent 50%),radial-gradient(ellipse at 90% 100%,rgba(0,150,255,.04) 0%,transparent 50%)}
.header{display:flex;align-items:center;justify-content:space-between;padding:14px 20px;background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);margin-bottom:16px;border-left:4px solid var(--brand)}
.header-left{display:flex;align-items:center;gap:14px}
.logo{width:42px;height:42px;background:linear-gradient(135deg,var(--brand),var(--brand2));border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:22px;flex-shrink:0}
.title{font-size:20px;font-weight:700;color:#fff;letter-spacing:.5px}
.subtitle{font-size:12px;color:var(--text2);margin-top:2px}
.header-right{display:flex;align-items:center;gap:20px;text-align:right}
.update-info{font-size:11px;color:var(--text2);line-height:1.6}
.update-info strong{color:var(--brand);font-family:'JetBrains Mono',monospace}
.btn-atualizar{display:inline-flex;align-items:center;gap:6px;padding:8px 16px;background:transparent;border:1px solid var(--brand);color:var(--brand);border-radius:8px;font-family:'Inter',sans-serif;font-size:13px;font-weight:600;cursor:pointer;text-decoration:none;transition:background .2s}
.btn-atualizar:hover{background:rgba(0,212,170,.1)}
.btn-atualizar::before{content:'●';font-size:8px;animation:blink 1.5s ease-in-out infinite}
@keyframes blink{0%,100%{opacity:1}50%{opacity:.3}}
.resumo{display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-bottom:16px}
.resumo-card{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);padding:24px 20px;text-align:center;position:relative;overflow:hidden}
.resumo-card::after{content:'';position:absolute;top:0;left:0;right:0;height:3px}
.resumo-card.normal::after{background:var(--brand)}
.resumo-card.atencao::after{background:var(--warn)}
.resumo-card.critico::after{background:var(--danger)}
.resumo-card.zerado::after{background:var(--purple)}
.resumo-icon{font-size:32px;margin-bottom:10px}
.resumo-num{font-size:52px;font-weight:700;line-height:1;font-family:'JetBrains Mono',monospace;margin-bottom:6px}
.resumo-card.normal .resumo-num{color:var(--brand)}
.resumo-card.atencao .resumo-num{color:var(--warn)}
.resumo-card.critico .resumo-num{color:var(--danger)}
.resumo-card.zerado .resumo-num{color:var(--purple)}
.resumo-label{font-size:13px;font-weight:700;text-transform:uppercase;letter-spacing:1.5px;color:var(--text2)}
.grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:14px}
.card{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);padding:18px;position:relative;overflow:hidden;transition:transform .2s,border-color .2s}
.card:hover{transform:translateY(-2px);border-color:rgba(255,255,255,.1)}
.card::before{content:'';position:absolute;top:0;left:0;right:0;height:3px}
.card.normal::before{background:var(--brand)}
.card.atencao::before{background:var(--warn)}
.card.critico::before{background:var(--danger)}
.card.zerado::before{background:var(--purple)}
.card-header{display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:12px}
.card-nome{font-size:15px;font-weight:700;color:#fff;line-height:1.3}
.card-unidade{font-size:11px;color:var(--text2);margin-top:2px}
.badge{font-size:10px;font-weight:700;padding:3px 10px;border-radius:20px;text-transform:uppercase;letter-spacing:.5px;flex-shrink:0}
.badge.normal{background:rgba(0,212,170,.15);color:var(--brand)}
.badge.atencao{background:rgba(245,158,11,.15);color:var(--warn)}
.badge.critico{background:rgba(239,68,68,.15);color:var(--danger)}
.badge.zerado{background:rgba(139,92,246,.15);color:var(--purple)}
.card-qty{display:flex;align-items:baseline;gap:6px;margin-bottom:10px}
.qty-num{font-size:46px;font-weight:700;line-height:1;font-family:'JetBrains Mono',monospace}
.card.normal .qty-num{color:var(--brand)}
.card.atencao .qty-num{color:var(--warn)}
.card.critico .qty-num{color:var(--danger)}
.card.zerado .qty-num{color:var(--purple)}
.qty-unit{font-size:14px;color:var(--text2)}
.progress{height:4px;background:var(--border);border-radius:99px;overflow:hidden;margin-bottom:14px}
.progress-bar{height:100%;border-radius:99px;transition:width .5s ease}
.card.normal .progress-bar{background:var(--brand)}
.card.atencao .progress-bar{background:var(--warn)}
.card.critico .progress-bar{background:var(--danger)}
.card.zerado .progress-bar{background:var(--purple)}
.card-info{display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:12px}
.info-item{background:var(--surface2);border-radius:6px;padding:8px 10px}
.info-label{font-size:10px;color:var(--text2);text-transform:uppercase;letter-spacing:.5px;margin-bottom:2px}
.info-val{font-size:13px;font-weight:700;color:var(--text)}
.card-footer{font-size:11px;color:var(--text2);padding-top:10px;border-top:1px solid var(--border)}
.footer{text-align:center;margin-top:20px;padding:14px;font-size:12px;color:var(--text2)}
@media(max-width:768px){.resumo{grid-template-columns:repeat(2,1fr)};body{padding:10px}}
</style>
</head>
<body>
<div class="header">
  <div class="header-left">
    <div class="logo">📦</div>
    <div>
      <div class="title">Painel de Estoque — T.I.</div>
      <div class="subtitle">Monitoramento em tempo real · Atualização automática a cada 60s</div>
    </div>
  </div>
  <div class="header-right">
    <div class="update-info">Última atualização:<br><strong><?= $ultima_atualizacao ?></strong></div>
    <a href="painel.php" class="btn-atualizar">Atualizar</a>
  </div>
</div>
<div class="resumo">
  <div class="resumo-card normal"><div class="resumo-icon">✅</div><div class="resumo-num"><?= $normal ?></div><div class="resumo-label">Normal</div></div>
  <div class="resumo-card atencao"><div class="resumo-icon">⚠️</div><div class="resumo-num"><?= $atencao ?></div><div class="resumo-label">Atenção</div></div>
  <div class="resumo-card critico"><div class="resumo-icon">🚨</div><div class="resumo-num"><?= $critico ?></div><div class="resumo-label">Crítico</div></div>
  <div class="resumo-card zerado"><div class="resumo-icon">❌</div><div class="resumo-num"><?= $zerado ?></div><div class="resumo-label">Zerado</div></div>
</div>
<div class="grid">
<?php foreach ($insumos as $item):
    $status=$item['status'];$atual=$item['estoque_atual'];$minimo=$item['estoque_minimo'];
    $pct=$item['pct'];$cobertura=$item['cobertura'];
    $labels=['normal'=>'NORMAL','atencao'=>'ATENÇÃO','critico'=>'CRÍTICO','zerado'=>'ZERADO'];
    $ultima=$item['ultima_mov']?date('d/m/Y H:i',strtotime($item['ultima_mov'])):'Nenhuma';
?>
<div class="card <?= $status ?>">
  <div class="card-header">
    <div><div class="card-nome"><?= htmlspecialchars($item['nome']) ?></div><div class="card-unidade">Unidade: <?= htmlspecialchars($item['unidade']) ?></div></div>
    <span class="badge <?= $status ?>"><?= $labels[$status] ?></span>
  </div>
  <div class="card-qty"><span class="qty-num"><?= $atual ?></span><span class="qty-unit"><?= htmlspecialchars($item['unidade']) ?></span></div>
  <div class="progress"><div class="progress-bar" style="width:<?= $pct ?>%"></div></div>
  <div class="card-info">
    <div class="info-item"><div class="info-label">Mínimo</div><div class="info-val"><?= $minimo ?> <?= htmlspecialchars($item['unidade']) ?></div></div>
    <div class="info-item"><div class="info-label">Cobertura</div><div class="info-val"><?= $cobertura ?>%</div></div>
  </div>
  <div class="card-footer">Última mov.: <?= $ultima ?></div>
</div>
<?php endforeach; ?>
</div>
<div class="footer">Sistema de Controle de Insumos T.I. — Hospital da Mulher Nossa Senhora de Nazaré</div>
<script>var s=60;setInterval(function(){s--;if(s<=0)window.location.reload();},1000);</script>
</body>
</html>

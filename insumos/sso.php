<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/config.php';
define('SSO_SECRET', 'InsumosSSOSecret2025');
define('SSO_TTL', 300);
$uid    = (int)($_GET['uid']    ?? 0);
$email  = trim($_GET['email']   ?? '');
$nome   = trim($_GET['nome']    ?? '');
$perfil = trim($_GET['perfil']  ?? 'normal');
$ts     = (int)($_GET['ts']     ?? 0);
$sig    = trim($_GET['sig']     ?? '');
$pagina = preg_replace('/[^a-z\-]/', '', $_GET['page'] ?? 'dashboard');
error_log('SSO chamado: uid='.$uid.' ts='.$ts);
if (!$uid || !$email || !$ts || !$sig) { http_response_code(400); die('Parâmetros inválidos.'); }
if (abs(time() - $ts) > SSO_TTL) { http_response_code(401); die('Token expirado.'); }
$payload  = $uid . '|' . $email . '|' . $ts;
$expected = hash_hmac('sha256', $payload, SSO_SECRET);
if (!hash_equals($expected, $sig)) { http_response_code(403); die('Assinatura inválida.'); }
$perfilGlpi = strtolower($perfil);
$perfilId = in_array($perfilGlpi, ['super-admin','super admin','superadmin','administrator','admin']) ? 1 : 3;
$pdo = db();
$pdo->prepare("DELETE FROM sessoes WHERE expira_em < NOW()")->execute();
$stmt = $pdo->prepare("SELECT u.id, u.nome, u.ativo, u.perfil_id, p.nome AS perfil FROM usuarios u JOIN perfis p ON p.id = u.perfil_id WHERE u.email = :email OR u.nome = :nome");
$stmt->execute([':email' => $email, ':nome' => $nome]);
$usuario = $stmt->fetch();
if (!$usuario) {
    $senhaHash = password_hash(bin2hex(random_bytes(16)), PASSWORD_BCRYPT);
    $pdo->prepare("INSERT INTO usuarios (nome, email, senha_hash, perfil_id) VALUES (:nome, :email, :hash, :perfil)")->execute([':nome' => $nome ?: $email, ':email' => $email, ':hash' => $senhaHash, ':perfil' => $perfilId]);
    $userId = (int)$pdo->lastInsertId();
    if ($perfilId === 1) {
        $pdo->prepare("INSERT INTO permissoes (usuario_id,p_entrada,p_saida,p_estoque,p_inventario,p_historico,p_relatorio,p_inv_editar,p_usuarios) VALUES (:uid,1,1,1,1,1,1,1,1)")->execute([':uid' => $userId]);
    } else {
        $pdo->prepare("INSERT INTO permissoes (usuario_id,p_entrada,p_saida,p_estoque,p_inventario,p_historico,p_relatorio,p_inv_editar,p_usuarios) VALUES (:uid,1,1,1,1,0,0,0,0)")->execute([':uid' => $userId]);
    }
} else {
    if (!$usuario['ativo']) { http_response_code(403); die('Usuário inativo.'); }
    $userId = (int)$usuario['id'];
    if ((int)$usuario['perfil_id'] !== $perfilId) {
        $pdo->prepare("UPDATE usuarios SET perfil_id = :pid WHERE id = :id")->execute([':pid' => $perfilId, ':id' => $userId]);
        if ($perfilId === 1) {
            $pdo->prepare("UPDATE permissoes SET p_entrada=1,p_saida=1,p_estoque=1,p_inventario=1,p_historico=1,p_relatorio=1,p_inv_editar=1,p_usuarios=1 WHERE usuario_id=:uid")->execute([':uid' => $userId]);
        }
    }
}
$token    = bin2hex(random_bytes(32));
$expiraEm = date('Y-m-d H:i:s', strtotime('+8 hours'));
$pdo->prepare("INSERT INTO sessoes (usuario_id, token, expira_em) VALUES (:uid, :tok, :exp)")->execute([':uid' => $userId, ':tok' => $token, ':exp' => $expiraEm]);
$pdo->prepare("UPDATE usuarios SET ultimo_login = NOW() WHERE id = :id")->execute([':id' => $userId]);
setcookie('insumos_token', $token, ['expires' => time()+28800, 'path' => '/', 'httponly' => false, 'samesite' => 'Lax']);
$userJson = json_encode(['id' => $userId, 'nome' => $nome ?: $email, 'email' => $email, 'perfil' => $perfilId === 1 ? 'superadmin' : 'funcionario']);
?>
<!DOCTYPE html><html lang="pt-BR"><head><meta charset="UTF-8">
<style>body{margin:0;background:#f0f4f8;display:flex;align-items:center;justify-content:center;min-height:100vh;font-family:sans-serif}@keyframes spin{to{transform:rotate(360deg)}}</style>
</head><body>
<div style="text-align:center"><div style="width:40px;height:40px;border:4px solid #e2e8f0;border-top-color:#00d4aa;border-radius:50%;animation:spin .8s linear infinite;margin:0 auto"></div><p style="color:#64748b;margin-top:16px;font-size:14px">Autenticando...</p></div>
<script>
try {
  localStorage.setItem('insumos_token', <?= json_encode($token) ?>);
  localStorage.setItem('insumos_user', <?= json_encode($userJson) ?>);
  localStorage.removeItem('insumos_perms');
} catch(e) {}
// Redireciona passando token na URL como fallback para iframe
var dest = 'index.php?sso_token=' + encodeURIComponent(<?= json_encode($token) ?>) + '&sso_page=<?= $pagina ?>';
// Tenta salvar no localStorage E redireciona com token na URL como dupla garantia
window.location.replace(dest);
</script>
</body></html>

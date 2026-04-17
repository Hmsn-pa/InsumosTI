<?php
// =============================================================
// sso.php  (na raiz do sistema de insumos: /insumos_ti/sso.php)
// Recebe o login SSO vindo do plugin GLPI, valida a assinatura
// HMAC e cria uma sessão autenticada no sistema de insumos.
// =============================================================
declare(strict_types=1);

require_once __DIR__ . '/includes/config.php';

// ── Chave secreta compartilhada com o plugin GLPI ──────────────
// DEVE ser idêntica à definida em front/main.php do plugin
define('SSO_SECRET', 'InsumosSSOSecret2025');

// ── Tolerância de tempo: 60 segundos ──────────────────────────
define('SSO_TTL', 60);

// ── Parâmetros recebidos ───────────────────────────────────────
$uid    = (int)($_GET['uid']    ?? 0);
$email  = trim($_GET['email']   ?? '');
$nome   = trim($_GET['nome']    ?? '');
$perfil = trim($_GET['perfil']  ?? 'normal');
$ts     = (int)($_GET['ts']     ?? 0);
$sig    = trim($_GET['sig']     ?? '');
$pagina = preg_replace('/[^a-z\-]/', '', $_GET['page'] ?? 'dashboard');

// ── Validações básicas ─────────────────────────────────────────
if (!$uid || !$email || !$ts || !$sig) {
    http_response_code(400);
    die('Parâmetros SSO inválidos.');
}

// ── Verifica janela de tempo (evita replay attacks) ────────────
if (abs(time() - $ts) > SSO_TTL) {
    http_response_code(401);
    die('Token SSO expirado. Recarregue a página no GLPI.');
}

// ── Verifica assinatura HMAC ───────────────────────────────────
$payload  = $uid . '|' . $email . '|' . $ts;
$expected = hash_hmac('sha256', $payload, SSO_SECRET);

if (!hash_equals($expected, $sig)) {
    http_response_code(403);
    die('Assinatura SSO inválida.');
}

// ── Mapeia perfil GLPI → perfil do sistema de insumos ─────────
$pdo = db();

// Limpa sessões expiradas do usuário
$pdo->prepare("DELETE FROM sessoes WHERE expira_em < NOW()")->execute();

// Busca ou cria o usuário no banco de insumos pelo e-mail
$stmt = $pdo->prepare("
    SELECT u.id, u.nome, u.ativo, p.nome AS perfil
    FROM usuarios u
    JOIN perfis p ON p.id = u.perfil_id
    WHERE u.email = :email
");
$stmt->execute([':email' => $email]);
$usuario = $stmt->fetch();

if (!$usuario) {
    // Usuário GLPI ainda não existe no sistema de insumos
    // Cria automaticamente com perfil funcionário
    $perfilId = 3; // funcionario

    // Senha aleatória (nunca será usada — login só via SSO)
    $senhaHash = password_hash(bin2hex(random_bytes(16)), PASSWORD_BCRYPT);

    $pdo->prepare("
        INSERT INTO usuarios (nome, email, senha_hash, perfil_id)
        VALUES (:nome, :email, :hash, :perfil)
    ")->execute([
        ':nome'   => $nome ?: $email,
        ':email'  => $email,
        ':hash'   => $senhaHash,
        ':perfil' => $perfilId,
    ]);

    $userId = (int)$pdo->lastInsertId();

    // Permissões padrão de funcionário
    $pdo->prepare("
        INSERT INTO permissoes
            (usuario_id, p_entrada, p_saida, p_estoque, p_inventario,
             p_historico, p_relatorio, p_inv_editar, p_usuarios)
        VALUES (:uid, 1, 1, 1, 1, 0, 0, 0, 0)
    ")->execute([':uid' => $userId]);

    $perfilNome = 'funcionario';
} else {
    if (!$usuario['ativo']) {
        http_response_code(403);
        die('Usuário inativo no sistema de insumos. Fale com o administrador.');
    }
    $userId     = (int)$usuario['id'];
    $perfilNome = $usuario['perfil'];
}

// ── Cria token de sessão ───────────────────────────────────────
$token    = bin2hex(random_bytes(32));
$expiraEm = date('Y-m-d H:i:s', strtotime('+8 hours'));

$pdo->prepare("
    INSERT INTO sessoes (usuario_id, token, expira_em)
    VALUES (:uid, :tok, :exp)
")->execute([':uid' => $userId, ':tok' => $token, ':exp' => $expiraEm]);

// Atualiza último login
$pdo->prepare("UPDATE usuarios SET ultimo_login = NOW() WHERE id = :id")
    ->execute([':id' => $userId]);

// ── Salva token em cookie seguro ───────────────────────────────
setcookie('insumos_token', $token, [
    'expires'  => strtotime('+8 hours'),
    'path'     => '/',
    'httponly' => false, // precisa ser lido pelo JS
    'samesite' => 'Lax',
]);

// ── Redireciona para o sistema com token no localStorage ────────
// Usa uma página intermediária em HTML para salvar no localStorage
// (cookies HttpOnly não são acessíveis ao JS, mas este não é HttpOnly)
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Autenticando...</title>
  <style>
    body { margin:0; display:flex; align-items:center; justify-content:center;
           min-height:100vh; font-family:sans-serif; background:#f0f4f8; }
    .spinner { width:40px; height:40px; border:4px solid #e2e8f0;
               border-top-color:#00d4aa; border-radius:50%;
               animation:spin .8s linear infinite; }
    @keyframes spin { to { transform:rotate(360deg); } }
    p { color:#64748b; margin-top:16px; font-size:14px; }
  </style>
</head>
<body>
  <div style="text-align:center">
    <div class="spinner"></div>
    <p>Autenticando com o GLPI...</p>
  </div>
  <script>
    // Salva token e dados do usuário no localStorage
    localStorage.setItem('insumos_token', <?= json_encode($token) ?>);
    localStorage.setItem('insumos_user',  <?= json_encode(json_encode([
        'id'     => $userId,
        'nome'   => $nome ?: $email,
        'email'  => $email,
        'perfil' => $perfilNome,
    ])) ?>);

    // Remove perms antigas para forçar recarregamento
    localStorage.removeItem('insumos_perms');

    // Redireciona para o sistema já autenticado
    setTimeout(() => {
        window.location.href = 'index.php#<?= $pagina ?>';
    }, 300);
  </script>
</body>
</html>

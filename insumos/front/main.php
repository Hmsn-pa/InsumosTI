<?php
/**
 * Plugin Insumos T.I. — front/main.php
 * Ponto de entrada integrado ao GLPI.
 * Autentica o usuário via SSO e exibe a aplicação em iframe.
 */

include('../../../inc/includes.php');

Session::checkLoginUser();

// -------------------------------------------------------
// Dados do usuário logado no GLPI
// -------------------------------------------------------
$glpiUser = new User();
$glpiUser->getFromDB(Session::getLoginUserID());

$uid    = (int) Session::getLoginUserID();
$email  = $glpiUser->fields['email'] ?? ($glpiUser->fields['name'] . '@glpi.local');
$nome   = trim(($glpiUser->fields['firstname'] ?? '') . ' ' . ($glpiUser->fields['realname'] ?? ''));
if (empty(trim($nome))) {
    $nome = $glpiUser->fields['name'];
}
// Detecta super-admin pelos direitos reais do perfil GLPI
$profileId  = $_SESSION['glpiactiveprofile']['id'] ?? 0;
$isCentral  = Session::getCurrentInterface() === 'central';
$isSuperIds = Profile::getSuperAdminProfilesId();
$isSuperAdmin = $isCentral && in_array($profileId, $isSuperIds);
$perfil = $isSuperAdmin ? 'superadmin' : 'normal';

// -------------------------------------------------------
// Gera token SSO assinado
// -------------------------------------------------------
$secret  = 'InsumosSSOSecret2025';
$ts      = time();
$payload = $uid . '|' . $email . '|' . $ts;
$sig     = hash_hmac('sha256', $payload, $secret);
$page    = preg_replace('/[^a-z\-]/', '', $_GET['page'] ?? 'dashboard');

// $CFG_GLPI['root_doc'] é o path da instância atual — mesma abordagem do dashglpi
global $CFG_GLPI;
$baseUrl = $CFG_GLPI['root_doc'] . '/plugins/insumos';
$appUrl  = $baseUrl . '/sso.php?' . http_build_query([
    'uid'    => $uid,
    'email'  => $email,
    'nome'   => $nome,
    'perfil' => $perfil,
    'ts'     => $ts,
    'sig'    => $sig,
    'page'   => $page,
]);

// -------------------------------------------------------
// Renderiza dentro do layout do GLPI
// -------------------------------------------------------
// Usa a interface real do usuário — evita redirect indevido para central/helpdesk
$interface = Session::getCurrentInterface() ?: 'helpdesk';
Html::header(
    __('Insumos T.I.', 'insumos'),
    $_SERVER['PHP_SELF'],
    $interface,
    'insumos'
);
?>

<div id="insumos-wrapper" style="
    width: 100%;
    height: calc(100vh - 130px);
    min-height: 600px;
    border: none;
    border-radius: 8px;
    overflow: hidden;
    background: #0a0f1e;
">
    <iframe
        id="insumos-frame"
        src="<?= htmlspecialchars($appUrl) ?>"
        style="width:100%; height:100%; border:none; display:block;"
        allow="clipboard-read; clipboard-write"
        title="Controle de Insumos T.I."
    ></iframe>
</div>

<script>
(function () {
    function ajustarAltura() {
        var wrapper = document.getElementById('insumos-wrapper');
        if (!wrapper) return;
        var offset = wrapper.getBoundingClientRect().top + window.scrollY;
        wrapper.style.height = (window.innerHeight - offset - 20) + 'px';
    }
    ajustarAltura();
    window.addEventListener('resize', ajustarAltura);
})();
</script>

<?php Html::footer(); ?>

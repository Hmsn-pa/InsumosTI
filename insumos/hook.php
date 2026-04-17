<?php
/**
 * Plugin Insumos T.I. — Hook functions
 */

function plugin_insumos_install(): bool { return true; }
function plugin_insumos_uninstall(): bool { return true; }

/**
 * Injeta "Insumos T.I." dentro do menu Assistência (helpdesk).
 *
 * O Twig do GLPI usa path(page) = getPrefixedUrl(page) que faz:
 *   root_doc + page
 *
 * Se page começa com '/', faz: '/motorista' + '/plugins/...' = '/motorista/plugins/...' ✓
 * Se page NÃO começa com '/', faz: '/motorista' + '/' + 'plugins/...' = '/motorista/plugins/...' ✓
 *
 * NUNCA colocar root_doc dentro do page — ele já é adicionado pelo GLPI automaticamente.
 */
function plugin_insumos_redefine_menus(array $menus): array
{
    if (Session::getLoginUserID()) {
        $menus['helpdesk']['content']['insumos'] = [
            'title' => __('Insumos T.I.', 'insumos'),
            'page'  => '/plugins/insumos/front/main.php',
            'icon'  => 'ti ti-package',
        ];
    }

    return $menus;
}

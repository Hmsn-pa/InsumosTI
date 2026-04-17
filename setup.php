<?php
define("PLUGIN_INSUMOS_VERSION", "1.0.0");
define("PLUGIN_INSUMOS_MIN_GLPI", "10.0.0");
define("PLUGIN_INSUMOS_MAX_GLPI", "10.0.99");
define("PLUGIN_INSUMOS_URL", "http://10.10.1.15/insumos_ti");

function plugin_version_insumos() {
   return [
      "name"         => "Controle de Insumos T.I.",
      "version"      => PLUGIN_INSUMOS_VERSION,
      "author"       => "T.I.",
      "license"      => "GPL v2+",
      "homepage"     => "",
      "requirements" => [
         "glpi" => ["min" => PLUGIN_INSUMOS_MIN_GLPI, "max" => PLUGIN_INSUMOS_MAX_GLPI],
         "php"  => ["min" => "7.4"],
      ],
   ];
}

function plugin_init_insumos() {
   global $PLUGIN_HOOKS;
   $PLUGIN_HOOKS[\Glpi\Plugin\Hooks::CSRF_COMPLIANT]["insumos"] = true;
   $PLUGIN_HOOKS["menu_toadd"]["insumos"] = ["helpdesk" => "PluginInsumos"];
}

function plugin_insumos_check_prerequisites() {
   if (version_compare(GLPI_VERSION, PLUGIN_INSUMOS_MIN_GLPI, "lt")) {
      echo "Requer GLPI >= " . PLUGIN_INSUMOS_MIN_GLPI;
      return false;
   }
   return true;
}

function plugin_insumos_check_config($verbose = false) { return true; }

class PluginInsumos extends CommonGLPI {
   static function getTypeName($nb = 0) { return 'Insumos T.I.'; }
   static function getIcon() { return 'ti ti-package'; }
   static function getMenuName() { return 'Insumos T.I.'; }
   static function canView() { return true; }
   static function canCreate() { return false; }
   static function getMenuContent() {
      $menu = parent::getMenuContent() ?: [];
      $menu['title'] = 'Insumos T.I.';
      $menu['page']  = Plugin::getWebDir('insumos', false) . '/front/main.php';
      $menu['icon']  = 'ti ti-package';
      $menu['links'] = [
         'Dashboard'  => Plugin::getWebDir('insumos', false) . '/front/main.php?page=dashboard',
         'Estoque'    => Plugin::getWebDir('insumos', false) . '/front/main.php?page=estoque',
         'Entrada'    => Plugin::getWebDir('insumos', false) . '/front/main.php?page=entrada',
         'Saída'      => Plugin::getWebDir('insumos', false) . '/front/main.php?page=saida',
         'Inventário' => Plugin::getWebDir('insumos', false) . '/front/main.php?page=inventario',
         'Relatórios' => Plugin::getWebDir('insumos', false) . '/front/main.php?page=relatorio',
      ];
      return $menu;
   }
}

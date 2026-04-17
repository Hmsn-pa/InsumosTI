// Ponte GLPI → Sistema de Insumos via postMessage
window.addEventListener('message', function(e) {
  if (e.data && e.data.insumos_page) {
    if (typeof navTo === 'function') navTo(e.data.insumos_page);
  }
});

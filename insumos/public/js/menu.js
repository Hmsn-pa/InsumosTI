/**
 * Insumos T.I. — Intercepta clique no menu e corrige o href.
 * Usa CFG_GLPI.root_doc que o próprio GLPI expõe ao JavaScript —
 * a fonte mais confiável independente de qualquer URL atual.
 */
(function () {

    function getCorrectUrl() {
        // CFG_GLPI.root_doc é injetado pelo GLPI em todas as páginas
        // Valor: '' (raiz) ou '/motorista' ou '/ti' etc.
        var rootDoc = (typeof CFG_GLPI !== 'undefined' && CFG_GLPI.root_doc)
            ? CFG_GLPI.root_doc
            : '';

        return window.location.origin + rootDoc + '/plugins/insumos/front/main.php';
    }

    function isInsumosPage() {
        return window.location.pathname.indexOf('/plugins/insumos/front/main.php') !== -1;
    }

    function findAnchor(el) {
        while (el && el.tagName !== 'A') el = el.parentElement;
        return el;
    }

    document.addEventListener('click', function (e) {
        var link = findAnchor(e.target);
        if (!link) return;

        var href = link.getAttribute('href') || '';
        if (href.indexOf('plugins/insumos/') === -1) return;

        e.stopImmediatePropagation();

        if (isInsumosPage()) {
            e.preventDefault();
            return;
        }

        // Corrige com root_doc oficial do GLPI e abre na mesma aba
        link.setAttribute('href', getCorrectUrl());
        link.removeAttribute('target');

    }, true);

})();

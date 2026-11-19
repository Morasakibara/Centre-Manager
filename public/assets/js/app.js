(function () {
    'use strict';

    // Tooltips Bootstrap
    document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function (el) {
        new bootstrap.Tooltip(el);
    });

    // Confirmations d'actions sensibles
    document.querySelectorAll('form[data-confirm]').forEach(function (form) {
        form.addEventListener('submit', function (event) {
            if (!window.confirm(form.getAttribute('data-confirm'))) {
                event.preventDefault();
            }
        });
    });

    // Menu mobile
    var sidebar = document.getElementById('sidebar_mobile');
    var backdrop = document.querySelector('.sidebar-backdrop');
    var mobileQuery = window.matchMedia('(max-width: 991.98px)');

    function setSidebar(ouvert) {
        if (!sidebar) return;
        sidebar.classList.toggle('show', ouvert);
        if (backdrop) backdrop.classList.toggle('show', ouvert);
        document.body.classList.toggle('sidebar-open', ouvert);
        document.querySelectorAll('[data-toggle-sidebar]').forEach(function (button) {
            button.setAttribute('aria-expanded', ouvert ? 'true' : 'false');
        });
        if (ouvert) {
            var premierLien = sidebar.querySelector('a[href]');
            if (premierLien) premierLien.focus({ preventScroll: true });
        }
    }

    document.querySelectorAll('[data-toggle-sidebar]').forEach(function (button) {
        button.addEventListener('click', function () {
            setSidebar(!sidebar.classList.contains('show'));
        });
    });
    if (backdrop) {
        backdrop.addEventListener('click', function () { setSidebar(false); });
    }
    if (sidebar) {
        sidebar.querySelectorAll('a[href]').forEach(function (lien) {
            lien.addEventListener('click', function () { setSidebar(false); });
        });
    }
    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && sidebar && sidebar.classList.contains('show')) {
            setSidebar(false);
        }
    });
    mobileQuery.addEventListener('change', function (event) {
        if (!event.matches) setSidebar(false);
    });

    // Impression
    document.querySelectorAll('[data-print]').forEach(function (button) {
        button.addEventListener('click', function () {
            window.print();
        });
    });

    // ----------------------------------------------------------------
    // Formulaire d'inscription : tarif, mode de paiement, échéancier
    // ----------------------------------------------------------------
    var formationSelect = document.getElementById('formation_id');
    var montantInput = document.getElementById('montant_total');
    var fraisHelp = document.getElementById('frais_help');
    var modeSelect = document.getElementById('mode_paiement');
    var echeancier = document.getElementById('echeancier_panel');
    var lignes = document.getElementById('echeancier_lignes');
    var difference = document.getElementById('echeancier_difference');

    function nombre(valeur) {
        return Math.round(parseFloat(String(valeur || '0').replace(/\s/g, '').replace(',', '.')) * 100) || 0;
    }

    function formater(centimes) {
        return (centimes / 100).toLocaleString('fr-FR', { maximumFractionDigits: 0 }) + ' FCFA';
    }

    function majTarif() {
        if (!formationSelect || !montantInput) return;
        var option = formationSelect.options[formationSelect.selectedIndex];
        if (!option || !option.value) return;
        var frais = option.getAttribute('data-frais') || '0';
        if (!montantInput.dataset.modifie) {
            montantInput.value = Math.round(parseFloat(frais));
        }
        if (fraisHelp) {
            fraisHelp.textContent = 'Tarif actuel de la formation : ' + formater(nombre(frais));
        }
        majDifference();
    }

    function ajouterLigne(libelle, montant, date) {
        if (!lignes) return;
        var modele = document.getElementById('echeancier_modele');
        var fragment = modele.content.cloneNode(true);
        var ligne = fragment.querySelector('.echeance-ligne');
        ligne.querySelector('.echeance-libelle').value = libelle || '';
        ligne.querySelector('.echeance-montant').value = montant || '';
        ligne.querySelector('.echeance-date').value = date || '';
        ligne.querySelector('.btn-supprimer-tranche').addEventListener('click', function () {
            ligne.remove();
            majDifference();
        });
        ligne.querySelectorAll('input').forEach(function (input) {
            input.addEventListener('input', majDifference);
        });
        lignes.appendChild(fragment);
        majDifference();
    }

    function majDifference() {
        if (!echeancier || !montantInput || !difference) return;
        var somme = 0;
        lignes.querySelectorAll('.echeance-montant').forEach(function (input) {
            somme += nombre(input.value);
        });
        var total = nombre(montantInput.value);
        var ecart = total - somme;
        difference.classList.remove('text-success', 'text-danger', 'text-muted');
        if (ecart === 0) {
            difference.classList.add('text-success');
            difference.textContent = 'Somme des tranches : ' + formater(somme) + ' — échéancier équilibré.';
        } else {
            difference.classList.add('text-danger');
            difference.textContent = 'Somme des tranches : ' + formater(somme) + ' — écart de ' + formater(ecart) + ' par rapport au montant total.';
        }
    }

    function majMode() {
        if (!modeSelect || !echeancier) return;
        if (modeSelect.value === 'tranches') {
            echeancier.classList.remove('d-none');
            if (lignes && lignes.children.length === 0) {
                var total = nombre(montantInput ? montantInput.value : 0);
                var tiers = total > 0 ? Math.floor(total / 3 / 100) * 100 : '';
                var aujourdhui = new Date();
                for (var i = 1; i <= 3; i++) {
                    var date = new Date(aujourdhui.getFullYear(), aujourdhui.getMonth() + (i - 1), aujourdhui.getDate());
                    ajouterLigne('Tranche ' + i, tiers ? tiers / 100 : '', date.toISOString().slice(0, 10));
                }
                majDifference();
            }
        } else {
            echeancier.classList.add('d-none');
        }
    }

    if (formationSelect) {
        formationSelect.addEventListener('change', majTarif);
        majTarif();
    }
    if (montantInput) {
        montantInput.addEventListener('input', function () {
            montantInput.dataset.modifie = '1';
            majDifference();
        });
    }
    if (modeSelect) {
        modeSelect.addEventListener('change', majMode);
        majMode();
    }
    var boutonTranche = document.getElementById('ajouter_tranche');
    if (boutonTranche) {
        boutonTranche.addEventListener('click', function () {
            var nombreLignes = lignes ? lignes.children.length + 1 : 1;
            ajouterLigne('Tranche ' + nombreLignes, '', '');
        });
    }

    // ----------------------------------------------------------------
    // Formulaire de paiement : solde restant indicatif
    // ----------------------------------------------------------------
    var inscriptionSelect = document.getElementById('inscription_id');
    var soldeInfo = document.getElementById('solde_info');
    var montantPaiement = document.getElementById('montant_paiement');

    function majSolde() {
        if (!inscriptionSelect || !soldeInfo) return;
        var option = inscriptionSelect.options[inscriptionSelect.selectedIndex];
        if (!option || !option.value) {
            soldeInfo.textContent = '';
            return;
        }
        var reste = option.getAttribute('data-solde') || '0';
        soldeInfo.textContent = 'Solde restant : ' + formater(nombre(reste));
        if (montantPaiement && !montantPaiement.value) {
            montantPaiement.max = reste;
            montantPaiement.placeholder = String(Math.round(parseFloat(reste)));
        }
    }

    if (inscriptionSelect) {
        inscriptionSelect.addEventListener('change', majSolde);
        majSolde();
    }

    // Date de paiement par défaut = maintenant
    var champDatePaiement = document.getElementById('date_paiement');
    if (champDatePaiement && !champDatePaiement.value) {
        var maintenant = new Date();
        maintenant.setMinutes(maintenant.getMinutes() - maintenant.getTimezoneOffset());
        champDatePaiement.value = maintenant.toISOString().slice(0, 16);
    }
})();

// Formulaire de réservation : champs selon l'occasion et le paiement, contrôle de l'âge
// et du permis au fil de la saisie, état des pièces ajoutées, libellé du bouton.
(() => {
  const form = document.querySelector('[data-formulaire]');
  if (!form) return;
  const { t } = window.Yamoo;

  const erreurs = form.querySelector('[data-erreurs]');
  if (erreurs) erreurs.focus();

  const bascule = (attribut, valeur) => form.querySelectorAll(`[${attribut}]`).forEach((bloc) => {
    bloc.hidden = bloc.getAttribute(attribut) !== valeur;
  });

  form.querySelectorAll('[data-mission]').forEach((r) => r.addEventListener('change', () => bascule('data-si-mission', r.value)));

  const libelle = form.querySelector('[data-envoyer-libelle]');
  const majPaiement = () => {
    const mode = form.querySelector('[data-paiement]:checked')?.value || 'mobile';
    bascule('data-si-paiement', mode);
    libelle.textContent = mode === 'remise' ? t('Envoyer ma demande') : t('Payer {total}', { total: form.dataset.total });
  };
  form.querySelectorAll('[data-paiement]').forEach((r) => r.addEventListener('change', majPaiement));

  // Le numéro Mobile Money reprend le téléphone tant qu'on ne l'a pas modifié.
  const tel = form.querySelector('[data-telephone]');
  const numero = form.querySelector('[data-numero]');
  if (tel && numero) {
    let numeroTouche = numero.value !== '';
    numero.addEventListener('input', () => { numeroTouche = true; });
    tel.addEventListener('input', () => { if (!numeroTouche) numero.value = tel.value; });
  }

  // Âge et ancienneté du permis, calculés au jour du départ.
  const limites = { naissance: form.dataset.naissanceMax, permis: form.dataset.permisMax };
  form.querySelectorAll('[data-controle]').forEach((champ) => {
    const cle = champ.dataset.controle;
    const sortie = form.querySelector(`[data-controle-message="${cle}"]`);
    const verifie = () => {
      if (!champ.value) { sortie.hidden = true; return; }
      const ok = champ.value <= limites[cle];
      sortie.hidden = false;
      sortie.className = ok ? 'champ-aide champ-ok' : 'champ-erreur';
      sortie.textContent = ok
        ? (cle === 'naissance' ? t('Âge vérifié pour la date de départ.') : t('Ancienneté du permis vérifiée.'))
        : (cle === 'naissance' ? t('Le conducteur doit avoir au moins 23 ans le jour du départ.') : t('Le permis doit avoir au moins 2 ans le jour du départ.'));
      champ.setAttribute('aria-invalid', String(!ok));
    };
    champ.addEventListener('change', verifie);
    if (champ.value) verifie();
  });

  form.querySelectorAll('[data-piece]').forEach((entree) => {
    const piece = entree.closest('.piece');
    const etat = piece.querySelector('[data-piece-etat]');
    const aide = etat.textContent;
    entree.addEventListener('change', () => {
      const n = entree.files.length;
      piece.classList.toggle('piece-ok', n > 0);
      etat.textContent = n ? (n === 1 ? entree.files[0].name : t('Fichiers ajoutés : {n}', { n })) : aide;
      if (n) entree.removeAttribute('aria-invalid');
    });
  });

  // Une erreur venue du serveur disparaît dès que la personne corrige le champ.
  form.addEventListener('input', (e) => efface(e.target));
  form.addEventListener('change', (e) => efface(e.target));
  function efface(champ) {
    const bloc = champ.closest('.champ, .piece, .champ-case, fieldset');
    if (!bloc) return;
    bloc.querySelectorAll('.champ-erreur[id^="err-"]').forEach((m) => m.remove());
    if (champ.dataset.controle === undefined) champ.removeAttribute('aria-invalid');
  }

  form.addEventListener('submit', () => {
    const bouton = form.querySelector('[data-envoyer]');
    bouton.setAttribute('aria-busy', 'true');
    libelle.textContent = t('Envoi en cours…');
  });
  window.addEventListener('pageshow', () => {
    form.querySelector('[data-envoyer]')?.removeAttribute('aria-busy');
    majPaiement();
  });
})();

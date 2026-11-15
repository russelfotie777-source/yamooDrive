// Attente du paiement Mobile Money : on interroge le serveur toutes les 3 secondes.
(() => {
  const bloc = document.querySelector('[data-paiement-attente]');
  if (!bloc) return;
  const { t } = window.Yamoo;
  const statut = bloc.querySelector('[data-statut]');
  const attente = bloc.querySelector('[data-etat="attente"]');
  const echec = bloc.querySelector('[data-etat="echec"]');
  let essais = 0;
  const interroge = async () => {
    if (!attente || attente.hidden) return;
    essais += 1;
    try {
      const r = await fetch(bloc.dataset.api, { headers: { Accept: 'application/json' }, cache: 'no-store' });
      const d = await r.json();
      if (d.suite) { statut.textContent = t('Paiement reçu. Ouverture de votre réservation…'); location.assign(d.suite); return; }
      if (d.paiement === 'echec') { attente.hidden = true; echec.hidden = false; echec.querySelector('input,button')?.focus(); return; }
    } catch {
      statut.textContent = t('Connexion perdue. Nouvel essai dans quelques secondes…');
    }
    if (essais === 20) statut.textContent = t('Toujours en attente. Vérifiez votre téléphone.');
    setTimeout(interroge, 3000);
  };
  setTimeout(interroge, 2500);
})();

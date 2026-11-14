// Fiche véhicule : prix total en direct, formule, galerie de photos.
(() => {
  const form = document.querySelector('[data-resa]');
  if (!form) return;
  const base = window.Yamoo?.base ?? '';
  const $ = (s) => form.querySelector(s);
  const prixJour = $('[data-resa-prix-jour]');
  const bouton = $('[data-resa-bouton]');
  const message = $('[data-resa-message]');
  const devis = $('[data-resa-devis]');
  const rassure = $('[data-resa-rassure]');
  const barrePrix = document.querySelector('[data-barre-prix]');
  const barreUnite = document.querySelector('[data-barre-unite]');
  const tarifs = JSON.parse(form.dataset.tarifs || '{}');
  let controleur = null;

  const formule = () => (form.querySelector('[name="formule"]:checked') || form.querySelector('[name="formule"]'))?.value;
  const montreMessage = (texte) => {
    message.hidden = !texte;
    if (texte) message.querySelector('span').textContent = texte;
  };

  const actualise = async () => {
    prixJour.textContent = tarifs[formule()] || prixJour.textContent;
    const params = new URLSearchParams(new FormData(form));
    params.set('vehicule', form.dataset.vehicule);
    const debut = params.get('debut');
    const fin = params.get('fin');
    // L'adresse de la page garde les choix, pour partager ou revenir.
    const adresse = new URLSearchParams(params);
    adresse.delete('vehicule');
    if (adresse.get('lieu_retour') === adresse.get('lieu')) adresse.delete('lieu_retour');
    [...adresse.keys()].forEach((k) => { if (!adresse.get(k)) adresse.delete(k); });
    history.replaceState(null, '', `${location.pathname}?${adresse}`);

    if (!debut || !fin) {
      devis.hidden = true;
      rassure.hidden = true;
      bouton.disabled = false;
      bouton.textContent = 'Choisir mes dates';
      montreMessage(null);
      barrePrix.textContent = tarifs[formule()];
      barreUnite.textContent = 'par jour';
      return;
    }
    controleur?.abort();
    controleur = new AbortController();
    form.setAttribute('aria-busy', 'true');
    try {
      const r = await fetch(`${base}/api/devis?${params}`, { signal: controleur.signal, headers: { Accept: 'application/json' } });
      const d = await r.json();
      if (!d.ok) {
        devis.hidden = true;
        rassure.hidden = true;
        bouton.disabled = true;
        bouton.textContent = 'Réserver';
        montreMessage(Object.values(d.erreurs || {})[0] || 'Vérifiez les dates.');
        return;
      }
      $('[data-resa-lignes]').innerHTML = d.lignes.map(([a, b]) => `<div><dt>${a}</dt><dd>${b}</dd></div>`).join('');
      $('[data-resa-total]').textContent = d.total;
      const annulation = $('[data-resa-annulation]');
      annulation.hidden = !d.annulation;
      annulation.querySelector('span').textContent = d.annulation || '';
      const caution = $('[data-resa-caution]');
      caution.hidden = !d.caution;
      caution.querySelector('span').textContent = d.caution ? `Caution remboursable de ${d.caution} à la remise des clés` : '';
      devis.hidden = false;
      rassure.hidden = !d.disponible;
      bouton.disabled = !d.disponible;
      bouton.textContent = 'Réserver';
      montreMessage(d.disponible ? null : d.message);
      barrePrix.textContent = d.total;
      barreUnite.textContent = `pour ${d.jours} jour${d.jours > 1 ? 's' : ''}`;
    } catch (e) {
      if (e.name !== 'AbortError') montreMessage('Le prix n’a pas pu être calculé. Vérifiez votre connexion puis réessayez.');
    } finally {
      form.removeAttribute('aria-busy');
    }
  };

  form.addEventListener('change', actualise);
  form.addEventListener('plage', actualise);
  form.addEventListener('submit', (e) => {
    const debut = form.querySelector('[name="debut"]').value;
    const fin = form.querySelector('[name="fin"]').value;
    if (!debut || !fin) {
      e.preventDefault();
      const b = form.querySelector(debut ? '#f-fin' : '#f-debut');
      b?.click();
    }
  });
  // Les deux lieux suivent le même choix tant que le client ne change pas le retour.
  const lieu = $('#f-lieu');
  const retour = $('#f-retour');
  let retourTouche = retour.value !== lieu.value;
  retour.addEventListener('change', () => { retourTouche = true; });
  lieu.addEventListener('change', () => { if (!retourTouche) retour.value = lieu.value; });

  // Galerie
  const dialogue = document.querySelector('[data-galerie]');
  if (dialogue) {
    document.querySelectorAll('[data-galerie-ouvrir]').forEach((b) => b.addEventListener('click', () => {
      dialogue.showModal();
      const cible = dialogue.querySelectorAll('.visionneuse-liste li')[Number(b.dataset.galerieOuvrir)];
      cible?.scrollIntoView({ block: 'start' });
    }));
    dialogue.querySelector('[data-galerie-fermer]').addEventListener('click', () => dialogue.close());
    dialogue.addEventListener('click', (e) => { if (e.target === dialogue) dialogue.close(); });
  }
})();

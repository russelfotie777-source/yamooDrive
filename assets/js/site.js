// Comportements communs : thème, menu, filtres, assistant, copie, moment d'arrivée de l'accueil.
(() => {
  const racine = document.documentElement;
  const base = document.body.dataset.base || '';
  const sombreSysteme = matchMedia('(prefers-color-scheme: dark)');

  const charge = (fichier) => new Promise((ok, ko) => {
    const deja = document.querySelector(`script[data-module="${fichier}"]`);
    if (deja) return deja.dataset.pret ? ok() : deja.addEventListener('load', ok);
    const s = document.createElement('script');
    s.src = `${base}/assets/js/${fichier}`;
    s.dataset.module = fichier;
    s.onload = () => { s.dataset.pret = '1'; ok(); };
    s.onerror = ko;
    document.head.append(s);
  });
  window.Yamoo = { base, charge };

  // Thème
  const estSombre = () => racine.dataset.theme ? racine.dataset.theme === 'sombre' : sombreSysteme.matches;
  const libelleTheme = () => document.querySelectorAll('[data-bascule-theme]').forEach((b) => {
    b.setAttribute('aria-label', estSombre() ? 'Passer en mode clair' : 'Passer en mode sombre');
  });
  document.querySelectorAll('[data-bascule-theme]').forEach((b) => b.addEventListener('click', () => {
    const suivant = estSombre() ? 'clair' : 'sombre';
    racine.dataset.theme = suivant;
    try { localStorage.setItem('yamoo-theme', suivant); } catch {}
    libelleTheme();
  }));
  libelleTheme();

  // Menu sur téléphone
  const menuBouton = document.querySelector('[data-menu]');
  const menu = document.getElementById('menu-mobile');
  if (menuBouton && menu) {
    const ferme = () => { menu.hidden = true; menuBouton.setAttribute('aria-expanded', 'false'); };
    menuBouton.addEventListener('click', () => {
      const ouvert = menuBouton.getAttribute('aria-expanded') === 'true';
      menu.hidden = ouvert;
      menuBouton.setAttribute('aria-expanded', String(!ouvert));
    });
    menu.addEventListener('click', (e) => { if (e.target.closest('a')) ferme(); });
    document.addEventListener('keydown', (e) => { if (e.key === 'Escape' && !menu.hidden) { ferme(); menuBouton.focus(); } });
  }

  // Filtres de catégorie : sans rechargement, l'adresse suit le filtre.
  document.querySelectorAll('[data-filtres]').forEach((groupe) => {
    const grille = groupe.parentElement.querySelector('[data-grille]');
    if (!grille) return;
    groupe.addEventListener('click', (e) => {
      const lien = e.target.closest('[data-filtre]');
      if (!lien) return;
      e.preventDefault();
      const cat = lien.dataset.filtre;
      groupe.querySelectorAll('[data-filtre]').forEach((l) => l.setAttribute('aria-pressed', String(l === lien)));
      grille.querySelectorAll('.carte').forEach((c) => { c.hidden = cat !== '' && c.dataset.categorie !== cat; });
      if (location.pathname.endsWith('/vehicules')) history.replaceState(null, '', lien.href);
    });
  });

  // La recherche n'envoie pas les champs laissés vides : l'adresse reste lisible.
  document.querySelectorAll('[data-recherche]').forEach((f) => f.addEventListener('submit', () => {
    f.querySelectorAll('select, input').forEach((c) => { if (c.name && !c.value) c.disabled = true; });
    setTimeout(() => f.querySelectorAll(':disabled').forEach((c) => { c.disabled = false; }));
  }));

  // Calendrier des dates, chargé seulement là où il sert.
  if (document.querySelector('[data-plage]') && !document.querySelector('script[src*="calendrier.js"]')) charge('calendrier.js');

  // Assistant : le script n'est chargé qu'au premier usage.
  const ouvreAssistant = async (e) => {
    const declencheur = e.currentTarget;
    await charge('assistant.js');
    window.YamooAssistant.ouvre(declencheur);
  };
  document.querySelectorAll('[data-assistant-ouvrir]').forEach((b) => b.addEventListener('click', ouvreAssistant));

  // Copier un lien
  document.querySelectorAll('[data-copier]').forEach((b) => b.addEventListener('click', async () => {
    const libelle = b.querySelector('[data-copier-libelle]');
    try {
      await navigator.clipboard.writeText(b.dataset.copier);
      if (libelle) { const avant = libelle.textContent; libelle.textContent = 'Lien copié'; setTimeout(() => { libelle.textContent = avant; }, 2000); }
    } catch {
      prompt('Copiez ce lien :', b.dataset.copier);
    }
  }));

  // Confirmation avant une action irréversible
  document.querySelectorAll('form[data-confirmer]').forEach((f) => f.addEventListener('submit', (e) => {
    if (!confirm(f.dataset.confirmer)) e.preventDefault();
  }));

  // Moment signature de l'accueil, joué une seule fois puis retiré.
  // La classe est posée dans le HTML pour jouer dès le premier affichage.
  document.querySelectorAll('.signature-en-cours, .signature-confirmation').forEach((bloc) => {
    Promise.all(bloc.getAnimations({ subtree: true }).map((a) => a.finished))
      .then(() => bloc.classList.remove('signature-en-cours', 'signature-confirmation'));
  });
})();

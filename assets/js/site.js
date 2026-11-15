// Comportements communs : thème, menu, filtres, assistant, copie, moment d'arrivée de l'accueil.
(() => {
  const html = document.documentElement;
  const base = document.body.dataset.base || '';
  const racine = document.body.dataset.racine || base;
  const textes = JSON.parse(document.getElementById('textes-js')?.textContent || '{}');
  // Texte traduit envoyé par la page ; {nom} est remplacé par la valeur donnée.
  const t = (cle, valeurs = {}) => (textes[cle] ?? cle).replace(/\{(\w+)\}/g, (_, k) => valeurs[k] ?? '');
  const intl = html.dataset.intl || 'fr-FR';
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
  window.Yamoo = { base, racine, charge, t, intl };

  // Thème
  const estSombre = () => html.dataset.theme ? html.dataset.theme === 'sombre' : sombreSysteme.matches;
  const libelleTheme = () => document.querySelectorAll('[data-bascule-theme]').forEach((b) => {
    b.setAttribute('aria-label', estSombre() ? t('Passer en mode clair') : t('Passer en mode sombre'));
  });
  document.querySelectorAll('[data-bascule-theme]').forEach((b) => b.addEventListener('click', () => {
    const suivant = estSombre() ? 'clair' : 'sombre';
    html.dataset.theme = suivant;
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

  // Choix de la langue : menu au clavier (flèches qui bouclent, Début, Fin, Échap).
  document.querySelectorAll('[data-langues]').forEach((bloc) => {
    const bouton = bloc.querySelector('[data-langues-bouton]');
    const menu = bloc.querySelector('[data-langues-menu]');
    const liens = [...menu.querySelectorAll('a')];
    const ouvert = () => bouton.getAttribute('aria-expanded') === 'true';
    const ouvre = (focus) => {
      bouton.setAttribute('aria-expanded', 'true');
      menu.classList.add('ouvert');
      (focus === 'fin' ? liens[liens.length - 1] : menu.querySelector('[aria-current]') || liens[0]).focus();
    };
    const ferme = (rendFocus) => {
      bouton.setAttribute('aria-expanded', 'false');
      menu.classList.remove('ouvert');
      if (rendFocus) bouton.focus();
    };
    bouton.addEventListener('click', () => (ouvert() ? ferme(false) : ouvre()));
    bouton.addEventListener('keydown', (e) => {
      if (e.key === 'ArrowDown') { e.preventDefault(); ouvre(); }
      if (e.key === 'ArrowUp') { e.preventDefault(); ouvre('fin'); }
    });
    menu.addEventListener('keydown', (e) => {
      const i = liens.indexOf(document.activeElement);
      const cible = { ArrowDown: liens[(i + 1) % liens.length], ArrowUp: liens[(i - 1 + liens.length) % liens.length], Home: liens[0], End: liens[liens.length - 1] }[e.key];
      if (cible) { e.preventDefault(); cible.focus(); }
      if (e.key === 'Escape') { e.preventDefault(); ferme(true); }
      if (e.key === 'Tab') ferme(false);
    });
    document.addEventListener('pointerdown', (e) => { if (ouvert() && !bloc.contains(e.target)) ferme(false); });
  });

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
      if (libelle) { const avant = libelle.textContent; libelle.textContent = t('Lien copié'); setTimeout(() => { libelle.textContent = avant; }, 2000); }
    } catch {
      prompt(t('Copiez ce lien :'), b.dataset.copier);
    }
  }));

  // Confirmation avant une action irréversible
  document.querySelectorAll('form[data-confirmer]').forEach((f) => f.addEventListener('submit', (e) => {
    if (!confirm(f.dataset.confirmer)) e.preventDefault();
  }));

  // Logo : une fois le tracé fini, plus de masque ni de découpe, le dessin reste net.
  // Celui du lever de rideau part avec le rideau.
  document.querySelectorAll('.logo-arrivee').forEach((svg) => {
    if (svg.closest('[data-entree]')) return;
    Promise.all(svg.getAnimations({ subtree: true }).map((a) => a.finished)).then(() => {
      svg.classList.remove('logo-arrivee');
      svg.querySelectorAll('[mask], [clip-path]').forEach((el) => { el.removeAttribute('mask'); el.removeAttribute('clip-path'); });
    });
  });

  // Moment signature de l'accueil, joué une seule fois puis retiré.
  // La classe est posée dans le HTML pour jouer dès le premier affichage.
  const signe = (bloc) => Promise.all(bloc.getAnimations({ subtree: true }).map((a) => a.finished))
    .then(() => bloc.classList.remove('signature-en-cours', 'signature-confirmation'));
  document.querySelectorAll('.signature-en-cours, .signature-confirmation').forEach(signe);

  // Lever de rideau : le titre se pose quand le rideau s'ouvre. N'importe quel geste
  // (clic, touche, molette, doigt) le passe : personne n'attend la fin pour réserver.
  const entree = document.querySelector('[data-entree]');
  if (entree) {
    const hero = document.querySelector('[data-signature-differee]');
    const gestes = ['pointerdown', 'keydown', 'wheel', 'touchstart'];
    let titrePose = false;
    let fini = false;
    let auClavier = false;
    const poseTitre = () => {
      if (titrePose || !hero) return;
      titrePose = true;
      hero.classList.add('signature-en-cours');
      signe(hero);
    };
    const termine = () => {
      if (fini) return;
      fini = true;
      gestes.forEach((g) => window.removeEventListener(g, geste));
      poseTitre();
      // Au clavier, le focus reprend au début de la page plutôt que de se perdre avec le rideau.
      const focusDedans = entree.contains(document.activeElement);
      entree.remove();
      if (focusDedans && auClavier) document.querySelector('.evitement')?.focus();
    };
    const passe = () => {
      if (fini) return;
      poseTitre();
      entree.classList.add('entree-passee');
      setTimeout(termine, 200);
    };
    const geste = (e) => {
      if (e.type === 'keydown' && ['Shift', 'Control', 'Alt', 'Meta'].includes(e.key)) return;
      auClavier = e.type === 'keydown';
      passe();
    };

    // Mouvement réduit : pas de rideau, le titre reste posé tel quel.
    if (getComputedStyle(entree).display === 'none') {
      entree.remove();
    } else {
      gestes.forEach((g) => window.addEventListener(g, geste, { passive: true }));
      entree.querySelector('[data-entree-passer]').addEventListener('click', passe);
      entree.querySelector('.entree-rideau').addEventListener('animationstart', (e) => {
        if (e.animationName === 'entree-rideau') poseTitre();
      });
      Promise.all(entree.getAnimations({ subtree: true }).map((a) => a.finished)).then(termine, () => {});
    }
  }
})();

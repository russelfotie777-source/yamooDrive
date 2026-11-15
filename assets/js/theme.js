// Chargé dans <head> pour appliquer le thème choisi avant le premier affichage.
try {
  const t = localStorage.getItem('yamoo-theme');
  if (t === 'clair' || t === 'sombre') document.documentElement.dataset.theme = t;
} catch {}

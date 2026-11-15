// Assistant de la page : conversation gardée le temps de la visite, cartes de véhicules
// et bouton de réservation renvoyés par le serveur.
(() => {
  if (window.YamooAssistant) return;
  const panneau = document.querySelector('[data-assistant]');
  const lanceur = document.querySelector('.assistant-lanceur');
  const fil = panneau.querySelector('[data-assistant-fil]');
  const accueil = panneau.querySelector('[data-assistant-accueil]');
  const form = panneau.querySelector('[data-assistant-form]');
  const zone = form.querySelector('textarea');
  const envoyer = form.querySelector('button[type="submit"]');
  const { racine, t } = window.Yamoo;
  const CLE = 'yamoo-assistant';
  let historique = [];
  let declencheur = lanceur;
  let enCours = false;

  try { historique = JSON.parse(sessionStorage.getItem(CLE) || '[]'); } catch { historique = []; }
  const sauve = () => { try { sessionStorage.setItem(CLE, JSON.stringify(historique.slice(-20))); } catch {} };

  const echappe = (t) => t.replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  const enrichit = (t) => echappe(t).replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>');
  const versHtml = (texte) => texte.trim().split(/\n{2,}/).map((bloc) => {
    const lignes = bloc.split('\n');
    if (lignes.every((l) => /^\s*[-•]\s+/.test(l))) {
      return `<ul>${lignes.map((l) => `<li>${enrichit(l.replace(/^\s*[-•]\s+/, ''))}</li>`).join('')}</ul>`;
    }
    const intro = [];
    const liste = [];
    lignes.forEach((l) => (/^\s*[-•]\s+/.test(l) ? liste : intro).push(l));
    if (liste.length && intro.length) {
      return `<p>${intro.map(enrichit).join('<br>')}</p><ul>${liste.map((l) => `<li>${enrichit(l.replace(/^\s*[-•]\s+/, ''))}</li>`).join('')}</ul>`;
    }
    return `<p>${lignes.map(enrichit).join('<br>')}</p>`;
  }).join('');

  const bulle = (m) => {
    const div = document.createElement('div');
    div.className = `bulle ${m.role === 'user' ? 'bulle-client' : 'bulle-assistant'}`;
    if (m.role === 'user') {
      div.textContent = m.texte;
      return div;
    }
    div.innerHTML = versHtml(m.texte || '');
    if (m.cartes?.length) {
      const ul = document.createElement('ul');
      ul.className = 'mini-cartes';
      m.cartes.forEach((c) => {
        const li = document.createElement('li');
        li.innerHTML = `<a class="mini-carte" href="${echappe(c.lien)}">
          <img src="${echappe(c.image)}" alt="" width="88" height="66" loading="lazy">
          <span><strong>${echappe(c.nom)}</strong><span>${echappe(c.detail)}</span>
          <span class="mini-prix"><strong>${echappe(c.prix)}</strong> ${echappe(c.unite)}</span>
          ${c.etat ? `<span class="mini-etat">${echappe(c.etat)}</span>` : ''}</span></a>`;
        ul.append(li);
      });
      div.append(ul);
    }
    if (m.action) {
      const a = document.createElement('a');
      a.className = 'bouton bouton-plein mini-action';
      a.href = m.action.lien;
      a.textContent = m.action.libelle;
      div.append(a);
    }
    return div;
  };

  const rendTout = () => {
    fil.querySelectorAll('.bulle').forEach((b) => b.remove());
    accueil.hidden = historique.length > 0;
    historique.forEach((m) => fil.append(bulle(m)));
    fil.scrollTop = fil.scrollHeight;
  };

  const ajuste = () => { zone.style.height = 'auto'; zone.style.height = `${Math.min(zone.scrollHeight, 120)}px`; };

  const pose = async (question) => {
    question = question.trim();
    if (!question || enCours) return;
    enCours = true;
    envoyer.disabled = true;
    historique.push({ role: 'user', texte: question });
    sauve();
    rendTout();
    zone.value = '';
    ajuste();
    const attente = document.createElement('div');
    attente.className = 'bulle bulle-assistant bulle-attente';
    attente.textContent = t('Je regarde…');
    fil.append(attente);
    fil.scrollTop = fil.scrollHeight;
    try {
      const r = await fetch(`${racine}/api/assistant`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF': document.body.dataset.csrf },
        body: JSON.stringify({ messages: historique.map(({ role, texte }) => ({ role, texte })) }),
      });
      const d = await r.json();
      historique.push({ role: 'assistant', texte: d.texte || '', cartes: d.cartes || [], action: d.action || null });
    } catch {
      historique.push({ role: 'assistant', texte: t('La réponse n’est pas arrivée. Vérifiez votre connexion puis réessayez, ou écrivez-nous sur WhatsApp.') });
    }
    attente.remove();
    sauve();
    rendTout();
    enCours = false;
    envoyer.disabled = false;
    zone.focus();
  };

  const ouvre = (source) => {
    declencheur = source || lanceur;
    panneau.hidden = false;
    rendTout();
    requestAnimationFrame(() => delete panneau.dataset.ferme);
    lanceur.setAttribute('aria-expanded', 'true');
    zone.focus();
  };
  const ferme = () => {
    panneau.dataset.ferme = '';
    lanceur.setAttribute('aria-expanded', 'false');
    setTimeout(() => { panneau.hidden = true; }, 300);
    (declencheur || lanceur).focus();
  };

  panneau.dataset.ferme = '';
  panneau.querySelector('[data-assistant-fermer]').addEventListener('click', ferme);
  panneau.addEventListener('keydown', (e) => { if (e.key === 'Escape') ferme(); });
  form.addEventListener('submit', (e) => { e.preventDefault(); pose(zone.value); });
  zone.addEventListener('input', ajuste);
  zone.addEventListener('keydown', (e) => {
    if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); pose(zone.value); }
  });
  panneau.querySelectorAll('[data-suggestion]').forEach((b) => b.addEventListener('click', () => pose(b.textContent)));

  window.YamooAssistant = { ouvre, ferme };
})();

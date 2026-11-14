// Choix des dates de départ et de retour. Les champs datetime-local du HTML restent la base :
// sans JavaScript ils fonctionnent seuls, avec JavaScript ils deviennent un calendrier.
(() => {
  if (window.YamooPlage) return;

  const iso = (d) => `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
  const depuisIso = (s) => { const [a, m, j] = s.split('-').map(Number); return new Date(a, m - 1, j); };
  const ajoute = (d, n) => new Date(d.getFullYear(), d.getMonth(), d.getDate() + n);
  const fmtCourt = new Intl.DateTimeFormat('fr-FR', { weekday: 'short', day: 'numeric', month: 'short' });
  const fmtLong = new Intl.DateTimeFormat('fr-FR', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });
  const fmtMois = new Intl.DateTimeFormat('fr-FR', { month: 'long', year: 'numeric' });
  const fmtJour = new Intl.DateTimeFormat('fr-FR', { day: 'numeric', month: 'long' });
  const SEMAINE = ['L', 'M', 'M', 'J', 'V', 'S', 'D'];
  const HEURES = [];
  for (let h = 6; h <= 22; h++) for (const m of ['00', '30']) if (!(h === 22 && m === '30')) HEURES.push(`${String(h).padStart(2, '0')}:${m}`);
  const etroit = matchMedia('(max-width: 760px)');

  class Plage {
    constructor(groupe) {
      this.groupe = groupe;
      this.entreeDebut = groupe.querySelector('[data-debut]');
      this.entreeFin = groupe.querySelector('[data-fin]');
      const min = (groupe.dataset.min || '').slice(0, 10);
      this.min = min ? depuisIso(min) : new Date();
      const prisSource = groupe.closest('[data-pris]');
      this.pris = new Set(prisSource ? JSON.parse(prisSource.dataset.pris || '[]') : []);
      const [d, hd] = (this.entreeDebut.value || '').split('T');
      const [f, hf] = (this.entreeFin.value || '').split('T');
      this.debut = d || null;
      this.fin = f || null;
      this.heureDebut = hd || '09:00';
      this.heureFin = hf || '09:00';
      this.mois = depuisIso((this.debut || iso(this.min)).slice(0, 8) + '01');
      this.vues = [];
      this.remplace();
    }

    // Les champs natifs deviennent cachés ; un bouton affiche la date choisie.
    remplace() {
      this.boutons = [this.entreeDebut, this.entreeFin].map((entree, i) => {
        const libelle = this.groupe.querySelector(`label[for="${entree.id}"]`);
        const bouton = document.createElement('button');
        bouton.type = 'button';
        bouton.className = 'champ-date';
        bouton.id = entree.id;
        bouton.setAttribute('aria-haspopup', 'dialog');
        bouton.setAttribute('aria-expanded', 'false');
        entree.removeAttribute('id');
        entree.type = 'hidden';
        entree.after(bouton);
        if (libelle) libelle.addEventListener('click', (e) => { e.preventDefault(); bouton.click(); });
        bouton.addEventListener('click', () => this.ouvre(bouton, i === 0 ? 'debut' : 'fin'));
        return bouton;
      });
      this.affiche();
    }

    affiche() {
      const texte = (jour, heure) => {
        if (!jour) return null;
        const t = fmtCourt.format(depuisIso(jour)).replace('.', '');
        return `${t.charAt(0).toUpperCase()}${t.slice(1)}, ${heure}`;
      };
      [[this.boutons[0], texte(this.debut, this.heureDebut)], [this.boutons[1], texte(this.fin, this.heureFin)]].forEach(([b, t]) => {
        b.textContent = t || 'Ajouter une date';
        b.classList.toggle('champ-date-vide', !t);
      });
    }

    construitPopover() {
      const p = document.createElement('div');
      p.className = 'calendrier';
      p.setAttribute('role', 'dialog');
      p.setAttribute('aria-label', 'Choisir les dates');
      p.dataset.cache = '';
      p.hidden = true;
      p.innerHTML = `
        <div class="cal-entete">
          <p class="cal-resume" aria-live="polite"></p>
          <div class="cal-nav">
            <button type="button" class="bouton-icone" data-mois="-1" aria-label="Mois précédent"><svg class="icone" aria-hidden="true"><use href="#i-chevron-gauche"></use></svg></button>
            <button type="button" class="bouton-icone" data-mois="1" aria-label="Mois suivant"><svg class="icone" aria-hidden="true"><use href="#i-chevron-droite"></use></svg></button>
          </div>
        </div>
        <div class="cal-mois-liste"></div>
        <div class="cal-heures">
          <div><label>Heure de départ<select data-heure="debut">${HEURES.map((h) => `<option>${h}</option>`).join('')}</select></label></div>
          <div><label>Heure de retour<select data-heure="fin">${HEURES.map((h) => `<option>${h}</option>`).join('')}</select></label></div>
        </div>
        <div class="cal-pied">
          <button type="button" class="bouton-lien" data-effacer>Effacer les dates</button>
          <button type="button" class="bouton" data-valider>Valider</button>
        </div>`;
      p.querySelectorAll('[data-mois]').forEach((b) => b.addEventListener('click', () => { this.mois = new Date(this.mois.getFullYear(), this.mois.getMonth() + Number(b.dataset.mois), 1); this.rendre(); }));
      p.querySelector('[data-effacer]').addEventListener('click', () => { this.debut = this.fin = null; this.rendre(); });
      p.querySelector('[data-valider]').addEventListener('click', () => { this.valide(); this.ferme(true); });
      p.querySelectorAll('[data-heure]').forEach((s) => s.addEventListener('change', () => {
        if (s.dataset.heure === 'debut') this.heureDebut = s.value; else this.heureFin = s.value;
      }));
      p.addEventListener('keydown', (e) => this.clavier(e));
      this.groupe.append(p);
      this.popover = p;
      this.vues.push({ racine: p, liste: p.querySelector('.cal-mois-liste'), resume: p.querySelector('.cal-resume'), nbMois: 2 });
    }

    // Calendrier posé dans la page (fiche véhicule) : il partage l'état du formulaire.
    enLigne(conteneur) {
      const p = document.createElement('div');
      p.className = 'calendrier calendrier-plat';
      p.innerHTML = `
        <div class="cal-entete">
          <p class="cal-resume" aria-live="polite"></p>
          <div class="cal-nav">
            <button type="button" class="bouton-icone" data-mois="-1" aria-label="Mois précédent"><svg class="icone" aria-hidden="true"><use href="#i-chevron-gauche"></use></svg></button>
            <button type="button" class="bouton-icone" data-mois="1" aria-label="Mois suivant"><svg class="icone" aria-hidden="true"><use href="#i-chevron-droite"></use></svg></button>
          </div>
        </div>
        <div class="cal-mois-liste"></div>
        <div class="cal-pied"><button type="button" class="bouton-lien" data-effacer>Effacer les dates</button></div>`;
      p.querySelectorAll('[data-mois]').forEach((b) => b.addEventListener('click', () => { this.mois = new Date(this.mois.getFullYear(), this.mois.getMonth() + Number(b.dataset.mois), 1); this.rendre(); }));
      p.querySelector('[data-effacer]').addEventListener('click', () => { this.debut = this.fin = null; this.valide(); });
      p.addEventListener('keydown', (e) => this.clavier(e));
      conteneur.append(p);
      this.vues.push({ racine: p, liste: p.querySelector('.cal-mois-liste'), resume: p.querySelector('.cal-resume'), nbMois: 2, plat: true });
      this.rendre();
    }

    ouvre(bouton, champ) {
      if (!this.popover) this.construitPopover();
      this.declencheur = bouton;
      this.champ = champ;
      if (this.debut) this.mois = depuisIso(this.debut.slice(0, 8) + '01');
      this.popover.querySelector('[data-heure="debut"]').value = this.heureDebut;
      this.popover.querySelector('[data-heure="fin"]').value = this.heureFin;
      this.rendre();
      this.popover.hidden = false;
      requestAnimationFrame(() => delete this.popover.dataset.cache);
      this.boutons.forEach((b) => b.setAttribute('aria-expanded', 'true'));
      const cible = this.popover.querySelector('.cal-jour[tabindex="0"]');
      if (cible) cible.focus({ preventScroll: !etroit.matches });
      this.horsClic = (e) => { if (!this.groupe.contains(e.target)) this.ferme(false); };
      setTimeout(() => document.addEventListener('pointerdown', this.horsClic));
    }

    ferme(rendFocus) {
      if (!this.popover || this.popover.hidden) return;
      this.popover.dataset.cache = '';
      this.boutons.forEach((b) => b.setAttribute('aria-expanded', 'false'));
      document.removeEventListener('pointerdown', this.horsClic);
      setTimeout(() => { this.popover.hidden = true; }, 150);
      if (rendFocus && this.declencheur) this.declencheur.focus();
    }

    // Une période ne peut pas enjamber un jour déjà réservé.
    chevauchePris(a, b) {
      for (let d = depuisIso(a); iso(d) <= b; d = ajoute(d, 1)) if (this.pris.has(iso(d))) return true;
      return false;
    }

    choisit(jour, plat) {
      if (!this.debut || this.fin || jour < this.debut || this.chevauchePris(this.debut, jour)) {
        this.debut = jour;
        this.fin = null;
      } else {
        this.fin = jour;
        if (this.fin === this.debut && this.heureFin <= this.heureDebut) this.heureFin = '18:00';
        if (this.popover) this.popover.querySelector('[data-heure="fin"]').value = this.heureFin;
      }
      this.focusJour = jour;
      if (plat) this.valide(); else this.rendre();
    }

    valide() {
      this.entreeDebut.value = this.debut ? `${this.debut}T${this.heureDebut}` : '';
      this.entreeFin.value = this.fin ? `${this.fin}T${this.heureFin}` : '';
      this.affiche();
      this.rendre();
      this.groupe.dispatchEvent(new CustomEvent('plage', { bubbles: true, detail: { debut: this.entreeDebut.value, fin: this.entreeFin.value } }));
    }

    resume() {
      if (this.debut && this.fin) return `Du ${fmtJour.format(depuisIso(this.debut))} au ${fmtJour.format(depuisIso(this.fin))}`;
      return this.debut ? 'Touchez le jour du retour' : 'Touchez le jour du départ';
    }

    rendre() {
      const aujourdhui = iso(new Date());
      const min = iso(this.min);
      const focus = this.focusJour || this.debut || min;
      this.vues.forEach((v) => {
        v.resume.textContent = this.resume();
        const nb = etroit.matches ? 1 : v.nbMois;
        v.liste.innerHTML = '';
        for (let k = 0; k < nb; k++) {
          const premier = new Date(this.mois.getFullYear(), this.mois.getMonth() + k, 1);
          const bloc = document.createElement('div');
          bloc.className = 'cal-mois';
          const titre = document.createElement('h3');
          titre.textContent = fmtMois.format(premier);
          const grille = document.createElement('div');
          grille.className = 'cal-grille';
          grille.setAttribute('role', 'grid');
          SEMAINE.forEach((s) => { const c = document.createElement('span'); c.className = 'cal-semaine'; c.textContent = s; grille.append(c); });
          const decalage = (premier.getDay() + 6) % 7;
          for (let i = 0; i < decalage; i++) grille.append(document.createElement('span'));
          for (let d = premier; d.getMonth() === premier.getMonth(); d = ajoute(d, 1)) {
            const j = iso(d);
            const b = document.createElement('button');
            b.type = 'button';
            b.className = 'cal-jour';
            b.textContent = d.getDate();
            b.dataset.jour = j;
            const pris = this.pris.has(j);
            const passe = j < min;
            let etat = '';
            if (pris) { b.classList.add('cal-pris'); etat = ', déjà réservé'; }
            if (passe || pris) b.setAttribute('aria-disabled', 'true');
            if (j === aujourdhui) b.classList.add('cal-aujourdhui');
            if (j === this.debut) { b.classList.add('cal-debut'); etat = ', départ'; if (this.fin) b.classList.add('cal-avec-fin'); }
            if (j === this.fin) { b.classList.add('cal-fin'); etat = ', retour'; }
            if (this.debut && this.fin && j > this.debut && j < this.fin) b.classList.add('cal-entre');
            b.setAttribute('aria-label', fmtLong.format(d) + etat);
            b.setAttribute('aria-pressed', String(j === this.debut || j === this.fin));
            b.tabIndex = j === focus ? 0 : -1;
            b.addEventListener('click', () => { if (!passe && !pris) this.choisit(j, v.plat); });
            grille.append(b);
          }
          bloc.append(titre, grille);
          v.liste.append(bloc);
        }
        if (!v.liste.querySelector('.cal-jour[tabindex="0"]')) {
          const premierLibre = v.liste.querySelector('.cal-jour:not([aria-disabled="true"])') || v.liste.querySelector('.cal-jour');
          if (premierLibre) premierLibre.tabIndex = 0;
        }
      });
    }

    clavier(e) {
      if (e.key === 'Escape') { e.preventDefault(); this.ferme(true); return; }
      const jour = e.target.closest('.cal-jour');
      if (!jour) return;
      const pas = { ArrowLeft: -1, ArrowRight: 1, ArrowUp: -7, ArrowDown: 7 }[e.key];
      let cible = null;
      const d = depuisIso(jour.dataset.jour);
      if (pas) cible = ajoute(d, pas);
      else if (e.key === 'Home') cible = ajoute(d, -((d.getDay() + 6) % 7));
      else if (e.key === 'End') cible = ajoute(d, 6 - ((d.getDay() + 6) % 7));
      else if (e.key === 'PageUp') cible = new Date(d.getFullYear(), d.getMonth() - 1, d.getDate());
      else if (e.key === 'PageDown') cible = new Date(d.getFullYear(), d.getMonth() + 1, d.getDate());
      if (!cible) return;
      e.preventDefault();
      const racine = e.currentTarget;
      const j = iso(cible);
      let b = racine.querySelector(`.cal-jour[data-jour="${j}"]`);
      if (!b) {
        this.mois = new Date(cible.getFullYear(), cible.getMonth(), 1);
        this.focusJour = j;
        this.rendre();
        b = racine.querySelector(`.cal-jour[data-jour="${j}"]`);
      }
      if (b) {
        racine.querySelectorAll('.cal-jour[tabindex="0"]').forEach((x) => { x.tabIndex = -1; });
        b.tabIndex = 0;
        b.focus();
      }
    }
  }

  window.YamooPlage = Plage;
  document.querySelectorAll('[data-plage]').forEach((g) => {
    g.plage = new Plage(g);
  });
  const enLigne = document.querySelector('[data-calendrier-en-ligne]');
  const groupeResa = document.querySelector('[data-resa] [data-plage]');
  if (enLigne && groupeResa) groupeResa.plage.enLigne(enLigne);
  document.dispatchEvent(new CustomEvent('plages-pretes'));
})();

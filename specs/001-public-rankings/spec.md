# Feature Specification: Classement Public du Circuit JEF

**Feature Branch**: `001-public-rankings`
**Created**: 2026-02-27
**Status**: Draft
**Input**: User description: "Create the JEF youth chess circuit rankings web application. The site will be in French. The public users can choose the year. The default is current year. By default, the general rankings are displayed. For each player, the following will be shown: age category, total points, followed by their rankings and number of points in each round. The layout must be simple and modern. The logo of FEFB can be provided by the user."

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Consulter le classement general (Priority: P1)

Un visiteur public (parent, joueur, responsable de club) arrive sur le
site et voit immediatement le classement general du circuit JEF pour
l'annee en cours. Le tableau affiche la liste des joueurs tries par
total de points (decroissant). Pour chaque joueur, on voit : sa
categorie d'age, son total de points, puis pour chaque manche du
circuit, son classement et le nombre de points obtenus.

**Why this priority**: C'est la raison d'etre de l'application. Sans
le classement general visible, le site n'a aucune utilite. C'est le
MVP minimal.

**Independent Test**: Peut etre teste en ouvrant le site dans un
navigateur et en verifiant que le classement s'affiche correctement
avec les donnees de l'annee courante.

**Acceptance Scenarios**:

1. **Given** le site contient des donnees de classement pour l'annee
   en cours, **When** un visiteur accede a la page d'accueil,
   **Then** le classement general s'affiche avec tous les joueurs
   tries par total de points decroissant.
2. **Given** le classement general est affiche, **When** le visiteur
   examine une ligne du tableau, **Then** il voit le nom du joueur,
   sa categorie d'age, son total de points, et pour chaque manche :
   son classement et ses points.
3. **Given** le classement general est affiche, **When** le visiteur
   consulte la page sur un telephone mobile, **Then** le tableau
   reste lisible et utilisable (responsive).

---

### User Story 2 - Naviguer par annee (Priority: P2)

Un visiteur souhaite consulter les classements d'une annee precedente.
Il selectionne l'annee desiree et le classement se met a jour pour
afficher les resultats de cette annee.

**Why this priority**: Les historiques sont importants pour suivre
la progression des joueurs et pour reference, mais le classement
de l'annee en cours est la priorite absolue.

**Independent Test**: Peut etre teste en selectionnant une annee
passee et en verifiant que les donnees affichees correspondent
bien a cette annee.

**Acceptance Scenarios**:

1. **Given** le classement de l'annee en cours est affiche,
   **When** le visiteur selectionne une annee precedente dans le
   selecteur, **Then** le classement se met a jour avec les
   donnees de l'annee choisie.
2. **Given** le visiteur a selectionne une annee, **When** il
   recharge la page, **Then** l'annee selectionnee est conservee
   (via l'URL).
3. **Given** le visiteur accede au site sans specifier d'annee,
   **When** la page se charge, **Then** l'annee courante est
   selectionnee par defaut.

---

### User Story 3 - Filtrer par categorie d'age (Priority: P3)

Un visiteur veut voir le classement pour une categorie d'age
specifique (par ex. U10, U12, U14). Il selectionne la categorie
et le systeme affiche le classement calcule specifiquement pour
cette categorie, avec des rangs propres (1er, 2e, 3e... au sein
de la categorie). Il ne s'agit pas d'un simple filtre du classement
general : un classement separe est calcule pour chaque categorie
d'age selon les regles de la FEFB.

**Why this priority**: Les classements par categorie sont essentiels
pour les parents et joueurs qui veulent connaitre leur position
dans leur groupe d'age. Cependant, le classement general suffit
pour une premiere version deployable.

**Independent Test**: Peut etre teste en selectionnant une categorie
d'age et en verifiant que le classement affiche correspond au
calcul specifique de cette categorie, avec des rangs propres.

**Acceptance Scenarios**:

1. **Given** le classement general est affiche, **When** le visiteur
   selectionne une categorie d'age (ex. U12), **Then** le classement
   de cette categorie s'affiche avec les rangs recalcules au sein
   de la categorie (1er, 2e, 3e...).
2. **Given** un classement par categorie est affiche, **When** le
   visiteur selectionne "Toutes les categories", **Then** le
   classement general s'affiche a nouveau.
3. **Given** un classement par categorie est affiche, **When** le
   visiteur change d'annee, **Then** la categorie selectionnee
   reste active et le classement de cette categorie pour la nouvelle
   annee s'affiche.

---

### User Story 4 - Import des resultats via fichier TRF (Priority: P4)

Un administrateur du circuit JEF (organisateur FEFB) importe les
resultats de chaque manche en telechargeant un fichier au format
TRF (Tournament Report File, format standard FIDE). Le systeme
parse le fichier TRF, extrait les donnees du tournoi et des joueurs
(nom, classement, resultats par ronde), sauvegarde les details du
tournoi, puis calcule automatiquement les classements du circuit
(general et par categorie d'age) selon les regles definies par la
FEFB.

**Why this priority**: Necessaire pour que le site fonctionne.
L'import TRF est la seule methode d'alimentation en donnees.
Le calcul automatique des classements est le coeur du systeme.

**Independent Test**: Peut etre teste en important un fichier TRF
pour une manche et en verifiant que les resultats du tournoi sont
sauvegardes et que les classements du circuit sont recalcules.

**Acceptance Scenarios**:

1. **Given** un administrateur est connecte, **When** il importe un
   fichier TRF valide pour une manche, **Then** le systeme extrait
   les donnees du tournoi (joueurs, resultats, classement de la
   manche) et les sauvegarde.
2. **Given** un fichier TRF a ete importe, **When** le systeme
   traite le fichier, **Then** les classements du circuit (general
   et par categorie d'age) sont recalcules automatiquement selon
   les regles de la FEFB.
3. **Given** un administrateur importe un fichier TRF invalide ou
   corrompu, **When** le systeme tente de le traiter, **Then** un
   message d'erreur clair est affiche et aucune donnee n'est
   modifiee.
4. **Given** un administrateur est connecte, **When** il telecharge
   le logo FEFB, **Then** le logo s'affiche sur le site public.
5. **Given** un fichier TRF a ete importe et traite, **When** un
   visiteur public consulte le classement, **Then** les resultats
   et classements mis a jour sont visibles.
6. **Given** un administrateur a deja importe un fichier TRF pour
   une manche, **When** il reimporte un fichier TRF pour la meme
   manche, **Then** les anciennes donnees de cette manche sont
   remplacees et les classements sont recalcules.

---

### User Story 5 - Consulter le detail d'un tournoi (Priority: P5)

Un visiteur souhaite voir les details d'un tournoi specifique du
circuit (une manche). Depuis le classement general, il clique sur
le nom d'une manche et accede a une page affichant la grille du
tournoi avec les resultats par ronde de chaque participant.

**Why this priority**: Les donnees sont deja disponibles via
l'import TRF. Cette fonctionnalite offre une vue complementaire
naturelle et attendue dans le contexte echiqueen, mais n'est pas
indispensable au MVP (classement du circuit).

**Independent Test**: Peut etre teste en cliquant sur un nom de
manche dans le classement et en verifiant que la grille du tournoi
s'affiche avec les resultats par ronde.

**Acceptance Scenarios**:

1. **Given** le classement general est affiche, **When** le visiteur
   clique sur le nom d'une manche (en-tete de colonne), **Then** il
   est redirige vers la page de detail de ce tournoi.
2. **Given** la page de detail d'un tournoi est affichee, **When**
   le visiteur examine le tableau, **Then** il voit la grille
   complete : chaque joueur avec ses resultats par ronde du tournoi
   (adversaire, couleur, resultat).
3. **Given** la page de detail d'un tournoi est affichee, **When**
   le visiteur souhaite revenir au classement du circuit, **Then**
   un lien de retour est clairement visible.

---

### Edge Cases

- Que se passe-t-il si aucune donnee n'existe pour l'annee
  selectionnee ? Le systeme affiche un message explicite indiquant
  qu'aucun classement n'est disponible pour cette annee.
- Que se passe-t-il si un joueur n'a pas participe a certaines
  manches ? Les colonnes de ces manches affichent un tiret ou restent
  vides, et le total ne comptabilise que les manches jouees.
- Que se passe-t-il si deux joueurs ont le meme total de points ?
  Le systeme les affiche avec le meme rang (ex-aequo) et le rang
  suivant est ajuste (1er, 2e, 2e, 4e...).
- Que se passe-t-il si le logo FEFB n'a pas ete fourni ? Le site
  s'affiche normalement sans logo, avec le nom "FEFB" en texte.
- Que se passe-t-il si le nombre de manches est eleve ? Le tableau
  reste navigable (defilement horizontal si necessaire).
- Que se passe-t-il si un fichier TRF contient un joueur deja connu
  du systeme (presente dans une manche precedente) ? Le systeme
  identifie le joueur existant et rattache le nouveau resultat a son
  profil.
- Que se passe-t-il si un fichier TRF contient un joueur inconnu ?
  Le systeme cree automatiquement le joueur a partir des donnees du
  fichier TRF (nom, date de naissance, club).
- Que se passe-t-il si un fichier TRF est reimporte pour une manche
  deja existante ? Les donnees de cette manche sont ecrasees et les
  classements du circuit sont recalcules.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: Le systeme DOIT afficher le classement general du
  circuit JEF par defaut a l'ouverture du site.
- **FR-002**: Le systeme DOIT afficher pour chaque joueur : son nom,
  sa categorie d'age, son total de points, et pour chaque manche
  son classement et ses points.
- **FR-003**: Le classement DOIT etre trie par total de points
  decroissant.
- **FR-004**: Les visiteurs DOIVENT pouvoir selectionner une annee
  pour consulter les classements correspondants.
- **FR-005**: L'annee courante DOIT etre selectionnee par defaut.
- **FR-006**: Les visiteurs DOIVENT pouvoir consulter un classement
  separe par categorie d'age (U8, U10, U12, U14, U16, U20). Chaque
  classement par categorie est calcule independamment avec des rangs
  propres (pas un simple filtre du classement general).
- **FR-007**: L'ensemble du site DOIT etre en langue francaise.
- **FR-008**: La mise en page DOIT etre simple, moderne et
  responsive (utilisable sur mobile, tablette et ordinateur).
- **FR-009**: Le logo FEFB DOIT pouvoir etre configure par un
  administrateur et s'afficher sur le site.
- **FR-010**: Le systeme DOIT gerer les ex-aequo (meme total de
  points) en affichant le meme rang pour les joueurs concernes.
- **FR-011**: Le systeme DOIT afficher un message explicite lorsque
  aucune donnee n'est disponible pour une annee ou une categorie.
- **FR-012**: L'administrateur DOIT pouvoir importer les resultats
  d'une manche en telechargeant un fichier au format TRF (Tournament
  Report File, standard FIDE).
- **FR-013**: Le systeme DOIT parser le fichier TRF et en extraire :
  les informations du tournoi, la liste des joueurs, et les
  resultats individuels (classement et score par joueur).
- **FR-014**: Le systeme DOIT sauvegarder les details du tournoi
  importe (resultats par ronde, classement de la manche) pour
  consultation ulterieure.
- **FR-015**: Le systeme DOIT calculer automatiquement les
  classements du circuit apres chaque import, selon les regles de
  points fournies par la FEFB. Cela inclut : un classement general
  et un classement separe pour chaque categorie d'age (U8, U10,
  U12, U14, U16, U20), chacun avec ses propres rangs.
- **FR-016**: Le systeme DOIT identifier les joueurs deja connus
  lors de l'import d'un nouveau fichier TRF et rattacher les
  resultats au profil existant. L'identification se fait par FIDE ID
  quand celui-ci est present dans le fichier TRF, sinon par
  correspondance nom + date de naissance.
- **FR-017**: Le systeme DOIT rejeter un fichier TRF invalide avec
  un message d'erreur explicite, sans modifier les donnees existantes.
- **FR-018**: Le systeme DOIT permettre le reimport d'un fichier TRF
  pour une manche existante, en remplacant les donnees precedentes.
- **FR-019**: Le systeme DOIT proposer une page de detail par
  tournoi, accessible depuis le classement du circuit (clic sur le
  nom de la manche).
- **FR-020**: La page de detail d'un tournoi DOIT afficher la grille
  complete : liste des joueurs avec, pour chaque ronde, l'adversaire,
  la couleur et le resultat.

### Key Entities

- **Joueur (Player)**: Un jeune participant au circuit JEF.
  Attributs cles : nom, prenom, date de naissance, categorie d'age,
  club, FIDE ID (optionnel). Identifiant unique : FIDE ID si
  disponible, sinon combinaison nom + date de naissance.
- **Manche (Round)**: Un tournoi individuel faisant partie du
  circuit JEF pour une annee donnee. Attributs cles : nom/numero,
  date, lieu, annee du circuit.
- **Resultat (Result)**: La performance d'un joueur lors d'une
  manche specifique. Attributs cles : joueur, manche, classement
  dans la manche, points obtenus.
- **Classement (Ranking)**: Le classement calcule d'un joueur pour
  une annee, dans un contexte donne (general ou categorie d'age).
  Attributs cles : joueur, annee, type (general ou categorie), total
  de points, rang. Un joueur a un classement general ET un classement
  dans sa categorie d'age.
- **Saison (Season)**: Une annee du circuit JEF, regroupant
  l'ensemble des manches. Attributs cles : annee, statut (en cours
  / terminee).

## Clarifications

### Session 2026-02-27

- Q: Comment identifier de maniere unique un joueur a travers les imports TRF successifs ? → A: Par FIDE ID quand disponible, sinon par nom + date de naissance.
- Q: Faut-il une page de detail par tournoi accessible publiquement ? → A: Oui, page de detail par tournoi accessible depuis le classement (grille, resultats par ronde).
- Q: Quelles sont les categories d'age exactes du circuit JEF ? → A: U8, U10, U12, U14, U16, U20. La categorie est determinee par l'age au 1er janvier de l'annee de la saison.
- Q: Les rangs affiches par categorie sont-ils recalcules ou conserves du general ? → A: Rangs recalcules au sein de la categorie. Un classement separe est calcule pour chaque categorie d'age (pas un simple filtre du classement general).

## Assumptions

- Les categories d'age du circuit JEF sont : U8, U10, U12, U14,
  U16, U20. La categorie d'un joueur est determinee par son age
  au 1er janvier de l'annee de la saison.
- Une saison du circuit JEF correspond a une annee civile.
- Le nombre de manches par saison est variable (typiquement entre
  4 et 10 tournois par an).
- Les regles de calcul des points du circuit seront fournies par
  l'utilisateur dans une phase ulterieure. Le systeme DOIT etre
  concu pour appliquer ces regles de maniere configurable.
- Le fichier TRF contient suffisamment d'informations pour
  identifier les joueurs (nom, date de naissance) et leurs
  resultats par ronde dans chaque tournoi.
- La categorie d'age d'un joueur est determinee a partir de sa
  date de naissance (extraite du fichier TRF) et de son age au
  1er janvier de l'annee de la saison.
- L'authentification administrateur utilise un systeme simple
  (identifiant/mot de passe) — le nombre d'administrateurs est
  tres limite (1-3 personnes).
- Le site est consulte principalement par des parents et des
  responsables de clubs, avec un pic de trafic apres chaque manche.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Un visiteur peut consulter le classement general du
  circuit JEF en moins de 3 secondes apres avoir accede au site.
- **SC-002**: Un visiteur peut basculer entre les annees et voir
  le classement mis a jour en moins de 2 secondes.
- **SC-003**: Le classement est lisible et navigable sur un ecran
  de telephone (largeur 375px minimum) sans perte d'information.
- **SC-004**: 100% des resultats saisis par l'administrateur sont
  refletes correctement dans le classement public.
- **SC-005**: Le classement est accessible sans creation de compte
  ni authentification pour les visiteurs publics.

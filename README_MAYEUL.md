# Guide Administration JetSetBoat

Bienvenue Mayeul ! Ce guide explique comment gérer les événements sur ton site depuis l'interface d'administration.

---

## 1. Se connecter à l'espace admin

1. Ouvre ton navigateur et va sur :
   **https://www.jetsetboat.fr/admin/**

2. Entre tes identifiants :
   - **Nom d'utilisateur :** `mayeul`
   - **Mot de passe :** `jetsetboat2024`

3. Clique sur **Se connecter**.

> Si tu vois le site en "construction" ou une ancienne page, efface le cache du navigateur (Ctrl+Shift+R) ou accède directement à `/admin/index.php`.

---

## 2. Créer un nouvel événement

1. Dans l'admin, clique sur le bouton **+ Nouvel événement** (en haut à droite).

2. Remplis le formulaire :
   | Champ | Exemple |
   |-------|---------|
   | **Titre** | JetSet Sunset Saint-Tropez |
   | **Date** | 2025-07-19 |
   | **Lieu** | Saint-Tropez |
   | **Description** | Coucher de soleil & deep house sur le pont... |
   | **Prix (€)** | 55 |
   | **Lien Stripe** | https://buy.stripe.com/... (voir section 5) |
   | **Image** | Choisir un fichier JPG/PNG (max 5 MB) |
   | **Vedette** | Cocher si tu veux que cet événement soit mis en avant |
   | **Sold out** | Laisser décoché à la création |
   | **Nombre total de places** | Ex : 80 |
   | **Places restantes** | Ex : 80 (même valeur au départ) |

3. Clique sur **Créer l'événement**.

4. L'événement apparaît **immédiatement** sur le site public dans la section "Événements".

---

## 3. Modifier un événement

1. Dans la liste des événements de l'admin, clique sur **Modifier** à droite de l'événement concerné.

2. Change les informations souhaitées dans le formulaire.

3. **Pour changer l'image :** sélectionne un nouveau fichier. Si tu laisses le champ vide, l'image actuelle est conservée.

4. Clique sur **Enregistrer les modifications**.

---

## 4. Marquer un événement comme complet (Sold out)

Quand toutes les places sont vendues, tu peux afficher un badge **"Complet"** rouge sur la carte de l'événement :

### Option 1 — Automatique
Si tu as renseigné un nombre de **places restantes** et qu'il atteint **0**, l'événement passe automatiquement en "Complet" à la prochaine modification.

### Option 2 — Manuel
1. Clique sur **Modifier** à côté de l'événement concerné.
2. Coche la case **"Sold out — marquer l'événement comme complet"**.
3. Clique sur **Enregistrer les modifications**.

**Effet sur le site public :**
- Le bouton "Réserver →" est remplacé par un badge rouge **"Complet"**
- Le lien Stripe est désactivé (plus de clics accidentels)
- La carte s'affiche légèrement assombrie

### Réouvrir les ventes
Pour réactiver un événement complet : décocher la case "Sold out" et cliquer sur **Enregistrer les modifications**.

---

## 5. Gérer les places disponibles

Les champs **"Nombre total de places"** et **"Places restantes"** permettent d'afficher l'urgence sur le site.

### Comportement sur le site public
- Si **places restantes ≤ 5** (et > 0) : un badge orange animé **"Plus que X places !"** apparaît sur la carte
- Si **places restantes = 0** : l'événement passe automatiquement en "Complet"

### Mise à jour après une vente
Après chaque vente Stripe, mets à jour les places restantes manuellement :
1. Clique sur **Modifier** à côté de l'événement.
2. Dans le champ **"Places restantes"**, diminue le chiffre du nombre de billets vendus.
3. Clique sur **Enregistrer les modifications**.

> **Astuce :** Pour être averti des ventes en temps réel, configure les notifications email dans ton dashboard Stripe (Paramètres → Notifications).

---

## 6. Supprimer un événement

1. Dans la liste, clique sur le bouton rouge **Supprimer** à droite de l'événement.

2. Une fenêtre de confirmation s'affiche — clique sur **Supprimer définitivement** pour confirmer.

> ⚠️ La suppression est irréversible. L'événement disparaît immédiatement du site.

---

## 7. Créer un vrai lien de paiement Stripe

Avant de publier un événement, crée son lien de paiement sur Stripe :

1. Va sur **https://dashboard.stripe.com** (connecte-toi avec le compte JetSetBoat).

2. Dans le menu gauche, clique sur **Payment Links**.

3. Clique sur **+ Create payment link**.

4. Configure :
   - **Produit :** crée un nouveau produit "JetSet Sunset — 14 juin 2025" avec le prix correspondant
   - **Quantité :** tu peux limiter le nombre de places disponibles directement via Stripe
   - **Redirection après paiement :** vers ton site si tu veux (optionnel)

5. Clique sur **Create link**. Stripe génère une URL comme :
   `https://buy.stripe.com/abcdefgh1234`

6. Copie cette URL et colle-la dans le champ **Lien de paiement Stripe** lors de la création ou modification de l'événement.

---

## 8. Uploader une image d'événement

L'image doit respecter ces règles :
- Format : **JPG, PNG ou WebP** (pas de GIF animé, pas de PDF)
- Taille maximale : **5 MB**
- Dimensions recommandées : **1200 × 800 px** (ratio paysage 3:2)

Si tu n'as pas d'image, tu peux coller une URL d'image dans le champ "URL de l'image".

> **Astuce :** Compresse tes images gratuitement sur https://squoosh.app avant de les uploader pour accélérer le chargement du site.

---

## 9. Mettre à jour le site sur OVH

Les modifications des événements sont **instantanées** — le fichier `events.json` est mis à jour sur le serveur et le site le lit immédiatement. Tu n'as pas besoin de toucher à FTP pour gérer les événements.

En revanche, si une mise à jour du design ou du code est effectuée par le développeur, il te fournira une nouvelle version à uploader via FTP :

1. Connecte-toi à ton espace FTP OVH (identifiants dans ton espace client OVH).
2. Uploade les fichiers fournis par le développeur dans le dossier `/public_html/`.
3. Ne touche pas à `events.json` — il contient tes données et ne doit pas être écrasé.

---

## 10. Questions fréquentes

**Q: Comment indiquer qu'un événement est complet sans supprimer le bouton Stripe ?**
R: Utilise la case à cocher "Sold out" dans le formulaire de modification. La carte affichera "Complet" en rouge et le lien Stripe sera désactivé côté site, mais tu peux le réactiver à tout moment en décochant la case.

**Q: Le badge "Plus que X places !" ne s'affiche pas ?**
R: Il apparaît uniquement si le champ "Places restantes" est renseigné et inférieur ou égal à 5. Vérifie que tu as bien saisi une valeur dans ce champ lors de la modification de l'événement.

**Q: L'image ne s'affiche pas après upload ?**
R: Vérifie que le dossier `images/events/` a les bonnes permissions sur OVH (chmod 755). Contacte l'hébergeur si besoin.

**Q: J'ai oublié mon mot de passe admin ?**
R: Contacte le développeur pour modifier le fichier `admin/config.php` sur le serveur. Tu peux aussi le changer toi-même via **Mot de passe** dans la barre de navigation de l'admin, si tu te souviens de ton mot de passe actuel.

**Q: Le formulaire de contact ne fonctionne pas ?**
R: Le formulaire envoie les emails vers `Mayeulg@yahoo.fr`. Si tu ne reçois rien, vérifie le dossier spam. Si le problème persiste, contacte le développeur pour reconfigurer le SMTP.

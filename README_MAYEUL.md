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
   | **Lien Stripe** | https://buy.stripe.com/... (voir section 4) |
   | **Image** | Choisir un fichier JPG/PNG (max 5 MB) |
   | **Vedette** | Cocher si tu veux que cet événement soit mis en avant |

3. Clique sur **Créer l'événement**.

4. L'événement apparaît **immédiatement** sur le site public dans la section "Événements".

---

## 3. Modifier un événement

1. Dans la liste des événements de l'admin, clique sur **Modifier** à droite de l'événement concerné.

2. Change les informations souhaitées dans le formulaire.

3. **Pour changer l'image :** sélectionne un nouveau fichier. Si tu laisses le champ vide, l'image actuelle est conservée.

4. Clique sur **Enregistrer les modifications**.

---

## 4. Supprimer un événement

1. Dans la liste, clique sur le bouton rouge **Supprimer** à droite de l'événement.

2. Une fenêtre de confirmation s'affiche — clique sur **Supprimer définitivement** pour confirmer.

> ⚠️ La suppression est irréversible. L'événement disparaît immédiatement du site.

---

## 5. Créer un vrai lien de paiement Stripe

Avant de publier un événement, crée son lien de paiement sur Stripe :

1. Va sur **https://dashboard.stripe.com** (connecte-toi avec le compte JetSetBoat).

2. Dans le menu gauche, clique sur **Payment Links**.

3. Clique sur **+ Create payment link**.

4. Configure :
   - **Produit :** crée un nouveau produit "JetSet Sunset — 14 juin 2025" avec le prix correspondant
   - **Quantité :** tu peux limiter le nombre de places disponibles
   - **Redirection après paiement :** vers ton site si tu veux (optionnel)

5. Clique sur **Create link**. Stripe génère une URL comme :
   `https://buy.stripe.com/abcdefgh1234`

6. Copie cette URL et colle-la dans le champ **Lien de paiement Stripe** lors de la création ou modification de l'événement.

---

## 6. Uploader une image d'événement

L'image doit respecter ces règles :
- Format : **JPG, PNG ou WebP** (pas de GIF animé, pas de PDF)
- Taille maximale : **5 MB**
- Dimensions recommandées : **1200 × 800 px** (ratio paysage 3:2)

Si tu n'as pas d'image, tu peux coller une URL d'image dans le champ "URL de l'image".

> **Astuce :** Compresse tes images gratuitement sur https://squoosh.app avant de les uploader pour accélérer le chargement du site.

---

## 7. Mettre à jour le site sur OVH

Les modifications des événements sont **instantanées** — le fichier `events.json` est mis à jour sur le serveur et le site le lit immédiatement. Tu n'as pas besoin de toucher à FTP pour gérer les événements.

En revanche, si une mise à jour du design ou du code est effectuée par le développeur, il te fournira une nouvelle version à uploader via FTP :

1. Connecte-toi à ton espace FTP OVH (identifiants dans ton espace client OVH).
2. Uploade les fichiers fournis par le développeur dans le dossier `/public_html/`.
3. Ne touche pas à `events.json` — il contient tes données et ne doit pas être écrasé.

---

## 8. Questions fréquentes

**Q: Un client a payé mais l'événement est complet, que faire ?**
R: Modifie le lien Stripe sur le dashboard Stripe pour désactiver les ventes (Payment Links → désactiver le lien). Tu n'as pas besoin de modifier l'événement sur le site.

**Q: L'image ne s'affiche pas après upload ?**
R: Vérifie que le dossier `images/events/` a les bonnes permissions sur OVH (chmod 755). Contacte l'hébergeur si besoin.

**Q: J'ai oublié mon mot de passe admin ?**
R: Contacte le développeur pour modifier le fichier `admin/config.php` sur le serveur.

**Q: Le formulaire de contact ne fonctionne pas ?**
R: Le formulaire envoie les emails vers `Mayeulg@yahoo.fr`. Si tu ne reçois rien, vérifie le dossier spam. Si le problème persiste, contacte le développeur pour reconfigurer le SMTP.

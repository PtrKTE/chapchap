# CHAPCHAP — Guide Utilisateur

## Accès a l'application

- **URL** : `https://chapchap.votredomaine.ci/admin`
- **Navigateur recommande** : Chrome, Firefox, Safari (versions recentes)
- **Mobile** : L'application fonctionne sur smartphone (interface responsive)

---

## Connexion

1. Ouvrir l'URL de l'application
2. Saisir votre **email** et **mot de passe**
3. Cliquer sur **Se connecter**

> En cas d'oubli de mot de passe, contacter l'administrateur.

---

## Tableau de bord

Apres connexion, vous arrivez sur le tableau de bord. Les widgets affiches dependent de votre profil :

| Profil | Widgets visibles |
|--------|-----------------|
| Gerant | CA jour/semaine/mois, Evolution CA, Top clients, Top produits, Impayes, Tresorerie, Production, Commerciaux, Stock, Alertes |
| Resp. Operations | Tous les widgets |
| Gestionnaire Stock | Stock, Alertes stock |
| Agent Production | Production (poulets traites, rendement, pertes) |
| Commercial | Top clients, Impayes, Performance commerciaux |

---

## Module Lots / Achats

### Creer un lot

1. Menu **Stock > Lots**
2. Cliquer **Nouveau lot**
3. Remplir :
   - **Fournisseur** (ex: OVECH RANCH)
   - **Type** : poulet ou oeuf
   - **Date de reception**
   - **Quantite recue** : nombre de poulets/oeufs
   - **Prix unitaire** en FCFA
   - **Cout transport** et **Autres couts** (optionnels)
   - **Poulets morts (PM)** et **Refuses (IR)** si applicable
4. Cliquer **Creer**

> Les champs **quantite utilisable**, **cout total** et **CMP** (Cout Moyen Unitaire) se calculent automatiquement.

### Suivi des factures

- Dans la fiche du lot, section **Facturation** :
  - Renseigner le **montant facture**
  - Mettre a jour le **montant regle** au fur et a mesure des paiements
  - Le **statut facture** se met a jour automatiquement (Non reglee / Partielle / Reglee)

---

## Module Production

### Saisir une production

1. Menu **Production > Productions**
2. Cliquer **Nouvelle production**
3. Selectionner le **lot source** (seuls les lots avec des poulets disponibles apparaissent)
4. Indiquer le **nombre de poulets traites**
5. Renseigner le **poids moyen entrant** (optionnel, en kg)
6. Dans la section **Produits obtenus**, ajouter chaque produit issu de la decoupe :
   - Escalopes, Cuisses, Ailes, Carcasses, Gesiers, Foies, etc.
   - Indiquer la **quantite** en kg ou unites
7. Noter les **pertes/casse** en kg
8. Cliquer **Creer**

### Valider une production

- Modifier le **statut** de "En cours" a "Validee"
- La validation genere automatiquement les **entrees de stock** pour chaque produit

> Une production validee ne peut plus etre modifiee.

---

## Module Stock

### Consulter le stock

1. Menu **Stock > Stock emplacements**
2. Voir la quantite et la valeur de chaque produit par emplacement
3. Utiliser les filtres pour chercher un produit ou un emplacement

### Mouvements de stock

- Menu **Stock > Mouvements stock** : historique complet de toutes les entrees/sorties
- Chaque mouvement indique le type (entree production, sortie vente, transfert, ajustement...)

### Transferts inter-sites

Pour transferer des produits du Site principal vers Belleville (ou inversement) :

1. Menu **Stock > Transferts**
2. Cliquer **Nouveau transfert**
3. Choisir l'**emplacement source** et la **destination**
4. Ajouter les produits et quantites a envoyer
5. Cliquer **Creer**

Lors de la reception :
- Ouvrir le transfert
- Renseigner les **quantites recues** pour chaque ligne
- Le statut passera automatiquement a "Recu"

### Inventaire physique

Pour faire un inventaire (comptage physique) :

1. Menu **Stock > Inventaires**
2. Cliquer **Nouvel inventaire**
3. Selectionner l'**emplacement** a inventorier
4. Pour chaque produit, le **stock theorique** (systeme) est affiche automatiquement
5. Saisir le **stock physique** (quantite comptee)
6. L'**ecart** se calcule automatiquement
7. Renseigner une **justification** pour chaque ecart

---

## Module Ventes

### Creer une vente

1. Menu **Commercial > Ventes**
2. Cliquer **Nouvelle vente**
3. Remplir :
   - **Client** (selectionner ou creer)
   - **Emplacement** (ou se fait la vente)
   - **Canal** : Boutique, Commercial, Livraison, B2B
   - **Commercial** (si applicable)
4. Ajouter les **lignes de vente** :
   - Selectionner le **produit**
   - Indiquer la **quantite** et le **prix unitaire**
   - Le montant se calcule automatiquement
5. Appliquer une **remise** si necessaire
6. Choisir le **mode de paiement** et le **statut** :
   - **Paye** : le client a tout paye
   - **Partiel** : paiement partiel, le reste en credit
   - **Credit** : rien paye, tout a credit
7. Cliquer **Creer**

> La creation d'une vente decremente automatiquement le stock.

### Encaisser un paiement sur credit

1. Ouvrir la vente en edition
2. Cliquer le bouton **Encaisser**
3. Saisir le **montant recu**, le **mode de paiement** et une reference
4. Le statut se met a jour automatiquement

### Imprimer un recu

- Sur la page d'edition de la vente, cliquer le bouton **Imprimer recu**
- Un PDF se telecharge avec le detail de la vente

### Annuler une vente

- Sur la page d'edition, cliquer **Annuler la vente**
- Indiquer le **motif d'annulation**
- Le stock est automatiquement reconstitue

---

## Module Clients

### Creer un client

1. Menu **Commercial > Clients**
2. Cliquer **Nouveau client**
3. Remplir les informations :
   - **Nom**, **Telephone**, **Adresse**, **Quartier**
   - **Type** : Restaurant, Maquis, Hotel, Supermarche, etc.
   - **Commercial attribue** (optionnel)
4. Le **code client** (CLI-XXXX) est genere automatiquement

### Importer des clients (Excel)

1. Sur la liste des clients, cliquer **Importer clients**
2. Selectionner un fichier Excel (.xlsx) avec les colonnes :
   - nom, telephone, email, adresse, quartier_zone, type_client
3. Les clients sont crees automatiquement

---

## Module Caisse

### Journal de caisse quotidien

1. Menu **Tresorerie > Caisses**
2. Cliquer **Nouvelle caisse** pour la journee
3. Remplir :
   - **Total encaisse** : ventes du jour
   - **Credits encaisses** : paiements recus sur anciennes ventes
   - **Total decaisse** : charges/depenses du jour
4. Section **Versements** :
   - **Versement especes** : montant verse a la banque
   - **Depots Wave/MTN** : paiements mobile
   - **Depots cheque** : cheques recus
5. Le **solde** et l'**ecart** se calculent automatiquement
6. L'ecart doit etre a 0 pour cloturer la caisse

### Imprimer le rapport de caisse

- Sur la page d'edition de la caisse, cliquer **Imprimer rapport**
- Un PDF detaille se telecharge

---

## Module Charges

### Saisir une charge/depense

1. Menu **Tresorerie > Charges**
2. Cliquer **Nouvelle charge**
3. Remplir :
   - **Categorie** (Carburant, Electricite, Loyer, etc.)
   - **Libelle** : description de la depense
   - **Montant** en FCFA
   - **Mode de paiement**
   - **Emplacement** concerne

---

## Module Eau (Production d'eau en sachets)

1. Menu **Production > Eau**
2. Cliquer **Nouvelle production**
3. Remplir :
   - **Date de production**
   - **Nombre de paquets produits**
   - **Consommation sachets** et **energie** (optionnel)

---

## Exports Excel

Depuis chaque liste, un bouton **Exporter Excel** permet de telecharger les donnees :

| Module | Bouton | Contenu |
|--------|--------|---------|
| Ventes | Exporter Excel | Toutes les ventes (filtrable par dates) |
| Lots | Exporter Excel | Tous les lots (filtrable par dates) |
| Clients | Exporter clients | Liste des clients actifs |
| Charges | Exporter Excel | Charges (filtrable par dates) |
| Caisses | Exporter Excel | Caisses (filtrable par dates) |
| Stock | Exporter Excel | Photo du stock actuel |

---

## Paiements

- Menu **Commercial > Paiements** : historique de tous les paiements recus
- Filtrable par mode de paiement et par date
- Le total des paiements est affiche en bas de la colonne Montant

> Les paiements sont crees automatiquement lors des ventes. Cette page est en lecture seule.

---

## Bonnes pratiques

1. **Cloturer la caisse chaque soir** avant de quitter
2. **Valider les productions** des que le comptage est termine
3. **Faire un inventaire** physique au moins une fois par semaine
4. **Verifier les impayes** regulierement (tableau de bord)
5. **Sauvegarder** : les backups automatiques se font chaque nuit a 2h

---

## Aide et support

En cas de probleme technique :
- Contacter l'administrateur systeme
- Decrire le probleme avec une capture d'ecran si possible
- Indiquer l'heure exacte du probleme (pour les logs)

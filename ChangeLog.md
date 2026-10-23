# Changelog

## 2.3.1 — 2026-10-23

- Correction du contrôle du prix minimum selon le mode natif Produits/Services : niveaux clients, tarifs clients, modes combinés et paliers sélectionnés ; absence de repli sur l'ancien minimum général lorsque le niveau est disponible.
- Contrôle du prix réellement appliqué après remise à l'ajout, l'édition et l'actualisation des documents commerciaux ; conservation des prix manuels lors d'une remise seule et omission diagnostiquée lorsque le contrôle est impossible.
- Avertissements précisant les niveaux incompatibles sans bloquer l'enregistrement des tarifs génériques ; vérification définitive avec le client réel.
- Compatibilité native explicitée : client + niveaux à partir de v21, dates de validité des tarifs clients à partir de v22, minimum nul conservé et paliers étrangers refusés ; aucune migration SQL ni réinitialisation nécessaire.
- Ajout de scénarios reproductibles utilisant les méthodes natives et d'une matrice CI Dolibarr v20 à v25 alpha ; les simulations sont distinguées des recettes réelles.
- Application des tarifs dégressifs liée au partage natif des prix de vente de Multicompany, sans réglage concurrent : tarifs locaux prioritaires, puis tarifs des entités autorisées par `getEntity('productprice')`. Le seul partage d'un produit/service ne partage plus ses tarifs PriceList.
- Sans partage des prix de vente, les tarifs étrangers ne peuvent plus modifier une ligne ni déclencher leur contrôle de minimum. La gestion reste dans l'entité propriétaire ; les documents partagés accessibles conservent leurs propres tarifs et leur contexte de coût.

## 2.3.0 — 2026-09-22

- Ajout de l’onglet de réglages **À propos**, alimenté par le descripteur, après **Compatibilité**.
- Correction de l’onglet natif **Tarifs dégressifs** sur les sous-pages des produits et services : expression testée avec les évaluateurs Dolibarr 20, 21, 22, 23.0.2 et 24.0.1, droits propres à chaque type et suppression des ajouts de secours. Le contrôle des utilisateurs externes reste dans la page, hors de l’expression évaluée.
- Choix de trois sources de prix de revient : personnalisé, produit natif et DynamicPrices. Lecture du coût courant valide dans l’entité du document, sans recalcul ; coût existant conservé avec alerte si la source est indisponible. Fonctionnement indépendant de l’automatisation des ventes DynamicsPrices.
- Migration rejouable de `cost_price_source` pour les tarifs et leur historique, conversion des anciennes entrées, adaptation du clonage et des imports/exports. Réactiver PriceList après installation pour migrer et renouveler les onglets/hooks ; les réglages existants restent conservés.
- Résolution commune des coûts dans les écrans et les documents commerciaux, y compris les factures récurrentes ; contrôles des droits et des entités renforcés, sans élévation administrateur. Socle déclaré Dolibarr 20+ / PHP 8.0+ inchangé ; traductions françaises et anglaises et tests ciblés ajoutés.

## 2.2.1

- Suppression de la propriété dynamique obsolète du descripteur sous PHP 8.2.

## 2.2.0

- Ajout de l'édition des lignes de tarifs dégressifs depuis l'onglet existant.
- Ajout d'un historique des créations et modifications de lignes avec date, nouvelle valeur, écart et utilisateur.
- Ajout du support des tags/catégories commandes et factures à partir de Dolibarr v22.
- Possibilité de regrouper les tags/catégories devis, commandes, factures et contrats sur une même ligne tarifaire.
- Ajout de la saisie TTC lorsque l'option d'affichage des prix TTC est active, avec stockage du prix HT.
- Renommage des libellés français en `Tags/Catégorie devis`, `Tags/Catégorie commandes`, `Tags/Catégorie factures` et `Tags/Catégorie contrats`.
- Ajout du réglage `PRICELIST_DOCUMENT_CATEGORY_PRIORITY` pour choisir la priorité entre tarifs de l'objet courant et tarifs tiers/catégorie client.
- Ajout d'une bannière permanente dans l'onglet des tarifs dégressifs rappelant la règle de priorité active.
- Blocage des tarifs dégressifs inférieurs au prix de vente minimum natif du produit, avec alerte sur les lignes existantes incohérentes.
- Ajout du mode de ligne `use_product_cost_price` pour utiliser le prix de revient natif du produit à la place d'un prix de revient personnalisé.
- Durcissement des droits : lecture conditionnée au droit de lecture des prix produit en permissions avancées, modification/suppression conditionnées au droit créer/modifier du type produit ou service.
- Correction de compatibilité : tags/catégories devis à partir de Dolibarr v23, commandes/factures à partir de v22, contrats fournis par PriceList derrière l'option `PRICELIST_ENABLE_CONTRACT_CATEGORIES`.
- Restauration du support local PriceList des tags/catégories contrats, avec activation explicite par option.
- Alignement de la résolution d'entité des tarifs dégressifs pour les commandes et factures.
- Correction de la recherche des tarifs dégressifs pour les contrats utilisant des produits partagés entre entités Multicompany.
- Correction de l'application des tarifs dégressifs lors de l'édition d'une ligne de contrat.
- Correction de la propriété `warnings` déclarée pour la classe de hooks avec PHP 8.2+.

## 2.1.0

- Ajout des tarifs par catégorie de devis et de contrat.
- Ajout de la déclaration de catégorie contrat portée par PriceList.
- Ajout du filtrage `entity` sur les grilles de prix.

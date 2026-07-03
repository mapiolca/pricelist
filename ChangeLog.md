# Changelog

## 2.2.0

- Ajout de l'édition des lignes de tarifs dégressifs depuis l'onglet existant.
- Ajout d'un historique des créations et modifications de lignes avec date, nouvelle valeur, écart et utilisateur.
- Ajout du support des tags/catégories commandes et factures à partir de Dolibarr v22.
- Possibilité de regrouper les tags/catégories devis, commandes, factures et contrats sur une même ligne tarifaire.
- Ajout de la saisie TTC lorsque l'option d'affichage des prix TTC est active, avec stockage du prix HT.
- Renommage des libellés français en `Tags/catégories devis` et `Tags/catégories contrats`.
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

# Changelog

## Unreleased

- Alignement de la résolution d'entité des tarifs dégressifs pour les commandes et factures.
- Correction de la recherche des tarifs dégressifs pour les contrats utilisant des produits partagés entre entités Multicompany.
- Correction de l'application des tarifs dégressifs lors de l'édition d'une ligne de contrat.
- Correction de la propriété `warnings` déclarée pour la classe de hooks avec PHP 8.2+.

## 2.1.0

- Ajout des tarifs par catégorie de devis et de contrat.
- Ajout de la déclaration de catégorie contrat lorsque `lmdbzoning` n'est pas actif.
- Ajout du filtrage `entity` sur les grilles de prix.

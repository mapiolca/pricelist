# pricelist
Module permettant de générer une grille de prix en fonction d'une quantité.

Il inclut la gestion des prix de revient afin de suivre le coût par paliers de quantités.

Les tarifs peuvent être ciblés par tiers, catégorie client, catégorie de devis ou catégorie de contrat.
Lors de l'application sur une ligne, la priorité est : catégorie de l'objet courant, tiers, catégorie client, puis tarif générique du produit.

# pricelist

Module Dolibarr permettant de gérer des tarifs dégressifs par quantité.

Il inclut la gestion des prix de revient afin de suivre le coût par paliers de quantités.

Les tarifs peuvent être ciblés par tiers, catégorie client, ou tags/catégories de documents commerciaux. Les tags/catégories de devis, commandes, factures et contrats peuvent être regroupés sur une même ligne de tarif ; les tiers et catégories client restent des périmètres séparés.

Lors de l'application sur une ligne, la priorité par défaut est : tags/catégories de l'objet courant, tiers, catégorie client, puis tarif générique du produit. Le réglage `PRICELIST_DOCUMENT_CATEGORY_PRIORITY` permet d'inverser la priorité entre le groupe objet et le groupe tiers/client.

Les tarifs dégressifs ne peuvent pas descendre sous le prix de vente minimum natif du produit. Les lignes existantes incohérentes sont signalées dans l'onglet et ne sont pas appliquées par les hooks.

Chaque ligne peut utiliser soit un prix de revient personnalisé, soit le prix de revient natif du produit (`Product::cost_price`).

Les lignes de tarif sont éditables depuis l'onglet des prix dégressifs. Chaque création et modification alimente un historique consultable en infobulle avec la date, la nouvelle valeur, l'écart et l'utilisateur.

Lorsque l'option **Afficher les prix TTC** est active, le formulaire permet de saisir un prix HT ou TTC. Le module conserve le prix HT comme source de vérité.

Les tags/catégories de devis utilisent le support natif Dolibarr disponible à partir de Dolibarr v23. Les tags/catégories commandes et factures utilisent le support natif disponible à partir de Dolibarr v22. Les tags/catégories contrats sont fournis par PriceList et exploités uniquement lorsque l'option `PRICELIST_ENABLE_CONTRACT_CATEGORIES` est active.

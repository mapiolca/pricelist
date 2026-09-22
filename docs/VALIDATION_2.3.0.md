# Validation PriceList 2.3.0

État au 2026-09-22 : correctif préparé pour la PR #7, sans déploiement ni publication de release par l’agent. L’utilisateur signale la régression de l’onglet sur son instance Dolibarr 23.0.2.

## Sources examinées

- Dolibarr 20.0.0, commit `697bf01970740a3339cd99cf055b4428fc5e051c` : `core/lib/functions.lib.php` (`dol_eval`, `verifCond`, `complete_head_from_modules`, `price2num`), `core/lib/security.lib.php` (`checkUserAccessToObject`), import natif (`ImportInsert`), signature `FactureRec::updateline` et `Form::selectarray`.
- Évaluateurs et construction des onglets des tags Dolibarr 21.0.0 (`fd970b582a4d8c5779a2958a4e9f4fce225cf085`), 22.0.0 (`49b9a6d19f3deb6d410c0e9b3310e95be4ea7710`), 23.0.2 (`ccef1102e6850b7545be7bad91cf0cc4c74ac6ea`) et 24.0.1 (`b7958385f00a92219f76a4dfea67dae93df97a25`) exécutés dans les tests.
- Checkout local, commit `0d20b226f5e13b848bb58528398967f52862688c` : version **25.0.0-alpha** déclarée dans `version.inc.php`. Correction de la qualification précédente : `24.0.1-1812-g0d20b226f5e` était une description Git, pas la version du code. Le lanceur de tests utilise désormais le tag demandé ou les constantes du fichier de version pour `HEAD`.
- DynamicsPrices 3.0.1, source locale : lecteur `DynamicPricesCostService::getDynamicCostPrice($productId, $entity, ['require_success' => true])`. Cette méthode lit le coût courant de l’entité exacte et refuse les états désactivés/calculs en erreur. Le résolveur de priorités n’est pas appelé par PriceList. Le dépôt DynamicsPrices contient des travaux concurrents, hors du patch PriceList.
- Diffusion, `admin/about.php` : référence de rendu natif des réglages. Les métadonnées de PriceList sont lues dans son propre descripteur.

Lecture de sources et simulation ne constituent pas des tests d’une installation complète ni une validation de toutes les versions intermédiaires.

## Contrôles exécutés

- PHP 8.4.22 : syntaxe de tous les fichiers PHP du module.
- `node --check js/pricelist_ttc.js` : syntaxe JavaScript valide.
- `php test/run.php ../dolibarr <version>` : **118 assertions réussies par exécution**, pour `20.0.0`, `21.0.0`, `22.0.0`, `23.0.2`, `24.0.1` et `HEAD` (25.0.0-alpha).
- Modes `missing` et `old` : fournisseur absent ou signature incompatible refusés proprement.
- Les tests chargent le code PriceList et les fonctions natives Dolibarr en mémoire, avec doubles de base, produits, utilisateurs et fournisseur. Ils couvrent : droits standards/avancés des deux types et administrateur sans droit, construction native d’un onglet unique après les onglets transverses, identifiant produit conservé, sources/coût zéro/arrondis MU, fournisseur désactivé/erreur, droits/accessibilité, entité de consultation et document partagé, création/modification/clonage/historique, imports et absence de triggers pendant la simulation, propagation des erreurs et rejeu de migration, paramètres natifs d’actualisation des cinq types de documents commerciaux.
- Aucun accès direct aux structures de droits ni élévation administrateur dans les chemins fonctionnels modifiés. Le rôle administrateur reste requis pour les réglages.
- `git diff --check` : aucune erreur de whitespace.
- PHPStan : **non exécuté**, aucun exécutable/configuration utilisable fourni dans PriceList, aucun binaire PHPStan dans les dépendances locales examinées. Aucun ignore ni baseline ajouté.

## Régression de l’onglet en Dolibarr 23.0.2

Les logs fournis par l’utilisateur montrent le refus de notre expression contenant `$user->socid`. L’échec a été reproduit avant correction avec `dol_eval()` et `verifCond()` extraits du tag 23.0.2 : cette propriété n’est pas autorisée par sa liste de variables. Le contrôle des utilisateurs externes est conservé dans `product.php`, en dehors du texte évalué. L’onglet utilise directement les permissions produit/service, sans élévation administrateur.

La matrice élargie a aussi reproduit un rejet des parenthèses imbriquées avec Dolibarr 21.0.0 ; les expressions produit/service et tiers sont simplifiées. Les tests vérifient les droits standards/avancés, un administrateur sans permission, le droit de lecture du tiers et le statut client. Les 118 assertions ci-dessus passent après correction. La validation initiale sur 20.0.0 et le checkout de développement ne couvrait pas ces différences.

Les déclarations étant persistées par Dolibarr, le correctif nécessite une désactivation/réactivation de PriceList après copie des fichiers, même si la version affichée est déjà 2.3.0. Aucun rafraîchissement de ces constantes n’est effectué pendant une simple consultation.

Les mêmes logs contiennent une erreur distincte de navigation mêlant filtre USF et fragment SQL. Le filtre USF par type existe dans la fiche produit native 23.0.2 ; l’origine complète du fragment SQL ajouté n’a pas été confirmée. Aucun correctif core ou Multicompany n’est inclus dans cette PR et les données privées des logs ne sont pas reproduites ici.

## Recette sur instance restant à exécuter

Aucune instance locale configurée ne sert ce correctif. Navigateur : **non exécuté**. Dolibarr 20/PHP 8.0, MySQL/MariaDB et Multicompany réels : **non exécutés**. Les simulations SQL ne prouvent pas le comportement du moteur MySQL/MariaDB ni les transactions réelles.

1. Installer sur une instance de recette, relever les réglages et réactiver PriceList. Vérifier migration depuis 2.2.x, nouvelle installation et rejeu après interruption ; conserver historiques et montants, constantes à `0` ou chaîne vide, attributions existantes et réglages par entité. Tester un préfixe SQL différent ; aucun nouvel index n’est ajouté.
2. Produits et services : fiche, prix de vente, prix d’achat, notes, fichiers joints, agenda et statistiques. Vérifier un onglet unique, l’objet conservé, l’ordre et l’accès direct. Répéter avec droits standards/avancés, lecture seule, absence de droit et administrateur sans permission.
3. Créer, modifier, cloner, importer et exporter chaque source. Changer de source, conserver zéro, refuser les valeurs invalides et vérifier l’historique. Vérifier les sélecteurs avec/sans JavaScript et les formulaires avec/sans token CSRF ; les contrôles natifs de `main.inc.php` restent actifs.
4. Deux entités avec produit partagé et coûts différents : écrans dans chaque entité, documents dans leur entité propre ou partagée, partage retiré, configuration des coûts différente. Aucune lecture du coût de l’entité propriétaire du produit à la place de celle du document.
5. Ajout, édition à quantité inchangée et actualisation sur devis, commandes, factures, factures récurrentes et contrats. Source absente ou calcul en erreur : coût existant conservé, vente/remise appliquée, alerte. Vérifier extrafields, prix d’achat et dates des factures récurrentes.
6. DynamicsPrices : automatisation des ventes désactivée puis active avec ses priorités/choix explicites. Vérifier le résultat final après tous les hooks/triggers et l’absence de boucle ; les réglages DynamicsPrices restent prioritaires pour ses propres traitements. PriceList n’écrit ni ne recalcule les coûts du fournisseur.
7. Rendu natif **À propos**, compatibilité, traductions françaises/anglaises, retour aux modules et unique roue dentée.

## Périmètre et checklist applicable

- Descripteur : ID existant **450008** et famille **Les Métiers du Bâtiment** conservés ; aucune nouvelle permission ni nouveau numéro attribué. L’unicité du numéro n’a pas été réauditée à l’échelle du parc/GitHub.
- SQL/migration, objet métier, permissions, entités, hooks, interface, traductions, imports/exports et livraison : analysés ; couverture simulée indiquée ci-dessus, limites d’intégration explicitement conservées.
- Agenda/Notifications : aucun nouvel événement ni traitement ajouté. Préfixe CRUD `PRICELIST` rendu explicite pour les appels existants. Aucun email envoyé.
- Documents PDF/ODT, stockage/fichiers joints, numérotation, API REST, cron, nouveaux tags/extrafields : aucune évolution, tests spécifiques non applicables.
- Aucun changement d’état d’une instance ni restauration à effectuer. Les travaux locaux DynamicsPrices ne font pas partie de cette PR.

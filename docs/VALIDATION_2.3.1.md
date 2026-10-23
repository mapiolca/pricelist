# PriceList 2.3.1 — validation du contrôle des minimums

État : changements préparés pour revue, sans déploiement ni publication de release.
Vérification locale initiale du 7 octobre 2026, complétée le 22 octobre 2026
après intégration du partage natif des prix de vente, sous PHP 8.4.22.

## Périmètre

Le contrôle utilise le contexte natif de l'entité et du client : prix unique,
multiprices, prix client, client et multiprices, quantité, quantité et multiprices.
La remise seule conserve le prix réel de la ligne. Un prix fixe est contrôlé avec
la remise conservée par le parcours. Le minimum est comparé au prix final HT,
avec les conversions et la précision unitaires natives.

Les règles génériques restent enregistrables en mode contextuel ; leurs
avertissements détaillent les niveaux incompatibles ou indisponibles. Un tarif
client est contrôlé lors de sa sauvegarde, puis chaque application est contrôlée
pour le client réel. Une résolution impossible omet l'enrichissement automatique
avec un diagnostic. Aucun changement SQL ou core n'est nécessaire.

## Sources et simulations exécutées

`test/run.php` extrait en mémoire la méthode native `Product::getSellPrice()`.
Les fixtures temporaires extraient également `ProductCustomerPrice::fetchAll()`
et `calcul_price_total()` sans modifier leurs corps. La base, les utilisateurs,
les sessions, les fournisseurs de prix et les collaborateurs de ces méthodes sont
simulés. Ces essais ne sont pas une recette sur instance Dolibarr complète.

| Source Dolibarr | Révision exacte lue et utilisée | Assertions réussies (PHP 8.4.22) |
|---|---|---:|
| 20.0.0 | `697bf01970740a3339cd99cf055b4428fc5e051c` | 240 |
| 21.0.0 | `fd970b582a4d8c5779a2958a4e9f4fce225cf085` | 243 |
| 22.0.0 | `49b9a6d19f3deb6d410c0e9b3310e95be4ea7710` | 257 |
| 23.0.2 | `ccef1102e6850b7545be7bad91cf0cc4c74ac6ea` | 262 |
| 24.0.1 | `b7958385f00a92219f76a4dfea67dae93df97a25` | 262 |
| 25.0.0-alpha | `d37fd9f3128ab8d52bd6399a2fe6ebbe7e65f62b` | 262 |

Le lanceur exécute maintenant les scénarios de modes de prix et d'isolation/
partage Multicompany dans la même exécution. Voir aussi
[la validation du partage](VALIDATION_2.3.1_MULTICOMPANY.md).

Commandes, depuis la racine du module, avec un dépôt Dolibarr voisin :

```powershell
php test/run.php ../dolibarr 20.0.0
php test/run.php ../dolibarr 21.0.0
php test/run.php ../dolibarr 22.0.0
php test/run.php ../dolibarr 23.0.2
php test/run.php ../dolibarr 24.0.1
php test/run.php ../dolibarr HEAD
git diff --check
```

`HEAD` doit être la révision alpha indiquée dans le tableau. La CI vérifie les
SHA des sources et ajoute une exécution PHP 8.0 / Dolibarr 20.0.0, en plus de la
matrice PHP 8.4 ci-dessus. Une simulation alpha ne valide pas une future v25 finale.

Contrats examinés : `htdocs/product/class/product.class.php`,
`htdocs/product/class/productcustomerprice.class.php`,
`htdocs/core/lib/price.lib.php`, réglages Produits/Services et fiches commerciales.
Le mode client et multiprices existe à partir de v21 ; sa configuration en v20
produit une indisponibilité contrôlée. Les dates de validité des tarifs clients
sont appliquées nativement à partir de v22 ; les versions antérieures conservent
leur comportement natif. La sélection du minimum de niveau pour quantité et
multiprices suit les champs utilisés par les fiches commerciales, car la méthode
native seule ne couvre pas entièrement ce cas.

## Scénarios couverts

- Reproduction du faux refus : minimum général 400, niveau Standard 275, règle
  350 ; acceptation Standard et refus du niveau dont le minimum vaut 400.
- Six modes, prix clients valides, expirés et futurs selon le contrat de version,
  replis natifs, minimum zéro, niveau absent et contributions du hook natif.
- Paliers natifs sélectionnés HT/TTC, palier inconnu ou appartenant à un autre
  niveau refusé ; aucun choix de palier fondé sur le seuil PriceList.
- Prix manuel conservé, zéro explicite, remise et prix fixe avec remise retenue,
  ajout et édition de lignes commerciales et de contrats.
- Actualisation des devis, commandes, factures, factures récurrentes et contrats,
  acceptation ou omission selon le montant final.
- Avertissements génériques par niveau, sauvegarde client, erreurs de lecture
  native, accès refusés et périmètres d'entités simulés.
- Précision unitaire native et non-régression des tests existants.

Les 20 fichiers PHP passent le contrôle de syntaxe. `composer validate --strict`
réussit. PHPStan n'a pas été exécuté : aucun exécutable ni configuration PHPStan
du module n'est disponible dans cet environnement. Aucun baseline ou ignore n'a
été ajouté. Les résultats de CI doivent être lus sur le SHA de tête de la PR.

## Recette réelle restant à effectuer

Sur une instance de test servant le commit de la PR :

1. Vérifier l'onglet À propos (version issue du descripteur : 2.3.1) et les
   avertissements des onglets produit, client et catégorie.
2. Reproduire 400 / 275 / 350 avec deux clients de niveaux différents ; vérifier
   les montants persistés après ajout, édition et actualisation des cinq types
   de documents, avec prix manuel, remise seule et prix fixe avec remise.
3. Tester les modes clients et quantité, leurs dates et paliers, les bases HT/TTC
   et les contributions des autres hooks installés. Les options quantité restent
   sous le contrôle de l'administrateur et ne sont pas activées par PriceList.
4. Tester les permissions et un produit partagé entre deux entités Multicompany,
   sans fuite ni changement d'entité propriétaire.
5. Vérifier conservation des réglages à la désactivation/réactivation et les
   contrôles CSRF natifs des formulaires existants.

Les droits, entités et hooks ont été simulés ; aucune configuration d'instance,
donnée métier ou notification réelle n'a été modifiée. Les parcours navigateur,
la persistance MySQL/MariaDB, Multicompany réel et PHPStan restent non exécutés.

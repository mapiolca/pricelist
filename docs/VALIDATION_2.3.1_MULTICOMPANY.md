# PriceList 2.3.1 — isolation des tarifs par entité

Correctif préparé le 22 octobre 2026, sans déploiement ni publication de release.
Base : `origin/main`, commit `41e6bef5a8f769d4604b8d70e29f21c1daaff9f6`.
Branche : `fix/pricelist-2.3.1-entity-isolation`.

## Cause et périmètre

Le résolveur `getPriceEntityCandidates()` ajoutait systématiquement l'entité
propriétaire du produit à celle du document. Un produit partagé pouvait donc
entraîner l'application d'un tarif étranger, ou son rejet pour minimum non
respecté, même sans partage des tarifs PriceList.

`get_price()` recherche désormais les tarifs uniquement dans l'entité du
document, après le contrôle existant de son accès. Sans document, il utilise
l'entité courante. Le helper de repli est supprimé. La priorité catégorie du
document / tiers / catégorie client / générique reste inchangée.

Le même résolveur est utilisé pour ajout, édition et actualisation des devis,
commandes, factures, factures récurrentes et contrats. Les écrans de gestion,
l'import et l'export utilisent déjà l'entité courante pour les tarifs : leur
contrat est conservé. Un document partagé accessible garde ses propres tarifs
et son contexte de coût ; le partage du produit ne change aucun propriétaire.
La session et `$conf->entity` ne sont jamais modifiés par la résolution.

Seul le résolveur métier change en production, avec la version du descripteur
et la documentation. Aucun SQL, migration, droit, hook déclaré, Agenda,
notification, document généré ou cron n'est ajouté ou modifié. Une réactivation
n'est pas nécessaire. À propos utilise déjà la version du descripteur.

Cette branche est indépendante de la PR #8 sur les modes de prix, encore ouverte
lors du préflight. Son contenu n'est pas intégré à ce correctif.

## Reproduction et résultats

Un scénario a été ajouté avant le patch : produit de l'entité A partagé avec B,
tarif présent seulement dans A et document de B. Le test exigeant l'absence de
tarif dans B échoue sur le code initial, puis réussit après suppression du repli.
Il s'agit d'une reproduction simulée, sans connexion à l'instance de l'incident.

Le banc exécute les méthodes PriceList avec base SQL, utilisateurs, produits,
catégories, documents et fournisseur de coûts simulés. Les fonctions natives de
normalisation monétaire et d'évaluation sont extraites en mémoire, sans copie ni
modification du core. Les filtres SQL de résolution sont observés ; les tables,
transactions et règles de partage réelles ne sont pas exécutées.

| Sources natives Dolibarr | Révision figée | Assertions réussies, PHP 8.4.22 |
|---|---|---:|
| 20.0.0 | `697bf01970740a3339cd99cf055b4428fc5e051c` | 157 |
| 21.0.0 | `fd970b582a4d8c5779a2958a4e9f4fce225cf085` | 157 |
| 22.0.0 | `49b9a6d19f3deb6d410c0e9b3310e95be4ea7710` | 165 |
| 23.0.2 | `ccef1102e6850b7545be7bad91cf0cc4c74ac6ea` | 169 |
| 24.0.1 | `b7958385f00a92219f76a4dfea67dae93df97a25` | 169 |
| 25.0.0-alpha | `d37fd9f3128ab8d52bd6399a2fe6ebbe7e65f62b` | 169 |

Les catégories de documents sont testées seulement lorsque leur capacité est
disponible dans la version simulée. L'alpha v25 ne valide pas une future finale.
La matrice CI ajoute PHP 8.0 / Dolibarr 20 ; elle vérifie les SHA des sources
avant exécution. Ses résultats doivent être rattachés au dernier commit de la PR.

Scénarios couverts par `test/entity_isolation.php` et `test/run.php` :

- Absence de contamination A vers B pour les cinq types de documents, y compris
  un tarif étranger inférieur au minimum ; aucune lecture des règles de A.
- Valeurs POST d'ajout/édition inchangées et aucune actualisation de ligne
  lorsqu'aucun tarif n'appartient à l'entité du document.
- Tarif local appliqué avec produit partagé ; tarif conservé dans A ; document
  partagé de A consulté depuis B conservant exclusivement les tarifs de A.
- Appel historique sans document limité à l'entité courante.
- Tarifs client, catégorie client et catégories de documents : isolement des
  tarifs étrangers et application des tarifs locaux, avec les deux priorités.
- Droits produit, produit non partagé et entité du document inaccessible refusés.
- Conservation du contexte de coût, précision monétaire native, import/export,
  métadonnées et autres scénarios existants du banc.

Commandes, depuis la racine du module avec un dépôt Dolibarr voisin :

```powershell
php test/run.php ../dolibarr 20.0.0
php test/run.php ../dolibarr 21.0.0
php test/run.php ../dolibarr 22.0.0
php test/run.php ../dolibarr 23.0.2
php test/run.php ../dolibarr 24.0.1
php test/run.php ../dolibarr d37fd9f3128ab8d52bd6399a2fe6ebbe7e65f62b
composer validate --strict
git diff --check
```

Les 19 fichiers PHP passent le lint ; Composer et le contrôle du diff passent.
PHPStan non exécuté : aucun exécutable ni configuration du module disponible.
Aucun ignore ni baseline ajouté. Aucun essai navigateur ou déploiement effectué.

## Recette réelle restante

Dans une instance de test servant le commit de la PR, relever les versions
Dolibarr, PHP et Multicompany, puis utiliser deux entités A et B :

1. Partager un produit de A vers B, sans partager les tarifs. Créer un tarif
   seulement dans A, puis vérifier son absence dans l'onglet PriceList de B.
2. Dans B, ajouter et éditer le produit sur devis, commande, facture, facture
   récurrente et contrat. Vérifier l'absence d'enrichissement PriceList étranger,
   de faux message de minimum et de modification à l'actualisation.
3. Créer un tarif distinct dans B : vérifier qu'il s'applique dans B et que celui
   de A continue de s'appliquer dans A, y compris les règles client/catégorie.
4. Si les documents sont partagés, consulter depuis B un document de A accessible
   et vérifier l'utilisation de ses tarifs de A ; refuser un document inaccessible.
5. Tester utilisateur avec droits partiels et partage produit global puis par
   élément. Vérifier conservation de l'entité authentifiée, des données et des
   réglages, ainsi que l'affichage 2.3.1 dans À propos.

La persistance MySQL/MariaDB, les contrôles CSRF réels, le partage individuel et
Multicompany réel restent à recetter. Aucun effet externe n'a été déclenché.

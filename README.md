# PriceList 2.3.1

Module Dolibarr permettant de gérer des tarifs dégressifs par quantité.

Il inclut la gestion des prix de revient afin de suivre le coût par paliers de quantités.

Les tarifs peuvent être ciblés par tiers, catégorie client, ou tags/catégories de documents commerciaux. Les tags/catégories de devis, commandes, factures et contrats peuvent être regroupés sur une même ligne de tarif ; les tiers et catégories client restent des périmètres séparés.

Lors de l'application sur une ligne, la priorité par défaut est : tags/catégories de l'objet courant, tiers, catégorie client, puis tarif générique du produit. Le réglage `PRICELIST_DOCUMENT_CATEGORY_PRIORITY` permet d'inverser la priorité entre le groupe objet et le groupe tiers/client.

Les tarifs dégressifs sont contrôlés contre le prix minimum natif applicable au client et au mode de prix actif. Les niveaux incompatibles sont signalés dans l'onglet ; un tarif générique reste enregistrable, puis les hooks refusent son application dans les contextes incompatibles.

Chaque ligne propose un Select2 **Source du prix de revient** :

- `custom` — montant personnalisé ; seul ce mode autorise la saisie du montant.
- `product` — prix de revient natif du produit (`Product::cost_price`).
- `dynamicprices` — coût courant valide fourni par le module optionnel **DynamicsPrices**.

Un montant nul au sens numérique (`0`) est valide. Une valeur absente, non numérique ou non finie n’est pas un coût exploitable. Les montants unitaires suivent `price2num(..., 'MU')`.

La source DynamicPrices nécessite l’activation de DynamicsPrices et de ses coûts (`DYNAMICPRICES_COST_ENABLE`), une version exposant `DynamicPricesCostService::getDynamicCostPrice()` avec l’option `require_success`, le droit `dynamicsprices / cost / read` et l’accès au produit/service. Les dépendances indisponibles sont indiquées dans **Réglages → Compatibilité**. Le chargement utilise le chemin logique Dolibarr `/dynamicsprices/`.

PriceList lit le coût existant sans recalcul ni écriture dans DynamicsPrices. Il ne passe pas par le résolveur de priorités de DynamicsPrices, qui peut lui-même consulter PriceList. Le réglage `DYNAMICPRICES_COST_USE_FOR_SALES` n’est pas requis. Si le coût manque, est inaccessible ou correspond à un calcul en erreur, le coût de la ligne commerciale est conservé ; le prix de vente ou la remise du tarif reste appliqué et une alerte est affichée. À l’ajout, les valeurs de coût déjà proposées par le formulaire natif restent inchangées.

L’entité du document détermine le coût commercial, même si le tarif et le produit appartiennent à une autre entité partagée. Dans les écrans tarifaires, l’entité de consultation est utilisée. `get_price()` transmet `cost_context_entity` et `cost_context_element` dans son résultat : les intégrations existantes peuvent continuer à appeler `getEffectiveCostPriceForRow($row)` avec un seul argument. Ces informations sont transitoires et ne sont jamais enregistrées dans le tarif.

Si l’automatisation des ventes de DynamicsPrices est également active, ses propres choix explicites et priorités restent applicables après les hooks PriceList. PriceList ne modifie pas cette configuration. La lecture directe empêche une boucle de résolution ; la coexistence complète des deux modules doit être vérifiée sur l’instance cible.

Les lignes de tarif sont éditables depuis l'onglet des prix dégressifs. Chaque création et modification alimente un historique consultable en infobulle avec la date, la nouvelle valeur, l’écart et l’utilisateur. Pour les sources produit et DynamicPrices, l’historique affiche la source choisie ; il ne présente jamais le coût courant comme un montant historique enregistré.

Lorsque l'option **Afficher les prix TTC** est active, le formulaire permet de saisir un prix HT ou TTC. Le module conserve le prix HT comme source de vérité.

Les tags/catégories de devis utilisent le support natif Dolibarr disponible à partir de Dolibarr v23. Les tags/catégories commandes et factures utilisent le support natif disponible à partir de Dolibarr v22. Les tags/catégories contrats sont fournis par PriceList et exploités uniquement lorsque l'option `PRICELIST_ENABLE_CONTRACT_CATEGORIES` est active.

## Navigation et réglages

**Tarifs dégressifs** est un onglet natif unique, placé après les onglets transverses des produits et services. Il utilise les droits de lecture du type concerné, ou son droit avancé de lecture des prix lorsque les permissions avancées sont activées. La modification et la suppression exigent le droit de création/modification du produit ou service. Aucun rôle administrateur ne remplace ces permissions.

**Réglages → À propos**, après **Compatibilité**, affiche les métadonnées du descripteur, les fonctionnalités et les liens utiles. `setup.php` reste la seule entrée de configuration depuis la liste des modules.

## Mise à jour vers 2.3.1

Le contrôle du minimum suit le mode natif activé dans Produits/Services : prix unique, niveaux clients, prix par client, client + niveaux, quantité ou quantité + niveaux. Un minimum nul reste valide. Les dates des tarifs clients sont respectées à partir de v22 ; le mode client + niveaux nécessite v21. Les modes natifs par quantité restent expérimentaux lorsqu'ils sont présentés ainsi par Dolibarr.

Un tarif générique ou par catégorie peut être enregistré même s'il est incompatible avec certains niveaux : l'écran indique les niveaux concernés et le contrôle définitif utilise le client de la ligne. Les prix clients et les paliers ne sont pas inventés dans un écran sans ce contexte. Un palier natif sélectionné doit appartenir au produit et au niveau du client.

Une remise Pricelist conserve le prix unitaire saisi ou déjà enregistré. Le contrôle compare le prix final après remise au minimum applicable, y compris lorsque le tarif fixe conserve une remise existante. Si le minimum ne peut pas être vérifié, l'enrichissement Pricelist est omis avec un message ; le parcours natif conserve ses propres contrôles.

Aucune migration SQL ni réinitialisation des réglages n'est nécessaire pour ce correctif. L'onglet À propos lit automatiquement la version 2.3.1 depuis le descripteur. Voir [la validation 2.3.1](docs/VALIDATION_2.3.1.md) pour les scénarios exécutés et la recette restante. Les instructions de migration 2.3.0 restent applicables aux installations plus anciennes.

## Mise à jour vers 2.3.0

Socle déclaré inchangé : **Dolibarr 20+ / PHP 8.0+**, MySQL/MariaDB. Version préparée pour revue, sans publication de release.

Après copie des fichiers, réactiver PriceList dans l’administration native pour exécuter la migration et renouveler les déclarations d’onglets/hooks. La migration ajoute `cost_price_source` aux tarifs et à leur historique. Les anciennes valeurs `use_product_cost_price=1` deviennent `product` et les autres `custom`. Le marqueur SQL `NULL` rend une migration interrompue rejouable ; une source déjà migrée reste inchangée. Les anciens montants et historiques sont conservés. Aucun index nouveau ni réglage réinitialisé.

Une correction du descripteur nécessite également cette désactivation/réactivation si 2.3.0 est déjà installée : Dolibarr enregistre les déclarations d’onglets en base. La condition corrigée évite notamment `$user->socid`, rejeté par l’évaluateur de Dolibarr 23.0.2 ; le contrôle des utilisateurs externes reste effectué directement dans la page PHP.

`cost_price_source` est l’unique valeur métier faisant autorité. La colonne historique `use_product_cost_price` reste une projection de compatibilité (`1` pour `product`, `0` autrement). Les nouveaux appels objet doivent renseigner `cost_price_source`. Une ancienne entrée ne comportant que le booléen est convertie à l’entrée ; modifier uniquement ce booléen sur un objet déjà chargé ne remplace pas sa source explicite.

Les imports natifs CSV/XLSX acceptent `custom`, `product`, `dynamicprices`, ainsi que les anciennes correspondances du booléen. Une source explicite est prioritaire ; les champs omis lors d’une mise à jour restent conservés. Les produits, tiers et auteurs acceptent les identifiants/références natifs (`id:…`, `ref:…`). L’import passe par les validations et l’historique de l’objet ; la simulation native conserve sa transaction de rollback et supprime les effets des triggers. L’export fournit la source enregistrée et le montant personnalisé, sans exporter un coût dynamique courant comme un instantané.

Les coûts sont appliqués lors de l’ajout, de la modification et de l’actualisation des lignes des devis, commandes, factures, factures récurrentes et contrats. La modification réévalue le tarif même si la quantité reste inchangée.

## Vérifications de développement

Le manifeste Composer déclare PHP 8.0 minimum, sans dépendance externe : Dolibarr fournit l’environnement d’exécution du module. Le workflow GitHub valide le manifeste et son fichier de verrouillage, exécute l’installation Composer puis vérifie la syntaxe des fichiers PHP suivis par Git.

```sh
composer validate --strict
composer install --prefer-dist --no-progress --no-interaction
```

Les tests métier ciblés nécessitent un checkout des sources Dolibarr :

```sh
php test/run.php /chemin/vers/dolibarr 20.0.0
php test/run.php /chemin/vers/dolibarr 21.0.0
php test/run.php /chemin/vers/dolibarr 22.0.0
php test/run.php /chemin/vers/dolibarr 23.0.2
php test/run.php /chemin/vers/dolibarr 24.0.1
php test/run.php /chemin/vers/dolibarr HEAD
php test/run.php /chemin/vers/dolibarr 20.0.0 missing
php test/run.php /chemin/vers/dolibarr 20.0.0 old
```

Ces tests utilisent des fonctions et méthodes natives extraites du checkout Dolibarr, en mémoire et dans des fichiers d'inclusion temporaires sous `.cache/pricing-*`, avec une base, des objets et un fournisseur simulés. Ils ne remplacent pas une recette sur instance. Voir [la validation 2.3.1](docs/VALIDATION_2.3.1.md) pour les preuves et les essais restant à exécuter.

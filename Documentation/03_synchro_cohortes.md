# Synchronisation des cohortes

On se sert du plugin `local_cohortsyncup1` développé pour l'occasion.
Comme pour le ldap, un script est disponible pour être lancé par cron, dans `local/cohortsyncup1/cli/sync_cohorts.php`.

Les tables `cohort` et `cohort_members` sont renseignées.
Une table supplémentaire, `up1_cohortsync_log` est utilisée pour journaliser les synchronisations des cohortes *et des utilisateurs*.

Depuis 2014, trois champs supplémentaires sont ajoutés à la table `cohort`
pour gérer l'annualisation des cohortes : **up1key**, **up1category** et **up1period**.

La synchronisation exploite les requêtes `userGroupsAndRoles` et `allGroups` du **webservice PAGS**.
Les urls sont stockées dans la configuration du plugin `local_cohortsyncup1`.

Les champs suivants sont lus dans les webservices avec les correspondances en termes de cohortes :

*  `key` -> idnumber et up1key
*  `name` -> name
*  `description` -> description
*  `modifyTimestamp` (WS allGroups seulement) : utilisé pour la mise à jour des cohortes
*  `rawKey` : ignoré
*  `role` : ignoré

Les champs `timecreated` et `timemodified` sont mis à jour si nécessaire dans la table `cohort`.


## Annualisation des cohortes

### Initialisation

Pour la rotation annuelle il faut mettre à jour le paramètre `cohort_period` puis lancer, **manuellement et une seule fois**

```
php sync_cohorts.php --yearly-rotate
```

### Théorie

1) Certaines cohortes sont *annualisées*, ie changent entièrement d'une année universitaire sur l'autre, d'autres pas.
La difficulté est que les cohortes annualisées gardent la même clé annuaire (champ `key` du webservice),
alors que dans Moodle, nous devons garder les versions successives de ces cohortes.

2) La synchronisation quotidienne doit se borner aux *cohortes non annualisées* et à celles *annualisées de l'année courante*.
Les autres constituent des archives.

3) Les cohortes annualisées sont celles des 4 catégories : **elp**, **diploma**, **gpelp**, **gpetp**,
définies à partir de la clé (key) par la fonction groupKeyToCategory() de `local_mwsgroups`.

4) l'année courante correspond au début de la période universitaire (ex. 2013 pour 2013-2014)
et doit être renseignée manuellement dans le paramètre `cohort_period` du plugin `local_cohortsyncup1`.

5) en conséquence, la période est toujours implicite dans les groupes annualisées (côté webservice),
mais on doit la rendre explicite (préfixe [YYYY] dans *name* et suffixe -YYYY dans *idnumber*) pendant la synchronisation.

### Structure

On ajoute trois champs à la table cohort :

   * **up1period**, la période/année (ex. 2013) isolée **si la cohorte est annualisée**, vide sinon
   * **up1category**, la catégorie de cohorte parmi (gpelp, gpetp, elp, diploma, structures, affiliation, other)
   * **up1key**, contient exactement l'attribut *key* du webservice pour les cohortes synchronisées, vide pour les autres.

Le champ *up1key* est unique s'il est non vide. C'est la référence pour les synchronisations 
(dans les premières versions du plugin c'était *idnumber*). 

Les champs *up1period* et *up1category* sont des auxiliaires pour les statistiques
et permettent d'accélérer certains traitements (tout en SQL, sans passer par des fonctions php).


### Fonctionnement

Les quatre champs *idnumber, up1key, up1period, up1category* sont initialisés à la création de la cohorte
et ne changent plus jusqu'à la prochaine rotation annuelle, où *up1key* peut être vidé.

À *chaque synchronisation*, le paramètre période courante est comparée à la valeur précédente :
s'il a changé (ex. de 2013 à 2014), les champs *up1key* sont vidés pour les cohortes annualisées concernées (2013).
La nouvelle synchronisation laisse donc ces enregistrements inchangés, et importe de nouvelles cohortes (ex. 2014).

## Exécution

Le processus est lancé quotidiennement par une tâche planifiée.

```
Synchroniser les cohortes UP1  \local_cohortsyncup1\task\synchronize_cohorts  (local_cohortsyncup1)
```

Elle est normalement lancée **après** la synchronisation des utilisateurs par `auth_ldapup1`.


### Options d'exécution

Le script a deux modes de fonctionnement :

1.  `--sync-from-users` qui retrouve les cohortes à partir des utilisateurs et se base sur le webservice **userGroupsAndRoles**.
Il remplit `cohort` et `cohort_members`.
2.  `--sync-all-groups` , qui récupère toutes les cohortes, synchronise les actives et supprime les anciennes
en se basant sur le webservice **allGroups**. Il ne remplit que `cohort`.

Pour les options communes, l'exécution manuelle permet plusieurs réglages,
notamment de verbosité `--verbose=` (0 à 3) et de date de référence `--since=` (timestamp unix).
En particulier, l'option `--init` permet de prendre en considération tous les utilisateurs et toutes les cohortes,
indépendamment de leur date de dernière modification.

#### Options spéciales

L'option `--delete-old` permet de forcer la suppression des anciennes cohortes (présentes dans la base mais absentes du webservice).
Les cohortes utilisées pour des inscriptions sont conservées (`kept`), les autres sont effectivement supprimées de la base (`deleted`).
Ces totaux sont affichés, selon le niveau de verbosité.

Pour plus de détails, cf. ``php local/cohortsyncup1/cli/sync_cohorts.php --help``

L'option `--fix-sync` permet de mettre à jour les utilisateurs "fantômes", présents dans la table **user** mais pas dans **user-sync**.
Avec l'option `--dryrun`, elle ne fait qu'un diagnostic, sans modifier la base.


### Tests et statistiques

#### Webservice

L'option `--test-ws` permet de vérifier la connexion au webservice et la récupération des données, sans aucune modification de la base.
Elle est compatible avec les niveaux de verbosité 1 (par défaut) à 3.
À partir du niveau 2, la sortie d'information de curl est également affichée, pour déboguer les problèmes de type *timeout*.

#### Cohérence de la base

L'option `--check` permet de vérifier certaines contraintes d'intégrité de la base.
En particulier utile pour le passage à l'annualisation.

#### Statistiques

L'option `--statistics` permet de connaître les statistiques des cohortes, par période et par catégorie.
Utile pour vérifier les changements d'année.

## Forcer une synchro cohortes/inscriptions

En cas de restauration de la table `cohort_members`, l'[évènement](http://docs.moodle.org/dev/Events_API#Cohorts) n'est pas déclenché,
il faut donc forcer le calcul des inscriptions individuelles par l'appel au script `moodle/enrol/cohort/cli/sync.php`.

Sinon, le cron Moodle relance ce calcul toutes les heures (moodle/enrol/cohort/lib.php::cron()).
Le délai est réglable dans `enrol/cohort/version.php`, paramètre `$plugin->cron = 60*60;`


## Références

* [M1208](http://tickets.silecs.info/mantis/view.php?id=1208) 
* [M1209](http://tickets.silecs.info/mantis/view.php?id=1209) 
* [M1210](http://tickets.silecs.info/mantis/view.php?id=1210)


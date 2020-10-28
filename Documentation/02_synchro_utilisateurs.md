# Connecteur utilisateurs

On se sert du plugin `auth_ldapup1`, une variante allégée du plugin LDAP de Moodle, qui fournit uniquement la synchronisation, et pas d'authentification.

L'installation de ce plugin crée la table `user_sync` qui stocke l'origine de chaque utilisateur (manuel, ou synchro, et dans ce cas, selon quel référentiel). 
Cette donnée est mise à jour par la synchronisation des utilisateurs et utilisée par la synchronisation des cohortes.

La principale table mise à jour est `user`.
En standard (configurable en appariement des données), le champ `username` correspond à l'attribut Ldap `edupersonPrincipalName`.

Les champs de profil personnalisés (table `custom_info_data`, objet `user`) pourraient également être impactés, mais ce n'est pas nécessaire pour les besoins actuellement exprimés.

## Configuration Moodle pour LDAP

Menu Administration du site ► Plugins ► Authentification ► Serveur LDAP

Les champs à renseigner sont :

*  URL du serveur 	`[...]`
*  Contextes  `[ou=people,dc=univ-paris1,dc=fr]`
*  Attribut utilisateur 	`[eduPersonPrincipalName]` (précise uid avec le domaine Shibboleth @univ-paris1.fr)
*  Classe objet `[objectClass=up1Person]` **(non utilisé pour l'instant, en dur dans le code)**
*  **Condition de synchronisation** (spécifique) `[modifyTimestamp>=[%lastcron%]]` **(non utilisé pour l'instant, en dur dans le code)**
*  Appariement des données / Data Mapping
   * Prénom `[givenName]`
   * Nom `[sn]`
   * Adresse de courriel `[mail]`
   * Numéro d'identification (idnumber) `[supannEtuId]` + Verrouillage = `Verrouillé` (code Apogée)

### Activation / désactivation

Tous les utilisateurs disponibles dans l'annuaire LDAP sont créés dans Moodle.
On utilise ensuite l'état `accountStatus` :

*  'active' : l'utilisateur est créé / mis à jour normalement, et réactivé le cas échéant
*  'disabled' : l'utilisateur est passé en suspendu (suspended=1 + auth=nologin)
*  (non défini) : traité comme 'active'

Aucun utilisateur n'est effacé de la base ni passé en état supprimé (`deleted=1`) automatiquement.

## Exécution

### Cron

La commande est lancée normalement par cron, avec un comportement incrémental : 
les utilisateurs dont l'attribut ldap *modifyTimestamp* est plus récent que la dernière synchronisation sont pris en compte. 
La ligne de cron enchaîne préférentiellement les scripts ldapup1 et cohortsyncup1, sur le modèle :

```
`20  *  *   *   *    cd /home/moodle/www/moodle && php auth/ldapup1/cli/sync_users.php && sleep 2 && php local/cohortsyncup1/cli/sync_cohorts.php`
```

### Manuelle

On peut forcer le script à ignorer l'attribut modifyTimestamp en lui passant l'option `--init` : 

```
cd /home/moodle/www/moodle && php auth/ldapup1/cli/sync_users.php --init
```

D'autres options (verbosité, date de référence, sortie...) sont disponibles, cf. l'aide en ligne de l'option `--help`.
Si la sortie choisie `--output=file`, le script met à jour le fichier `sync_users.log` avec les actions effectuées sur chaque utilisateur modifié.


## Références

* [M1205](http://tickets.silecs.info/mantis/view.php?id=1205) 
* [M1206](http://tickets.silecs.info/mantis/view.php?id=1206) 
* [M1207](http://tickets.silecs.info/mantis/view.php?id=1207)


# Connecteurs aux référentiels utilisateurs

## Aperçu général

Les connecteurs utilisateurs créent 3 types d'objet dans Moodle :

1. les utilisateurs (import et synchronisation), à partir de l'annuaire LDAP
2. les cohortes (import et synchronisation), à partir du webservice *groups/userGroupsAndRoles* [exemple](http://ticetest.univ-paris1.fr/web-service-groups/userGroupsAndRoles?uid=e0g411g01n6)
3. les appartenances utilisateur/cohortes, à partir du webservice *groups/userGroupsAndRoles*

En début de développement (juin 2012), la synchronisation était prévue pour toujours partir des utilisateurs, sans interroger les groupes institutionnels. Cette méthode a été rapidement abandonnée (octobre 2012), pour permettre d'inscrire aux cours des cohortes qui existaient mais n'étaient pas encore peuplées.

Le travail est réparti entre deux scripts, détaillés dans les pages suivantes :

 1.  [synchro utilisateurs](synchro utilisateurs) (LDAP)
 2.  [synchro cohortes](synchro cohortes) (webservice groupes PAGS)


## Statistiques

Pour le diagnostic, Silecs a développé le plugin `report_up1userstats`, accessible dans *Administration du site > Rapports > UP1 Users statistics*.

Il affiche les nombres d'utilisateurs, de cohortes et d'appartenances issues des connecteurs (LDAP et PAGS), ainsi que la répartition des cohortes en types et les 10 cohortes les plus peuplées.

Il indique aussi la date de la dernière synchronisation, pour les deux plugins. Ces informations sont stockées dans la table `up1_cohortsync_log`, avec les actions `sync:begin` et `sync:end`.

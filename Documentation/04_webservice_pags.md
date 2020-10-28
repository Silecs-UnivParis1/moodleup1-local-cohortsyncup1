# Webservice PAGS

Le webservice PAGS est fourni par l'UP1 comme surcouche au LDAP. Il permet une recherche dans les groupes et les utilisateurs.


## Actions disponibles

### search

Renvoie tous les groupes ET les utilisateurs correspondant à l'expression. 

Exemple <http://wsgroups.univ-paris1.fr/search?token=TRUC&maxRows=2>

### userGroupsAndRoles

Renvoie tous les groupes dont fait partie l'utilisateur requêté, plus son rôle (non renseigné ?).

Exemple <http://wsgroups.univ-paris1.fr/userGroupsAndRoles?uid=e0g411g01n6>

### userGroupsId

Renvoie tous les groupes dont fait partie l'utilisateur requêté.

Exemple <http://wsgroups.univ-paris1.fr/userGroupsId?uid=e0g411g01n6>


## Webservice interne

Pour pouvoir utiliser le même widget de sélection, Silcs a intégré 
un webservice interne émulant partiellement le webservice officiel 
(actions `userGroupsId` et `search`) dans le plugin `local_mwsgroups`.

URL du webservice :

*  <https://moodle-test.univ-paris1.fr/local/mwsgroups/service-search.php?token=riga>
*  <https://moodle-test.univ-paris1.fr/local/mwsgroups/service-userGroups.php?uid=prigaux%40univ-paris1.fr>


## Widget client

Page de démonstration du widget basé sur le webservice UP1 :
<https://moodle-test.univ-paris1.fr/local/widget_groupsel/groupsel-demo.php>

La page HTML intégrant ce widget doit contenir une ligne :
```
<div class="by-widget group-select">
```

Même widget basé sur le webservice interne :
<https://moodle-test.univ-paris1.fr/local/widget_groupsel/groupselint-demo.php>

La page appelante doit contenir une ligne :
```
<div class="by-widget group-select group-select-internal">
```




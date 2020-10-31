# Mémo technique Moodle standard

Indépendamment de notre plugin, il y a deux façons d'inscrire des cohortes **à un cours** dans Moodle :

1. individuellement ("enrol users")
2. par cohorte ("enrol cohorts") si le plugin [Cohort sync](http://docs.moodle.org/22/en/Cohort_sync) est activé.

Le plugin `cohortsyncup1` ne s'occupe pas de cette partie de l'opération, qui reste entièrement manuelle.
C'est toujours la deuxième méthode ("enrol cohorts") qui doit être utilisée.


## Inscription individuelle (déconseillée)

Une fois inscrite, l'appartenance à la cohorte est "oubliée" pour le cours.
Pour chaque utilisateur inscrit, un enregistrement est créé dans 2 tables : `role_assignments` (RA) et `user_enrolments` (UE).
Dans UE, l'enregistrement référence (`enrolid`) la table `enrol`, avec la méthode `manual`.


## Inscription par cohorte

l'information de la cohorte d'origine est conservée.
Il y a toujours un enregistrement ajouté par utilisateur dans RA et UE mais à la différence de ce qui précède

1.  un enregistrement global est aussi ajouté à la table `enrol` avec : `enrol='cohort', customint1=(cohortid)`
2.  c'est cet enregistrement qui est référencé dans `user_enrolments` (`UE.enrolid = enrol.id`)
3.  dans la table `role_assignments` on a `component="enrol_cohort"` et `itemid = (enrol.id)`

# Registre des traitements et procédure de violation de données

Responsable du traitement : Jean-Christophe SANNA, contact@gta5-online.fr.

## Traitements

| Traitement | Données | Base légale | Destinataires | Conservation |
|---|---|---|---|---|
| Comptes et service | Nom, prénom, pseudo, mail, mot de passe haché, langue, groupe, dates, possessions, braquages, cibles de prime | Exécution du contrat | Infomaniak (Suisse) | Anonymisation après 3 ans d'inactivité (mail 30 jours avant) ; 30 jours si mail non validé |
| Sécurité des connexions | IP et date des connexions, IP et identifiant saisi lors des échecs | Intérêt légitime | Administrateurs, Infomaniak | 1 an |

## Exercice des droits

- Export et suppression en libre-service : page « Mon compte » (`/user/account`).
- Rectification et autres demandes : contact@gta5-online.fr, réponse sous un mois.
- Une suppression anonymise la ligne `user` et efface les données rattachées.

## Tâches planifiées à garder actives en production

- `php bin/console users:purge` (quotidien)
- `php bin/console logins:remove-old` (quotidien)

## Violation de données

1. Contenir l'incident, puis noter les faits, les données et les personnes touchées, et les mesures prises : garder cette trace même sans notification.
2. S'il existe un risque pour les personnes : notifier la CNIL sous 72 heures après en avoir pris connaissance (notifications.cnil.fr).
3. Si le risque est élevé : prévenir aussi les personnes concernées sans tarder.

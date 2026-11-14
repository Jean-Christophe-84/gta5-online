<?php

namespace App\Helper;

use App\Entity\User;
use DateMalformedStringException;
use DateTime;
use Doctrine\DBAL\Exception as DoctrineDBALException;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

readonly class UserHelper
{
    const string ANONYMIZED_DOMAIN     = 'anonymized.invalid';
    const string INACTIVITY_LIMIT      = '3 years';
    const string INACTIVITY_WARNING    = '30 days';
    const int MAX_ATTEMPTS             = 3;
    const int MAX_FIELD_LENGTH         = 255;
    const int PASSWORD_MIN_LENGTH      = 12;
    const int PERSONAL_DATA_MIN_LENGTH = 3;
    const string RESET_PASSWORD        = 'reset_password';
    const string UNVERIFIED_LIMIT      = '30 days';
    const string VERIFY_MAIL           = 'verify_mail';

    const array USER_TABLES = [
        'bounty_target_user', 'heist_casino', 'heist_cayo_perico', 'heist_kortz_center_heist_kortz_center_secondary_loot', 'heist_kortz_center',
        'login_success', 'property_user', 'vehicle_user', 'weapon_user'
    ];

    const array LINKS = [
        self::RESET_PASSWORD => [1, 'reset_password_user', 'Email/reset_password.html.twig', 'reset_password_mail_subject'],
        self::VERIFY_MAIL    => [48, 'verify_mail_user', 'Email/verify_mail.html.twig', 'verify_mail_mail_subject']
    ];

    public function __construct(
        private ConfigurationHelper   $configurationHelper, private EntityManagerInterface $entityManager, private MailerInterface $mailer,
        private ParameterBagInterface $parameterBag, private RateLimiterFactoryInterface $accountMailRecipientLimiter, private RequestStack $requestStack,
        private Security              $security, private UrlGeneratorInterface $urlGenerator
    ) {
    }

    /**
     * Efface les données personnelles du compte, sans supprimer sa ligne : created_by et updated_by de tout le site y font référence
     *
     * @throws DoctrineDBALException
     */
    public function anonymize(User $user): void
    {
        $connection = $this->entityManager->getConnection();
        $id         = $user->id;

        foreach (self::USER_TABLES as $table) {
            $connection->executeStatement('DELETE FROM ' . $table . ' WHERE user_id = :id', ['id' => $id]);
        }

        $connection->executeStatement(
            'DELETE FROM login_error WHERE email IN (:mail, :username)', ['mail' => $user->getMail(), 'username' => $user->getUsername()]
        );
        $connection->executeStatement(
            "UPDATE user SET lastname = :name, firstname = :name, mail = :mail, username = :username, password = :password, salt = NULL, active = 0,
             roles = :roles, last_activity = NULL, verify_mail = 0, verify_code = '', verify_code_expires_at = NULL, verify_attempts = 0, personal_datas = 0,
             inactivity_warned_at = NULL WHERE id = :id",
            [
                'id'       => $id,
                'mail'     => 'user-' . $id . '@' . self::ANONYMIZED_DOMAIN,
                'name'     => 'anonymized',
                'password' => bin2hex(random_bytes(32)),
                'roles'    => json_encode(['ROLE_USER']),
                'username' => 'anonymized-' . $id
            ]
        );

        $this->entityManager->refresh($user);
    }

    /**
     * Vérifie le code d'un lien : au bout de MAX_ATTEMPTS erreurs, le code est invalidé et il faut redemander un lien.
     * Un code correct reste valable : l'appelant l'invalide une fois l'action réussie (un mot de passe refusé peut être corrigé avec le même lien)
     */
    public function checkCode(User $user, string $code): bool
    {
        if ($user->getVerifyCode() === '' || !$user->getVerifyCodeExpiresAt() || $user->getVerifyCodeExpiresAt() < new DateTime()) {
            return false;
        }

        if (hash_equals($user->getVerifyCode(), hash('sha256', $code))) {
            return true;
        }

        $user->setVerifyAttempts($user->getVerifyAttempts() + 1);

        if ($user->getVerifyAttempts() >= self::MAX_ATTEMPTS) {
            $this->invalidateCode($user);
        }

        $this->entityManager->flush();

        return false;
    }

    public function getFilter(bool $defaultFilter = false)
    {
        if ($defaultFilter || !$filter = $this->requestStack->getSession()->get('user')) {
            return [
                'groupGtaId' => 0,
                'active'     => null,
                'role'       => '0',
                'search'     => '',
                'page'       => 1,
                'order'      => ['user-firstname' => 'ASC', 'user-lastname' => 'ASC']
            ];
        }

        return $this->keepPageInRange($filter);
    }

    /**
     * @throws DoctrineDBALException
     */
    public function getFilterActives(array $filter): array
    {
        $hasValues = false;

        $query = "
            SELECT user.active, COUNT(DISTINCT user.id) AS numberUsers
            FROM user
            LEFT JOIN group_gta ON user.group_gta_id = group_gta.id
            LEFT JOIN group_gta_locale ON group_gta_locale.group_gta_id = group_gta.id AND group_gta_locale.locale_id = :localeId
        ";

        if ($filter['groupGtaId'] !== 0) {
            $query     .= " WHERE group_gta.id = :groupGtaId";
            $hasValues = true;
        }

        if ($filter['role'] !== '0') {
            $query .= $hasValues ? " AND user.roles LIKE :role" : " WHERE user.roles LIKE :role";
        }

        $query .= " GROUP BY user.active ORDER BY user.active";

        $statement = $this->entityManager->getConnection()->prepare($query);
        $statement->bindValue('localeId', $this->security->getUser()->getLocale()->id);

        if ($filter['groupGtaId'] !== 0) {
            $statement->bindValue('groupGtaId', $filter['groupGtaId']);
        }

        if ($filter['role'] !== '0') {
            $statement->bindValue('role', '%' . $filter['role'] . '%');
        }

        return $statement->executeQuery()->fetchAllAssociative();
    }

    /**
     * @throws DoctrineDBALException
     */
    public function getFilterGroupGtaIds(array $filter): array|int
    {
        $query = "
            SELECT group_gta_locale.name, user.id, COUNT(DISTINCT user.id) AS numberUsers
            FROM user
            LEFT JOIN group_gta ON user.group_gta_id = group_gta.id
            LEFT JOIN group_gta_locale ON group_gta_locale.group_gta_id = group_gta.id AND group_gta_locale.locale_id = :localeId
            WHERE group_gta.id IS NOT NULL
        ";

        if ($filter['active'] !== null) {
            $query .= " AND user.active = :active";
        }

        if ($filter['role'] !== '0') {
            $query .= " AND user.roles LIKE :role";
        }

        $query .= " GROUP BY group_gta.id ORDER BY group_gta_locale.name";

        $statement = $this->entityManager->getConnection()->prepare($query);
        $statement->bindValue('localeId', $this->security->getUser()->getLocale()->id);

        if ($filter['active'] !== null) {
            $statement->bindValue('active', $filter['active']);
        }

        if ($filter['role'] !== '0') {
            $statement->bindValue('role', '%' . $filter['role'] . '%');
        }

        return $statement->executeQuery()->fetchAllAssociative();
    }

    /**
     * @throws DoctrineDBALException
     */
    public function getFilterRoles(array $filter): array|int
    {
        $hasValues = false;

        $query = "
            SELECT user.roles, user.id, COUNT(DISTINCT user.id) AS numberUsers
            FROM user
            LEFT JOIN group_gta ON user.group_gta_id = group_gta.id
            LEFT JOIN group_gta_locale ON group_gta_locale.group_gta_id = group_gta.id AND group_gta_locale.locale_id = :localeId
        ";

        if ($filter['groupGtaId'] !== 0) {
            $query     .= " WHERE group_gta.id = :groupGtaId";
            $hasValues = true;
        }

        if ($filter['active'] !== null) {
            $query .= $hasValues ? " AND user.active = :active" : " WHERE user.active = :active";
        }

        $query .= " GROUP BY user.roles ORDER BY user.roles";

        $statement = $this->entityManager->getConnection()->prepare($query);
        $statement->bindValue('localeId', $this->security->getUser()->getLocale()->id);

        if ($filter['groupGtaId'] !== 0) {
            $statement->bindValue('groupGtaId', $filter['groupGtaId']);
        }

        if ($filter['active'] !== null) {
            $statement->bindValue('active', $filter['active']);
        }

        return $statement->executeQuery()->fetchAllAssociative();
    }

    /**
     * Règles d'un nouveau mot de passe (mêmes règles que isValidPassword() côté JS) : longueur, une majuscule, une minuscule, un chiffre et un caractère spécial
     * (ni lettre, ni chiffre, ni espace). Il ne doit pas non plus contenir le nom, le prénom, le pseudo ou le début du mail (avant le @) : chaque mot
     * d'au moins PERSONAL_DATA_MIN_LENGTH caractères est cherché sans tenir compte des accents ni de la casse
     *
     * @throws DoctrineDBALException
     */
    /**
     * @throws DoctrineDBALException
     */
    public function getInactiveUsersToAnonymize(): array
    {
        return $this->findUsers(
            'COALESCE(last_activity, created_at) < :limit AND inactivity_warned_at IS NOT NULL AND inactivity_warned_at < :warned',
            ['limit' => $this->getDate(self::INACTIVITY_LIMIT), 'warned' => $this->getDate(self::INACTIVITY_WARNING)]
        );
    }

    /**
     * @throws DoctrineDBALException
     */
    public function getInactiveUsersToWarn(): array
    {
        $limit = new DateTime('- ' . self::INACTIVITY_LIMIT)->modify('+ ' . self::INACTIVITY_WARNING)->format('Y-m-d H:i:s');

        return $this->findUsers(
            'COALESCE(last_activity, created_at) < :limit AND (inactivity_warned_at IS NULL OR inactivity_warned_at < COALESCE(last_activity, created_at))',
            ['limit' => $limit]
        );
    }

    public function getPasswordMessage(string $password, string $lastname, string $firstname, string $username, string $mail): ?string
    {
        if (mb_strlen($password) > self::MAX_FIELD_LENGTH) {
            return ucfirst($this->configurationHelper->getTranslateLocale('the_fields_cannot_exceed_characters', ['count' => self::MAX_FIELD_LENGTH]));
        }

        if (
            mb_strlen($password) < self::PASSWORD_MIN_LENGTH || !preg_match('/\p{Lu}/u', $password) || !preg_match('/\p{Ll}/u', $password)
            || !preg_match('/\p{Nd}/u', $password) || !preg_match('/[^\p{L}\p{N}\s]/u', $password)
        ) {
            return ucfirst($this->configurationHelper->getTranslateLocale('the_password_must_contain_at_least', ['count' => self::PASSWORD_MIN_LENGTH]));
        }

        $normalize = fn(string $text): string => (string)transliterator_transliterate('Any-Latin; Latin-ASCII; Lower()', $text);
        $password  = $normalize($password);

        foreach ([$lastname, $firstname, $username, explode('@', $mail)[0]] as $personalData) {
            foreach (preg_split('/[^a-z0-9]+/', $normalize($personalData), -1, PREG_SPLIT_NO_EMPTY) as $word) {
                if (strlen($word) >= self::PERSONAL_DATA_MIN_LENGTH && str_contains($password, $word)) {
                    return ucfirst($this->configurationHelper->getTranslateLocale('the_password_must_not_contain_your_personal_information'));
                }
            }
        }

        return null;
    }

    /**
     * @throws DoctrineDBALException
     */
    public function getPersonalDatas(User $user): array
    {
        $connection = $this->entityManager->getConnection();
        $account    = $connection->fetchAssociative(
            'SELECT lastname, firstname, mail, username, roles, last_activity, verify_mail, personal_datas, created_at, updated_at FROM user WHERE id = :id',
            ['id' => $user->id]
        );

        $account['roles'] = json_decode($account['roles'], true);

        $datas = ['account' => $account];

        foreach (self::USER_TABLES as $table) {
            $datas[$table] = $connection->fetchAllAssociative('SELECT * FROM ' . $table . ' WHERE user_id = :id', ['id' => $user->id]);
        }

        $datas['login_error'] = $connection->fetchAllAssociative(
            'SELECT * FROM login_error WHERE email IN (:mail, :username)', ['mail' => $user->getMail(), 'username' => $user->getUsername()]
        );

        return $datas;
    }

    public function getRole(array|string|null $object): string
    {
        if (is_string($object)) {
            $object = json_decode($object, true);
        }

        if (!is_array($object) || $object === []) {
            return "";
        }

        $roles = array_map(
            fn(mixed $role): string => "<span>" . htmlspecialchars((string) $role, ENT_QUOTES) . "</span>",
            array_values($object)
        );

        return implode("<br/>", $roles);
    }

    public function getRoles(): array
    {
        return ['ROLE_ADMIN', 'ROLE_USER'];
    }

    /**
     * @throws DoctrineDBALException
     */
    public function getTotalNames(bool $defaultFilter = false): string
    {
        return $this->configurationHelper->formatNumber($this->getUsers($this->getFilter($defaultFilter), true));
    }

    /**
     * @throws DoctrineDBALException
     */
    /**
     * @throws DoctrineDBALException
     */
    public function getUnverifiedUsers(): array
    {
        return $this->findUsers('verify_mail = 0 AND created_at < :limit', ['limit' => $this->getDate(self::UNVERIFIED_LIMIT)]);
    }

    public function getUser(int $userId): array|false
    {
        $query = "
            SELECT user.id, user.lastname, user.firstname, user.mail, user.username, user.active, group_gta.id AS groupGtaId, user.roles
            FROM user
            LEFT JOIN group_gta ON user.group_gta_id = group_gta.id
            WHERE user.id = :userId
            GROUP BY user.id
        ";

        $statement = $this->entityManager->getConnection()->prepare($query);
        $statement->bindValue('userId', $userId);

        return $statement->executeQuery()->fetchAssociative();
    }

    /**
     * @throws DoctrineDBALException
     */
    public function getUsers(array $filter, string $count = ''): array|int
    {
        $perPage = $this->parameterBag->get('per_page');
        $values  = $filter['search'] ? explode(' ', $filter['search']) : [];

        if ($count !== '') {
            $query = "SELECT COUNT(DISTINCT user.id) AS count";
        } else {
            $query = "SELECT user.id, user.lastname, user.firstname, user.mail, user.username, user.active, group_gta_locale.name AS groupGtaName, user.roles";
        }

        $query .= "
            FROM user
            LEFT JOIN group_gta ON group_gta.id = user.group_gta_id
            LEFT JOIN group_gta_locale ON group_gta.id = group_gta_locale.group_gta_id AND group_gta_locale.locale_id = :localeId
        ";

        foreach ($values as $key => $value) {
            if ($key) {
                $query .= "
                        AND (
                            user.lastname LIKE :search_" . $key . "
                            OR user.firstname LIKE :search_" . $key . "
                            OR user.mail LIKE :search_" . $key . "
                            OR user.username LIKE :search_" . $key . "
                            OR group_gta_locale.name LIKE :search_" . $key . "
                            OR user.roles LIKE :search_" . $key . "
                        )
                    ";
            } else {
                $query .= "
                        WHERE (
                            user.lastname LIKE :search_" . $key . "
                            OR user.firstname LIKE :search_" . $key . "
                            OR user.mail LIKE :search_" . $key . "
                            OR user.username LIKE :search_" . $key . "
                            OR group_gta_locale.name LIKE :search_" . $key . "
                            OR user.roles LIKE :search_" . $key . "
                        )
                    ";
            }
        }

        $query .= $count !== '' ? "" : " GROUP BY user.id";

        if ($count === '' && !empty($filter['order'])) {
            $query .= $this->configurationHelper->getOrderBy($query, $filter['order']);
        }

        $query .= $count !== '' ? "" : " LIMIT " . $perPage . " OFFSET " . ($perPage * ($filter['page'] - 1));

        $statement = $this->entityManager->getConnection()->prepare($query);
        $statement->bindValue('localeId', $this->security->getUser()->getLocale()->id);

        foreach ($values as $key => $value) {
            $statement->bindValue('search_' . $key, '%' . $value . '%');
        }

        if ($count) {
            return $statement->executeQuery()->fetchAssociative()['count'] ?? 0;
        }

        return $statement->executeQuery()->fetchAllAssociative();
    }

    /**
     * @throws DoctrineDBALException
     */
    public function isLastAdmin(User $user): bool
    {
        if (!in_array('ROLE_ADMIN', $user->getRoles(), true)) {
            return false;
        }

        return !$this->entityManager->getConnection()->fetchOne(
            "SELECT COUNT(*) FROM user WHERE active = 1 AND roles LIKE '%ROLE_ADMIN%' AND id <> :id", ['id' => $user->id]
        );
    }

    public function invalidateCode(User $user): void
    {
        $user->setVerifyCode('');
        $user->setVerifyCodeExpiresAt(null);
        $user->setVerifyAttempts(0);
    }

    /**
     * Prévient le titulaire d'un mail qu'une inscription a été tentée avec : l'inscription répond pareil que le mail soit libre ou non
     *
     * @throws DoctrineDBALException|TransportExceptionInterface
     */
    public function sendAccountAlreadyExists(User $user): void
    {
        if (!$this->accountMailRecipientLimiter->create((string)$user->id)->consume()->isAccepted()) {
            return;
        }

        $this->sendMail($user, 'Email/account_already_exists.html.twig', 'account_already_exists_mail_subject', ['url' => $this->getSiteUrl('login')]);
    }

    /**
     * Génère un nouveau code (seul son hash est stocké) puis envoie le lien par mail
     *
     * @throws DoctrineDBALException|TransportExceptionInterface
     */
    public function sendLink(User $user, string $type): void
    {
        if (!$this->accountMailRecipientLimiter->create((string)$user->id)->consume()->isAccepted()) {
            return;
        }

        [$hours, $route, $template, $subject] = self::LINKS[$type];

        $code = bin2hex(random_bytes(32));

        $user->setVerifyCode(hash('sha256', $code));
        $user->setVerifyCodeExpiresAt(new DateTime('+' . $hours . ' hours'));
        $user->setVerifyAttempts(0);

        $this->entityManager->flush();

        $this->sendMail($user, $template, $subject, ['hours' => $hours, 'url' => $this->getSiteUrl($route, ['userId' => $user->id, 'code' => $code])]);
    }

    /**
     * Prévient le titulaire d'un compte inactif de son anonymisation prochaine
     *
     * @throws DoctrineDBALException|TransportExceptionInterface
     */
    public function sendInactivityWarning(User $user): void
    {
        $this->sendMail($user, 'Email/inactive_account.html.twig', 'your_inactive_account_will_be_deleted', ['url' => $this->getSiteUrl('login')]);

        $user->setInactivityWarnedAt(new DateTime());
        $this->entityManager->flush();
    }

    /**
     * Prévient le titulaire du compte que son mot de passe a changé : s'il n'en est pas à l'origine, il peut réagir. Pas de limite d'envoi,
     * chaque changement doit être signalé
     *
     * @throws DoctrineDBALException|TransportExceptionInterface
     */
    public function sendPasswordChanged(User $user): void
    {
        $this->sendMail($user, 'Email/password_changed.html.twig', 'password_changed_mail_subject', ['url' => $this->getSiteUrl('login')]);
    }

    /**
     * Les comptes des administrateurs sont exclus : leur anonymisation automatique pourrait fermer l'accès à l'administration
     *
     * @throws DoctrineDBALException
     */
    private function findUsers(string $condition, array $parameters): array
    {
        $ids = $this->entityManager->getConnection()->fetchFirstColumn(
            'SELECT id FROM user WHERE ' . $condition . " AND mail NOT LIKE :anonymized AND roles NOT LIKE '%ROLE_ADMIN%'",
            [...$parameters, 'anonymized' => '%@' . self::ANONYMIZED_DOMAIN]
        );

        return $this->entityManager->getRepository(User::class)->findBy(['id' => $ids]);
    }

    /**
     * @throws DateMalformedStringException
     */
    private function getDate(string $duration): string
    {
        return new DateTime('- ' . $duration)->format('Y-m-d H:i:s');
    }

    /**
     * URL des liens des mails : toujours l'adresse du site (URL_SITE), jamais l'hôte envoyé par le navigateur,
     * sinon une requête forgée avec un autre en-tête Host enverrait un lien valide vers un site tiers
     */
    private function getSiteUrl(string $route, array $parameters = []): string
    {
        return rtrim($this->parameterBag->get('url_site'), '/') . $this->urlGenerator->generate($route, $parameters);
    }

    /**
     * Le mail est écrit dans la langue du destinataire, pas dans celle de la personne qui le déclenche (un administrateur par exemple).
     * Il ne dépend pas de la requête (langue passée au template) : il peut partir après la réponse
     *
     * @throws DoctrineDBALException|TransportExceptionInterface
     */
    private function sendMail(User $user, string $template, string $subject, array $mailContext): void
    {
        $locale = $user->getLocale()->getCode();
        $from   = $this->configurationHelper->getTranslateLocale('no_reply_mail_local_part', [], $locale) . '@' . $this->parameterBag->get('mailer_domain');

        $this->mailer->send(
            new TemplatedEmail()
                ->from(new Address($from, 'GTA 5 Online'))
                ->to(new Address($user->getMail(), $user->getUsername()))
                ->subject($this->configurationHelper->mbUcfirst($this->configurationHelper->getTranslateLocale($subject, [], $locale)))
                ->htmlTemplate($template)
                ->context([...$mailContext, 'locale' => $locale, 'username' => $user->getUsername()])
        );
    }

    /**
     * Une page au-delà de la dernière (après un filtre ou une suppression) revient à la première
     *
     * @throws DoctrineDBALException
     */
    private function keepPageInRange(array $filter): array
    {
        if ($filter['page'] > 1 && $this->getUsers($filter, true) <= ($filter['page'] - 1) * $this->parameterBag->get('per_page')) {
            $filter['page'] = 1;

            $this->requestStack->getSession()->set('user', $filter);
        }

        return $filter;
    }
}

<?php

namespace App\Helper;

use App\Entity\Locale;
use App\Entity\User;
use App\Enum\SpeedUnit;
use DeepL\DeepLClient;
use DeepL\DeepLException;
use DeepL\TooManyRequestsException;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception as DoctrineDBALException;
use Doctrine\ORM\EntityManagerInterface;
use NumberFormatter;
use RuntimeException;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

readonly class ConfigurationHelper
{
    const string SITE_VERIFY_URL = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';
    const int DEEPL_MAX_ATTEMPTS = 3;
    const int DEEPL_RETRY_DELAY  = 5;
    const int TURNSTILE_TIMEOUT  = 5;

    public function __construct(
        private EntityManagerInterface $entityManager, private ParameterBagInterface $parameterBag, private RequestStack $requestStack,
        private Security               $security, private TranslatorInterface $translator
    ) {
    }

    public function checkTurnstile(?string $response, ?string $remoteIp = null): bool
    {
        if (empty($response)) {
            return false;
        }

        $remoteIp = (in_array($remoteIp, [null, '', '0'], true)) ? $this->requestStack->getCurrentRequest()->getClientIp() : $remoteIp;

        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, self::SITE_VERIFY_URL);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, self::TURNSTILE_TIMEOUT);
        curl_setopt($curl, CURLOPT_TIMEOUT, self::TURNSTILE_TIMEOUT);
        curl_setopt(
            $curl, CURLOPT_POSTFIELDS,
            ["secret" => $this->parameterBag->get('turnstile_secret_key'), 'response' => $response, 'remoteip' => $remoteIp]
        );

        if (!is_string($result = curl_exec($curl))) {
            return false;
        }

        $result = json_decode($result, true);

        return is_array($result) && !empty($result['success']);
    }

    /**
     * Supprime un élément avec ses données propres (traductions, liaisons, coches des utilisateurs) : tout ou rien
     *
     * @throws DoctrineDBALException
     */
    public function deleteRows(array $queries, int $id): void
    {
        $this->entityManager->getConnection()->transactional(function (Connection $connection) use ($queries, $id) {
            foreach ($queries as $query) {
                $connection->executeStatement($query, ['id' => $id]);
            }
        });
    }

    public function formatMoney(float|int|string|null $number, int $decimals = 0): string
    {
        return $this->getTranslateLocale('amount_in_dollars', ['amount' => $this->formatNumber($number, $decimals)]);
    }

    public function formatNumber(float|int|string|null $number, int $decimals = 0, bool $trimZeros = false): string
    {
        $numberFormatter = new NumberFormatter($this->getDefaultLocale(), NumberFormatter::DECIMAL);
        $numberFormatter->setAttribute(NumberFormatter::MIN_FRACTION_DIGITS, $trimZeros ? 0 : $decimals);
        $numberFormatter->setAttribute(NumberFormatter::MAX_FRACTION_DIGITS, $decimals);
        $numberFormatter->setAttribute(NumberFormatter::ROUNDING_MODE, NumberFormatter::ROUND_HALFUP);

        return $numberFormatter->format((float) $number);
    }

    public function formatPercent(float|int|string|null $number, int $decimals = 0, bool $trimZeros = false): string
    {
        $numberFormatter = new NumberFormatter($this->getDefaultLocale(), NumberFormatter::PERCENT);
        $numberFormatter->setAttribute(NumberFormatter::MIN_FRACTION_DIGITS, $trimZeros ? 0 : $decimals);
        $numberFormatter->setAttribute(NumberFormatter::MAX_FRACTION_DIGITS, $decimals);
        $numberFormatter->setAttribute(NumberFormatter::ROUNDING_MODE, NumberFormatter::ROUND_HALFUP);

        return $numberFormatter->format((float) $number / 100);
    }

    public function formatSpeed(float|string|null $kilometers, float|string|null $miles): string
    {
        $speedUnit = $this->getSpeedUnit();
        $speed     = $speedUnit === SpeedUnit::MILES ? $miles : $kilometers;

        return $speed ? $this->formatNumber($speed, 2) . "\u{A0}" . $this->getTranslateLocale($speedUnit) : '';
    }

    public function getDefaultLocale(): string
    {
        if ($this->security->getUser() instanceof User
            && $this->security->getUser()->getLocale()->getCode() != $this->requestStack->getSession()->get('default_locale')
        ) {
            $this->requestStack->getSession()->set('default_locale', $this->security->getUser()->getLocale()->getCode());
        }

        static $fallbackLocale = null;

        if ($this->requestStack->getSession()->get('default_locale')) {
            return $this->requestStack->getSession()->get('default_locale');
        }

        return $fallbackLocale ??= $this->entityManager->getRepository(Locale::class)->findOneBy(['default' => true])?->getCode() ?? 'fr';
    }

    /**
     * Ce qui empêche de supprimer un élément : [type => requête renvoyant le nom de chaque élément lié (paramètres :id et :localeId)]
     *
     * @throws DoctrineDBALException
     */
    public function getDeletionBlockers(array $queries, int $id): array
    {
        $blockers = [];

        foreach ($queries as $type => $query) {
            $parameters = ['id' => $id];

            if (str_contains($query, ':localeId')) {
                $parameters['localeId'] = $this->security->getUser()->getLocale()->id;
            }

            if ($names = $this->entityManager->getConnection()->fetchFirstColumn($query, $parameters)) {
                $blockers[] = ['count' => count($names), 'names' => array_slice($names, 0, 10), 'type' => $type];
            }
        }

        return $blockers;
    }

    /**
     * @throws DoctrineDBALException
     */
    public function getMissingFieldMessage(Request $request, array $fields): ?string
    {
        foreach ($fields as $field => $message) {
            if (trim((string)$request->request->get($field)) === '') {
                return ucfirst($this->getTranslateLocale($message));
            }
        }

        return null;
    }

    /**
     * Seules les colonnes d'une table présente dans la requête sont triées : une clé inventée ou périmée est ignorée au lieu de casser la liste
     *
     * @throws DoctrineDBALException
     */
    public function getOrderBy(string $query, array $orders, array $expressions = []): string
    {
        static $columns = null;

        $columns ??= array_flip($this->entityManager->getConnection()->fetchFirstColumn(
            "SELECT CONCAT(TABLE_NAME, '.', COLUMN_NAME) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE()"
        ));

        $orderBy = [];

        foreach ($orders as $column => $direction) {
            if (!is_string($column) || !preg_match('/^[a-z0-9_]+-[a-z0-9_]+$/', $column) || !in_array($direction, ['ASC', 'DESC'], true)) {
                continue;
            }

            if (isset($expressions[$column])) {
                $orderBy[] = $expressions[$column] . ' ' . $direction;

                continue;
            }

            $sqlColumn = str_replace('-', '.', $column);
            $table     = strstr($sqlColumn, '.', true);

            if (!preg_match('/\b' . $table . '\b/', $query) || (!isset($columns[$sqlColumn]) && !str_contains($query, $sqlColumn))) {
                continue;
            }

            $orderBy[] = $sqlColumn . ' ' . $direction;
        }

        return $orderBy === [] ? '' : ' ORDER BY ' . implode(', ', $orderBy);
    }

    public function getSpeedUnit(): SpeedUnit
    {
        static $speedUnits = [];

        $code = $this->getDefaultLocale();

        return $speedUnits[$code] ??= $this->entityManager->getRepository(Locale::class)->findOneBy(['code' => $code])?->getSpeedUnit()
                                     ?? SpeedUnit::KILOMETERS;
    }

    public function getTextWithColon(string $text): string
    {
        return $this->getTranslateLocale('text_with_colon', ['text' => $text]);
    }

    /**
     * Sans langue précisée : langue de l'utilisateur connecté ou de la session (un mail passe celle de son destinataire)
     *
     * @throws DoctrineDBALException
     */
    public function getTranslateLocale(string|TranslatableInterface $string, array $parameters = [], ?string $locale = null): string
    {
        if ($string instanceof TranslatableInterface) {
            return $string->trans($this->translator, $locale ?? $this->getDefaultLocale());
        }

        return $this->translator->trans($string, $parameters, null, $locale ?? $this->getDefaultLocale());
    }

    public function indexByEntityId(array $entities, string $name): array
    {
        $indexedEntities = [];

        foreach ($entities as $entity) {
            $indexedEntities[$entity->{'get' . $name}()->id] = $entity;
        }

        return $indexedEntities;
    }

    public function mbUcfirst($string): string
    {
        if (function_exists('mb_strtoupper') && function_exists('mb_substr')) {
            return mb_strtoupper(mb_substr($string, 0, 1), 'UTF-8') . mb_substr($string, 1);
        }

        if ($string[0] >= "\xc3") {
            return ($string[1] >= "\xa0" ? ($string[0] . chr(ord($string[1]) - 32)) : ($string[0] . $string[1])) . substr($string, 2);
        }

        return ucfirst($string);
    }

    public function removeExternalData(array $rows): array
    {
        if ($this->security->isGranted('ROLE_ADMIN')) {
            return $rows;
        }

        foreach ($rows as $key => $row) {
            if (!is_array($row)) {
                continue;
            }

            foreach (['url', 'webPath'] as $field) {
                if (array_key_exists($field, $row)) {
                    $rows[$key][$field] = null;
                }
            }

            if (!empty($row['propertyTwos'])) {
                $rows[$key]['propertyTwos'] = $this->removeExternalData($row['propertyTwos']);
            }
        }

        return $rows;
    }

    public function removeUpload(string $folder, ?string $path): void
    {
        if (!$path || basename($path) !== $path) {
            return;
        }

        $file = $this->parameterBag->get('kernel.project_dir') . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR
                . $folder . DIRECTORY_SEPARATOR . $path;

        if (is_file($file)) {
            unlink($file);
        }
    }

    /**
     * @throws DeepLException
     */
    public function translate($text, $source = null, $target = 'en'): string
    {
        $authKey = $this->parameterBag->get('deepl_auth_key');

        if (!$authKey) {
            throw new RuntimeException('Missing DEEPL_AUTH_KEY environment variable.');
        }

        $deeplClient = new DeepLClient($authKey);

        for ($attempt = 1; ; $attempt++) {
            try {
                $result = $deeplClient->translateText($text, $source, strtoupper($target));

                return $result->text . PHP_EOL;
            } catch (TooManyRequestsException $exception) {
                if ($attempt >= self::DEEPL_MAX_ATTEMPTS) {
                    throw $exception;
                }

                sleep(self::DEEPL_RETRY_DELAY);
            }
        }
    }
}

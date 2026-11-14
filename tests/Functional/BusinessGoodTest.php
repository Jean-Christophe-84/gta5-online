<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use Doctrine\DBAL\Exception as DoctrineDBALException;

class BusinessGoodTest extends AppWebTestCase
{
    /**
     * @throws DoctrineDBALException
     */
    public function testFormSavesTheAdditionalInformationsAndNamesTheBusiness(): void
    {
        $this->loginAs(1);

        $business = $this->connection->fetchAssociative(
            'SELECT business_locale.business_id AS id, business_locale.name, business_locale.locale_id AS localeId FROM business_locale
             INNER JOIN user ON user.locale_id = business_locale.locale_id AND user.id = ?
             WHERE business_locale.name <> \'\' LIMIT 1',
            [self::USER_ID]
        );

        $response = $this->post(
            '/business-good/form', ['additionalInformations' => ' Ajout ', 'businessGoodId' => '', 'businessId' => $business['id']], '/business-goods'
        );

        $this->assertTrue($response['success']);
        $this->assertStringContainsString('"' . $business['name'] . '"', $response['message']);

        $businessGoodId = (int)$this->fetchOne('SELECT MAX(id) FROM business_good WHERE business_id = ?', [$business['id']]);

        $this->assertSame('Ajout', $this->getAdditionalInformations($businessGoodId, (int)$business['localeId']));

        $response = $this->post(
            '/business-good/form',
            ['additionalInformations' => 'Mise à jour', 'businessGoodId' => $businessGoodId, 'businessId' => $business['id']],
            '/business-goods'
        );

        $this->assertTrue($response['success']);
        $this->assertSame('Mise à jour', $this->getAdditionalInformations($businessGoodId, (int)$business['localeId']));
        $this->assertSame(
            1, (int)$this->fetchOne('SELECT COUNT(*) FROM business_good_locale WHERE business_good_id = ?', [$businessGoodId])
        );
    }

    /**
     * @throws DoctrineDBALException
     */
    private function getAdditionalInformations(int $businessGoodId, int $localeId): mixed
    {
        return $this->fetchOne(
            'SELECT additional_informations FROM business_good_locale WHERE business_good_id = ? AND locale_id = ?', [$businessGoodId, $localeId]
        );
    }
}

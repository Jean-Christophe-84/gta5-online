<?php

namespace App\Controller\User;

use App\Helper\UserHelper;
use Doctrine\DBAL\Exception as DoctrineDBALException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class ExportAccountController extends AbstractController
{
    /**
     * @throws DoctrineDBALException
     */
    #[Route(path: '/user/account/export', name: 'export_account_user')]
    #[IsGranted(new Expression('is_granted("ROLE_USER")'))]
    public function export(UserHelper $userHelper): JsonResponse
    {
        $response = new JsonResponse($userHelper->getPersonalDatas($this->getUser()));

        $response->setEncodingOptions(JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $response->headers->set(
            'Content-Disposition', $response->headers->makeDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, 'gta5-online-my-datas.json')
        );

        return $response;
    }
}

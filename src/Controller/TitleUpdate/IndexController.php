<?php

namespace App\Controller\TitleUpdate;

use App\Entity\TitleUpdate;
use App\Helper\ConfigurationHelper;
use App\Helper\TitleUpdateHelper;
use Doctrine\DBAL\Exception as DoctrineDBALException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class IndexController extends AbstractController
{
    /**
     * @throws DoctrineDBALException
     */
    #[Route(path: '/title-updates', name: 'title-updates')]
    #[IsGranted(new Expression('is_granted("ROLE_USER")'))]
    public function index(ConfigurationHelper $configurationHelper, TitleUpdateHelper $titleUpdateHelper): Response
    {
        if (!$this->isGranted(TitleUpdate::PERMISSION_TITLE_UPDATE_VIEW)) {
            throw new AccessDeniedHttpException(ucfirst($configurationHelper->getTranslateLocale("you_are_not_allowed_to_access_this_page")));
        }

        $filter = $titleUpdateHelper->getFilter();

        return $this->render(
            'TitleUpdate/index.html.twig',
            [
                'filter'       => $filter,
                'titleUpdates' => $titleUpdateHelper->getTitleUpdates($filter),
                'totalNames'   => $titleUpdateHelper->getTotalNames()
            ]
        );
    }
}

<?php

namespace App\Controller\Property;

use App\Entity\Business;
use App\Entity\Property;
use App\Entity\PropertyAcquisition;
use App\Entity\PropertyArea;
use App\Entity\PropertyLocale;
use App\Entity\PropertyLocation;
use App\Entity\PropertyProperty;
use App\Entity\PropertyPropertyAcquisition;
use App\Entity\PropertyType;
use App\Entity\PropertyUser;
use App\Entity\TitleUpdate;
use App\Entity\User;
use App\Helper\ConfigurationHelper;
use App\Helper\PropertyHelper;
use App\Service\ErrorResponder;
use Doctrine\DBAL\Exception as DoctrineDBALException;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class FormController extends AbstractController
{
    /**
     * @throws DoctrineDBALException
     */
    #[Route(path: '/property/form', name: 'form_property', methods: ['POST'])]
    #[IsGranted(new Expression('is_granted("ROLE_USER")'))]
    public function form(
        ConfigurationHelper $configurationHelper, EntityManagerInterface $entityManager, ErrorResponder $errorResponder,
        PropertyHelper      $propertyHelper, Request $request
    ): Response {
        $type = $request->request->get('propertyId') !== "" ? 'update' : 'add';

        if ($message = $configurationHelper->getMissingFieldMessage(
            $request, [
                'name'  => 'please_enter_the_name',
                'price' => 'please_enter_the_price'
            ]
        )) {
            return new JsonResponse(['message' => $message, 'success' => false]);
        }

        $business = $propertyHelper->getBusinessMaximums()[(int)$request->request->get('businessId')] ?? null;

        if ($business && $request->request->get('propertyTypeId') && (int)$business['propertyTypeId'] !== (int)$request->request->get('propertyTypeId')) {
            return new JsonResponse(
                ['message' => ucfirst($configurationHelper->getTranslateLocale('the_business_does_not_match_the_property_type')), 'success' => false]
            );
        }

        if ($message = $propertyHelper->getBusinessMaximumMessage(
            (int)$request->request->get('propertyId') ? : null, (int)$request->request->get('businessId') ? : null,
            (bool)$request->request->get('owned'), (bool)$request->request->get('wanted')
        )) {
            return new JsonResponse(['message' => $message, 'success' => false]);
        }

        if ($message = $propertyHelper->getParentPropertyMessage(
            (int)$request->request->get('propertyId') ? : null, (bool)$request->request->get('owned')
        )) {
            return new JsonResponse(['message' => $message, 'success' => false]);
        }

        try {
            if ($request->request->get('propertyTypeId')) {
                $propertyType = $entityManager->getRepository(PropertyType::class)->find($request->request->get('propertyTypeId'));
            } else {
                $propertyType = null;
            }

            if ($request->request->get('businessId')) {
                $business = $entityManager->getRepository(Business::class)->find($request->request->get('businessId'));
            } else {
                $business = null;
            }

            if ($request->request->get('propertyAreaId')) {
                $propertyArea = $entityManager->getRepository(PropertyArea::class)->find($request->request->get('propertyAreaId'));
            } else {
                $propertyArea = null;
            }

            if ($request->request->get('propertyLocationId')) {
                $propertyLocation = $entityManager->getRepository(PropertyLocation::class)->find($request->request->get('propertyLocationId'));
            } else {
                $propertyLocation = null;
            }

            if ($request->request->get('titleUpdateId')) {
                $titleUpdate = $entityManager->getRepository(TitleUpdate::class)->find($request->request->get('titleUpdateId'));
            } else {
                $titleUpdate = null;
            }

            if ($type === 'update') {
                if (!$this->isGranted(Property::PERMISSION_PROPERTY_EDIT)) {
                    return new JsonResponse(
                        ['message' => ucfirst($configurationHelper->getTranslateLocale('you_are_not_allowed_to_access_this_page')), 'success' => false]
                    );
                }

                $property = $entityManager->getRepository(Property::class)->find($request->request->get('propertyId'));
            } else {
                if (!$this->isGranted(Property::PERMISSION_PROPERTY_ADD)) {
                    return new JsonResponse(
                        ['message' => ucfirst($configurationHelper->getTranslateLocale('you_are_not_allowed_to_access_this_page')), 'success' => false]
                    );
                }

                $property = new Property();
            }

            /** @var User $user */
            $user           = $this->getUser();
            $propertyLocale = $entityManager->getRepository(PropertyLocale::class)->findOneBy(['property' => $property, 'locale' => $user->getLocale()]);

            if (trim($request->request->get('comment'))) {
                if (!$propertyLocale instanceof PropertyLocale) {
                    $propertyLocale = new PropertyLocale();
                    $propertyLocale->setProperty($property);
                    $propertyLocale->setLocale($user->getLocale());
                }

                $propertyLocale->setComment(trim($request->request->get('comment')));

                $entityManager->persist($propertyLocale);
            } else {
                if ($propertyLocale instanceof PropertyLocale) {
                    $entityManager->remove($propertyLocale);
                }
            }

            $propertyUser = $entityManager->getRepository(PropertyUser::class)->findOneBy(['user' => $user, 'property' => $property]);

            if (!$propertyUser instanceof PropertyUser) {
                $propertyUser = new PropertyUser();
                $propertyUser->setUser($user);
                $propertyUser->setProperty($property);
            }

            $propertyUser->setOwned($request->request->get('owned'));
            $propertyUser->setWanted($request->request->get('wanted'));

            $entityManager->persist($propertyUser);

            $property->setName(trim($request->request->get('name')));
            $property->setPropertyType($propertyType);
            $property->setBusiness($business);
            $property->setPropertyArea($propertyArea);
            $property->setPropertyLocation($propertyLocation);
            $property->setTitleUpdate($titleUpdate);
            $property->setPrice($request->request->get('price'));
            $property->setDiscountedPrice($request->request->get('discountedPrice') ? : null);
            $property->setDiscountedPercentage($request->request->get('discountedPercentage') ? : null);
            $property->setCapacityVehicles($request->request->get('capacityVehicles') ? : null);
            $property->setCapacityBicycles($request->request->get('capacityBicycles') ? : null);

            if ($this->isGranted('ROLE_ADMIN')) {
                $property->setUrl($request->request->get('url') ? : null);
            }

            $entityManager->persist($property);
            $entityManager->flush();

            $propertyPropertyAcquisitionsDb = $configurationHelper->indexByEntityId(
                $entityManager->getRepository(PropertyPropertyAcquisition::class)->findBy(['property' => $property]), 'PropertyAcquisition'
            );
            $propertyPropertyTwosDb         = $configurationHelper->indexByEntityId(
                $entityManager->getRepository(PropertyProperty::class)->findBy(['propertyOne' => $property]), 'PropertyTwo'
            );

            // PropertyAcquisitions
            if (!empty($request->request->all('propertyAcquisitionIds'))) {
                $positionPropertyPropertyAcquisition = 1;

                foreach ($request->request->all('propertyAcquisitionIds') as $propertyAcquisitionId) {
                    $propertyAcquisition = $entityManager->getRepository(PropertyAcquisition::class)->find($propertyAcquisitionId);

                    if ($propertyAcquisition instanceof PropertyAcquisition) {
                        if (isset($propertyPropertyAcquisitionsDb[$propertyAcquisitionId])) {
                            $propertyPropertyAcquisition = $propertyPropertyAcquisitionsDb[$propertyAcquisitionId];
                            unset($propertyPropertyAcquisitionsDb[$propertyAcquisitionId]);
                        } else {
                            $propertyPropertyAcquisition = new PropertyPropertyAcquisition();
                            $propertyPropertyAcquisition->setProperty($property);
                            $propertyPropertyAcquisition->setPropertyAcquisition($propertyAcquisition);
                        }

                        $propertyPropertyAcquisition->setPosition($positionPropertyPropertyAcquisition);

                        $entityManager->persist($propertyPropertyAcquisition);

                        $positionPropertyPropertyAcquisition++;
                    }
                }
            }

            foreach ($propertyPropertyAcquisitionsDb as $propertyPropertyAcquisitionToRemove) {
                $entityManager->remove($propertyPropertyAcquisitionToRemove);
            }

            // Property twos
            if (!empty($request->request->all('propertyTwoIds'))) {
                $positionPropertyProperty = 1;

                foreach ($request->request->all('propertyTwoIds') as $propertyTwoId) {
                    if (($propertyTwo = $entityManager->getRepository(Property::class)->find($propertyTwoId)) instanceof Property) {
                        if (isset($propertyPropertyTwosDb[$propertyTwoId])) {
                            $propertyProperty = $propertyPropertyTwosDb[$propertyTwoId];
                            unset($propertyPropertyTwosDb[$propertyTwoId]);
                        } else {
                            $propertyProperty = new PropertyProperty();
                            $propertyProperty->setPropertyTwo($propertyTwo);
                            $propertyProperty->setPropertyTwo($property);
                        }

                        $propertyProperty->setPosition($positionPropertyProperty);

                        $entityManager->persist($propertyProperty);

                        $positionPropertyProperty++;
                    }
                }
            }

            foreach ($propertyPropertyTwosDb as $propertyPropertyTwoToRemove) {
                $entityManager->remove($propertyPropertyTwoToRemove);
            }

            $entityManager->flush();

            $parameters = $propertyHelper->getParameters($propertyHelper->getFilter());

            return new JsonResponse(
                [
                    'filter'     => $this->renderView('Property/_partial/filter.html.twig', $parameters),
                    'filterTwo'  => $this->renderView('Property/_partial/filter_two.html.twig', $parameters),
                    'message'    => ucfirst(
                        $configurationHelper->getTranslateLocale(
                            'success_property_form', ['name' => trim($request->request->get('name')), 'type' => $type]
                        )
                    ),
                    'properties' => $parameters['properties'],
                    'success'    => true,
                    'summary'    => $this->renderView('_partial/summary.html.twig', ['entity' => 'properties', 'summary' => $parameters['summary']]),
                    'template'   => $this->renderView('Property/_partial/template.html.twig', $parameters)
                ]
            );
        } catch (Exception $exception) {
            $message = ucfirst(
                $configurationHelper->getTranslateLocale(
                    'error_property_form', ['name' => trim($request->request->get('name')), 'type' => $type]
                )
            );

            return $errorResponder->json($exception, $message);
        }
    }
}

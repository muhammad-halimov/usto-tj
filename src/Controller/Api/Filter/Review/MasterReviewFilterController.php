<?php

namespace App\Controller\Api\Filter\Review;

use App\Entity\User;
use App\Repository\ReviewRepository;
use App\Repository\UserRepository;
use Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;

class MasterReviewFilterController extends AbstractController
{
    private readonly ReviewRepository $reviewRepository;
    private readonly UserRepository $userRepository;

    public function __construct(
        ReviewRepository  $reviewRepository,
        UserRepository    $userRepository
    )
    {
        $this->reviewRepository = $reviewRepository;
        $this->userRepository = $userRepository;
    }

    public function __invoke(int $id): JsonResponse
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        try {
            /** @var User $user */
            $user = $this->userRepository->find($id);
            if (!$user) return $this->json([], 404);

            $data = $this->reviewRepository->findUserReviewsByMasterRole($user);

            return empty($data)
                ? $this->json([], 404)
                : $this->json($data, 200, [],
                    [
                        'groups' => ['reviews:read'],
                        'skip_null_values' => false,
                    ]
                );
        } catch (Exception $e) {
            return $this->json([
                'error' => $e->getMessage(),
                'trace' => $e->getTrace()
            ], 500);
        }
    }
}

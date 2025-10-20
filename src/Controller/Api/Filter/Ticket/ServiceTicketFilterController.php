<?php

namespace App\Controller\Api\Filter\Ticket;

use App\Repository\TicketRepository;
use Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;

class ServiceTicketFilterController extends AbstractController
{
    private readonly TicketRepository $ticketRepository;

    public function __construct(
        TicketRepository  $ticketRepository
    )
    {
        $this->ticketRepository = $ticketRepository;
    }

    public function __invoke(): JsonResponse
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        try {
            $data = $this->ticketRepository->findUserTicketsByStatus(true);

            return empty($data)
                ? $this->json([], 404)
                : $this->json($data, 200, [],
                    [
                        'groups' => ['userTickets:read'],
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

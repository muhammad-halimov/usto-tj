<?php

namespace App\State\Localization\Geography;

use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\Ticket\Ticket;
use App\Repository\Ticket\TicketRepository;
use App\Service\Extra\LocalizationService;
use App\State\Localization\AbstractLocalizationProvider;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;

readonly class TicketGeographyLocalizationProvider extends AbstractLocalizationProvider
{
    public function __construct(
        ProviderInterface   $itemProvider,
        ProviderInterface   $collectionProvider,
        RequestStack        $requestStack,
        LocalizationService $localizationService,
        private Security    $security,
        private TicketRepository $ticketRepository,
        private LoggerInterface $logger,
    ) {
        parent::__construct($itemProvider, $collectionProvider, $requestStack, $localizationService);
    }

    protected function supports(object $entity): bool
    {
        return $entity instanceof Ticket;
    }

    /**
     * Для одиночного GET:
     *   1. Загружаем тикет по ID без фильтра approved.
     *   2. Если approved=true — виден всем.
     *   3. Если approved=false — виден только автору (сравниваем email/identifier).
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        if ($operation instanceof Get) {
            $id     = (int) ($uriVariables['id'] ?? 0);
            $ticket = $this->ticketRepository->find($id);

            $this->logger->info('TicketProvider[GET]', [
                'id'          => $id,
                'found'       => $ticket instanceof Ticket,
                'approved'    => $ticket?->getApproved(),
                'authorEmail' => $ticket?->getAuthor()?->getUserIdentifier(),
                'currentUser' => $this->security->getUser()?->getUserIdentifier(),
            ]);

            if (!$ticket instanceof Ticket) {
                return null;
            }

            $locale = $this->requestStack->getCurrentRequest()?->query->get('locale', 'tj') ?? 'tj';

            // Approved tickets are visible to everyone
            if ($ticket->getApproved()) {
                $this->localize($ticket, $locale);
                return $ticket;
            }

            // Unapproved: only the author can see it
            $user = $this->security->getUser();
            if ($user !== null && $ticket->getAuthor() !== null) {
                if ($ticket->getAuthor()->getUserIdentifier() === $user->getUserIdentifier()) {
                    $this->localize($ticket, $locale);
                    return $ticket;
                }
            }

            return null;
        }

        $this->logger->info('TicketProvider[non-Get]', ['opClass' => get_class($operation)]);

        return parent::provide($operation, $uriVariables, $context);
    }

    protected function localize(object $entity, string $locale): void
    {
        /** @var Ticket $entity */
        $this->localizationService->localizeGeography($entity, $locale);

        if ($entity->getCategory()) {
            $this->localizationService->localizeEntity($entity->getCategory(), $locale);
        }

        if ($entity->getUnit()) {
            $this->localizationService->localizeEntity($entity->getUnit(), $locale);
        }

        if ($entity->getSubcategory()) {
            $this->localizationService->localizeEntity($entity->getSubcategory(), $locale);
        }
    }
}

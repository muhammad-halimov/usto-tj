<?php

namespace App\Command;

use App\Repository\Extra\EntityRevisionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Удаляет записи EntityRevision (audit trail), у которых истёк retention
 * (expiresAt не null и уже в прошлом). Записи с expiresAt = null хранятся
 * бессрочно и этой командой не трогаются — так конкретный писатель
 * (листенер) явно отключает retention для отдельной записи.
 *
 * БД сама ничего не чистит — expiresAt просто дата в колонке, реальное
 * удаление делает только эта команда.
 *
 * Запуск вручную:
 *   php bin/console app:prune-entity-revisions
 *   php bin/console app:prune-entity-revisions --dry-run   # без реального удаления
 *
 * Рекомендуемая настройка cron (на сервере, раз в сутки в 03:30):
 *   30 3 * * * /usr/bin/php /var/www/html/bin/console app:prune-entity-revisions --env=prod >> /var/log/prune-entity-revisions.log 2>&1
 *
 * Через Symfony Scheduler (если используется symfony/scheduler):
 *   #[AsPeriodicTask(frequency: '1 day', jitter: 60)]
 */
#[AsCommand(
    name: 'app:prune-entity-revisions',
    description: 'Удаляет записи audit trail (EntityRevision) с истёкшим retention',
)]
class PruneEntityRevisionsCommand extends Command
{
    public function __construct(
        private readonly EntityRevisionRepository $entityRevisionRepository,
        private readonly EntityManagerInterface   $em,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption(
            'dry-run',
            null,
            InputOption::VALUE_NONE,
            'Показать, что будет удалено, без реального удаления (безопасный режим)',
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io     = new SymfonyStyle($input, $output);
        $dryRun = (bool) $input->getOption('dry-run');

        $io->title('Очистка EntityRevision с истёкшим retention');

        if ($dryRun) {
            $io->note('Режим dry-run: удаление не выполняется.');
        }

        $expired = $this->entityRevisionRepository->findExpired();

        if (empty($expired)) {
            $io->success('Нет записей для удаления.');
            return Command::SUCCESS;
        }

        $io->table(
            ['ID', 'entityType', 'entityId', 'action', 'expiresAt'],
            array_map(fn($r) => [
                $r->getId(),
                $r->getEntityType(),
                $r->getEntityId(),
                $r->getAction(),
                $r->getExpiresAt()->format('Y-m-d H:i'),
            ], $expired),
        );

        $count = count($expired);

        if ($dryRun) {
            $io->warning("Dry-run: было бы удалено {$count} записей.");
            return Command::SUCCESS;
        }

        foreach ($expired as $revision) {
            $this->em->remove($revision);
        }

        $this->em->flush();

        $io->success("Удалено {$count} записей EntityRevision.");

        return Command::SUCCESS;
    }
}

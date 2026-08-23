<?php

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Шорткат-подсказка: грузит ТОЛЬКО прод-справочники (категории/подкатегории,
 * единицы измерения, география, причины жалоб, юр. документы) — без
 * dev-тестовых пользователей/тикетов/чатов.
 *
 * Все классы в src/DataFixture/Prod помечены FixtureGroupInterface::
 * getGroups() => ['prod'] и не зависят от App\DataFixture\Dev (см. docblock
 * над каждым из них — CategoryFixture/UnitFixture раньше зависели от
 * dev-тикетов, это вынесено в отдельную Dev\Additional\TicketCategoryLinkFixture).
 *
 * Просто обёртка над:
 *   php bin/console doctrine:fixtures:load --group=prod
 * — если это забудется, эта команда сама всплывёт в `php bin/console` списком
 * (namespace app:) как явная подсказка на нужный флаг.
 */
#[AsCommand(
    name: 'app:fixtures:load-prod',
    description: 'Грузит только прод-справочники (категории/юниты/география/причины жалоб/юр. документы), без dev-данных — эквивалент doctrine:fixtures:load --group=prod',
)]
class LoadProdFixturesCommand extends Command
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $command = $this->getApplication()?->find('doctrine:fixtures:load');

        if (!$command) {
            $output->writeln('<error>Команда doctrine:fixtures:load не найдена — установлен ли doctrine/doctrine-fixtures-bundle?</error>');
            return Command::FAILURE;
        }

        $subInput = new ArrayInput(['--group' => ['prod']]);
        $subInput->setInteractive($input->isInteractive());

        return $command->run($subInput, $output);
    }
}
